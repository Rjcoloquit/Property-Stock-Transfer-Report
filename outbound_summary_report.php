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
<body>
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
                    <small class="fw-normal" style="font-size: 0.72rem;">Outbound Summary Report</small>
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
                    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                        <h1 class="h5 mb-0">Outbound Summary Report</h1>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.print();">Print</button>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 mb-3 no-print"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <!-- Filter Section -->
                    <div class="card mb-4 no-print" style="background-color: #f8f9fa; border: 1px solid #dee2e6;">
                        <div class="card-body p-3">
                            <h6 class="mb-3 fw-bold">Filter</h6>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label for="filterProgram" class="form-label form-label-sm">End-user / Program</label>
                                    <input type="text" class="form-control form-control-sm" id="filterProgram" placeholder="Search program...">
                                </div>
                                <div class="col-md-3">
                                    <label for="filterDescription" class="form-label form-label-sm">Item Description</label>
                                    <input type="text" class="form-control form-control-sm" id="filterDescription" placeholder="Search item...">
                                </div>
                                <div class="col-md-2">
                                    <label for="filterDateFrom" class="form-label form-label-sm">Date From</label>
                                    <input type="date" class="form-control form-control-sm" id="filterDateFrom">
                                </div>
                                <div class="col-md-2">
                                    <label for="filterDateTo" class="form-label form-label-sm">Date To</label>
                                    <input type="date" class="form-control form-control-sm" id="filterDateTo">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="clearFilterBtn">Clear Filters</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive" style="font-size: 0.85rem;">
                        <table class="table table-sm table-striped align-middle mb-0" id="outboundTable" style="font-size: 0.8rem;">
                            <thead class="table-light">
                                <tr style="font-size: 0.75rem;">
                                    <th style="padding: 0.35rem 0.5rem;">End-user</th>
                                    <th style="padding: 0.35rem 0.5rem;">PO #</th>
                                    <th style="padding: 0.35rem 0.5rem;">Date Released</th>
                                    <th style="padding: 0.35rem 0.5rem;">Item Description</th>
                                    <th style="padding: 0.35rem 0.5rem;">Exp Date</th>
                                    <th class="text-end" style="padding: 0.35rem 0.5rem;">Qty</th>
                                    <th style="padding: 0.35rem 0.5rem;">UOM</th>
                                    <th class="text-end" style="padding: 0.35rem 0.5rem;">Unit Cost</th>
                                    <th class="text-end" style="padding: 0.35rem 0.5rem;">Total Cost</th>
                                    <th style="padding: 0.35rem 0.5rem;">Recipient</th>
                                    <th style="padding: 0.35rem 0.5rem;">PTR #</th>
                                </tr>
                            </thead>
                            <tbody style="font-size: 0.75rem;">
                                <?php if (empty($rows)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center text-muted" style="padding: 0.35rem 0.5rem;">No outbound records found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr style="border-bottom: 0.5px solid #dee2e6;" data-program="<?= htmlspecialchars((string) ($row['program'] ?? '')) ?>" data-description="<?= htmlspecialchars((string) ($row['description'] ?? '')) ?>" data-date="<?= htmlspecialchars((string) ($row['date_released'] ?? '')) ?>">
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['program'] ?? '-')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['po_no'] ?? '-')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['date_released'] ?? '-')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['description'] ?? '-')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['expiration_date'] ?? '-')) ?></td>
                                            <td class="text-end" style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['quantity'] ?? '0')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['unit'] ?? '-')) ?></td>
                                            <td class="text-end" style="padding: 0.35rem 0.5rem;"><?= formatMoney($row['unit_cost'] ?? 0) ?></td>
                                            <td class="text-end" style="padding: 0.35rem 0.5rem;"><?= formatMoney($row['total_cost'] ?? 0) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['recipient'] ?? '-')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['ptr_no'] ?? '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
            const table = document.getElementById('outboundTable');
            const rows = table.querySelectorAll('tbody tr');

            function applyFilters() {
                const programFilter = filterProgramInput.value.toLowerCase();
                const descriptionFilter = filterDescriptionInput.value.toLowerCase();
                const dateFromFilter = filterDateFromInput.value;
                const dateToFilter = filterDateToInput.value;

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
                });
            }

            function clearFilters() {
                filterProgramInput.value = '';
                filterDescriptionInput.value = '';
                filterDateFromInput.value = '';
                filterDateToInput.value = '';
                applyFilters();
            }

            filterProgramInput.addEventListener('keyup', applyFilters);
            filterDescriptionInput.addEventListener('keyup', applyFilters);
            filterDateFromInput.addEventListener('change', applyFilters);
            filterDateToInput.addEventListener('change', applyFilters);
            clearFilterBtn.addEventListener('click', clearFilters);
        })();
    </script>
</body>
</html>
