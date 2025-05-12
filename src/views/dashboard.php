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
$stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? AND is_active = 1 ORDER BY due_day");
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
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Account Summary</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Current Balance:</strong> $<?php echo number_format($settings['current_balance'], 2); ?></p>
                        <p><strong>Days until payday:</strong> <?php echo $daysUntilPayday; ?></p>
                        <p><strong>Weekends until payday:</strong> <?php echo $weekends; ?></p>
                        <p><strong>Upcoming payments:</strong> $<?php echo number_format($totalUpcoming, 2); ?></p>
                        <p><strong>Remaining after bills:</strong> $<?php echo number_format($remainingBalance, 2); ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recurring Payments</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                            Add Payment
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Amount</th>
                                        <th>Due Day</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['name']); ?></td>
                                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo $payment['due_day']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-payment" data-id="<?php echo $payment['id']; ?>">Edit</button>
                                            <button class="btn btn-sm btn-danger delete-payment" data-id="<?php echo $payment['id']; ?>">Delete</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
                    <h5 class="modal-title">Add Recurring Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addPaymentForm">
                        <div class="mb-3">
                            <label for="paymentName" class="form-label">Payment Name</label>
                            <input type="text" class="form-control" id="paymentName" required>
                        </div>
                        <div class="mb-3">
                            <label for="paymentAmount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="paymentAmount" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="dueDay" class="form-label">Due Day</label>
                            <input type="number" class="form-control" id="dueDay" min="1" max="31" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="savePayment">Save Payment</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/dashboard.js"></script>
</body>
</html> 