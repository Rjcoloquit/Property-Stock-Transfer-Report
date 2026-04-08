<?php
session_start();
require_once __DIR__ . '/config/rbac.php';
ptr_require_login();
ptr_require_page_access('outbound_summary_report');
ptr_block_encoder_mutations();

require_once __DIR__ . '/config/database.php';

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
$error = '';
$rows = [];
$totalReleasedQty = 0;
$totalReleasedAmount = 0.0;

try {
    $pdo = getConnection();
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
    $poNoColumnStmt = $pdo->query("SHOW COLUMNS FROM inventory_records LIKE 'po_no'");
    if (!$poNoColumnStmt || !$poNoColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE inventory_records ADD COLUMN po_no VARCHAR(100) DEFAULT NULL AFTER program');
    }
    $releaseStatusColumnStmt = $pdo->query("SHOW COLUMNS FROM inventory_records LIKE 'release_status'");
    if (!$releaseStatusColumnStmt || !$releaseStatusColumnStmt->fetch()) {
        $pdo->exec("ALTER TABLE inventory_records ADD COLUMN release_status VARCHAR(20) NOT NULL DEFAULT 'released' AFTER record_date");
    }
    $releasedAtColumnStmt = $pdo->query("SHOW COLUMNS FROM inventory_records LIKE 'released_at'");
    if (!$releasedAtColumnStmt || !$releasedAtColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE inventory_records ADD COLUMN released_at DATETIME DEFAULT NULL AFTER release_status');
    }

    $stmt = $pdo->query(
        'SELECT
            program,
            po_no,
            COALESCE(DATE(released_at), record_date) AS date_released,
            description,
            expiration_date,
            quantity,
            unit,
            unit_cost,
            (quantity * unit_cost) AS total_cost,
            recipient,
            ptr_no
        FROM inventory_records
        WHERE COALESCE(release_status, "released") = "released"
        ORDER BY COALESCE(released_at, record_date) DESC, id DESC'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $reportRow) {
        $totalReleasedQty += (int) ($reportRow['quantity'] ?? 0);
        $totalReleasedAmount += (float) ($reportRow['total_cost'] ?? 0);
    }
} catch (PDOException $e) {
    $error = 'Unable to load outbound summary report right now.';
}

