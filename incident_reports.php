<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
$message = trim((string) ($_GET['msg'] ?? ''));
$selectedId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$error = '';
$reports = [];
$selectedReport = null;
$selectedSpecifics = [];

try {
    $pdo = getConnection();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS incident_reports (
            id INT NOT NULL AUTO_INCREMENT,
            name_of_office VARCHAR(255) DEFAULT NULL,
            address VARCHAR(255) DEFAULT NULL,
            incident_no VARCHAR(120) DEFAULT NULL,
            incident_type VARCHAR(255) DEFAULT NULL,
            location VARCHAR(255) DEFAULT NULL,
            specifics_json LONGTEXT,
            persons_involved TEXT,
            remarks LONGTEXT,
            action_taken LONGTEXT,
            prepared_by_name VARCHAR(255) DEFAULT NULL,
            prepared_by_designation VARCHAR(255) DEFAULT NULL,
            prepared_by_date DATE DEFAULT NULL,
            submitted_to_name VARCHAR(255) DEFAULT NULL,
            submitted_to_designation VARCHAR(255) DEFAULT NULL,
            submitted_to_date DATE DEFAULT NULL,
            created_by VARCHAR(150) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );
    $incidentDateTimeColumnStmt = $pdo->query("SHOW COLUMNS FROM incident_reports LIKE 'incident_datetime'");
    if (!$incidentDateTimeColumnStmt || !$incidentDateTimeColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE incident_reports ADD COLUMN incident_datetime DATETIME DEFAULT NULL AFTER incident_type');
    }

    $listStmt = $pdo->query(
        'SELECT id, incident_no, incident_type, incident_datetime, location, name_of_office, created_by, created_at
         FROM incident_reports
         ORDER BY id DESC'
    );
    $reports = $listStmt->fetchAll();

    if ($selectedId <= 0 && !empty($reports)) {
        $selectedId = (int) ($reports[0]['id'] ?? 0);
    }

    if ($selectedId > 0) {
        $detailStmt = $pdo->prepare('SELECT * FROM incident_reports WHERE id = ? LIMIT 1');
        $detailStmt->execute([$selectedId]);
        $selectedReport = $detailStmt->fetch();

        if ($selectedReport) {
            $decoded = json_decode((string) ($selectedReport['specifics_json'] ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $selectedSpecifics[] = [
                        'item' => (string) ($row['item'] ?? ''),
                        'uom' => (string) ($row['uom'] ?? ''),
                        'program' => (string) ($row['program'] ?? ''),
                        'po' => (string) ($row['po'] ?? ''),
                        'batch' => (string) ($row['batch'] ?? ''),
                        'exp' => (string) ($row['exp'] ?? ''),
                        'qty' => (string) ($row['qty'] ?? ''),
                    ];
                }
            }
        }
    }
} catch (PDOException $e) {
    $error = 'Unable to load incident reports right now.';
}

