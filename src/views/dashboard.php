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
    <style>
        .calendar {
            background: #fff;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .calendar-header {
            margin-bottom: 1rem;
        }
        .calendar-header h4 {
            margin: 0;
            color: #2c3e50;
        }
        .calendar-grid {
            display: grid;
            gap: 1px;
            background: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            overflow: hidden;
        }
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .calendar-weekday {
            padding: 0.5rem;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e9ecef;
        }
        .calendar-day {
            aspect-ratio: 1;
            display: grid;
            grid-template-rows: auto 1fr;
            background: white;
            padding: 0.25rem;
            position: relative;
            font-size: 0.9rem;
            color: #495057;
            transition: all 0.2s ease;
            min-height: 60px;
            width: 100%;
            box-sizing: border-box;
        }
        .calendar-day-number {
            text-align: left;
            padding: 0.25rem;
            font-weight: 500;
        }
        .calendar-day-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            gap: 0.25rem;
            font-size: 0.8rem;
            height: 100%;
            overflow: hidden;
        }
        .calendar-day-events {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            width: 100%;
            overflow: hidden;
        }
        .calendar-day-footer {
            margin-top: auto;
            width: 100%;
        }
        .calendar-day:hover {
            background: #f8f9fa;
        }
        .calendar-day.text-muted {
            color: #adb5bd;
            background: #f8f9fa;
        }
        .calendar-day.today {
            background-color: #e3f2fd;
            color: #0d6efd;
        }
        .calendar-day.today .calendar-day-number {
            font-weight: bold;
        }
        .calendar-day.payday {
            background-color: #e8f5e9;
            color: #198754;
        }
        .calendar-day.payday .calendar-day-number {
            font-weight: bold;
        }
        .payday-indicator {
            position: absolute;
            bottom: 2px;
            right: 2px;
            font-size: 0.8em;
        }
        .balance-indicator {
            background: #e3f2fd;
            color: #0d6efd;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
            text-align: center;
            width: 100%;
        }
        .payment-indicator {
            background: #fff3cd;
            color: #856404;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
            text-align: center;
            width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            box-sizing: border-box;
        }
        .payment-amount {
            font-weight: bold;
        }
        .calendar-day.today .balance-indicator {
            background: #0d6efd;
            color: white;
        }
        .running-balance {
            color: #6c757d;
            font-size: 0.75rem;
            text-align: center;
            width: 100%;
            padding: 0.25rem 0;
            border-top: 1px solid #dee2e6;
            margin-top: 0.25rem;
        }
        .calendar-day.today .running-balance {
            display: none;
        }
        @media (max-width: 768px) {
            .calendar-weekday {
                font-size: 0.8rem;
                padding: 0.25rem;
            }
            .calendar-day {
                font-size: 0.8rem;
                min-height: 50px;
            }
            .calendar-day-content {
                font-size: 0.7rem;
            }
            .running-balance {
                font-size: 0.65rem;
                padding: 0.15rem 0;
            }
        }
    </style>
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
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

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
                        <!-- Calendar -->
                        <div class="calendar mb-4">
                            <div class="calendar-header text-center mb-2">
                                <h4><?php echo date('F Y'); ?></h4>
                            </div>
                            <div class="calendar-grid">
                                <div class="calendar-weekdays">
                                    <?php
                                    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                                    foreach ($weekdays as $day) {
                                        echo "<div class='calendar-weekday'>$day</div>";
                                    }
                                    ?>
                                </div>
                                <div class="calendar-days">
                                    <?php
                                    // Get all payments for the current month
                                    $firstDay = new DateTime(date('Y-m-01'));
                                    $lastDay = new DateTime(date('Y-m-t'));
                                    $today = new DateTime();
                                    
                                    // Adjust first day to Monday (1) instead of Sunday (0)
                                    $firstDayOfWeek = $firstDay->format('N'); // 1 (Monday) to 7 (Sunday)
                                    $currentDay = clone $firstDay;
                                    $currentDay->modify('-' . ($firstDayOfWeek - 1) . ' days');

                                    // Create a map of payments by date
                                    $paymentsByDate = [];
                                    foreach ($payments as $payment) {
                                        $dueDate = new DateTime($payment['due_date']);
                                        
                                        // For weekly payments, calculate all instances in the current month
                                        if ($payment['frequency'] === 'weekly') {
                                            $tempDate = clone $dueDate;
                                            // Go back to the first occurrence in the current month
                                            while ($tempDate > $firstDay) {
                                                $tempDate->modify('-1 week');
                                            }
                                            // Add all weekly occurrences up to the end of the month
                                            while ($tempDate <= $lastDay) {
                                                if ($tempDate >= $firstDay) {
                                                    $dateKey = $tempDate->format('Y-m-d');
                                                    if (!isset($paymentsByDate[$dateKey])) {
                                                        $paymentsByDate[$dateKey] = [];
                                                    }
                                                    $paymentsByDate[$dateKey][] = $payment;
                                                }
                                                $tempDate->modify('+1 week');
                                            }
                                        } else {
                                            // For non-weekly payments, just add the single occurrence
                                            $dateKey = $dueDate->format('Y-m-d');
                                            if (!isset($paymentsByDate[$dateKey])) {
                                                $paymentsByDate[$dateKey] = [];
                                            }
                                            $paymentsByDate[$dateKey][] = $payment;
                                        }
                                    }

                                    // Calculate running balance for each day
                                    $runningBalance = $settings['current_balance'];
                                    $balanceByDate = [];
                                    $currentDate = clone $today;
                                    $lastDayOfMonth = clone $lastDay;
                                    
                                    // First, get all payments for the current month
                                    $monthPayments = [];
                                    foreach ($payments as $payment) {
                                        $dueDate = new DateTime($payment['due_date']);
                                        
                                        // For weekly payments, calculate all instances
                                        if ($payment['frequency'] === 'weekly') {
                                            $tempDate = clone $dueDate;
                                            // Go back to the first occurrence in the current month
                                            while ($tempDate > $firstDay) {
                                                $tempDate->modify('-1 week');
                                            }
                                            // Add all weekly occurrences up to the end of the month
                                            while ($tempDate <= $lastDay) {
                                                if ($tempDate >= $today) {
                                                    $dateKey = $tempDate->format('Y-m-d');
                                                    if (!isset($monthPayments[$dateKey])) {
                                                        $monthPayments[$dateKey] = [];
                                                    }
                                                    $monthPayments[$dateKey][] = $payment;
                                                }
                                                $tempDate->modify('+1 week');
                                            }
                                        } else {
                                            // For non-weekly payments, just add the single occurrence
                                            if ($dueDate >= $today && $dueDate <= $lastDayOfMonth) {
                                                $dateKey = $dueDate->format('Y-m-d');
                                                if (!isset($monthPayments[$dateKey])) {
                                                    $monthPayments[$dateKey] = [];
                                                }
                                                $monthPayments[$dateKey][] = $payment;
                                            }
                                        }
                                    }
                                    
                                    // Calculate running balance for each day
                                    while ($currentDate <= $lastDayOfMonth) {
                                        $dateKey = $currentDate->format('Y-m-d');
                                        $balanceByDate[$dateKey] = $runningBalance;
                                        
                                        // Subtract any payments due on this day
                                        if (isset($monthPayments[$dateKey])) {
                                            foreach ($monthPayments[$dateKey] as $payment) {
                                                $runningBalance -= $payment['amount'];
                                            }
                                        }
                                        
                                        $currentDate->modify('+1 day');
                                    }

                                    // Display calendar days
                                    while ($currentDay <= $lastDay) {
                                        $isCurrentMonth = $currentDay->format('m') === $firstDay->format('m');
                                        $isToday = $currentDay->format('Y-m-d') === $today->format('Y-m-d');
                                        $isPayday = $isCurrentMonth && $currentDay->format('d') == $settings['payday'];
                                        $isFuture = $currentDay > $today;
                                        
                                        $classes = ['calendar-day'];
                                        if (!$isCurrentMonth) $classes[] = 'text-muted';
                                        if ($isToday) $classes[] = 'today';
                                        if ($isPayday) $classes[] = 'payday';
                                        
                                        echo "<div class='" . implode(' ', $classes) . "'>";
                                        echo "<div class='calendar-day-number'>" . $currentDay->format('j') . "</div>";
                                        echo "<div class='calendar-day-content'>";
                                        echo "<div class='calendar-day-events'>";
                                        
                                        // Show balance on today's date
                                        if ($isToday) {
                                            $symbol = '';
                                            switch($settings['currency']) {
                                                case 'USD': $symbol = '$'; break;
                                                case 'EUR': $symbol = '€'; break;
                                                default: $symbol = '£';
                                            }
                                            echo "<div class='balance-indicator'>" . $symbol . number_format($settings['current_balance'], 2) . "</div>";
                                        }
                                        
                                        // Show payments for this date
                                        $dateKey = $currentDay->format('Y-m-d');
                                        if (isset($paymentsByDate[$dateKey])) {
                                            foreach ($paymentsByDate[$dateKey] as $payment) {
                                                $symbol = '';
                                                switch($settings['currency']) {
                                                    case 'USD': $symbol = '$'; break;
                                                    case 'EUR': $symbol = '€'; break;
                                                    default: $symbol = '£';
                                                }
                                                echo "<div class='payment-indicator' title='" . htmlspecialchars($payment['name']) . "'>" . 
                                                     htmlspecialchars($payment['name']) . " <span class='payment-amount'>" . 
                                                     $symbol . number_format($payment['amount'], 2) . "</span></div>";
                                            }
                                        }
                                        
                                        echo "</div>"; // Close calendar-day-events
                                        
                                        // Show running balance for future dates
                                        if ($isFuture && $isCurrentMonth) {
                                            $dateKey = $currentDay->format('Y-m-d');
                                            if (isset($balanceByDate[$dateKey])) {
                                                $symbol = '';
                                                switch($settings['currency']) {
                                                    case 'USD': $symbol = '$'; break;
                                                    case 'EUR': $symbol = '€'; break;
                                                    default: $symbol = '£';
                                                }
                                                echo "<div class='calendar-day-footer'>";
                                                echo "<div class='running-balance'>" . $symbol . number_format($balanceByDate[$dateKey], 2) . "</div>";
                                                echo "</div>";
                                            }
                                        }
                                        
                                        echo "</div>"; // Close calendar-day-content
                                        if ($isPayday) echo "<span class='payday-indicator'>💰</span>";
                                        echo "</div>";
                                        
                                        $currentDay->modify('+1 day');
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($payments)): ?>
                            <p class="text-muted">No payments added yet. Click "Add Payment" to get started.</p>
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