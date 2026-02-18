<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
$expiredAlerts = [];
$expiringWithinSixMonthsAlerts = [];
$expiringSixMonthsUpAlerts = [];
$expiredCount = 0;
$expiringWithinSixMonthsCount = 0;
$expiringSixMonthsUpCount = 0;
$error = '';

try {
    $pdo = getConnection();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INT NOT NULL AUTO_INCREMENT,
            product_description TEXT,
            uom VARCHAR(50) DEFAULT NULL,
            cost_per_unit DECIMAL(12,2) DEFAULT 0.00,
            expiry_date DATE DEFAULT NULL,
            program VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS product_batches (
            id INT NOT NULL AUTO_INCREMENT,
            product_id INT NOT NULL,
            batch_number VARCHAR(100) NOT NULL,
            stock_quantity INT DEFAULT 0,
            expiry_date DATE DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );

    $hasProductsExpiryDate = false;
    $hasProductBatchesTable = false;
    $expiryColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'expiry_date'");
    if ($expiryColumnStmt && $expiryColumnStmt->fetch()) {
        $hasProductsExpiryDate = true;
    }
    $batchTableStmt = $pdo->query("SHOW TABLES LIKE 'product_batches'");
    if ($batchTableStmt && $batchTableStmt->fetch()) {
        $hasProductBatchesTable = true;
    }

    $today = strtotime(date('Y-m-d'));
    $sixMonthsDays = 183;

    $expiryRows = [];
    if ($hasProductBatchesTable) {
        $expiryStmt = $pdo->query('
            SELECT
                p.product_description,
                p.program,
                b.batch_number,
                b.expiry_date,
                b.stock_quantity
            FROM product_batches b
            INNER JOIN products p ON p.id = b.product_id
            WHERE b.expiry_date IS NOT NULL
            ORDER BY b.expiry_date ASC
        ');
        $expiryRows = $expiryStmt->fetchAll();
    } elseif ($hasProductsExpiryDate) {
        $expiryStmt = $pdo->query('
            SELECT
                product_description,
                program,
                NULL AS batch_number,
                expiry_date,
                NULL AS stock_quantity
            FROM products
            WHERE expiry_date IS NOT NULL
            ORDER BY expiry_date ASC
        ');
        $expiryRows = $expiryStmt->fetchAll();
    }

    foreach ($expiryRows as $row) {
        $expiryDate = (string) ($row['expiry_date'] ?? '');
        if ($expiryDate === '') {
            continue;
        }

        $expiryTs = strtotime($expiryDate);
        if ($expiryTs === false) {
            continue;
        }

        $daysToExpiry = (int) floor(($expiryTs - $today) / 86400);
        $alertItem = [
            'description' => (string) ($row['product_description'] ?? '-'),
            'program' => (string) ($row['program'] ?? ''),
            'batch_number' => (string) ($row['batch_number'] ?? ''),
            'expiry_date' => $expiryDate,
            'stock_quantity' => $row['stock_quantity'] !== null ? (int) $row['stock_quantity'] : null,
            'days_to_expiry' => $daysToExpiry,
        ];

        if ($daysToExpiry < 0) {
            $expiredAlerts[] = $alertItem;
        } elseif ($daysToExpiry <= $sixMonthsDays) {
            $expiringWithinSixMonthsAlerts[] = $alertItem;
        } else {
            $expiringSixMonthsUpAlerts[] = $alertItem;
        }
    }

    $expiredCount = count($expiredAlerts);
    $expiringWithinSixMonthsCount = count($expiringWithinSixMonthsAlerts);
    $expiringSixMonthsUpCount = count($expiringSixMonthsUpAlerts);
} catch (PDOException $e) {
    $error = 'Unable to load notifications right now. Please check your database setup.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260212">
</head>
<body class="notifications-page">
    <header class="navbar navbar-expand-lg navbar-light bg-white app-header px-3 px-md-4">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h6 app-header-title d-flex align-items-center gap-2">
                <?php if (file_exists(__DIR__ . '/PHO.png')): ?>
                    <a href="home.php" class="app-header-logo-link" aria-label="Go to homepage">
                        <img src="PHO.png" alt="Palawan Health Office Logo" class="app-logo-circle" style="height: 40px; width: 40px;">
                    </a>
                <?php endif; ?>
                <span class="d-inline-flex flex-column lh-sm">
                    <span>Provincial Health Office</span>
                    <small class="fw-normal" style="font-size: 0.72rem;">Notifications</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
            </div>
        </div>
    </header>
    <main class="py-4">
        <div class="container">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php else: ?>
                <div class="card app-card mb-3 notifications-hero-card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <h1 class="h5 mb-0">Expiration Notifications</h1>
                            <div class="notif-search-wrap">
                                <input
                                    type="text"
                                    id="expirySearchInput"
                                    class="form-control form-control-sm notif-search-input"
                                    placeholder="Search item name, batch, or program"
                                    aria-label="Search expiration notifications"
                                >
                            </div>
                        </div>
                        <p class="text-muted mb-0">Track inventory risk by urgency: expired, expiring within 6 months, and long-term expirations.</p>
                        <div class="row g-2 mt-2">
                            <div class="col-md-4">
                                <div class="notif-summary-box notif-summary-danger">
                                    <div class="notif-summary-label">Expired</div>
                                    <div class="notif-summary-value"><?= number_format($expiredCount) ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="notif-summary-box notif-summary-warning">
                                    <div class="notif-summary-label">Within 6 Months</div>
                                    <div class="notif-summary-value"><?= number_format($expiringWithinSixMonthsCount) ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="notif-summary-box notif-summary-success">
                                    <div class="notif-summary-label">6+ Months</div>
                                    <div class="notif-summary-value"><?= number_format($expiringSixMonthsUpCount) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="card app-card dashboard-notif-major h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h2 class="h6 mb-0">Expired</h2>
                                    <span class="badge rounded-pill text-bg-danger"><?= number_format($expiredCount) ?></span>
                                </div>
                                <?php if ($expiredCount === 0): ?>
                                    <div class="small text-muted mb-0">No expired items right now.</div>
                                <?php else: ?>
                                    <div class="small mb-2 text-muted">
                                        <strong><?= number_format($expiredCount) ?></strong> item(s) already expired.
                                    </div>
                                    <div class="dashboard-notif-list dashboard-notif-scroll">
                                        <?php foreach ($expiredAlerts as $alert): ?>
                                            <div class="dashboard-notif-item">
                                                <div>
                                                    <strong><?= htmlspecialchars($alert['description']) ?></strong>
                                                    <?php if ($alert['batch_number'] !== ''): ?>
                                                        <span class="text-muted">| Batch: <?= htmlspecialchars($alert['batch_number']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($alert['program'] !== ''): ?>
                                                        <div class="small text-muted"><?= htmlspecialchars($alert['program']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-end">
                                                    <div><?= htmlspecialchars($alert['expiry_date']) ?></div>
                                                    <div class="text-danger fw-semibold">
                                                        Expired <?= abs($alert['days_to_expiry']) ?> day(s) ago
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card app-card dashboard-notif-other h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h2 class="h6 mb-0">Expiring within 6 months</h2>
                                    <span class="badge rounded-pill text-bg-warning"><?= number_format($expiringWithinSixMonthsCount) ?></span>
                                </div>
                                <?php if ($expiringWithinSixMonthsCount === 0): ?>
                                    <div class="small text-muted mb-0">No items expiring within 6 months.</div>
                                <?php else: ?>
                                    <div class="small mb-2 text-muted">
                                        <strong><?= number_format($expiringWithinSixMonthsCount) ?></strong> item(s) need priority monitoring.
                                    </div>
                                    <div class="dashboard-notif-list dashboard-notif-scroll">
                                        <?php foreach ($expiringWithinSixMonthsAlerts as $alert): ?>
                                            <div class="dashboard-notif-item">
                                                <div>
                                                    <strong><?= htmlspecialchars($alert['description']) ?></strong>
                                                    <?php if ($alert['batch_number'] !== ''): ?>
                                                        <span class="text-muted">| Batch: <?= htmlspecialchars($alert['batch_number']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($alert['program'] !== ''): ?>
                                                        <div class="small text-muted"><?= htmlspecialchars($alert['program']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-end">
                                                    <div><?= htmlspecialchars($alert['expiry_date']) ?></div>
                                                    <div class="text-warning-emphasis fw-semibold">
                                                        Due in <?= $alert['days_to_expiry'] ?> day(s)
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card app-card dashboard-notif-upcoming h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h2 class="h6 mb-0">Expiring in 6 months and up</h2>
                                    <span class="badge rounded-pill text-bg-success"><?= number_format($expiringSixMonthsUpCount) ?></span>
                                </div>
                                <?php if ($expiringSixMonthsUpCount === 0): ?>
                                    <div class="small text-muted mb-0">No items in this range.</div>
                                <?php else: ?>
                                    <div class="small mb-2 text-muted">
                                        <strong><?= number_format($expiringSixMonthsUpCount) ?></strong> item(s) for long-term monitoring.
                                    </div>
                                    <div class="dashboard-notif-list dashboard-notif-scroll">
                                        <?php foreach ($expiringSixMonthsUpAlerts as $alert): ?>
                                            <div class="dashboard-notif-item">
                                                <div>
                                                    <strong><?= htmlspecialchars($alert['description']) ?></strong>
                                                    <?php if ($alert['batch_number'] !== ''): ?>
                                                        <span class="text-muted">| Batch: <?= htmlspecialchars($alert['batch_number']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($alert['program'] !== ''): ?>
                                                        <div class="small text-muted"><?= htmlspecialchars($alert['program']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-end">
                                                    <div><?= htmlspecialchars($alert['expiry_date']) ?></div>
                                                    <div class="text-success fw-semibold">
                                                        Due in <?= $alert['days_to_expiry'] ?> day(s)
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="assets/js/notifications.js"></script>
</body>
</html>
