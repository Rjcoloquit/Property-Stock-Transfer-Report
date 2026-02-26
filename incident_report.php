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

// Fetch products for dropdown in specifics (all products)
$productStmt = $pdo->query('SELECT DISTINCT product_description, uom FROM products ORDER BY product_description ASC');
$products = $productStmt ? $productStmt->fetchAll(PDO::FETCH_ASSOC) : [];
$productsByDescription = [];
foreach ($products as $product) {
    $productsByDescription[$product['product_description']] = $product;
}

// Fetch expired products and their batch data (products with batches where expiry_date < today)
$expiredProductsByDescription = [];
$expiredBatchesByProduct = [];
$batchTableStmt = $pdo->query("SHOW TABLES LIKE 'product_batches'");
if ($batchTableStmt && $batchTableStmt->fetch()) {
    $expiredStmt = $pdo->query(
        'SELECT DISTINCT p.product_description, p.uom
         FROM products p
         INNER JOIN product_batches b ON b.product_id = p.id
         WHERE b.expiry_date IS NOT NULL AND b.expiry_date < CURDATE()
         ORDER BY p.product_description ASC'
    );
    if ($expiredStmt) {
        $expiredProducts = $expiredStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($expiredProducts as $product) {
            $expiredProductsByDescription[$product['product_description']] = $product;
        }
    }
    $batchesStmt = $pdo->query(
        'SELECT p.product_description, p.po_no, p.program, b.batch_number, b.expiry_date
         FROM products p
         INNER JOIN product_batches b ON b.product_id = p.id
         WHERE b.expiry_date IS NOT NULL AND b.expiry_date < CURDATE()
         ORDER BY p.product_description ASC, b.expiry_date ASC'
    );
    if ($batchesStmt) {
        while ($row = $batchesStmt->fetch(PDO::FETCH_ASSOC)) {
            $desc = $row['product_description'];
            if (!isset($expiredBatchesByProduct[$desc])) {
                $expiredBatchesByProduct[$desc] = [];
            }
            $expiredBatchesByProduct[$desc][] = [
                'batch_number' => (string) $row['batch_number'],
                'expiry_date'  => isset($row['expiry_date']) ? (string) $row['expiry_date'] : '',
                'po_no'        => isset($row['po_no']) ? (string) $row['po_no'] : '',
                'program'      => isset($row['program']) ? (string) $row['program'] : '',
            ];
        }
    }
}

// Fetch distinct programs for datalist
$programStmt = $pdo->query('SELECT DISTINCT program FROM products WHERE program IS NOT NULL AND TRIM(program) != "" ORDER BY program ASC');
$programList = $programStmt ? array_column($programStmt->fetchAll(PDO::FETCH_ASSOC), 'program') : [];

// Get next incident number
$maxIncidentStmt = $pdo->query('SELECT MAX(CAST(SUBSTRING(incident_no, 6) AS UNSIGNED)) as max_num FROM incident_reports WHERE incident_no IS NOT NULL AND incident_no LIKE "INC-%"');
$maxResult = $maxIncidentStmt ? $maxIncidentStmt->fetch(PDO::FETCH_ASSOC) : null;
$nextIncidentNum = ($maxResult && $maxResult['max_num']) ? intval($maxResult['max_num']) + 1 : 1;
$nextIncidentNo = 'INC-' . $nextIncidentNum;

// Set current datetime if not already set
if (empty($formData['incident_datetime'])) {
    $now = new DateTime('now');
    $formData['incident_datetime'] = $now->format('Y-m-d\TH:i');
}