if (empty($selectedSpecifics)) {
    $selectedSpecifics = array_fill(0, 3, [
        'item' => '',
        'uom' => '',
        'program' => '',
        'po' => '',
        'batch' => '',
        'exp' => '',
        'qty' => '',
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Incident Reports</title>
    <link rel="stylesheet" href="style.css?v=20260305">
    <style>
        .incident-report-list-page .incident-detail-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #2b6843;
            margin-bottom: 0.2rem;
        }
        .incident-report-list-page .incident-detail-value {
            border: 1px solid #dbece2;
            border-radius: 0.35rem;
            background: #fff;
            padding: 0.45rem 0.55rem;
            min-height: 2.25rem;
        }
        .incident-report-list-page .incident-spec-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .incident-report-list-page .incident-spec-table th,
        .incident-report-list-page .incident-spec-table td {
            border: 1px solid #dbece2;
            padding: 0.4rem 0.45rem;
            font-size: 0.86rem;
            overflow-wrap: break-word;
            word-wrap: break-word;
            text-align: center;
        }
        .incident-report-list-page .incident-spec-table th {
            background: #eff8f3;
            color: #245f3d;
            text-transform: uppercase;
            font-size: 0.75rem;
            text-align: center;
        }
        .incident-report-list-page .report-details-screen {
            display: block;
        }
        .incident-report-list-page .incident-print-sheet {
            display: none;
        }
        @media print {
            html, body {
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 0;
                background: #ffffff;
            }
            body {
                background: #ffffff;
                margin: 0;
                padding: 0;
            }
            body * {
                display: none !important;
            }
            .incident-print-sheet {
                display: block !important;
            }
            .incident-print-sheet,
            .incident-print-sheet * {
                display: block !important;
                visibility: visible !important;
            }
            .incident-print-sheet {
                display: block !important;
                position: static !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                background: #ffffff !important;
                page-break-inside: avoid;
                page-break-after: auto;
            }
            .no-print {
                display: none !important;
            }
            @page {
                size: A4;
                margin: 10mm;
                background: #ffffff;
                @top-left {
                    content: none !important;
                }
                @top-right {
                    content: none !important;
                }
                @top-center {
                    content: none !important;
                }
                @bottom-left {
                    content: none !important;
                }
                @bottom-right {
                    content: none !important;
                }
                @bottom-center {
                    content: none !important;
                }
            }
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            .incident-print-sheet-content * {
                display: block !important;
            }
            .incident-print-sheet table {
                display: table !important;
            }
            .incident-print-sheet thead {
                display: table-header-group !important;
            }
            .incident-print-sheet tbody {
                display: table-row-group !important;
            }
            .incident-print-sheet tr {
                display: table-row !important;
            }
            .incident-print-sheet th,
            .incident-print-sheet td {
                display: table-cell !important;
            }
            .incident-print-sheet-content div,
            .incident-print-sheet-content span {
                display: block !important;
            }
            .incident-print-wrapper {
                display: block !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .incident-print-sheet-content {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                background: #fff !important;
                box-sizing: border-box !important;
                font-family: 'Times New Roman', Georgia, serif !important;
                font-size: 9pt !important;
                line-height: 1.3 !important;
                page-break-inside: avoid !important;
                margin: 0 !important;
            }
            .incident-print-sheet-content .print-header {
                text-align: center;
                margin-bottom: 4pt;
                border-bottom: 1px solid #333;
                padding-bottom: 4pt;
            }
            .incident-print-sheet-content .print-office-name {
                font-weight: 700;
                font-size: 11pt;
                letter-spacing: 0.02em;
            }
            .incident-print-sheet-content .print-address {
                font-size: 8.5pt;
                margin-top: 1pt;
            }
            .incident-print-sheet-content .print-title {
                text-align: center;
                font-weight: 700;
                font-size: 13pt;
                letter-spacing: 0.05em;
                margin: 6pt 0 3pt;
            }
            .incident-print-sheet-content .print-incident-no {
                text-align: center;
                margin-bottom: 4pt;
                font-size: 9pt;
            }
            .incident-print-sheet-content table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 4pt;
                font-size: 8.5pt;
            }
            .incident-print-sheet-content th,
            .incident-print-sheet-content td {
                border: 1px solid #333;
                padding: 2pt 3pt;
                text-align: left;
                vertical-align: top;
            }
            .incident-print-sheet-content th {
                background: #f5f5f5;
                font-weight: 700;
                font-size: 8pt;
                width: 22%;
            }
            .incident-print-sheet-content .print-section-label {
                font-weight: 700;
                font-size: 8pt;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                color: #1a472a;
                margin: 4pt 0 2pt;
            }
            .incident-print-sheet-content .specs-table {
                table-layout: fixed;
            }
            .incident-print-sheet-content .specs-table th,
            .incident-print-sheet-content .specs-table td {
                padding: 1.5pt 2pt;
                font-size: 7.5pt;
                overflow-wrap: break-word;
                word-wrap: break-word;
                text-align: center;
            }
            .incident-print-sheet-content .specs-table th:nth-child(1) { width: 28%; }
            .incident-print-sheet-content .specs-table th:nth-child(2) { width: 8%; }
            .incident-print-sheet-content .specs-table th:nth-child(3) { width: 18%; }
            .incident-print-sheet-content .specs-table th:nth-child(4) { width: 12%; }
            .incident-print-sheet-content .specs-table th:nth-child(5) { width: 12%; }
            .incident-print-sheet-content .specs-table th:nth-child(6) { width: 12%; }
            .incident-print-sheet-content .specs-table th {
                background: #e8e8e8;
            }
            .incident-print-sheet-content .signature-table {
                margin-top: 4pt;
                font-size: 8.5pt;
            }
            .incident-print-sheet-content .signature-table td {
                text-align: center;
                vertical-align: bottom;
            }
            .incident-print-sheet-content .signature-label {
                font-size: 7pt;
                padding: 2pt;
            }
        }
    </style>
</head>
<body class="incident-report-list-page">
    <header class="navbar navbar-expand-lg navbar-light bg-white app-header px-3 px-md-4 no-print">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h6 app-header-title d-flex align-items-center gap-2">
                <?php if (file_exists(__DIR__ . '/PHO.png')): ?>
                    <a href="home.php" class="app-header-logo-link" aria-label="Go to homepage">
                        <img src="PHO.png" alt="Palawan Health Office Logo" class="app-logo-circle app-logo-md">
                    </a>
                <?php endif; ?>
                <span class="d-inline-flex flex-column lh-sm">
                    <span>Provincial Health Office</span>
                    <small class="fw-normal">Saved Incident Reports</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="incident_report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">New Incident</a>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
            </div>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h1 class="h5 mb-0">Saved Incident Reports</h1>
                <?php if ($selectedReport): ?>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print();">Print</button>
                <?php endif; ?>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-success py-2 mb-3 no-print"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-lg-5 no-print">
                    <div class="card app-card">
                        <div class="card-body">
                            <h2 class="h6 mb-3">Report List</h2>
                            <?php if (empty($reports)): ?>
                                <div class="alert alert-info py-2 mb-0">No saved incident reports yet.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Incident No.</th>
                                                <th>Type</th>
                                                <th>Incident Date/Time</th>
                                                <th>Date</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reports as $report): ?>
                                                <?php $reportId = (int) ($report['id'] ?? 0); ?>
                                                <tr>
                                                    <td><?= $reportId ?></td>
                                                    <td><?= htmlspecialchars((string) ($report['incident_no'] ?? '-')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($report['incident_type'] ?? '-')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($report['incident_datetime'] ?? '-')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($report['created_at'] ?? '-')) ?></td>
                                                    <td class="text-center">
                                                        <a href="incident_reports.php?id=<?= $reportId ?>" class="btn btn-outline-secondary btn-sm <?= $selectedId === $reportId ? 'active' : '' ?>">Open</a>
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

                <div class="col-lg-7">
                    <div class="card app-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h6 mb-0">Report Details</h2>
                            </div>
                            <?php if (!$selectedReport): ?>
                                <div class="alert alert-info py-2 mb-0">Select a saved report to view details.</div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="incident-detail-label">Name of Office</div>
                                        <div class="incident-detail-value"><?= htmlspecialchars((string) ($selectedReport['name_of_office'] ?? '-')) ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="incident-detail-label">Address</div>
                                        <div class="incident-detail-value"><?= htmlspecialchars((string) ($selectedReport['address'] ?? '-')) ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="incident-detail-label">Incident No.</div>
                                        <div class="incident-detail-value"><?= htmlspecialchars((string) ($selectedReport['incident_no'] ?? '-')) ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="incident-detail-label">Incident Type</div>
                                        <div class="incident-detail-value"><?= htmlspecialchars((string) ($selectedReport['incident_type'] ?? '-')) ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="incident-detail-label">Location</div>
                                        <div class="incident-detail-value"><?= htmlspecialchars((string) ($selectedReport['location'] ?? '-')) ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="incident-detail-label">Date/Time of Incident</div>
                                        <div class="incident-detail-value"><?= htmlspecialchars((string) ($selectedReport['incident_datetime'] ?? '-')) ?></div>
                                    </div>
                                </div>

                                <div class="table-responsive mt-3">
                                    <table class="incident-spec-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 24%;">Item</th>
                                                <th style="width: 7%;">UOM</th>
                                                <th style="width: 16%;">Program</th>
                                                <th style="width: 10%;">PO #</th>
                                                <th style="width: 10%;">Batch #</th>
                                                <th style="width: 10%;">Exp. Date</th>
                                                <th style="width: 8%;">Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($selectedSpecifics as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) ($row['item'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['uom'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['program'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['po'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['batch'] ?? '')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($row['exp'] ?? '')) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars((string) ($row['qty'] ?? '')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-12">
                                        <div class="incident-detail-label">Remarks</div>
                                        <div class="incident-detail-value"><?= nl2br(htmlspecialchars((string) ($selectedReport['remarks'] ?? '-'))) ?></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="incident-detail-label">Action Taken</div>
                                        <div class="incident-detail-value"><?= nl2br(htmlspecialchars((string) ($selectedReport['action_taken'] ?? '-'))) ?></div>
                                    </div>
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <div class="incident-detail-label">Prepared By</div>
                                        <div class="incident-detail-value">
                                            <div><strong>Name:</strong> <?= htmlspecialchars((string) ($selectedReport['prepared_by_name'] ?? '-')) ?></div>
                                            <div><strong>Designation:</strong> <?= htmlspecialchars((string) ($selectedReport['prepared_by_designation'] ?? '-')) ?></div>
                                            <div><strong>Date:</strong> <?= htmlspecialchars((string) ($selectedReport['prepared_by_date'] ?? '-')) ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="incident-detail-label">Submitted To</div>
                                        <div class="incident-detail-value">
                                            <div><strong>Name:</strong> <?= htmlspecialchars((string) ($selectedReport['submitted_to_name'] ?? '-')) ?></div>
                                            <div><strong>Designation:</strong> <?= htmlspecialchars((string) ($selectedReport['submitted_to_designation'] ?? '-')) ?></div>
                                            <div><strong>Date:</strong> <?= htmlspecialchars((string) ($selectedReport['submitted_to_date'] ?? '-')) ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php if ($selectedReport): ?>
    <div class="incident-print-sheet">
        <div class="incident-print-wrapper">
            <div class="incident-print-sheet-content">
                <div class="print-header">
                    <div class="print-office-name"><?= htmlspecialchars((string) ($selectedReport['name_of_office'] ?: 'Provincial Health Office')) ?></div>
                    <div class="print-address"><?= htmlspecialchars((string) ($selectedReport['address'] ?: '-')) ?></div>
                </div>

                <div class="print-title">INCIDENT REPORT</div>

                <div class="print-incident-no">
                    No: <strong><?= htmlspecialchars((string) ($selectedReport['incident_no'] ?? '-')) ?></strong>
                </div>

                <table>
                    <tr>
                        <th>Incident Type:</th>
                        <td><?= htmlspecialchars((string) ($selectedReport['incident_type'] ?? '-')) ?></td>
                        <th>Date/Time:</th>
                        <td><?= htmlspecialchars((string) ($selectedReport['incident_datetime'] ?? '-')) ?></td>
                    </tr>
                    <tr>
                        <th>Location:</th>
                        <td colspan="3"><?= htmlspecialchars((string) ($selectedReport['location'] ?? '-')) ?></td>
                    </tr>
                </table>

                <div class="print-section-label">Specifics</div>
                <table class="specs-table" style="table-layout: fixed;">
                    <thead>
                        <tr>
                            <th style="width: 24%;">Item</th>
                            <th style="width: 7%;">UOM</th>
                            <th style="width: 16%;">Program</th>
                            <th style="width: 10%;">PO #</th>
                            <th style="width: 10%;">Batch #</th>
                            <th style="width: 10%;">Exp Date</th>
                            <th style="width: 8%;">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($selectedSpecifics as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['item'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['uom'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['program'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['po'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['batch'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['exp'] ?? '')) ?></td>
                            <td class="text-center"><?= htmlspecialchars((string) ($row['qty'] ?? '')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <table>
                    <tr>
                        <th style="vertical-align: top;">Remarks:</th>
                        <td style="padding: 8pt 10pt; min-height: 120pt; font-size: 9.5pt;"><?= nl2br(htmlspecialchars((string) ($selectedReport['remarks'] ?? '-'))) ?></td>
                    </tr>
                    <tr>
                        <th style="vertical-align: top;">Action Taken:</th>
                        <td style="padding: 8pt 10pt; min-height: 120pt; font-size: 9.5pt;"><?= nl2br(htmlspecialchars((string) ($selectedReport['action_taken'] ?? '-'))) ?></td>
                    </tr>
                </table>

                <table class="signature-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="border: 1px solid #222; padding: 3px 4px; width: 50%; text-align: center; background: #f4f4f4; font-weight: 700; font-size: 8pt;">Prepared By</th>
                            <th style="border: 1px solid #222; padding: 3px 4px; width: 50%; text-align: center; background: #f4f4f4; font-weight: 700; font-size: 8pt;">Submitted To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="border: 1px solid #222; padding: 3px 4px; height: 35px; text-align: center; vertical-align: bottom; font-size: 8pt;">
                                <?= htmlspecialchars((string) ($selectedReport['prepared_by_name'] ?? '-')) ?>
                            </td>
                            <td style="border: 1px solid #222; padding: 3px 4px; height: 35px; text-align: center; vertical-align: bottom; font-size: 8pt;">
                                <?= htmlspecialchars((string) ($selectedReport['submitted_to_name'] ?? '-')) ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #222; padding: 3px 4px; text-align: center; height: 60px; vertical-align: bottom; font-size: 7pt; background: #fafafa;">Signature</td>
                            <td style="border: 1px solid #222; padding: 3px 4px; text-align: center; height: 60px; vertical-align: bottom; font-size: 7pt; background: #fafafa;">Signature</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #222; padding: 3px 4px; text-align: center; font-size: 7pt;">
                                <?= htmlspecialchars((string) ($selectedReport['prepared_by_designation'] ?? '-')) ?>
                            </td>
                            <td style="border: 1px solid #222; padding: 3px 4px; text-align: center; font-size: 7pt;">
                                <?= htmlspecialchars((string) ($selectedReport['submitted_to_designation'] ?? '-')) ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #222; padding: 3px 4px; text-align: center; font-size: 7pt;">
                                <?= htmlspecialchars((string) ($selectedReport['prepared_by_date'] ?? '-')) ?>
                            </td>
                            <td style="border: 1px solid #222; padding: 3px 4px; text-align: center; font-size: 7pt;">
                                <?= htmlspecialchars((string) ($selectedReport['submitted_to_date'] ?? '-')) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
