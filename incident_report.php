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
    'location' => '',
    'persons_involved' => '',
    'chronology' => '',
    'followup_actions' => '',
    'witnesses' => '',
    'contact_details' => '',
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
        chronology LONGTEXT,
        followup_actions LONGTEXT,
        witnesses LONGTEXT,
        contact_details LONGTEXT,
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
        $stmt = $pdo->prepare(
            'INSERT INTO incident_reports (
                name_of_office,
                address,
                incident_no,
                incident_type,
                location,
                specifics_json,
                persons_involved,
                chronology,
                followup_actions,
                witnesses,
                contact_details,
                prepared_by_name,
                prepared_by_designation,
                prepared_by_date,
                submitted_to_name,
                submitted_to_designation,
                submitted_to_date,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $formData['name_of_office'] !== '' ? $formData['name_of_office'] : null,
            $formData['address'] !== '' ? $formData['address'] : null,
            $formData['incident_no'] !== '' ? $formData['incident_no'] : null,
            $formData['incident_type'] !== '' ? $formData['incident_type'] : null,
            $formData['location'] !== '' ? $formData['location'] : null,
            json_encode($specRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $formData['persons_involved'] !== '' ? $formData['persons_involved'] : null,
            $formData['chronology'] !== '' ? $formData['chronology'] : null,
            $formData['followup_actions'] !== '' ? $formData['followup_actions'] : null,
            $formData['witnesses'] !== '' ? $formData['witnesses'] : null,
            $formData['contact_details'] !== '' ? $formData['contact_details'] : null,
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
            border: 1px solid #d7f0e1;
        }
        .incident-report-page .ir-title {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            text-align: center;
            margin: 0;
            color: #1f3b2d;
        }
        .incident-report-page .ir-subtitle {
            text-align: center;
            color: #5f7a6c;
            font-size: 0.88rem;
            margin-top: 0.25rem;
            margin-bottom: 1rem;
        }
        .incident-report-page .ir-section {
            border: 1px solid #e4efe9;
            border-radius: 0.5rem;
            padding: 0.9rem;
            margin-bottom: 1rem;
            background: #fcfffd;
        }
        .incident-report-page .ir-section-title {
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #28804b;
            margin: 0 0 0.7rem 0;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid #d7f0e1;
        }
        .incident-report-page .ir-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #1f3b2d;
            margin-bottom: 0.25rem;
        }
        .incident-report-page .ir-input,
        .incident-report-page .ir-textarea {
            width: 100%;
            border: 1px solid #cfe4d8;
            border-radius: 0.4rem;
            padding: 0.45rem 0.55rem;
            font-size: 0.9rem;
            background: #fff;
        }
        .incident-report-page .ir-textarea {
            min-height: 84px;
            resize: vertical;
        }
        .incident-report-page .ir-specs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.2rem;
        }
        .incident-report-page .ir-specs-table th {
            text-align: left;
            padding: 0.45rem 0.55rem;
            border: 1px solid #d7f0e1;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            background: #eff8f3;
            color: #245f3d;
        }
        .incident-report-page .ir-specs-table td {
            padding: 0.15rem;
            border: 1px solid #e1ece6;
        }
        .incident-report-page .ir-specs-table input {
            border: 0;
            width: 100%;
            background: transparent;
            padding: 0.35rem 0.4rem;
            font-size: 0.88rem;
        }
        .incident-report-page .ir-sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .incident-report-page .ir-sig-block {
            border: 1px solid #e4efe9;
            border-radius: 0.5rem;
            padding: 0.8rem;
            background: #fcfffd;
        }
        .incident-report-page .ir-sig-line {
            border-bottom: 1px solid #2d2d2d;
            min-height: 38px;
            margin-bottom: 0.55rem;
        }
        @media (max-width: 767px) {
            .incident-report-page .ir-sign-grid {
                grid-template-columns: 1fr;
            }
        }
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
            .incident-report-page .no-print {
                display: none !important;
            }
            .incident-report-page .app-card,
            .incident-report-page .incident-sheet,
            .incident-report-page .ir-section,
            .incident-report-page .ir-sig-block {
                border-color: #666 !important;
                box-shadow: none !important;
                background: #fff !important;
            }
            .incident-report-page .container {
                max-width: 100% !important;
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
                    <h2 class="ir-title">Incident Report</h2>
                    <p class="ir-subtitle">Complete all applicable fields and keep this report for records.</p>

                    <form id="incidentForm" method="post" action="incident_report.php">
                        <div class="ir-section">
                            <h3 class="ir-section-title">General Information</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="ir-label" for="name_of_office">Name of Office</label>
                                    <input type="text" class="ir-input" id="name_of_office" name="name_of_office" value="<?= htmlspecialchars($formData['name_of_office']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="ir-label" for="address">Address</label>
                                    <input type="text" class="ir-input" id="address" name="address" value="<?= htmlspecialchars($formData['address']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="ir-label" for="incident_no">Incident No.</label>
                                    <input type="text" class="ir-input" id="incident_no" name="incident_no" value="<?= htmlspecialchars($formData['incident_no']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="ir-label" for="incident_type">Incident Type</label>
                                    <input type="text" class="ir-input" id="incident_type" name="incident_type" value="<?= htmlspecialchars($formData['incident_type']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="ir-label" for="location">Location</label>
                                    <input type="text" class="ir-input" id="location" name="location" value="<?= htmlspecialchars($formData['location']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="ir-section">
                            <h3 class="ir-section-title">Item Specifics</h3>
                            <div class="table-responsive">
                                <table class="ir-specs-table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>UOM</th>
                                            <th>Program</th>
                                            <th>PO #</th>
                                            <th>Batch #</th>
                                            <th>Exp. Date</th>
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
                            </div>
                        </div>

                        <div class="ir-section">
                            <h3 class="ir-section-title">Narrative Details</h3>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="ir-label" for="persons_involved">Persons Involved</label>
                                    <input type="text" class="ir-input" id="persons_involved" name="persons_involved" value="<?= htmlspecialchars($formData['persons_involved']) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="ir-label" for="chronology">Chronology of Events</label>
                                    <textarea class="ir-textarea" id="chronology" name="chronology" rows="4"><?= htmlspecialchars($formData['chronology']) ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="ir-label" for="followup_actions">Follow-up Actions</label>
                                    <textarea class="ir-textarea" id="followup_actions" name="followup_actions" rows="3"><?= htmlspecialchars($formData['followup_actions']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="ir-label" for="witnesses">Witness(es) and Designation</label>
                                    <textarea class="ir-textarea" id="witnesses" name="witnesses" rows="3"><?= htmlspecialchars($formData['witnesses']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="ir-label" for="contact_details">Contact Details</label>
                                    <textarea class="ir-textarea" id="contact_details" name="contact_details" rows="3"><?= htmlspecialchars($formData['contact_details']) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="ir-sign-grid">
                            <div class="ir-sig-block">
                                <div class="ir-label">Prepared by</div>
                                <div class="ir-sig-line"></div>
                                <label class="ir-label" for="prepared_by_name">Name</label>
                                <input type="text" class="ir-input mb-2" id="prepared_by_name" name="prepared_by_name" value="<?= htmlspecialchars($formData['prepared_by_name']) ?>">
                                <label class="ir-label" for="prepared_by_designation">Designation</label>
                                <input type="text" class="ir-input mb-2" id="prepared_by_designation" name="prepared_by_designation" value="<?= htmlspecialchars($formData['prepared_by_designation']) ?>">
                                <label class="ir-label" for="prepared_by_date">Date</label>
                                <input type="date" class="ir-input" id="prepared_by_date" name="prepared_by_date" value="<?= htmlspecialchars($formData['prepared_by_date']) ?>">
                            </div>
                            <div class="ir-sig-block">
                                <div class="ir-label">Submitted to</div>
                                <div class="ir-sig-line"></div>
                                <label class="ir-label" for="submitted_to_name">Name</label>
                                <input type="text" class="ir-input mb-2" id="submitted_to_name" name="submitted_to_name" value="<?= htmlspecialchars($formData['submitted_to_name']) ?>">
                                <label class="ir-label" for="submitted_to_designation">Designation</label>
                                <input type="text" class="ir-input mb-2" id="submitted_to_designation" name="submitted_to_designation" value="<?= htmlspecialchars($formData['submitted_to_designation']) ?>">
                                <label class="ir-label" for="submitted_to_date">Date</label>
                                <input type="date" class="ir-input" id="submitted_to_date" name="submitted_to_date" value="<?= htmlspecialchars($formData['submitted_to_date']) ?>">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
