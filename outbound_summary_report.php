<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
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
    <link rel="stylesheet" href="style.css?v=20260305">
    <style>
        .outbound-page .inventory-table-wrapper {
            overflow-x: auto;
            overflow-y: visible;
        }
        .outbound-page #outboundTable {
            width: 100%;
            min-width: 1320px;
            table-layout: fixed;
            border-collapse: collapse;
        }
        .outbound-page #outboundTable th,
        .outbound-page #outboundTable td {
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            vertical-align: middle;
        }
        .outbound-page #outboundTable th:nth-child(1),
        .outbound-page #outboundTable td:nth-child(1) {
            width: 13%;
        }
        .outbound-page #outboundTable th:nth-child(4),
        .outbound-page #outboundTable td:nth-child(4) {
            width: 18%;
        }
        .outbound-page #outboundTable th:nth-child(10),
        .outbound-page #outboundTable td:nth-child(10) {
            width: 13%;
        }
        .outbound-page #outboundTable th:nth-child(2),
        .outbound-page #outboundTable td:nth-child(2),
        .outbound-page #outboundTable th:nth-child(3),
        .outbound-page #outboundTable td:nth-child(3),
        .outbound-page #outboundTable th:nth-child(5),
        .outbound-page #outboundTable td:nth-child(5),
        .outbound-page #outboundTable th:nth-child(6),
        .outbound-page #outboundTable td:nth-child(6),
        .outbound-page #outboundTable th:nth-child(7),
        .outbound-page #outboundTable td:nth-child(7),
        .outbound-page #outboundTable th:nth-child(8),
        .outbound-page #outboundTable td:nth-child(8),
        .outbound-page #outboundTable th:nth-child(9),
        .outbound-page #outboundTable td:nth-child(9),
        .outbound-page #outboundTable th:nth-child(11),
        .outbound-page #outboundTable td:nth-child(11) {
            white-space: nowrap;
            word-break: normal;
            overflow-wrap: normal;
        }

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
                border-bottom: 1px solid #333;
                padding-bottom: 5mm !important;
            }
            .app-header-title span {
                font-size: 10pt !important;
            }
            .app-header-title small {
                font-size: 8pt !important;
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
                border-collapse: collapse;
            }
            .table thead,
            .table tbody {
                display: table-row-group;
            }
            .table th,
            .table td {
                border: 1px solid #333 !important;
                padding: 2pt 3pt !important;
                white-space: normal !important;
                word-break: break-word;
                overflow-wrap: anywhere;
            }
            .table th:nth-child(2),
            .table td:nth-child(2),
            .table th:nth-child(3),
            .table td:nth-child(3),
            .table th:nth-child(5),
            .table td:nth-child(5),
            .table th:nth-child(6),
            .table td:nth-child(6),
            .table th:nth-child(7),
            .table td:nth-child(7),
            .table th:nth-child(8),
            .table td:nth-child(8),
            .table th:nth-child(9),
            .table td:nth-child(9),
            .table th:nth-child(11),
            .table td:nth-child(11) {
                white-space: nowrap !important;
                word-break: normal;
                overflow-wrap: normal;
            }
            .table thead th {
                background-color: #f5f5f5 !important;
                font-weight: 700;
                text-align: center;
                vertical-align: middle;
            }
            .table tbody tr {
                page-break-inside: avoid;
            }
            h1, h5 {
                margin: 0 0 5mm 0 !important;
                font-size: 12pt !important;
            }
        }
    </style>
