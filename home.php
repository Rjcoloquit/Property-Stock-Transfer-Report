<?php
session_start();

// Require login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/ptr_numbering.php';

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
$totalItems = 0;
$totalTransactions = 0;
$totalPtr = 0;
$totalQuantity = 0;
$totalAmount = 0.0;
$recentTransactions = [];
$topPrograms = [];
$chartLabels = [];
$chartTransactions = [];
$chartQuantities = [];
$chartAmounts = [];
$dashboardError = '';

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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS inventory_records (
            id INT NOT NULL AUTO_INCREMENT,
            expiration_date DATE DEFAULT NULL,
            unit VARCHAR(50) DEFAULT NULL,
            description TEXT,
            quantity INT DEFAULT 0,
            unit_cost DECIMAL(10,2) DEFAULT 0.00,
            program VARCHAR(255) DEFAULT NULL,
            recipient VARCHAR(255) DEFAULT NULL,
            ptr_no VARCHAR(50) DEFAULT NULL,
            record_date DATE DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
    $releaseStatusColumnStmt = $pdo->query("SHOW COLUMNS FROM inventory_records LIKE 'release_status'");
    if (!$releaseStatusColumnStmt || !$releaseStatusColumnStmt->fetch()) {
        $pdo->exec("ALTER TABLE inventory_records ADD COLUMN release_status VARCHAR(20) NOT NULL DEFAULT 'released' AFTER record_date");
    }
    $releasedAtColumnStmt = $pdo->query("SHOW COLUMNS FROM inventory_records LIKE 'released_at'");
    if (!$releasedAtColumnStmt || !$releasedAtColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE inventory_records ADD COLUMN released_at DATETIME DEFAULT NULL AFTER release_status');
    }
    normalizeExistingPtrNumbers($pdo);

    $totalItems = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $totalTransactions = (int) $pdo->query('SELECT COUNT(*) FROM inventory_records WHERE COALESCE(release_status, "released") = "released"')->fetchColumn();
    $totalPtr = (int) $pdo->query('SELECT COUNT(DISTINCT ptr_no) FROM inventory_records WHERE ptr_no IS NOT NULL AND TRIM(ptr_no) <> "" AND COALESCE(release_status, "released") = "released"')->fetchColumn();

    $totalsStmt = $pdo->query('SELECT COALESCE(SUM(quantity), 0) AS total_qty, COALESCE(SUM(quantity * unit_cost), 0) AS total_amount FROM inventory_records WHERE COALESCE(release_status, "released") = "released"');
    $totalsRow = $totalsStmt->fetch();
    $totalQuantity = (int) ($totalsRow['total_qty'] ?? 0);
    $totalAmount = (float) ($totalsRow['total_amount'] ?? 0);

    $recentStmt = $pdo->query('
        SELECT record_date, ptr_no, recipient, description, quantity, unit_cost, program
        FROM inventory_records
        WHERE COALESCE(release_status, "released") = "released"
        ORDER BY record_date DESC, id DESC
        LIMIT 8
    ');
    $recentTransactions = $recentStmt->fetchAll();

    $programStmt = $pdo->query('
        SELECT COALESCE(NULLIF(TRIM(program), ""), "Unassigned") AS program_name, SUM(quantity) AS total_qty
        FROM inventory_records
        WHERE COALESCE(release_status, "released") = "released"
        GROUP BY program_name
        ORDER BY total_qty DESC
        LIMIT 5
    ');
    $topPrograms = $programStmt->fetchAll();

    $trendStmt = $pdo->query('
        SELECT record_date, COUNT(*) AS tx_count, COALESCE(SUM(quantity), 0) AS total_qty, COALESCE(SUM(quantity * unit_cost), 0) AS total_amount
        FROM inventory_records
        WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
          AND COALESCE(release_status, "released") = "released"
        GROUP BY record_date
        ORDER BY record_date ASC
    ');
    $trendRows = $trendStmt->fetchAll();
    $trendByDate = [];
    foreach ($trendRows as $row) {
        $trendByDate[(string) $row['record_date']] = $row;
    }

    for ($i = 6; $i >= 0; $i--) {
        $dateKey = date('Y-m-d', strtotime("-{$i} days"));
        $label = date('M d', strtotime($dateKey));
        $row = $trendByDate[$dateKey] ?? null;

        $chartLabels[] = $label;
        $chartTransactions[] = (int) ($row['tx_count'] ?? 0);
        $chartQuantities[] = (int) ($row['total_qty'] ?? 0);
        $chartAmounts[] = round((float) ($row['total_amount'] ?? 0), 2);
    }

} catch (PDOException $e) {
    $dashboardError = 'Unable to load dashboard data right now. Please check your database setup.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260305">
</head>
<body class="home-page">
    <header class="navbar navbar-expand-lg navbar-light bg-white app-header px-3 px-md-4">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h6 app-header-title d-flex align-items-center gap-2">
                <?php if (file_exists(__DIR__ . '/PHO.png')): ?>
                    <a href="home.php" class="app-header-logo-link" aria-label="Go to homepage">
                        <img src="PHO.png" alt="Palawan Health Office Logo" class="app-logo-circle app-logo-md">
                    </a>
                <?php endif; ?>
                <span class="d-inline-flex flex-column lh-sm">
                    <span>Provincial Health Office</span>
                    <small class="fw-normal">Home</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip">
                    Signed in as <strong><?= htmlspecialchars($username) ?></strong>
                </span>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
            </div>
        </div>
    </header>
    <main class="py-4">
        <div class="container">
            <?php if ($dashboardError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($dashboardError) ?></div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-lg-3">
                    <div class="card app-card dashboard-sidebar">
                        <div class="card-body">
                            <h2 class="h6 mb-1">Navigation Panel</h2>
                            <p class="small text-muted mb-3">Quick access to core supply modules.</p>
                            <nav class="dashboard-nav" aria-label="Dashboard navigation">
                                <a href="create_ptr.php" class="dashboard-nav-link dashboard-nav-link-primary">
                                    <span class="dashboard-nav-title d-flex align-items-center justify-content-between gap-2 w-100">
                                        <span>Create New PTR</span>
                                        <span class="dashboard-nav-icon">+</span>
                                    </span>
                                    <span class="dashboard-nav-meta">Prepare and save a new property transfer report</span>
                                </a>
                                <a href="report.php" class="dashboard-nav-link">
                                    <span class="dashboard-nav-title">Transaction History</span>
                                    <span class="dashboard-nav-meta">Review and filter saved transactions</span>
                                </a>
                                <a href="outbound_summary_report.php" class="dashboard-nav-link">
                                    <span class="dashboard-nav-title">Outbound Summary Report</span>
                                    <span class="dashboard-nav-meta">Summary of released items and recipients</span>
                                </a>
                                <a href="pending_transactions.php" class="dashboard-nav-link">
                                    <span class="dashboard-nav-title">Pending Transactions</span>
                                    <span class="dashboard-nav-meta">Release PTR before stock deduction and printing</span>
                                </a>
                                <a href="stock_card.php" class="dashboard-nav-link">
                                    <span class="dashboard-nav-title">Stock Card</span>
                                    <span class="dashboard-nav-meta">Prepare stock card details and running balances</span>
                                </a>
                                <a href="item_list.php" class="dashboard-nav-link">
                                    <span class="dashboard-nav-title">Manage Items</span>
                                    <span class="dashboard-nav-meta">Maintain item descriptions and costs</span>
                                </a>
                                <a href="notifications.php" class="dashboard-nav-link">
                                    <span class="dashboard-nav-title">Notifications</span>
                                    <span class="dashboard-nav-meta">Track expiration alerts by priority</span>
                                </a>
                                <a href="incident_report.php" class="dashboard-nav-link">
                                    <span class="dashboard-nav-title">Incident Report</span>
                                    <span class="dashboard-nav-meta">Warehouse operations incident report (Annex 16)</span>
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>
                <div class="col-lg-9">
                    <div class="card app-card mb-3">
                        <div class="card-body">
                            <h1 class="h4 mb-1">Dashboard</h1>
                            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($username) ?>. Here is your supply operations snapshot.</p>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-xl-3">
                            <div class="card app-card dashboard-stat-card h-100">
                                <div class="card-body">
                                    <div class="dashboard-stat-label">Total Items</div>
                                    <div class="dashboard-stat-value" data-animate="<?= $totalItems ?>"><?= number_format($totalItems) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card app-card dashboard-stat-card h-100">
                                <div class="card-body">
                                    <div class="dashboard-stat-label">Transactions</div>
                                    <div class="dashboard-stat-value" data-animate="<?= $totalTransactions ?>"><?= number_format($totalTransactions) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card app-card dashboard-stat-card h-100">
                                <div class="card-body">
                                    <div class="dashboard-stat-label">PTR Documents</div>
                                    <div class="dashboard-stat-value" data-animate="<?= $totalPtr ?>"><?= number_format($totalPtr) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="card app-card dashboard-stat-card h-100">
                                <div class="card-body">
                                    <div class="dashboard-stat-label">Total Released Qty</div>
                                    <div class="dashboard-stat-value" data-animate="<?= $totalQuantity ?>"><?= number_format($totalQuantity) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-lg-8">
                            <div class="card app-card h-100">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                        <h2 class="h6 mb-0">7-Day Activity Trend</h2>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Trend type">
                                            <button type="button" class="btn btn-outline-primary trend-toggle active" data-series="transactions">Transactions</button>
                                            <button type="button" class="btn btn-outline-primary trend-toggle" data-series="quantity">Quantity</button>
                                            <button type="button" class="btn btn-outline-primary trend-toggle" data-series="amount">Amount</button>
                                        </div>
                                    </div>
                                    <div class="trend-chart-canvas-wrap">
                                        <canvas id="trendChartCanvas" aria-label="Trend bar chart"></canvas>
                                    </div>
                                    <div id="trendLegend" class="small text-muted mt-2"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card app-card h-100">
                                <div class="card-body">
                                    <h2 class="h6 mb-3">Top Programs by Quantity</h2>
                                    <?php if (empty($topPrograms)): ?>
                                        <div class="text-muted small">No program data yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($topPrograms as $program): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="small me-2"><?= htmlspecialchars($program['program_name']) ?></span>
                                                <span class="badge text-bg-light border"><?= number_format((int) $program['total_qty']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <hr>
                                    <div class="small text-muted">Total estimated released value</div>
                                    <div class="h5 mb-0">PHP <?= number_format($totalAmount, 2) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card app-card">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <h2 class="h6 mb-0">Recent Transactions</h2>
                                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                                    <input type="text" id="txFilterInput" class="form-control form-control-sm recent-tx-filter-input" placeholder="Filter recent transactions">
                                    <a href="create_ptr.php" class="btn btn-primary btn-sm">+ Add Transaction</a>
                                </div>
                            </div>
                            <div class="table-responsive recent-tx-table-wrap">
                                <table class="table table-sm table-striped align-middle mb-0 recent-tx-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="tx-col-date">Date</th>
                                            <th class="tx-col-ptr">PTR No.</th>
                                            <th class="tx-col-recipient">Recipient</th>
                                            <th class="tx-col-description">Description</th>
                                            <th class="tx-col-qty text-end">Qty</th>
                                            <th class="tx-col-unit-cost text-end">Unit Cost</th>
                                            <th class="tx-col-amount text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentTxBody">
                                        <?php if (empty($recentTransactions)): ?>
                                            <tr>
                                                <td colspan="7" class="text-muted">No transactions found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentTransactions as $row): ?>
                                                <tr>
                                                    <td class="tx-col-date text-nowrap"><?= htmlspecialchars((string) ($row['record_date'] ?? '-')) ?></td>
                                                    <td class="tx-col-ptr text-nowrap"><?= htmlspecialchars((string) ($row['ptr_no'] ?? '-')) ?></td>
                                                    <td class="tx-col-recipient tx-ellipsis" title="<?= htmlspecialchars((string) ($row['recipient'] ?? '-')) ?>">
                                                        <?= htmlspecialchars((string) ($row['recipient'] ?? '-')) ?>
                                                    </td>
                                                    <td class="tx-col-description tx-ellipsis" title="<?= htmlspecialchars((string) ($row['description'] ?? '-')) ?>">
                                                        <?= htmlspecialchars((string) ($row['description'] ?? '-')) ?>
                                                    </td>
                                                    <td class="tx-col-qty text-end text-nowrap"><?= number_format((int) ($row['quantity'] ?? 0)) ?></td>
                                                    <td class="tx-col-unit-cost text-end text-nowrap"><?= number_format((float) ($row['unit_cost'] ?? 0), 2) ?></td>
                                                    <td class="tx-col-amount text-end text-nowrap"><?= number_format((float) ($row['quantity'] ?? 0) * (float) ($row['unit_cost'] ?? 0), 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        window.homeConfig = {
            trendData: {
                labels: <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                transactions: <?= json_encode($chartTransactions) ?>,
                quantity: <?= json_encode($chartQuantities) ?>,
                amount: <?= json_encode($chartAmounts) ?>
            }
        };
    </script>
    <script src="assets/js/smooth_motion.js?v=20260325"></script>
    <script src="assets/js/home.js"></script>
</body>
</html>