function formatMoney($value): string
{
    return number_format((float) $value, 2, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outbound Summary Report</title>
    <link rel="stylesheet" href="style.css?v=20260408outfit">
    <style>
        /* Print preview only — page-local (restores prior print layout; screen uses style.css) */
        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            body {
                margin: 0;
                padding: 0;
                font-size: 8pt;
            }
            .no-print {
                display: none !important;
            }
            .app-header {
                position: static !important;
                margin-bottom: 5mm;
                border-bottom: 1px solid #000;
                padding-bottom: 5mm !important;
            }
            .app-header-title span {
                font-size: 10pt !important;
            }
            .app-header-title small {
                font-size: 8pt !important;
            }
            .outbound-page .print-only {
                display: block !important;
            }
            .container {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
            }
            .card-body {
                padding: 0 !important;
            }
            .table-responsive {
                overflow: visible !important;
            }
            .table {
                font-size: 7.5pt !important;
                margin-bottom: 0 !important;
                width: 100%;
                min-width: 0 !important;
                table-layout: auto !important;
                border-collapse: collapse;
            }
            .table thead,
            .table tbody,
            .table tfoot {
                display: table-row-group;
            }
            .table th,
            .table td {
                border: 1px solid #000 !important;
                padding: 2pt 3pt !important;
                white-space: normal !important;
                word-break: normal;
                overflow-wrap: break-word;
                color: #000 !important;
                background: #fff !important;
            }
            .table thead th {
                background-color: #fff !important;
                font-weight: 700;
                text-align: center;
                vertical-align: middle;
                border-bottom: 2px solid #000 !important;
            }
            .table tbody tr {
                page-break-inside: avoid;
            }
            .outbound-print-meta {
                border: 1px solid #000;
                padding: 3mm;
                margin-bottom: 3mm;
                font-size: 8pt;
            }
            .outbound-print-meta strong {
                font-weight: 700;
            }
            h1, .h5 {
                margin: 0 0 5mm 0 !important;
                font-size: 12pt !important;
            }
        }
    </style>
</head>
<body class="outbound-page report-page">
    <header class="navbar navbar-expand-lg app-header px-3 px-md-4 no-print">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h6 app-header-title d-flex align-items-center gap-2">
                <?php if (file_exists(__DIR__ . '/PHO.png')): ?>
                    <a href="home.php" class="app-header-logo-link" aria-label="Go to homepage">
                        <img src="PHO.png" alt="Palawan Health Office Logo" class="app-logo-circle app-logo-md">
                    </a>
                <?php endif; ?>
                <span class="d-inline-flex flex-column lh-sm">
                    <span>Provincial Health Office</span>
                    <small class="fw-normal">Outbound Summary Report</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="current_stock_report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Stock Report</a>
                <a href="report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Report</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
            </div>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <div class="card app-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
                        <h1 class="h5 mb-0">Outbound Summary Report</h1>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="outbound-kpi-chip">Rows: <strong id="visibleRowsCount"><?= number_format(count($rows)) ?></strong></span>
                            <span class="outbound-kpi-chip">Qty: <strong id="visibleQtyTotal"><?= number_format($totalReleasedQty) ?></strong></span>
                            <span class="outbound-kpi-chip">Amount: <strong id="visibleAmountTotal"><?= formatMoney($totalReleasedAmount) ?></strong></span>
                            <button type="button" class="btn btn-primary dashboard-item-search-submit" onclick="window.print();">Print</button>
                        </div>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 mb-3 no-print"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <!-- Filter Section -->
                    <div class="card mb-4 no-print outbound-filter-card">
                        <div class="card-body">
                            <h2 class="h6 mb-3">Filter records</h2>
                            <p class="small text-muted mb-3">Filter the table below; KPI chips and grand total update to match visible rows.</p>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3 inventory-search-bar">
                                    <label for="filterProgram" class="form-label mb-1">End-user / Program</label>
                                    <input type="text" class="form-control" id="filterProgram" placeholder="Search program…">
                                </div>
                                <div class="col-md-3 inventory-search-bar">
                                    <label for="filterDescription" class="form-label mb-1">Item Description</label>
                                    <input type="text" class="form-control" id="filterDescription" placeholder="Search item…">
                                </div>
                                <div class="col-md-2 inventory-search-bar">
                                    <label for="filterRecipient" class="form-label mb-1">Recipient</label>
                                    <input type="text" class="form-control" id="filterRecipient" placeholder="Search recipient…">
                                </div>
                                <div class="col-md-2 inventory-search-bar">
                                    <label for="filterDateFrom" class="form-label mb-1">Date From</label>
                                    <input type="date" class="form-control" id="filterDateFrom">
                                </div>
                                <div class="col-md-2 inventory-search-bar">
                                    <label for="filterDateTo" class="form-label mb-1">Date To</label>
                                    <input type="date" class="form-control" id="filterDateTo">
                                </div>
                                <div class="col-md-2 d-grid">
                                    <label class="form-label mb-1 d-none d-md-block">&nbsp;</label>
                                    <button type="button" class="btn btn-outline-secondary dashboard-item-search-submit" id="clearFilterBtn">Clear filters</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="inventory-table-container outbound-table-shell">
                        <div class="outbound-print-meta print-only">
                            <div><strong>Outbound Summary Report</strong></div>
                            <div><strong>Total Released Quantity:</strong> <?= number_format($totalReleasedQty) ?></div>
                            <div><strong>Total Released Amount:</strong> <?= formatMoney($totalReleasedAmount) ?></div>
                        </div>
                        <div class="inventory-stats no-print">
                            Total records (visible):
                            <span class="inventory-stats-value" id="visibleRowsCount2"><?= number_format(count($rows)) ?></span>
                        </div>
                        <div class="inventory-table-wrapper">
                            <table class="table inventory-table outbound-table mb-0" id="outboundTable">
                            <thead>
                                <tr>
                                    <th scope="col" class="col-out-program">End-user</th>
                                    <th scope="col" class="col-out-po">PO #</th>
                                    <th scope="col" class="col-out-date">Date Released</th>
                                    <th scope="col" class="col-out-desc">Item Description</th>
                                    <th scope="col" class="col-out-exp text-nowrap">Exp Date</th>
                                    <th scope="col" class="col-out-qty">Qty</th>
                                    <th scope="col" class="col-out-uom">UOM</th>
                                    <th scope="col" class="col-out-unit-cost">Unit Cost</th>
                                    <th scope="col" class="col-out-total-cost">Total Cost</th>
                                    <th scope="col" class="col-out-recipient">Recipient</th>
                                    <th scope="col" class="col-out-ptr">PTR #</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr class="outbound-empty-row">
                                        <td colspan="11" class="text-center py-5">
                                            <div class="inventory-empty-state">
                                                <div class="inventory-empty-state-icon">📦</div>
                                                <div><strong>No outbound records found.</strong></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr data-record-row="true" data-program="<?= htmlspecialchars((string) ($row['program'] ?? '')) ?>" data-description="<?= htmlspecialchars((string) ($row['description'] ?? '')) ?>" data-recipient="<?= htmlspecialchars((string) ($row['recipient'] ?? '')) ?>" data-date="<?= htmlspecialchars((string) ($row['date_released'] ?? '')) ?>" data-qty="<?= (int) ($row['quantity'] ?? 0) ?>" data-total-cost="<?= number_format((float) ($row['total_cost'] ?? 0), 2, '.', '') ?>">
                                            <td class="col-out-program"><?= htmlspecialchars((string) ($row['program'] ?? '-')) ?></td>
                                            <td class="col-out-po"><?= htmlspecialchars((string) ($row['po_no'] ?? '-')) ?></td>
                                            <td class="col-out-date text-nowrap"><?= htmlspecialchars((string) ($row['date_released'] ?? '-')) ?></td>
                                            <td class="col-out-desc"><?= htmlspecialchars((string) ($row['description'] ?? '-')) ?></td>
                                            <td class="col-out-exp text-nowrap"><?= htmlspecialchars((string) ($row['expiration_date'] ?? '-')) ?></td>
                                            <td class="col-out-qty"><?= htmlspecialchars((string) ($row['quantity'] ?? '0')) ?></td>
                                            <td class="col-out-uom"><?= htmlspecialchars((string) ($row['unit'] ?? '-')) ?></td>
                                            <td class="col-out-unit-cost"><span class="inventory-currency"><?= number_format((float) ($row['unit_cost'] ?? 0), 2) ?></span></td>
                                            <td class="col-out-total-cost"><span class="inventory-currency"><?= number_format((float) ($row['total_cost'] ?? 0), 2) ?></span></td>
                                            <td class="col-out-recipient"><?= htmlspecialchars((string) ($row['recipient'] ?? '-')) ?></td>
                                            <td class="col-out-ptr"><?= htmlspecialchars((string) ($row['ptr_no'] ?? '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="outbound-table-foot">
                                <tr>
                                    <td colspan="8" class="outbound-grand-label"><strong>Grand total (filtered)</strong></td>
                                    <td class="outbound-grand-amount"><strong id="filteredGrandTotal"><?= formatMoney($totalReleasedAmount) ?></strong></td>
                                    <td colspan="2" class="outbound-grand-spacer"></td>
                                </tr>
                            </tfoot>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function() {
            'use strict';

            const filterProgramInput = document.getElementById('filterProgram');
            const filterDescriptionInput = document.getElementById('filterDescription');
            const filterRecipientInput = document.getElementById('filterRecipient');
            const filterDateFromInput = document.getElementById('filterDateFrom');
            const filterDateToInput = document.getElementById('filterDateTo');
            const clearFilterBtn = document.getElementById('clearFilterBtn');
            const visibleRowsCount = document.getElementById('visibleRowsCount');
            const visibleRowsCount2 = document.getElementById('visibleRowsCount2');
            const visibleQtyTotal = document.getElementById('visibleQtyTotal');
            const visibleAmountTotal = document.getElementById('visibleAmountTotal');
            const filteredGrandTotal = document.getElementById('filteredGrandTotal');
            const table = document.getElementById('outboundTable');
            const rows = table.querySelectorAll('tbody tr[data-record-row="true"]');

            function formatMoney(value) {
                return Number(value || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function applyFilters() {
                const programFilter = filterProgramInput.value.toLowerCase();
                const descriptionFilter = filterDescriptionInput.value.toLowerCase();
                const recipientFilter = filterRecipientInput.value.toLowerCase();
                const dateFromFilter = filterDateFromInput.value;
                const dateToFilter = filterDateToInput.value;
                let visibleCount = 0;
                let filteredQty = 0;
                let filteredAmount = 0;

                rows.forEach(row => {
                    const programText = (row.getAttribute('data-program') || '').toLowerCase();
                    const descriptionText = (row.getAttribute('data-description') || '').toLowerCase();
                    const recipientText = (row.getAttribute('data-recipient') || '').toLowerCase();
                    const dateText = row.getAttribute('data-date') || '';
                    const qtyValue = Number(row.getAttribute('data-qty') || 0);
                    const totalCostValue = Number(row.getAttribute('data-total-cost') || 0);

                    let showRow = true;

                    // Filter by program/end-user
                    if (programFilter && !programText.includes(programFilter)) {
                        showRow = false;
                    }

                    // Filter by description
                    if (descriptionFilter && !descriptionText.includes(descriptionFilter)) {
                        showRow = false;
                    }

                    // Filter by recipient
                    if (recipientFilter && !recipientText.includes(recipientFilter)) {
                        showRow = false;
                    }

                    // Filter by date range
                    if (dateFromFilter && dateText < dateFromFilter) {
                        showRow = false;
                    }
                    if (dateToFilter && dateText > dateToFilter) {
                        showRow = false;
                    }

                    row.style.display = showRow ? '' : 'none';
                    if (showRow) {
                        visibleCount++;
                        filteredQty += Number.isFinite(qtyValue) ? qtyValue : 0;
                        filteredAmount += Number.isFinite(totalCostValue) ? totalCostValue : 0;
                    }
                });

                if (visibleRowsCount) {
                    visibleRowsCount.textContent = visibleCount.toLocaleString();
                }
                if (visibleRowsCount2) {
                    visibleRowsCount2.textContent = visibleCount.toLocaleString();
                }
                if (visibleQtyTotal) {
                    visibleQtyTotal.textContent = filteredQty.toLocaleString();
                }
                if (visibleAmountTotal) {
                    visibleAmountTotal.textContent = formatMoney(filteredAmount);
                }
                if (filteredGrandTotal) {
                    filteredGrandTotal.textContent = formatMoney(filteredAmount);
                }
            }

            function clearFilters() {
                filterProgramInput.value = '';
                filterDescriptionInput.value = '';
                filterRecipientInput.value = '';
                filterDateFromInput.value = '';
                filterDateToInput.value = '';
                applyFilters();
            }

            filterProgramInput.addEventListener('input', applyFilters);
            filterDescriptionInput.addEventListener('input', applyFilters);
            filterRecipientInput.addEventListener('input', applyFilters);
            filterDateFromInput.addEventListener('change', applyFilters);
            filterDateToInput.addEventListener('change', applyFilters);
            clearFilterBtn.addEventListener('click', clearFilters);

            applyFilters();
        })();
    </script>
    <script src="assets/js/smooth_motion.js?v=20260325"></script>
</body>
</html>