</head>
<body class="outbound-page">
    <header class="navbar navbar-expand-lg navbar-light bg-white app-header px-3 px-md-4 no-print">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h6 app-header-title d-flex align-items-center gap-2">
                <?php if (file_exists(__DIR__ . '/PHO.png')): ?>
                    <a href="home.php" class="app-header-logo-link" aria-label="Go to homepage">
                        <img src="PHO.png" alt="Palawan Health Office Logo" class="app-logo-circle" style="height: 40px; width: 40px;">
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
                            <span class="outbound-kpi-chip">Qty: <strong><?= number_format($totalReleasedQty) ?></strong></span>
                            <span class="outbound-kpi-chip">Amount: <strong><?= formatMoney($totalReleasedAmount) ?></strong></span>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.print();">Print</button>
                        </div>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 mb-3 no-print"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <!-- Filter Section -->
                    <div class="card mb-4 no-print outbound-filter-card">
                        <div class="card-body">
                            <h6 class="mb-3 fw-bold">Filter Records</h6>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label for="filterProgram" class="form-label form-label-sm">End-user / Program</label>
                                    <input type="text" class="form-control" id="filterProgram" placeholder="Search program...">
                                </div>
                                <div class="col-md-3">
                                    <label for="filterDescription" class="form-label form-label-sm">Item Description</label>
                                    <input type="text" class="form-control" id="filterDescription" placeholder="Search item...">
                                </div>
                                <div class="col-md-2">
                                    <label for="filterDateFrom" class="form-label form-label-sm">Date From</label>
                                    <input type="date" class="form-control" id="filterDateFrom">
                                </div>
                                <div class="col-md-2">
                                    <label for="filterDateTo" class="form-label form-label-sm">Date To</label>
                                    <input type="date" class="form-control" id="filterDateTo">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="clearFilterBtn">Clear Filters</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="inventory-table-container">
                        <div class="inventory-stats no-print">
                            Total Records: <span class="inventory-stats-value" id="visibleRowsCount2"><?= count($rows) ?></span>
                        </div>
                        <div class="inventory-table-wrapper">
                            <table class="table inventory-table" id="outboundTable">
                            <thead>
                                <tr>
                                    <th>End-user</th>
                                    <th>PO #</th>
                                    <th>Date Released</th>
                                    <th>Item Description</th>
                                    <th class="text-nowrap text-center">Exp Date</th>
                                    <th class="text-end">Qty</th>
                                    <th>UOM</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Total Cost</th>
                                    <th>Recipient</th>
                                    <th>PTR #</th>
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
                                        <tr data-record-row="true" data-program="<?= htmlspecialchars((string) ($row['program'] ?? '')) ?>" data-description="<?= htmlspecialchars((string) ($row['description'] ?? '')) ?>" data-date="<?= htmlspecialchars((string) ($row['date_released'] ?? '')) ?>">
                                            <td><?= htmlspecialchars((string) ($row['program'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['po_no'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['date_released'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['description'] ?? '-')) ?></td>
                                        <td class="text-center text-nowrap"><?= htmlspecialchars((string) ($row['expiration_date'] ?? '-')) ?></td>
                                        <td class="text-end"><?= htmlspecialchars((string) ($row['quantity'] ?? '0')) ?></td>
                                        <td class="text-center"><?= htmlspecialchars((string) ($row['unit'] ?? '-')) ?></td>
                                        <td class="text-end"><span class="inventory-currency"><?= number_format((float)($row['unit_cost'] ?? 0), 2) ?></span></td>
                                        <td class="text-end"><span class="inventory-currency"><?= number_format((float)($row['total_cost'] ?? 0), 2) ?></span></td>
                                            <td><?= htmlspecialchars((string) ($row['recipient'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($row['ptr_no'] ?? '-')) ?></td>
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
    </main>

    <script>
        (function() {
            'use strict';

            const filterProgramInput = document.getElementById('filterProgram');
            const filterDescriptionInput = document.getElementById('filterDescription');
            const filterDateFromInput = document.getElementById('filterDateFrom');
            const filterDateToInput = document.getElementById('filterDateTo');
            const clearFilterBtn = document.getElementById('clearFilterBtn');
            const visibleRowsCount = document.getElementById('visibleRowsCount');
            const visibleRowsCount2 = document.getElementById('visibleRowsCount2');
            const table = document.getElementById('outboundTable');
            const rows = table.querySelectorAll('tbody tr[data-record-row="true"]');

            function applyFilters() {
                const programFilter = filterProgramInput.value.toLowerCase();
                const descriptionFilter = filterDescriptionInput.value.toLowerCase();
                const dateFromFilter = filterDateFromInput.value;
                const dateToFilter = filterDateToInput.value;
                let visibleCount = 0;

                rows.forEach(row => {
                    const programText = (row.getAttribute('data-program') || '').toLowerCase();
                    const descriptionText = (row.getAttribute('data-description') || '').toLowerCase();
                    const dateText = row.getAttribute('data-date') || '';

                    let showRow = true;

                    // Filter by program/end-user
                    if (programFilter && !programText.includes(programFilter)) {
                        showRow = false;
                    }

                    // Filter by description
                    if (descriptionFilter && !descriptionText.includes(descriptionFilter)) {
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
                    }
                });

                if (visibleRowsCount) {
                    visibleRowsCount.textContent = visibleCount.toLocaleString();
                }
                if (visibleRowsCount2) {
                    visibleRowsCount2.textContent = visibleCount.toLocaleString();
                }
            }

            function clearFilters() {
                filterProgramInput.value = '';
                filterDescriptionInput.value = '';
                filterDateFromInput.value = '';
                filterDateToInput.value = '';
                applyFilters();
            }

            filterProgramInput.addEventListener('input', applyFilters);
            filterDescriptionInput.addEventListener('input', applyFilters);
            filterDateFromInput.addEventListener('change', applyFilters);
            filterDateToInput.addEventListener('change', applyFilters);
            clearFilterBtn.addEventListener('click', clearFilters);

            applyFilters();
        })();
    </script>
</body>
</html>
