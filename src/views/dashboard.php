<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Get user settings
$stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$settings = $stmt->fetch();

// Get user's payments
$stmt = $pdo->prepare("
    SELECT p.*, 
           CASE 
               WHEN p.frequency = 'monthly' THEN DATE_ADD(p.due_date, INTERVAL 1 MONTH)
               WHEN p.frequency = 'weekly' THEN DATE_ADD(p.due_date, INTERVAL 1 WEEK)
               WHEN p.frequency = 'yearly' THEN DATE_ADD(p.due_date, INTERVAL 1 YEAR)
           END as next_due_date
    FROM payments p 
    WHERE p.user_id = ? 
    ORDER BY p.due_date ASC
");
$stmt->execute([$_SESSION['user_id']]);
$payments = $stmt->fetchAll();

// Calculate remaining days until payday
$today = new DateTime();
$payday = new DateTime();
$payday->setDate($today->format('Y'), $today->format('m'), $settings['payday']);
if ($payday < $today) {
    $payday->modify('+1 month');
}
$daysUntilPayday = $today->diff($payday)->days;

// Calculate remaining weekends
$weekends = 0;
$currentDate = clone $today;
while ($currentDate < $payday) {
    if ($currentDate->format('N') >= 6) { // 6 = Saturday, 7 = Sunday
        $weekends++;
    }
    $currentDate->modify('+1 day');
}

// Calculate upcoming payments
$upcomingPayments = [];
$totalUpcoming = 0;
foreach ($payments as $payment) {
    $dueDate = new DateTime();
    $dueDate->setDate($today->format('Y'), $today->format('m'), $payment['due_day']);
    if ($dueDate < $today) {
        $dueDate->modify('+1 month');
    }
    if ($dueDate <= $payday) {
        $upcomingPayments[] = $payment;
        $totalUpcoming += $payment['amount'];
    }
}

$remainingBalance = $settings['current_balance'] - $totalUpcoming;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($config['app']['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/"><?php echo htmlspecialchars($config['app']['name']); ?></a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Settings Section -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Settings</h5>
                    </div>
                    <div class="card-body">
                        <form action="/api/settings/update.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-3">
                                <label for="currency" class="form-label">Currency</label>
                                <select class="form-select" id="currency" name="currency" required>
                                    <option value="GBP" <?php echo $settings['currency'] === 'GBP' ? 'selected' : ''; ?>>£ (GBP)</option>
                                    <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>$ (USD)</option>
                                    <option value="EUR" <?php echo $settings['currency'] === 'EUR' ? 'selected' : ''; ?>>€ (EUR)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="current_balance" class="form-label">Current Balance</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <?php
                                        switch($settings['currency']) {
                                            case 'USD': echo '$'; break;
                                            case 'EUR': echo '€'; break;
                                            default: echo '£';
                                        }
                                        ?>
                                    </span>
                                    <input type="number" class="form-control" id="current_balance" name="current_balance" 
                                           value="<?php echo htmlspecialchars($settings['current_balance']); ?>" 
                                           step="0.01" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="payday" class="form-label">Pay Day</label>
                                <input type="number" class="form-control" id="payday" name="payday" 
                                       value="<?php echo htmlspecialchars($settings['payday']); ?>" 
                                       min="1" max="31" required>
                                <div class="form-text">Day of the month you get paid (1-31)</div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Save Settings</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Payments Section -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Your Payments</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                            Add Payment
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                            <p class="text-muted">No payments added yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Next Due</th>
                                            <th>Frequency</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($payment['name']); ?></td>
                                                <td>
                                                    <?php
                                                    $symbol = '';
                                                    switch($settings['currency']) {
                                                        case 'USD': $symbol = '$'; break;
                                                        case 'EUR': $symbol = '€'; break;
                                                        default: $symbol = '£';
                                                    }
                                                    echo $symbol . number_format($payment['amount'], 2);
                                                    ?>
                                                </td>
                                                <td><?php echo date('d M Y', strtotime($payment['due_date'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($payment['next_due_date'])); ?></td>
                                                <td><?php echo ucfirst($payment['frequency']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary edit-payment" 
                                                            data-payment-id="<?php echo $payment['id']; ?>">
                                                        Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger delete-payment"
                                                            data-payment-id="<?php echo $payment['id']; ?>">
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addPaymentForm" action="/api/payments/add.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label for="payment_name" class="form-label">Payment Name</label>
                            <input type="text" class="form-control" id="payment_name" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label for="payment_amount" class="form-label">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <?php
                                    switch($settings['currency']) {
                                        case 'USD': echo '$'; break;
                                        case 'EUR': echo '€'; break;
                                        default: echo '£';
                                    }
                                    ?>
                                </span>
                                <input type="number" class="form-control" id="payment_amount" name="amount" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="payment_due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="payment_due_date" name="due_date" required>
                        </div>

                        <div class="mb-3">
                            <label for="payment_frequency" class="form-label">Frequency</label>
                            <select class="form-select" id="payment_frequency" name="frequency" required>
                                <option value="monthly">Monthly</option>
                                <option value="weekly">Weekly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Add Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/dashboard.js"></script>
</body>
</html> 