if (empty($formData['incident_no'])) {
    $formData['incident_no'] = $nextIncidentNo;
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
        .incident-form-page .incident-sheet {
            border: 1px solid #222;
            padding: 12px 16px;
            background: #fff;
            font-size: 0.82rem;
        }
        .incident-sheet table {
            width: 100%;
            border-collapse: collapse;
        }
        .incident-sheet th,
        .incident-sheet td {
            border: 1px solid #222;
            padding: 4px 6px;
            vertical-align: top;
            font-size: 0.78rem;
        }
        .incident-sheet th {
            background: #f9f9f9;
            font-weight: 700;
            text-align: left;
        }
        /* Specifics Table Styling */
        #specificsTable {
            border: 1px solid #dee2e6;
        }
        #specificsTable thead th {
            background: linear-gradient(to bottom, #f8f9fa, #e9ecef);
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            font-size: 0.8rem;
            padding: 8px 10px;
        }
        #specificsTable tbody tr {
            border-bottom: 1px solid #dee2e6;
            transition: background-color 0.15s ease-in-out;
        }
        #specificsTable tbody tr:hover {
            background-color: #f8f9fa;
        }
        #specificsTable tbody td {
            padding: 6px 8px;
            border: none;
            border-right: 1px solid #dee2e6;
        }
        #specificsTable tbody td:last-child {
            border-right: none;
        }
        #specificsTable .form-control-sm {
            height: 30px;
            padding: 4px 6px;
            font-size: 0.8rem;
        }
        #specificsTable .form-control-sm:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .spec-row {
            background-color: #fff;
        }
        /* A4 print preview - fit one page */
        #previewPrintArea {
            width: 210mm;
            max-height: 297mm;
            max-width: 100%;
            box-sizing: border-box;
            padding: 8px 12px !important;
            font-size: 0.75rem !important;
        }
        #previewPrintArea > div:first-child { margin-bottom: 6px !important; }
        #previewPrintArea > div:first-child > div:last-child { font-size: 0.8rem !important; }
        #previewPrintArea .specs-table th,
        #previewPrintArea .specs-table td { padding: 2px 4px !important; font-size: 0.7rem !important; }
        #previewPrintArea table { margin-bottom: 6px !important; }
        #previewPrintArea table th,
        #previewPrintArea table td { padding: 3px 4px !important; font-size: 0.75rem !important; }
        #previewPrintArea .specs-table thead th { font-size: 0.7rem !important; }
        @media print {
            @page {
                size: A4;
                margin: 8mm;
            }
            body * {
                visibility: hidden;
            }
            #previewPrintArea,
            #previewPrintArea * {
                visibility: visible;
            }
            .modal,
            .modal-dialog,
            .modal-content,
            .modal-body {
                display: block !important;
                position: static !important;
                width: 100% !important;
                height: auto !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                background: transparent !important;
            }
            #previewPrintArea {
                position: static !important;
                width: 100% !important;
                max-width: 100% !important;
                max-height: none !important;
                height: auto !important;
                min-height: 100% !important;
                border: none !important;
                padding: 6mm 8mm !important;
                margin: 0 !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            #previewPrintArea .specs-table th,
            #previewPrintArea .specs-table td { padding: 2px 3px !important; font-size: 0.65rem !important; }
            #previewPrintArea table th,
            #previewPrintArea table td { padding: 2px 3px !important; font-size: 0.7rem !important; }
            .incident-form-page .card-body,
            .incident-form-page .modal-header,
            .incident-form-page .modal-footer,
            .modal-header,
            .modal-footer {
                display: none !important;
            }
        }
    </style>
