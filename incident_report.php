<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
$errors = [];

$formData = [
    'name_of_office' => '',
    'address' => '',
    'incident_no' => '',
    'incident_type' => '',
    'incident_datetime' => '',
    'location' => '',
    'persons_involved' => '',
    'remarks' => '',
    'action_taken' => '',
    'prepared_by_name' => '',
    'prepared_by_designation' => '',
    'prepared_by_date' => '',
    'submitted_to_name' => '',
    'submitted_to_designation' => '',
    'submitted_to_date' => '',
];
$specRows = array_fill(0, 3, [
    'item' => '',
    'uom' => '',
    'program' => '',
    'po' => '',
    'batch' => '',
    'exp' => '',
]);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $key => $value) {
        $formData[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $specItems = isset($_POST['spec_item']) && is_array($_POST['spec_item']) ? $_POST['spec_item'] : [];
    $specUom = isset($_POST['spec_oum']) && is_array($_POST['spec_oum']) ? $_POST['spec_oum'] : [];
    $specProgram = isset($_POST['spec_program']) && is_array($_POST['spec_program']) ? $_POST['spec_program'] : [];
    $specPo = isset($_POST['spec_po']) && is_array($_POST['spec_po']) ? $_POST['spec_po'] : [];
    $specBatch = isset($_POST['spec_batch']) && is_array($_POST['spec_batch']) ? $_POST['spec_batch'] : [];
    $specExp = isset($_POST['spec_exp']) && is_array($_POST['spec_exp']) ? $_POST['spec_exp'] : [];
    $specCount = max(count($specItems), count($specUom), count($specProgram), count($specPo), count($specBatch), count($specExp), 3);

    $specRows = [];
    $hasAnySpecifics = false;
    for ($i = 0; $i < $specCount; $i++) {
        $row = [
            'item' => trim((string) ($specItems[$i] ?? '')),
            'uom' => trim((string) ($specUom[$i] ?? '')),
            'program' => trim((string) ($specProgram[$i] ?? '')),
            'po' => trim((string) ($specPo[$i] ?? '')),
            'batch' => trim((string) ($specBatch[$i] ?? '')),
            'exp' => trim((string) ($specExp[$i] ?? '')),
        ];
        if ($row['item'] !== '' || $row['uom'] !== '' || $row['program'] !== '' || $row['po'] !== '' || $row['batch'] !== '' || $row['exp'] !== '') {
            $hasAnySpecifics = true;
        }
        $specRows[] = $row;
    }

    $hasAnyMainField = false;
    foreach ($formData as $value) {
        if ($value !== '') {
            $hasAnyMainField = true;
            break;
        }
    }
    if (!$hasAnyMainField && !$hasAnySpecifics) {
        $errors[] = 'Please fill out at least one incident detail before saving.';
    }

    if (empty($errors)) {
        $incidentDateTimeValue = null;
        if ($formData['incident_datetime'] !== '') {
            $incidentDateTimeValue = str_replace('T', ' ', $formData['incident_datetime']);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO incident_reports (
                name_of_office,
                address,
                incident_no,
                incident_type,
                incident_datetime,
                location,
                specifics_json,
                persons_involved,
                remarks,
                action_taken,
                prepared_by_name,
                prepared_by_designation,
                prepared_by_date,
                submitted_to_name,
                submitted_to_designation,
                submitted_to_date,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $formData['name_of_office'] !== '' ? $formData['name_of_office'] : null,
            $formData['address'] !== '' ? $formData['address'] : null,
            $formData['incident_no'] !== '' ? $formData['incident_no'] : null,
            $formData['incident_type'] !== '' ? $formData['incident_type'] : null,
            $incidentDateTimeValue,
            $formData['location'] !== '' ? $formData['location'] : null,
            json_encode($specRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $formData['persons_involved'] !== '' ? $formData['persons_involved'] : null,
            $formData['remarks'] !== '' ? $formData['remarks'] : null,
            $formData['action_taken'] !== '' ? $formData['action_taken'] : null,
            $formData['prepared_by_name'] !== '' ? $formData['prepared_by_name'] : null,
            $formData['prepared_by_designation'] !== '' ? $formData['prepared_by_designation'] : null,
            $formData['prepared_by_date'] !== '' ? $formData['prepared_by_date'] : null,
            $formData['submitted_to_name'] !== '' ? $formData['submitted_to_name'] : null,
            $formData['submitted_to_designation'] !== '' ? $formData['submitted_to_designation'] : null,
            $formData['submitted_to_date'] !== '' ? $formData['submitted_to_date'] : null,
            $username,
        ]);
        header('Location: incident_reports.php?msg=' . urlencode('Incident report saved successfully.'));
        exit;
    }
}

while (count($specRows) < 3) {
    $specRows[] = [
        'item' => '',
        'uom' => '',
        'program' => '',
        'po' => '',
        'batch' => '',
        'exp' => '',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Report - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260219">
    <style>
        .incident-report-page .incident-sheet {
            max-width: 880px;
            margin: 0 auto;
            border: 1px solid #222;
            background: #fff;
            padding: 18px 18px 14px;
        }
        .incident-report-page .ir-top {
            text-align: center;
            margin-bottom: 8px;
        }
        .incident-report-page .ir-line-field {
            width: 320px;
            max-width: 100%;
            margin: 0 auto 4px;
        }
        .incident-report-page .ir-line-field input {
            border: 0;
            border-bottom: 1px solid #222;
            border-radius: 0;
            width: 100%;
            padding: 0 2px;
            font-size: 0.85rem;
            text-align: center;
            background: transparent;
        }
        .incident-report-page .ir-line-field .caption {
            font-size: 0.62rem;
            text-transform: uppercase;
            margin-top: 1px;
            color: #222;
            letter-spacing: 0.02em;
        }
        .incident-report-page .ir-title {
            text-align: center;
            font-size: 1.08rem;
            font-weight: 700;
            margin: 10px 0 2px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        .incident-report-page .ir-no-row {
            text-align: center;
            font-size: 0.82rem;
            margin-bottom: 8px;
        }
        .incident-report-page .ir-no-row input {
            border: 0;
            border-bottom: 1px solid #222;
            border-radius: 0;
            width: 120px;
            padding: 0 2px;
            background: transparent;
        }
        .incident-report-page .ir-main-table,
        .incident-report-page .ir-sign-table,
        .incident-report-page .ir-specs-table {
            width: 100%;
            border-collapse: collapse;
        }
        .incident-report-page .ir-main-table th,
        .incident-report-page .ir-main-table td,
        .incident-report-page .ir-sign-table th,
        .incident-report-page .ir-sign-table td,
        .incident-report-page .ir-specs-table th,
        .incident-report-page .ir-specs-table td {
            border: 1px solid #222;
            padding: 4px 6px;
            vertical-align: top;
            font-size: 0.8rem;
            color: #111;
        }
        .incident-report-page .ir-main-table th,
        .incident-report-page .ir-sign-table th {
            width: 19%;
            font-weight: 700;
            background: #f9f9f9;
        }
        .incident-report-page .ir-main-table .narrow-label {
            width: 12%;
        }
        .incident-report-page .ir-main-table .wide-input {
            width: 38%;
        }
        .incident-report-page .ir-cell-input,
        .incident-report-page .ir-cell-textarea,
        .incident-report-page .ir-specs-table input {
            width: 100%;
            border: 0;
            background: transparent;
            padding: 0;
            margin: 0;
            font-size: 0.8rem;
        }
        .incident-report-page .ir-cell-textarea {
            min-height: 72px;
            resize: none;
            line-height: 1.2;
        }
        .incident-report-page .ir-cell-textarea.short {
            min-height: 40px;
        }
        .incident-report-page .ir-help-text {
            display: block;
            margin-top: 4px;
            font-size: 0.72rem;
            font-style: italic;
            color: #333;
            line-height: 1.2;
        }
        .incident-report-page .ir-specs-table th {
            text-transform: uppercase;
            font-size: 0.72rem;
            text-align: center;
            background: #f9f9f9;
        }
        .incident-report-page .ir-specs-table td {
            min-height: 24px;
        }
        .incident-report-page .ir-sign-table {
            margin-top: 10px;
        }
        .incident-report-page .ir-sign-table thead th {
            text-align: center;
            font-size: 0.85rem;
            background: #f4f4f4;
            width: 40%;
        }
        .incident-report-page .ir-sign-table .label-col {
            width: 20%;
            font-weight: 700;
            background: #f9f9f9;
        }
        .incident-report-page .ir-sign-table .sig-row input,
        .incident-report-page .ir-sign-table td input {
            width: 100%;
            border: 0;
            background: transparent;
            padding: 0;
            font-size: 0.8rem;
        }
        .incident-report-page .ir-sign-table .sig-row td {
            min-height: 28px;
        }
        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm;
            }
            .incident-report-page .no-print {
                display: none !important;
            }
            .incident-report-page main.py-4 {
                padding: 0 !important;
            }
            .incident-report-page .container {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .incident-report-page .incident-sheet {
                border: 1px solid #222 !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 10px 12px 8px !important;
            }
            .incident-report-page .ir-main-table th,
            .incident-report-page .ir-main-table td,
            .incident-report-page .ir-sign-table th,
            .incident-report-page .ir-sign-table td,
            .incident-report-page .ir-specs-table th,
            .incident-report-page .ir-specs-table td {
                padding: 3px 4px;
                font-size: 0.72rem;
            }
            .incident-report-page .ir-cell-textarea {
                min-height: 58px;
            }
            .incident-report-page .ir-cell-textarea.short {
                min-height: 34px;
            }
        }
    </style>
</head>
<body class="incident-report-page report-page">
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
                    <small class="fw-normal" style="font-size: 0.72rem;">Incident Report</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Home</a>
                <a href="incident_reports.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Saved Incidents</a>
                <a href="report.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Report</a>
                <a href="create_ptr.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Create PTR</a>
                <a href="pending_transactions.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Pending</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Log out</a>
            </div>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h1 class="h5 mb-0">Incident Report</h1>
                <div class="d-flex gap-2">
                    <a href="incident_reports.php" class="btn btn-outline-secondary btn-sm">View Saved Reports</a>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print();">Print</button>
                    <button type="submit" form="incidentForm" class="btn btn-primary btn-sm">Save Report</button>
                </div>
            </div>

            <div class="card app-card incident-sheet">
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger py-2 mb-3 no-print">
                            <?php foreach ($errors as $error): ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form id="incidentForm" method="post" action="incident_report.php">
                        <div class="ir-top">
                            <div class="ir-line-field">
                                <input type="text" name="name_of_office" value="<?= htmlspecialchars($formData['name_of_office']) ?>">
                                <div class="caption">Name of Office</div>
                            </div>
                            <div class="ir-line-field">
                                <input type="text" name="address" value="<?= htmlspecialchars($formData['address']) ?>">
                                <div class="caption">Address</div>
                            </div>
                        </div>

                        <h2 class="ir-title">Incident Report</h2>
                        <div class="ir-no-row">
                            No:
                            <input type="text" name="incident_no" value="<?= htmlspecialchars($formData['incident_no']) ?>">
                        </div>

                        <table class="ir-main-table">
                            <tr>
                                <th class="narrow-label">Incident Type:</th>
                                <td class="wide-input"><input type="text" class="ir-cell-input" name="incident_type" value="<?= htmlspecialchars($formData['incident_type']) ?>"></td>
                                <th class="narrow-label">Date/Time of Incident:</th>
                                <td class="wide-input"><input type="datetime-local" class="ir-cell-input" name="incident_datetime" value="<?= htmlspecialchars($formData['incident_datetime']) ?>"></td>
                            </tr>
                            <tr>
                                <th>Location:</th>
                                <td colspan="3"><input type="text" class="ir-cell-input" name="location" value="<?= htmlspecialchars($formData['location']) ?>"></td>
                            </tr>
                            <tr>
                                <th>Specifics:</th>
                                <td colspan="3" style="padding:0;">
                                    <table class="ir-specs-table">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>UOM</th>
                                                <th>Program</th>
                                                <th>PO #</th>
                                                <th>Batch #</th>
                                                <th>Exp Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($specRows as $specRow): ?>
                                                <tr>
                                                    <td><input type="text" name="spec_item[]" value="<?= htmlspecialchars((string) ($specRow['item'] ?? '')) ?>"></td>
                                                    <td><input type="text" name="spec_oum[]" value="<?= htmlspecialchars((string) ($specRow['uom'] ?? '')) ?>"></td>
                                                    <td><input type="text" name="spec_program[]" value="<?= htmlspecialchars((string) ($specRow['program'] ?? '')) ?>"></td>
                                                    <td><input type="text" name="spec_po[]" value="<?= htmlspecialchars((string) ($specRow['po'] ?? '')) ?>"></td>
                                                    <td><input type="text" name="spec_batch[]" value="<?= htmlspecialchars((string) ($specRow['batch'] ?? '')) ?>"></td>
                                                    <td><input type="text" name="spec_exp[]" value="<?= htmlspecialchars((string) ($specRow['exp'] ?? '')) ?>"></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <th>Persons Involved:</th>
                                <td colspan="3"><input type="text" class="ir-cell-input" name="persons_involved" value="<?= htmlspecialchars($formData['persons_involved']) ?>"></td>
                            </tr>
                            <tr>
                                <th>Remarks:</th>
                                <td colspan="3">
                                    <textarea class="ir-cell-textarea" name="remarks"><?= htmlspecialchars($formData['remarks']) ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th>Action Taken:</th>
                                <td colspan="3"><textarea class="ir-cell-textarea short" name="action_taken"><?= htmlspecialchars($formData['action_taken']) ?></textarea></td>
                            </tr>
                        </table>

                        <table class="ir-sign-table">
                            <thead>
                                <tr>
                                    <th class="label-col"></th>
                                    <th>Prepared By</th>
                                    <th>Submitted To</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="sig-row">
                                    <td class="label-col">Signature:</td>
                                    <td><input type="text" name="prepared_by_signature"></td>
                                    <td><input type="text" name="submitted_to_signature"></td>
                                </tr>
                                <tr>
                                    <td class="label-col">Name:</td>
                                    <td><input type="text" name="prepared_by_name" value="<?= htmlspecialchars($formData['prepared_by_name']) ?>"></td>
                                    <td><input type="text" name="submitted_to_name" value="<?= htmlspecialchars($formData['submitted_to_name']) ?>"></td>
                                </tr>
                                <tr>
                                    <td class="label-col">Designation:</td>
                                    <td><input type="text" name="prepared_by_designation" value="<?= htmlspecialchars($formData['prepared_by_designation']) ?>"></td>
                                    <td><input type="text" name="submitted_to_designation" value="<?= htmlspecialchars($formData['submitted_to_designation']) ?>"></td>
                                </tr>
                                <tr>
                                    <td class="label-col">Date:</td>
                                    <td><input type="date" name="prepared_by_date" value="<?= htmlspecialchars($formData['prepared_by_date']) ?>"></td>
                                    <td><input type="date" name="submitted_to_date" value="<?= htmlspecialchars($formData['submitted_to_date']) ?>"></td>
                                </tr>
                            </tbody>
                        </table>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
