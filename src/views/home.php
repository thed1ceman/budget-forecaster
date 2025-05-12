<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - <?php echo htmlspecialchars($config['app']['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/"><?php echo htmlspecialchars($config['app']['name']); ?></a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/login">Login</a>
                <a class="nav-link" href="/register">Register</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <h1 class="display-4 mb-4">Welcome to <?php echo htmlspecialchars($config['app']['name']); ?></h1>
                <p class="lead mb-4">Take control of your finances by tracking your recurring payments and managing your budget effectively.</p>
                
                <div class="row mt-5">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3>Track Payments</h3>
                                <p>Keep track of all your recurring payments and their due dates in one place.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3>Smart Planning</h3>
                                <p>Calculate your remaining balance and plan your expenses until your next payday.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="/register" class="btn btn-primary btn-lg me-3">Get Started</a>
                    <a href="/login" class="btn btn-outline-primary btn-lg">Login</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 