</head>
<body class="incident-form-page">
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
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="incident_reports.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Saved Incidents</a>
                <a href="report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Report</a>
                <a href="create_ptr.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Create PTR</a>
                <a href="pending_transactions.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Pending</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
            </div>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <div class="card app-card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Incident Report</h2>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger py-2 mb-3 no-print">
                            <?php foreach ($errors as $error): ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form id="incidentForm" method="post" action="incident_report.php" autocomplete="off" novalidate>
                        <!-- Summary Section -->
                        <div class="row g-3 mb-4 pb-3 border-bottom">
                            <div class="col-md-6">
                                <label for="name_of_office" class="form-label fw-bold">Office Name</label>
                                <input type="text" class="form-control form-control-sm" id="name_of_office" name="name_of_office" value="<?= htmlspecialchars($formData['name_of_office']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="address" class="form-label fw-bold">Address</label>
                                <input type="text" class="form-control form-control-sm" id="address" name="address" value="<?= htmlspecialchars($formData['address']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="incident_no" class="form-label fw-bold">Incident No.</label>
                                <input type="text" class="form-control form-control-sm" id="incident_no" name="incident_no" value="<?= htmlspecialchars($formData['incident_no']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="incident_type" class="form-label fw-bold">Incident Type</label>
                                <select class="form-control form-control-sm" id="incident_type" name="incident_type">
                                    <option value="">-- Select Type --</option>
                                    <option value="Expired Commodity" <?= $formData['incident_type'] === 'Expired Commodity' ? 'selected' : '' ?>>Expired Commodity</option>
                                    <option value="Damage Commodity" <?= $formData['incident_type'] === 'Damage Commodity' ? 'selected' : '' ?>>Damage Commodity</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="incident_datetime" class="form-label fw-bold">Date/Time</label>
                                <input type="datetime-local" class="form-control form-control-sm" id="incident_datetime" name="incident_datetime" value="<?= htmlspecialchars($formData['incident_datetime']) ?>">
                            </div>
                            <div class="col-12">
                                <label for="location" class="form-label fw-bold">Location</label>
                                <input type="text" class="form-control form-control-sm" id="location" name="location" value="<?= htmlspecialchars($formData['location']) ?>">
                            </div>
                        </div>

                        <!-- Specifics Section -->
                        <div class="mb-4 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0">Specifics</h6>
                                <button type="button" id="addItemBtn" class="btn btn-sm btn-outline-primary">+ Add Item</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0" id="specificsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 22%">Item</th>
                                            <th style="width: 10%">UOM</th>
                                            <th style="width: 18%">Program</th>
                                            <th style="width: 12%">PO #</th>
                                            <th style="width: 12%">Batch #</th>
                                            <th style="width: 14%">Exp Date</th>
                                            <th style="width: 12%" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="specificsBody">
                                        <?php
                                        $useExpiredItems = ($formData['incident_type'] === 'Expired Commodity' && !empty($expiredProductsByDescription));
                                        $itemProductList = $useExpiredItems ? $expiredProductsByDescription : $productsByDescription;
                                        ?>
                                        <datalist id="specItemDatalist"></datalist>
                                        <datalist id="specProgramDatalist">
                                            <?php foreach ($programList as $prog): ?>
                                                <option value="<?= htmlspecialchars($prog) ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                        <?php foreach ($specRows as $rowIdx => $specRow): ?>
                                            <tr class="spec-row">
                                                <td>
                                                    <input type="text" name="spec_item[]" class="form-control form-control-sm border-0 product-input" list="specItemDatalist" value="<?= htmlspecialchars($specRow['item']) ?>" placeholder="Type or select item">
                                                </td>
                                                <td><input type="text" name="spec_oum[]" class="form-control form-control-sm border-0" value="<?= htmlspecialchars($specRow['uom']) ?>" placeholder="UOM"></td>
                                                <td><input type="text" name="spec_program[]" class="form-control form-control-sm border-0" list="specProgramDatalist" value="<?= htmlspecialchars($specRow['program']) ?>" placeholder="Type or select program"></td>
                                                <td><input type="text" name="spec_po[]" class="form-control form-control-sm border-0" value="<?= htmlspecialchars($specRow['po']) ?>" placeholder="PO"></td>
                                                <td>
                                                    <datalist id="batchDatalist-<?= $rowIdx ?>"></datalist>
                                                    <input type="text" name="spec_batch[]" class="form-control form-control-sm border-0 spec-batch-input" list="batchDatalist-<?= $rowIdx ?>" value="<?= htmlspecialchars($specRow['batch']) ?>" placeholder="Batch #">
                                                </td>
                                                <td><input type="date" name="spec_exp[]" class="form-control form-control-sm border-0" value="<?= htmlspecialchars($specRow['exp']) ?>"></td>
                                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" style="padding: 2px 8px; font-size: 0.8rem;">Remove</button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Persons and Remarks Section -->
                        <div class="row g-3 mb-4 pb-3 border-bottom">
                            <div class="col-12">
                                <label for="persons_involved" class="form-label fw-bold">Persons Involved</label>
                                <input type="text" class="form-control form-control-sm" id="persons_involved" name="persons_involved" value="<?= htmlspecialchars($formData['persons_involved']) ?>">
                            </div>
                            <div class="col-12">
                                <label for="remarks" class="form-label fw-bold">Remarks</label>
                                <textarea class="form-control form-control-sm" id="remarks" name="remarks" rows="3" style="font-size: 0.9rem;"><?= htmlspecialchars($formData['remarks']) ?></textarea>
                            </div>
                            <div class="col-12">
                                <label for="action_taken" class="form-label fw-bold">Action Taken</label>
                                <textarea class="form-control form-control-sm" id="action_taken" name="action_taken" rows="3" style="font-size: 0.9rem;"><?= htmlspecialchars($formData['action_taken']) ?></textarea>
                            </div>
                        </div>

                        <!-- Prepared By Section -->
                        <div class="row g-3 mb-4 pb-3 border-bottom">
                            <div class="col-12">
                                <h6 class="fw-bold mb-2">Prepared By</h6>
                            </div>
                            <div class="col-md-6">
                                <label for="prepared_by_name" class="form-label form-label-sm">Name</label>
                                <input type="text" class="form-control form-control-sm" id="prepared_by_name" name="prepared_by_name" value="<?= htmlspecialchars($formData['prepared_by_name']) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="prepared_by_designation" class="form-label form-label-sm">Designation</label>
                                <input type="text" class="form-control form-control-sm" id="prepared_by_designation" name="prepared_by_designation" value="<?= htmlspecialchars($formData['prepared_by_designation']) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="prepared_by_date" class="form-label form-label-sm">Date</label>
                                <input type="date" class="form-control form-control-sm" id="prepared_by_date" name="prepared_by_date" value="<?= htmlspecialchars($formData['prepared_by_date']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label form-label-sm">Signature</label>
                                <div style="border-bottom: 1px solid #333; height: 60px; margin-top: 20px;"></div>
                            </div>
                        </div>

                        <!-- Submitted To Section -->
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <h6 class="fw-bold mb-2">Submitted To</h6>
                            </div>
                            <div class="col-md-6">
                                <label for="submitted_to_name" class="form-label form-label-sm">Name</label>
                                <input type="text" class="form-control form-control-sm" id="submitted_to_name" name="submitted_to_name" value="<?= htmlspecialchars($formData['submitted_to_name']) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="submitted_to_designation" class="form-label form-label-sm">Designation</label>
                                <input type="text" class="form-control form-control-sm" id="submitted_to_designation" name="submitted_to_designation" value="<?= htmlspecialchars($formData['submitted_to_designation']) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="submitted_to_date" class="form-label form-label-sm">Date</label>
                                <input type="date" class="form-control form-control-sm" id="submitted_to_date" name="submitted_to_date" value="<?= htmlspecialchars($formData['submitted_to_date']) ?>">
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 pt-3">
                            <button type="button" id="nextPreviewBtn" class="btn btn-primary">Next</button>
                            <a href="incident_reports.php" class="btn btn-outline-secondary">View Saved Reports</a>
                            <a href="home.php" class="btn btn-outline-secondary">Home</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5 mb-0" id="previewModalLabel">Incident Report Preview</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="previewPrintArea" class="incident-sheet" style="border: 1px solid #222; padding: 8px 12px; background: #fff; font-size: 0.75rem;">
                        <div style="text-align: center; margin-bottom: 4px;">
                            <div id="previewOfficeName" style="font-weight: 700; font-size: 0.95rem; margin-bottom: 2px;">[Name of Office]</div>
                            <div id="previewAddress" style="font-size: 0.8rem;">[Address]</div>
                        </div>

                        <div style="text-align: center; margin: 4px 0; font-weight: 700; font-size: 0.95rem; letter-spacing: 0.02em;">INCIDENT REPORT</div>

                        <div style="text-align: center; margin-bottom: 6px; font-size: 0.8rem;">
                            No: <span id="previewIncidentNo" style="border-bottom: 1px solid #222; padding: 0 4px; min-width: 100px; display: inline-block;">-</span>
                        </div>

                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
                            <tr>
                                <th style="border: 1px solid #222; padding: 3px 4px; width: 20%; text-align: left; background: #f9f9f9; font-weight: 700; font-size: 0.75rem;">Incident Type:</th>
                                <td style="border: 1px solid #222; padding: 3px 4px; width: 35%;">
                                    <span id="previewIncidentType">-</span>
                                </td>
                                <th style="border: 1px solid #222; padding: 3px 4px; width: 20%; text-align: left; background: #f9f9f9; font-weight: 700; font-size: 0.75rem;">Date/Time:</th>
                                <td style="border: 1px solid #222; padding: 3px 4px; width: 25%;">
                                    <span id="previewIncidentDateTime">-</span>
                                </td>
                            </tr>
                            <tr>
                                <th style="border: 1px solid #222; padding: 3px 4px; text-align: left; background: #f9f9f9; font-weight: 700; font-size: 0.75rem;">Location:</th>
                                <td colspan="3" style="border: 1px solid #222; padding: 3px 4px;">
                                    <span id="previewLocation">-</span>
                                </td>
                            </tr>
                        </table>

                        <div style="margin-bottom: 4px;">
                            <div style="font-weight: 700; color: #2b6843; text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.02em; margin-bottom: 2px;">Specifics:</div>
                            <table class="specs-table" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="border: 1px solid #222; padding: 2px 3px; background: #f0f0f0; font-weight: 700; font-size: 0.7rem;">Item</th>
                                        <th style="border: 1px solid #222; padding: 2px 3px; background: #f0f0f0; font-weight: 700; font-size: 0.7rem;">UOM</th>
                                        <th style="border: 1px solid #222; padding: 2px 3px; background: #f0f0f0; font-weight: 700; font-size: 0.7rem;">Program</th>
                                        <th style="border: 1px solid #222; padding: 2px 3px; background: #f0f0f0; font-weight: 700; font-size: 0.7rem;">PO #</th>
                                        <th style="border: 1px solid #222; padding: 2px 3px; background: #f0f0f0; font-weight: 700; font-size: 0.7rem;">Batch #</th>
                                        <th style="border: 1px solid #222; padding: 2px 3px; background: #f0f0f0; font-weight: 700; font-size: 0.7rem;">Exp Date</th>
                                    </tr>
                                </thead>
                                <tbody id="previewSpecificsBody">
                                </tbody>
                            </table>
                        </div>

                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 4px;">
                            <tr>
                                <th style="border: 1px solid #222; padding: 3px 4px; width: 18%; text-align: left; background: #f9f9f9; font-weight: 700; font-size: 0.7rem;">Persons Involved:</th>
                                <td style="border: 1px solid #222; padding: 3px 4px; font-size: 0.7rem;">
                                    <span id="previewPersonsInvolved" style="white-space: pre-wrap;">-</span>
                                </td>
                            </tr>
                            <tr>
                                <th style="border: 1px solid #222; padding: 3px 4px; text-align: left; vertical-align: top; background: #f9f9f9; font-weight: 700; font-size: 0.7rem;">Remarks:</th>
                                <td style="border: 1px solid #222; padding: 3px 4px; min-height: 24px; font-size: 0.7rem;">
                                    <span id="previewRemarks" style="white-space: pre-wrap;">-</span>
                                </td>
                            </tr>
                            <tr>
                                <th style="border: 1px solid #222; padding: 3px 4px; text-align: left; vertical-align: top; background: #f9f9f9; font-weight: 700; font-size: 0.7rem;">Action Taken:</th>
                                <td style="border: 1px solid #222; padding: 3px 4px; min-height: 24px; font-size: 0.7rem;">
                                    <span id="previewActionTaken" style="white-space: pre-wrap;">-</span>
                                </td>
                            </tr>
                        </table>

                        <table style="width: 100%; border-collapse: collapse; margin-top: 6px;">
                            <thead>
                                <tr>
                                    <th style="border: 1px solid #222; padding: 3px 4px; width: 50%; text-align: center; background: #f4f4f4; font-weight: 700; font-size: 0.75rem;">Prepared By</th>
                                    <th style="border: 1px solid #222; padding: 3px 4px; width: 50%; text-align: center; background: #f4f4f4; font-weight: 700; font-size: 0.75rem;">Submitted To</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="border: 1px solid #222; padding: 3px 4px; height: 52px; text-align: center; vertical-align: bottom; font-size: 0.75rem;">
                                        <span id="previewPreparedByName">-</span>
                                    </td>
                                    <td style="border: 1px solid #222; padding: 3px 4px; height: 52px; text-align: center; vertical-align: bottom; font-size: 0.75rem;">
                                        <span id="previewSubmittedToName">-</span>
                                    </td>
                                </tr>
                                <tr style="font-size: 0.65rem;">
                                    <td style="border: 1px solid #222; padding: 2px 3px; text-align: center;">Signature</td>
                                    <td style="border: 1px solid #222; padding: 2px 3px; text-align: center;">Signature</td>
                                </tr>
                                <tr>
                                    <td style="border: 1px solid #222; padding: 2px 3px; text-align: center; font-size: 0.7rem;">
                                        <span id="previewPreparedByDesignation">-</span>
                                    </td>
                                    <td style="border: 1px solid #222; padding: 2px 3px; text-align: center; font-size: 0.7rem;">
                                        <span id="previewSubmittedToDesignation">-</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: 1px solid #222; padding: 2px 3px; text-align: center; font-size: 0.7rem;">
                                        <span id="previewPreparedByDate">-</span>
                                    </td>
                                    <td style="border: 1px solid #222; padding: 2px 3px; text-align: center; font-size: 0.7rem;">
                                        <span id="previewSubmittedToDate">-</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="printPreviewBtn" class="btn btn-outline-secondary">Print</button>
                    <button type="submit" form="incidentForm" class="btn btn-primary">Save Report</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            'use strict';

            var incidentForm = document.getElementById('incidentForm');
            var nextPreviewBtn = document.getElementById('nextPreviewBtn');
            var previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
            var printPreviewBtn = document.getElementById('printPreviewBtn');

            function textOrDash(value) {
                var clean = String(value || '').trim();
                return clean === '' ? '-' : clean;
            }

            function updatePreview() {
                // Update basic fields
                document.getElementById('previewOfficeName').textContent = textOrDash(document.getElementById('name_of_office').value);
                document.getElementById('previewAddress').textContent = textOrDash(document.getElementById('address').value);
                document.getElementById('previewIncidentNo').textContent = textOrDash(document.getElementById('incident_no').value);
                document.getElementById('previewIncidentType').textContent = textOrDash(document.getElementById('incident_type').value);
                document.getElementById('previewIncidentDateTime').textContent = textOrDash(document.getElementById('incident_datetime').value);
                document.getElementById('previewLocation').textContent = textOrDash(document.getElementById('location').value);
                document.getElementById('previewPersonsInvolved').textContent = textOrDash(document.getElementById('persons_involved').value);
                document.getElementById('previewRemarks').textContent = textOrDash(document.getElementById('remarks').value);
                document.getElementById('previewActionTaken').textContent = textOrDash(document.getElementById('action_taken').value);
                document.getElementById('previewPreparedByName').textContent = textOrDash(document.getElementById('prepared_by_name').value);
                document.getElementById('previewPreparedByDesignation').textContent = textOrDash(document.getElementById('prepared_by_designation').value);
                document.getElementById('previewPreparedByDate').textContent = textOrDash(document.getElementById('prepared_by_date').value);
                document.getElementById('previewSubmittedToName').textContent = textOrDash(document.getElementById('submitted_to_name').value);
                document.getElementById('previewSubmittedToDesignation').textContent = textOrDash(document.getElementById('submitted_to_designation').value);
                document.getElementById('previewSubmittedToDate').textContent = textOrDash(document.getElementById('submitted_to_date').value);

                // Update specifics table
                var specItemInputs = document.querySelectorAll('input[name="spec_item[]"]');
                var previewSpecificsBody = document.getElementById('previewSpecificsBody');
                previewSpecificsBody.innerHTML = '';

                specItemInputs.forEach(function(itemInput, index) {
                    var item = textOrDash(itemInput.value);
                    var uom = textOrDash(document.querySelectorAll('input[name="spec_oum[]"]')[index]?.value || '');
                    var program = textOrDash(document.querySelectorAll('input[name="spec_program[]"]')[index]?.value || '');
                    var po = textOrDash(document.querySelectorAll('input[name="spec_po[]"]')[index]?.value || '');
                    var batch = textOrDash(document.querySelectorAll('input[name="spec_batch[]"]')[index]?.value || '');
                    var exp = textOrDash(document.querySelectorAll('input[name="spec_exp[]"]')[index]?.value || '');

                    var row = document.createElement('tr');
                    row.innerHTML = '<td style="border: 1px solid #222; padding: 2px 3px; font-size: 0.7rem;">' + item + '</td>' +
                                  '<td style="border: 1px solid #222; padding: 2px 3px; font-size: 0.7rem;">' + uom + '</td>' +
                                  '<td style="border: 1px solid #222; padding: 2px 3px; font-size: 0.7rem;">' + program + '</td>' +
                                  '<td style="border: 1px solid #222; padding: 2px 3px; font-size: 0.7rem;">' + po + '</td>' +
                                  '<td style="border: 1px solid #222; padding: 2px 3px; font-size: 0.7rem;">' + batch + '</td>' +
                                  '<td style="border: 1px solid #222; padding: 2px 3px; font-size: 0.7rem;">' + exp + '</td>';
                    previewSpecificsBody.appendChild(row);
                });
            }

            nextPreviewBtn.addEventListener('click', function() {
                updatePreview();
                previewModal.show();
            });

            printPreviewBtn.addEventListener('click', function() {
                window.print();
            });

            // Add Item Button Handler
            var addItemBtn = document.getElementById('addItemBtn');
            var specificsBody = document.getElementById('specificsBody');
            var productsData = <?= json_encode(array_keys($productsByDescription)) ?>;
            var expiredProductsData = <?= json_encode(array_keys($expiredProductsByDescription)) ?>;
            var expiredBatchesByProduct = <?= json_encode($expiredBatchesByProduct) ?>;
            var batchDatalistIdCounter = 100;

            function createDatalistOptions(useExpired) {
                var list = (useExpired && expiredProductsData.length > 0) ? expiredProductsData : productsData;
                var html = '';
                list.forEach(function(desc) {
                    html += '<option value="' + String(desc).replace(/"/g, '&quot;') + '">';
                });
                return html;
            }

            function refreshItemDatalist() {
                var useExpired = document.getElementById('incident_type').value === 'Expired Commodity' && expiredProductsData.length > 0;
                var datalist = document.getElementById('specItemDatalist');
                if (datalist) datalist.innerHTML = createDatalistOptions(useExpired);
            }

            document.getElementById('incident_type').addEventListener('change', refreshItemDatalist);
            refreshItemDatalist();

            addItemBtn.addEventListener('click', function() {
                var newRow = document.createElement('tr');
                newRow.className = 'spec-row';
                var batchListId = 'batchDatalist-' + (++batchDatalistIdCounter);

                newRow.innerHTML = '<td><input type="text" name="spec_item[]" class="form-control form-control-sm border-0 product-input" list="specItemDatalist" placeholder="Type or select item"></td>' +
                    '<td><input type="text" name="spec_oum[]" class="form-control form-control-sm border-0" placeholder="UOM"></td>' +
                    '<td><input type="text" name="spec_program[]" class="form-control form-control-sm border-0" list="specProgramDatalist" placeholder="Type or select program"></td>' +
                    '<td><input type="text" name="spec_po[]" class="form-control form-control-sm border-0" placeholder="PO #"></td>' +
                    '<td><datalist id="' + batchListId + '"></datalist><input type="text" name="spec_batch[]" class="form-control form-control-sm border-0 spec-batch-input" list="' + batchListId + '" placeholder="Batch #"></td>' +
                    '<td><input type="date" name="spec_exp[]" class="form-control form-control-sm border-0"></td>' +
                    '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-item-btn">Remove</button></td>';

                specificsBody.appendChild(newRow);
                attachProductInputHandler(newRow.querySelector('input[name="spec_item[]"]'));
                attachBatchInputHandler(newRow.querySelector('input[name="spec_batch[]"]'));

                // Attach remove handler to new button
                newRow.querySelector('.remove-item-btn').addEventListener('click', function() {
                    var rowsLeft = specificsBody.querySelectorAll('.spec-row').length;
                    if (rowsLeft > 1) {
                        newRow.remove();
                    } else {
                        alert('At least one item must remain in the Specifics section.');
                    }
                });
            });

            // Remove Item Button Handler (for initial rows)
            document.querySelectorAll('.remove-item-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var rowsLeft = specificsBody.querySelectorAll('.spec-row').length;
                    if (rowsLeft > 1) {
                        btn.closest('tr').remove();
                    } else {
                        alert('At least one item must remain in the Specifics section.');
                    }
                });
            });

            // Populate UOM, batch, exp when product is typed/selected
            var productMetaByDescription = <?= json_encode($productsByDescription) ?>;
            
            function fillBatchFields(row, batch) {
                var expInput = row.querySelector('input[name="spec_exp[]"]');
                var poInput = row.querySelector('input[name="spec_po[]"]');
                var programInput = row.querySelector('input[name="spec_program[]"]');
                if (expInput && batch.expiry_date) expInput.value = batch.expiry_date;
                if (poInput && batch.po_no) poInput.value = batch.po_no;
                if (programInput && batch.program) programInput.value = batch.program;
            }

            function applyExpiredBatchesForRow(row, itemVal) {
                if (!itemVal || !expiredBatchesByProduct[itemVal]) return;
                var batches = expiredBatchesByProduct[itemVal];
                var batchInput = row.querySelector('input[name="spec_batch[]"]');
                var expInput = row.querySelector('input[name="spec_exp[]"]');
                var listId = batchInput.getAttribute('list');
                var datalist = listId ? document.getElementById(listId) : null;
                if (datalist) {
                    datalist.innerHTML = batches.map(function(b) {
                        return '<option value="' + String(b.batch_number).replace(/"/g, '&quot;') + '">';
                    }).join('');
                }
                if (batches.length === 1) {
                    batchInput.value = batches[0].batch_number;
                    fillBatchFields(row, batches[0]);
                } else if (batches.length > 1 && !batchInput.value) {
                    batchInput.value = batches[0].batch_number;
                    fillBatchFields(row, batches[0]);
                }
            }

            function fillExpFromBatchForRow(row) {
                var itemInput = row.querySelector('input[name="spec_item[]"]');
                var batchInput = row.querySelector('input[name="spec_batch[]"]');
                var itemVal = String(itemInput && itemInput.value || '').trim();
                var batchVal = String(batchInput && batchInput.value || '').trim();
                if (!itemVal || !batchVal || !expiredBatchesByProduct[itemVal]) return;
                var batches = expiredBatchesByProduct[itemVal];
                var match = batches.find(function(b) { return String(b.batch_number) === batchVal; });
                if (match) fillBatchFields(row, match);
            }

            function attachProductInputHandler(inputElement) {
                if (!inputElement) return;
                function maybeFillRow() {
                    var val = String(inputElement.value || '').trim();
                    var row = inputElement.closest('tr');
                    if (productMetaByDescription[val]) {
                        var uomInput = row.querySelector('input[name="spec_oum[]"]');
                        if (uomInput) uomInput.value = productMetaByDescription[val].uom || '';
                    }
                    if (document.getElementById('incident_type').value === 'Expired Commodity' && expiredBatchesByProduct[val]) {
                        applyExpiredBatchesForRow(row, val);
                    }
                }
                inputElement.addEventListener('change', maybeFillRow);
                inputElement.addEventListener('blur', maybeFillRow);
            }

            function attachBatchInputHandler(batchInput) {
                if (!batchInput) return;
                function onBatchChange() {
                    var row = batchInput.closest('tr');
                    fillExpFromBatchForRow(row);
                }
                batchInput.addEventListener('change', onBatchChange);
                batchInput.addEventListener('blur', onBatchChange);
            }

            document.querySelectorAll('input[name="spec_item[]"]').forEach(function(input) {
                attachProductInputHandler(input);
            });
            document.querySelectorAll('input[name="spec_batch[]"]').forEach(function(input) {
                attachBatchInputHandler(input);
            });

            // Real-time Date/Time: update incident_datetime with current date/time and keep it live
            var incidentDateTimeInput = document.getElementById('incident_datetime');
            function updateIncidentDateTime() {
                if (!incidentDateTimeInput) return;
                var now = new Date();
                var year = now.getFullYear();
                var month = String(now.getMonth() + 1).padStart(2, '0');
                var day = String(now.getDate()).padStart(2, '0');
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                incidentDateTimeInput.value = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
            }
            updateIncidentDateTime();
            setInterval(updateIncidentDateTime, 10000);

            // Print styles are in page <style> block
        })();
    </script>
</body>
</html>
