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
$printRecipients = [];

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
        $recipientName = trim((string) ($reportRow['recipient'] ?? ''));
        if ($recipientName !== '') {
            $printRecipients[$recipientName] = true;
        }
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
        .outbound-page .outbound-filter-card {
            border: 1px solid #d7e7de;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(17, 27, 22, 0.05);
            background: linear-gradient(180deg, #f9fcfa 0%, #ffffff 100%);
        }
        .outbound-page .outbound-filter-title {
            font-size: 0.92rem;
            letter-spacing: 0.02em;
            color: #1f3b2d;
            margin-bottom: 0.9rem;
        }
        .outbound-page .outbound-filter-row {
            align-items: end;
        }
        .outbound-page .outbound-filter-row .form-label {
            font-size: 0.78rem;
            margin-bottom: 0.3rem;
            color: #2e4f3f;
        }
        .outbound-page .outbound-filter-input {
            min-height: 40px;
            text-align: left;
            border-radius: 8px;
            border-color: #bfd8ca;
            font-size: 0.86rem;
            padding-left: 0.7rem;
        }
        .outbound-page .outbound-filter-input::placeholder {
            text-align: left;
            color: #7b9688;
        }
        .outbound-page .outbound-filter-clear {
            min-height: 40px;
            border-radius: 8px;
            font-weight: 600;
        }
        .outbound-page .outbound-records-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.78rem 1rem;
            border-bottom: 1px solid #1e1e1e;
            background: linear-gradient(90deg, rgba(40, 128, 75, 0.05), rgba(40, 128, 75, 0.015));
        }
        .outbound-page .outbound-records-label {
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #446455;
        }
        .outbound-page .outbound-records-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 90px;
            padding: 0.32rem 0.72rem;
            border-radius: 999px;
            border: 1px solid #b7d6c5;
            background: #ffffff;
            color: #1f3b2d;
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 1;
        }
        .outbound-page .inventory-table-container {
            border: 1px solid #1e1e1e;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(17, 27, 22, 0.08);
        }
        .outbound-page .inventory-table-wrapper {
            overflow-x: hidden;
            overflow-y: hidden;
            border: 0;
            border-radius: 0;
            background: #fff;
        }
        .outbound-page #outboundTable {
            width: 100%;
            min-width: 0;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .outbound-page .print-only {
            display: none;
        }
        .outbound-page #outboundTable th,
        .outbound-page #outboundTable td {
            border: 1px solid #1e1e1e;
            color: #1a1a1a;
            background: #fff;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            vertical-align: middle;
            padding: 0.52rem 0.42rem;
            line-height: 1.3;
            font-size: 0.81rem;
        }
        .outbound-page #outboundTable thead th {
            background: #f5f7f6;
            font-weight: 700;
            border-bottom: 2px solid #1b1b1b;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            font-size: 0.73rem;
            text-align: center;
        }
        .outbound-page #outboundTable tbody tr:nth-child(even) {
            background: #fbfcfb;
        }
        .outbound-page #outboundTable tbody tr:hover {
            background: inherit;
            box-shadow: none !important;
            transform: none !important;
            position: static;
            z-index: auto;
        }
        .outbound-page #outboundTable tbody tr {
            transition: none !important;
        }
        .outbound-page .app-card:hover {
            transform: none !important;
        }
        .outbound-page #outboundTable td,
        .outbound-page #outboundTable th {
            white-space: normal;
        }
        .outbound-page #outboundTable tfoot td {
            font-weight: 700;
            background: #f4f8f6;
            text-align: center;
            vertical-align: middle;
        }
        .outbound-page #outboundTable tfoot .outbound-grand-label {
            text-align: right;
            letter-spacing: 0.02em;
            color: #1f3b2d;
            background: #f8fbf9;
        }
        .outbound-page #outboundTable tfoot .outbound-grand-amount {
            text-align: center;
            font-size: 0.86rem;
            color: #1f3b2d;
            background: #f8fbf9;
        }
        .outbound-page #outboundTable tbody td {
            vertical-align: middle;
        }
        .outbound-page #outboundTable tbody td:nth-child(1),
        .outbound-page #outboundTable tbody td:nth-child(4),
        .outbound-page #outboundTable tbody td:nth-child(10) {
            text-align: left;
        }
        .outbound-page #outboundTable tbody td:nth-child(2),
        .outbound-page #outboundTable tbody td:nth-child(3),
        .outbound-page #outboundTable tbody td:nth-child(5),
        .outbound-page #outboundTable tbody td:nth-child(7),
        .outbound-page #outboundTable tbody td:nth-child(11) {
            text-align: center;
        }
        .outbound-page #outboundTable tbody td:nth-child(6),
        .outbound-page #outboundTable tbody td:nth-child(8),
        .outbound-page #outboundTable tbody td:nth-child(9) {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .outbound-page #outboundTable tfoot td:nth-child(2) {
            font-variant-numeric: tabular-nums;
        }
        .outbound-page #outboundTable thead th:nth-child(4),
        .outbound-page #outboundTable thead th:nth-child(1),
        .outbound-page #outboundTable thead th:nth-child(10) {
            text-align: left;
        }

        @media (max-width: 1200px) {
            .outbound-page #outboundTable th,
            .outbound-page #outboundTable td {
                font-size: 0.76rem;
                padding: 0.4rem 0.3rem;
            }
            .outbound-page .outbound-records-bar {
                padding: 0.65rem 0.75rem;
            }
            .outbound-page .outbound-records-pill {
                min-width: 78px;
            }
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
            .table tbody {
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
                            <span class="outbound-kpi-chip">Qty: <strong id="visibleQtyTotal"><?= number_format($totalReleasedQty) ?></strong></span>
                            <span class="outbound-kpi-chip">Amount: <strong id="visibleAmountTotal"><?= formatMoney($totalReleasedAmount) ?></strong></span>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.print();">Print</button>
                        </div>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 mb-3 no-print"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <!-- Filter Section -->
                    <div class="card mb-4 no-print outbound-filter-card">
                        <div class="card-body">
                            <h6 class="outbound-filter-title fw-bold">Filter Records</h6>
                            <div class="row g-2 outbound-filter-row">
                                <div class="col-md-3">
                                    <label for="filterProgram" class="form-label form-label-sm">End-user / Program</label>
                                    <input type="text" class="form-control outbound-filter-input" id="filterProgram" placeholder="Search program...">
                                </div>
                                <div class="col-md-3">
                                    <label for="filterDescription" class="form-label form-label-sm">Item Description</label>
                                    <input type="text" class="form-control outbound-filter-input" id="filterDescription" placeholder="Search item...">
                                </div>
                                <div class="col-md-2">
                                    <label for="filterRecipient" class="form-label form-label-sm">Recipient</label>
                                    <input type="text" class="form-control outbound-filter-input" id="filterRecipient" placeholder="Search recipient...">
                                </div>
                                <div class="col-md-2">
                                    <label for="filterDateFrom" class="form-label form-label-sm">Date From</label>
                                    <input type="date" class="form-control outbound-filter-input" id="filterDateFrom">
                                </div>
                                <div class="col-md-2">
                                    <label for="filterDateTo" class="form-label form-label-sm">Date To</label>
                                    <input type="date" class="form-control outbound-filter-input" id="filterDateTo">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary w-100 outbound-filter-clear" id="clearFilterBtn">Clear Filters</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="inventory-table-container">
                        <div class="outbound-print-meta print-only">
                            <div><strong>Outbound Summary Report</strong></div>
                            <div><strong>Total Released Quantity:</strong> <?= number_format($totalReleasedQty) ?></div>
                            <div><strong>Total Released Amount:</strong> <?= formatMoney($totalReleasedAmount) ?></div>
                            <div><strong>Recipients:</strong> <?= !empty($printRecipients) ? htmlspecialchars(implode(', ', array_keys($printRecipients))) : '-' ?></div>
                        </div>
                        <div class="outbound-records-bar no-print">
                            <span class="outbound-records-label">Total Records</span>
                            <span class="outbound-records-pill" id="visibleRowsCount2"><?= number_format(count($rows)) ?></span>
                        </div>
                        <div class="inventory-table-wrapper">
                            <table class="table inventory-table" id="outboundTable">
                            <colgroup>
                                <col style="width:12%">
                                <col style="width:8%">
                                <col style="width:9%">
                                <col style="width:18%">
                                <col style="width:8%">
                                <col style="width:6%">
                                <col style="width:6%">
                                <col style="width:8%">
                                <col style="width:9%">
                                <col style="width:10%">
                                <col style="width:6%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>End-user</th>
                                    <th>PO #</th>
                                    <th>Date Released</th>
                                    <th>Item Description</th>
                                    <th class="text-nowrap text-center">Exp Date</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-center">UOM</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Total Cost</th>
                                    <th>Recipient</th>
                                    <th class="text-center">PTR #</th>
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
                            <tfoot>
                                <tr>
                                    <td colspan="8" class="outbound-grand-label"><strong>Grand Total (Filtered):</strong></td>
                                    <td class="outbound-grand-amount"><strong id="filteredGrandTotal"><?= formatMoney($totalReleasedAmount) ?></strong></td>
                                    <td colspan="2"></td>
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
