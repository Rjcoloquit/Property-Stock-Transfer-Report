<?php
session_start();
require_once __DIR__ . '/config/rbac.php';
ptr_require_login();
ptr_require_page_access('current_stock_report');
ptr_block_encoder_mutations();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/dashboard_inventory_helper.php';

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';

$filters = [
    'description' => trim($_GET['f_desc'] ?? ''),
    'program' => trim($_GET['f_program'] ?? ''),
];

$rows = [];
$error = '';

try {
    $pdo = getConnection();
    $rows = ptr_current_stock_report_rows($pdo, $filters);
    $rows = array_values(array_filter($rows, static function (array $row): bool {
        return (float) ($row['stock'] ?? 0) > 0;
    }));
} catch (Throwable $e) {
    error_log('current_stock_report.php: ' . $e->getMessage());
    $error = 'Unable to load stock data. Please try again.';
}

$clearUrl = 'current_stock_report.php';

// Map internal keys to GET param names for building clear/preserve links
$getParams = [];
if ($filters['description'] !== '') {
    $getParams['f_desc'] = $filters['description'];
}
if ($filters['program'] !== '') {
    $getParams['f_program'] = $filters['program'];
}
$filterQueryString = $getParams === [] ? '' : '?' . http_build_query($getParams);

$filterSummaryParts = [];
if ($filters['description'] !== '') {
    $filterSummaryParts[] = 'Product description contains "' . $filters['description'] . '"';
}
if ($filters['program'] !== '') {
    $filterSummaryParts[] = 'Program contains "' . $filters['program'] . '"';
}
$filterSummaryText = $filterSummaryParts === [] ? '' : implode(' · ', $filterSummaryParts);
$hasPgpLogo = file_exists(__DIR__ . '/PGP.png');
$hasPhoLogo = file_exists(__DIR__ . '/PHO.png');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Stock Report - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260412">
</head>
<body class="stock-report-page report-page">
    <header class="navbar navbar-expand-lg app-header px-3 px-md-4 no-print">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h6 app-header-title d-flex align-items-center gap-2">
                <?php if ($hasPhoLogo): ?>
                    <a href="home.php" class="app-header-logo-link" aria-label="Go to homepage">
                        <img src="PHO.png" alt="Palawan Health Office Logo" class="app-logo-circle app-logo-md">
                    </a>
                <?php endif; ?>
                <span class="d-inline-flex flex-column lh-sm">
                    <span>Provincial Health Office</span>
                    <small class="fw-normal">Current Stock Report</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="item_list.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Manage Items</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
            </div>
        </div>
    </header>

    <main class="py-4">
        <div class="container stock-report-main">
            <div class="stock-report-print-header d-none" aria-hidden="true">
                <div class="stock-report-print-letterhead">
                    <div class="stock-report-print-logo-slot">
                        <?php if ($hasPgpLogo): ?>
                            <img src="PGP.png" alt="" class="stock-report-print-logo-img" width="72" height="72">
                        <?php endif; ?>
                    </div>
                    <div class="stock-report-print-titles">
                        <div class="stock-report-print-org">Provincial Health Office</div>
                        <div class="stock-report-print-doc-title">Current Stock Report</div>
                        <div class="stock-report-print-subtitle">On-hand inventory by product and batch</div>
                    </div>
                    <div class="stock-report-print-logo-slot">
                        <?php if ($hasPhoLogo): ?>
                            <img src="PHO.png" alt="" class="stock-report-print-logo-img" width="72" height="72">
                        <?php endif; ?>
                    </div>
                </div>
                <hr class="stock-report-print-rule">
                <dl class="stock-report-print-meta">
                    <div class="stock-report-print-meta-row">
                        <dt>Generated</dt>
                        <dd><?= htmlspecialchars(date('F j, Y')) ?></dd>
                    </div>
                    <div class="stock-report-print-meta-row">
                        <dt>Prepared by</dt>
                        <dd><?= htmlspecialchars($username) ?></dd>
                    </div>
                </dl>
                <?php if ($filterSummaryText !== ''): ?>
                    <div class="stock-report-print-filters">
                        <strong>Filters applied:</strong> <?= htmlspecialchars($filterSummaryText) ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger no-print"><?= htmlspecialchars($error) ?></div>
            <?php else: ?>

                <div class="card app-card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
                            <h1 class="h5 mb-0">Current Stock Report</h1>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-primary dashboard-item-search-submit" onclick="window.print()">Print</button>
                            </div>
                        </div>

                        <form method="get" action="current_stock_report.php" class="stock-report-filters no-print mb-3">
                            <h2 class="h6 mb-2">Filters</h2>
                            <p class="small text-muted mb-3">Leave a field blank to ignore it. All active filters apply together.</p>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-6 col-lg-5 inventory-search-bar">
                                    <label class="form-label mb-1" for="f_desc">Product Description</label>
                                    <input type="text" class="form-control" id="f_desc" name="f_desc"
                                        value="<?= htmlspecialchars($filters['description']) ?>" placeholder="Contains…">
                                </div>
                                <div class="col-md-6 col-lg-5 inventory-search-bar">
                                    <label class="form-label mb-1" for="f_program">Program</label>
                                    <input type="text" class="form-control" id="f_program" name="f_program"
                                        value="<?= htmlspecialchars($filters['program']) ?>" placeholder="Contains…">
                                </div>
                                <div class="col-12 col-lg-auto d-flex flex-wrap gap-2 pt-2 pt-lg-0 stock-report-filter-actions">
                                    <button type="submit" class="btn btn-primary dashboard-item-search-submit">Apply filters</button>
                                    <a href="<?= htmlspecialchars($clearUrl) ?>" class="btn btn-outline-secondary dashboard-item-search-submit">Clear all</a>
                                </div>
                            </div>
                        </form>

                        <div class="inventory-table-container stock-report-table-shell mb-0">
                            <div class="inventory-stats no-print">
                                Rows shown:
                                <span class="inventory-stats-value"><?= number_format(count($rows)) ?></span>
                                <?php if ($filterQueryString !== ''): ?>
                                    <span class="small text-muted ms-2">(filters active)</span>
                                <?php endif; ?>
                            </div>
                            <div class="inventory-table-wrapper stock-report-table-scroll">
                                <table class="table inventory-table stock-report-table mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col" class="col-description">Product Description</th>
                                            <th scope="col" class="col-program">Program</th>
                                            <th scope="col" class="col-batch">Batch Number</th>
                                            <th scope="col" class="col-uom">UOM</th>
                                            <th scope="col" class="col-stock">Stock</th>
                                            <th scope="col" class="col-expiry">Expiry Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($rows)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">No stock lines match the current filters.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($rows as $r): ?>
                                                <?php
                                                $exp = $r['expiry_date'] ?? null;
                                                $expDisp = '-';
                                                if ($exp !== null && trim((string) $exp) !== '') {
                                                    $expDisp = (string) $exp;
                                                }
                                                ?>
                                                <tr>
                                                    <td class="col-description"><?= htmlspecialchars((string) ($r['product_description'] ?? '-')) ?></td>
                                                    <td class="col-program"><?php
                                                        $progVal = trim((string) ($r['program'] ?? ''));
                                                        echo htmlspecialchars($progVal !== '' ? $progVal : '-');
                                                        ?></td>
                                                    <td class="col-batch"><?= htmlspecialchars((string) (($r['batch_number'] ?? '') !== '' ? $r['batch_number'] : '-')) ?></td>
                                                    <td class="col-uom"><?= htmlspecialchars((string) ($r['uom'] ?? '-')) ?></td>
                                                    <td class="col-stock"><?= number_format((float) ($r['stock'] ?? 0), 0, '.', ',') ?></td>
                                                    <td class="col-expiry"><?= htmlspecialchars($expDisp) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="stock-report-print-footer d-none text-center" aria-hidden="true">— End of report —</p>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </main>
    <script src="assets/js/smooth_motion.js?v=20260325"></script>
</body>
</html>
