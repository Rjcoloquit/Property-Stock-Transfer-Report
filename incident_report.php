<?php
session_start();
require_once __DIR__ . '/config/rbac.php';
ptr_require_login();
ptr_require_page_access('incident_report');

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
$editingId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;

$specRows = array_fill(0, 1, [
    'item' => '',
    'uom' => '',
    'program' => '',
    'po' => '',
    'batch' => '',
    'exp' => '',
    'qty' => '',
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
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
$allBatchesByProduct = [];
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

    $allBatchesStmt = $pdo->query(
        'SELECT p.product_description, p.po_no, p.program, b.batch_number, b.expiry_date, b.stock_quantity
         FROM products p
         INNER JOIN product_batches b ON b.product_id = p.id
         ORDER BY p.product_description ASC, b.expiry_date ASC, b.id ASC'
    );
    if ($allBatchesStmt) {
        while ($row = $allBatchesStmt->fetch(PDO::FETCH_ASSOC)) {
            $desc = (string) ($row['product_description'] ?? '');
            if ($desc === '') {
                continue;
            }
            if (!isset($allBatchesByProduct[$desc])) {
                $allBatchesByProduct[$desc] = [];
            }
            $allBatchesByProduct[$desc][] = [
                'batch_number' => (string) ($row['batch_number'] ?? ''),
                'expiry_date'  => isset($row['expiry_date']) ? (string) $row['expiry_date'] : '',
                'po_no'        => isset($row['po_no']) ? (string) $row['po_no'] : '',
                'program'      => isset($row['program']) ? (string) $row['program'] : '',
                'stock_quantity' => isset($row['stock_quantity']) ? (int) $row['stock_quantity'] : 0,
            ];
        }
    }
}

// Fetch distinct programs for datalist
$programStmt = $pdo->query('SELECT DISTINCT program FROM products WHERE program IS NOT NULL AND TRIM(program) != "" ORDER BY program ASC');
$programList = $programStmt ? array_column($programStmt->fetchAll(PDO::FETCH_ASSOC), 'program') : [];

// Get next incident number
$maxIncidentStmt = $pdo->query('SELECT MAX(CAST(SUBSTRING(incident_no, 5) AS UNSIGNED)) as max_num FROM incident_reports WHERE incident_no IS NOT NULL AND LOWER(incident_no) LIKE "inc-%"');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $editingId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM incident_reports WHERE id = ? LIMIT 1');
    $editStmt->execute([$editingId]);
    $editReport = $editStmt->fetch(PDO::FETCH_ASSOC);
    if ($editReport) {
        foreach (array_keys($formData) as $key) {
            if ($key === 'incident_datetime') {
                $rawDateTime = (string) ($editReport['incident_datetime'] ?? '');
                $formData[$key] = $rawDateTime !== '' ? date('Y-m-d\TH:i', strtotime($rawDateTime)) : '';
                continue;
            }
            $formData[$key] = trim((string) ($editReport[$key] ?? ''));
        }

        $decodedEditSpecifics = json_decode((string) ($editReport['specifics_json'] ?? ''), true);
        if (is_array($decodedEditSpecifics) && !empty($decodedEditSpecifics)) {
            $specRows = [];
            foreach ($decodedEditSpecifics as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $specRows[] = [
                    'item' => trim((string) ($row['item'] ?? '')),
                    'uom' => trim((string) ($row['uom'] ?? '')),
                    'program' => trim((string) ($row['program'] ?? '')),
                    'po' => trim((string) ($row['po'] ?? '')),
                    'batch' => trim((string) ($row['batch'] ?? '')),
                    'exp' => trim((string) ($row['exp'] ?? '')),
                    'qty' => trim((string) ($row['qty'] ?? '')),
                ];
            }
        }
    } else {
        $errors[] = 'Selected incident report was not found.';
        $editingId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editingId = (int) ($_POST['report_id'] ?? 0);
    foreach ($formData as $key => $value) {
        $formData[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $specItems = isset($_POST['spec_item']) && is_array($_POST['spec_item']) ? $_POST['spec_item'] : [];
    $specUom = isset($_POST['spec_oum']) && is_array($_POST['spec_oum']) ? $_POST['spec_oum'] : [];
    $specProgram = isset($_POST['spec_program']) && is_array($_POST['spec_program']) ? $_POST['spec_program'] : [];
    $specPo = isset($_POST['spec_po']) && is_array($_POST['spec_po']) ? $_POST['spec_po'] : [];
    $specBatch = isset($_POST['spec_batch']) && is_array($_POST['spec_batch']) ? $_POST['spec_batch'] : [];
    $specExp = isset($_POST['spec_exp']) && is_array($_POST['spec_exp']) ? $_POST['spec_exp'] : [];
    $specQty = isset($_POST['spec_qty']) && is_array($_POST['spec_qty']) ? $_POST['spec_qty'] : [];
    $specCount = max(count($specItems), count($specUom), count($specProgram), count($specPo), count($specBatch), count($specExp), count($specQty), 1);

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
            'qty' => trim((string) ($specQty[$i] ?? '')),
        ];
        if ($row['item'] !== '' || $row['uom'] !== '' || $row['program'] !== '' || $row['po'] !== '' || $row['batch'] !== '' || $row['exp'] !== '' || $row['qty'] !== '') {
            $hasAnySpecifics = true;
        }
        $specRows[] = $row;
    }

    $requiredMainFields = [
        'name_of_office' => 'Office Name',
        'address' => 'Address',
        'incident_no' => 'Incident No.',
        'incident_type' => 'Incident Type',
        'incident_datetime' => 'Date/Time',
        'location' => 'Location',
        'remarks' => 'Remarks',
        'action_taken' => 'Action Taken',
        'prepared_by_name' => 'Prepared By Name',
        'prepared_by_designation' => 'Prepared By Designation',
        'prepared_by_date' => 'Prepared By Date',
        'submitted_to_name' => 'Submitted To Name',
        'submitted_to_designation' => 'Submitted To Designation',
        'submitted_to_date' => 'Submitted To Date',
    ];
    $missingMainFields = [];
    foreach ($requiredMainFields as $fieldKey => $fieldLabel) {
        if (trim((string) ($formData[$fieldKey] ?? '')) === '') {
            $missingMainFields[] = $fieldLabel;
        }
    }
    if ($missingMainFields !== []) {
        $errors[] = 'Please fill the required fields: ' . implode(', ', $missingMainFields) . '.';
    }

    $incompleteSpecificRows = [];
    $invalidQtyRows = [];
    foreach ($specRows as $idx => $row) {
        $rowNum = $idx + 1;
        if (
            $row['item'] === '' ||
            $row['uom'] === '' ||
            $row['program'] === '' ||
            $row['po'] === '' ||
            $row['batch'] === '' ||
            $row['exp'] === '' ||
            $row['qty'] === ''
        ) {
            $incompleteSpecificRows[] = $rowNum;
        }
        if ($row['qty'] !== '' && (!ctype_digit($row['qty']) || (int) $row['qty'] <= 0)) {
            $invalidQtyRows[] = $rowNum;
        }
    }
    if ($incompleteSpecificRows !== []) {
        $errors[] = 'Please complete all Specifics fields for row(s): ' . implode(', ', $incompleteSpecificRows) . '.';
    }
    if ($invalidQtyRows !== []) {
        $errors[] = 'Qty must be a whole number greater than 0 for row(s): ' . implode(', ', $invalidQtyRows) . '.';
    }

    if (empty($errors)) {
        $incidentDateTimeValue = null;
        if ($formData['incident_datetime'] !== '') {
            $incidentDateTimeValue = str_replace('T', ' ', $formData['incident_datetime']);
        }
        if ($editingId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE incident_reports
                 SET name_of_office = ?,
                     address = ?,
                     incident_no = ?,
                     incident_type = ?,
                     incident_datetime = ?,
                     location = ?,
                     specifics_json = ?,
                     persons_involved = ?,
                     remarks = ?,
                     action_taken = ?,
                     prepared_by_name = ?,
                     prepared_by_designation = ?,
                     prepared_by_date = ?,
                     submitted_to_name = ?,
                     submitted_to_designation = ?,
                     submitted_to_date = ?,
                     created_by = ?
                 WHERE id = ?
                 LIMIT 1'
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
                $editingId,
            ]);
        } else {
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
        }

        // Process item disposal - reduce stock quantities and remove items when disposed
        if ($hasProductBatchesTable && $editingId <= 0) {
            foreach ($specRows as $specRow) {
                $itemDesc = trim($specRow['item']);
                $batchNum = trim($specRow['batch']);
                $qtyDisposed = (int) ($specRow['qty'] ?? 0);

                if ($itemDesc !== '' && $batchNum !== '' && $qtyDisposed > 0) {
                    // Find the product by description
                    $productLookup = $pdo->prepare('SELECT id FROM products WHERE TRIM(product_description) = ? LIMIT 1');
                    $productLookup->execute([$itemDesc]);
                    $product = $productLookup->fetch();

                    if ($product) {
                        $productId = (int) $product['id'];

                        // Find the batch and reduce stock
                        $batchLookup = $pdo->prepare('SELECT id, stock_quantity FROM product_batches WHERE product_id = ? AND batch_number = ? LIMIT 1');
                        $batchLookup->execute([$productId, $batchNum]);
                        $batch = $batchLookup->fetch();

                        if ($batch) {
                            $batchId = (int) $batch['id'];
                            $currentStock = (int) $batch['stock_quantity'];
                            $newStock = max(0, $currentStock - $qtyDisposed);

                            if ($newStock <= 0) {
                                // Delete the batch when stock reaches 0
                                $deleteBatchStmt = $pdo->prepare('DELETE FROM product_batches WHERE id = ?');
                                $deleteBatchStmt->execute([$batchId]);

                                // Check if product has any remaining batches
                                $remainingBatchesStmt = $pdo->prepare('SELECT COUNT(*) as batch_count FROM product_batches WHERE product_id = ?');
                                $remainingBatchesStmt->execute([$productId]);
                                $remainingBatches = $remainingBatchesStmt->fetch();

                                // If no batches remain, delete the product
                                if ($remainingBatches && (int) $remainingBatches['batch_count'] === 0) {
                                    $deleteProductStmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
                                    $deleteProductStmt->execute([$productId]);
                                }
                            } else {
                                // Update stock quantity
                                $updateBatchStmt = $pdo->prepare('UPDATE product_batches SET stock_quantity = ? WHERE id = ?');
                                $updateBatchStmt->execute([$newStock, $batchId]);
                            }
                        }
                    }
                }
            }
        }

        header('Location: incident_reports.php?msg=' . urlencode($editingId > 0 ? 'Incident report updated successfully.' : 'Incident report saved successfully.'));
        exit;
    }
}

    while (count($specRows) < 1) {
    $specRows[] = [
        'item' => '',
        'uom' => '',
        'program' => '',
        'po' => '',
        'batch' => '',
        'exp' => '',
        'qty' => '',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Report - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260408incident">
</head>
<body class="incident-form-page report-page">
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
                    <small class="fw-normal">Incident Report</small>
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
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h5 mb-0"><?= $editingId > 0 ? 'Edit Incident Report' : 'Incident Report' ?></h2>
                        <a href="incident_reports.php" class="btn btn-outline-secondary btn-sm">View Saved Reports</a>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger py-3 mb-3 no-print incident-error-summary" role="alert">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="fw-bold">Please fix the following before saving:</span>
                            </div>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li class="mb-1"><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form id="incidentForm" method="post" action="incident_report.php<?= $editingId > 0 ? '?edit_id=' . (int) $editingId : '' ?>" autocomplete="off" novalidate>
                        <input type="hidden" name="report_id" value="<?= (int) $editingId ?>">
                        <!-- Summary Section -->
                        <div class="row g-3 mb-4 pb-3 border-bottom">
                            <div class="col-md-6">
                                <label for="name_of_office" class="form-label fw-bold">Office Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control incident-input" id="name_of_office" name="name_of_office" value="<?= htmlspecialchars($formData['name_of_office']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="address" class="form-label fw-bold">Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control incident-input" id="address" name="address" value="<?= htmlspecialchars($formData['address']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="incident_no" class="form-label fw-bold">Incident No. <span class="text-danger">*</span></label>
                                <input type="text" class="form-control incident-input" id="incident_no" name="incident_no" value="<?= htmlspecialchars($formData['incident_no']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="incident_type" class="form-label fw-bold">Incident Type <span class="text-danger">*</span></label>
                                <select class="form-control incident-input" id="incident_type" name="incident_type" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="Expired Commodity" <?= $formData['incident_type'] === 'Expired Commodity' ? 'selected' : '' ?>>Expired Commodity</option>
                                    <option value="Damage Commodity" <?= $formData['incident_type'] === 'Damage Commodity' ? 'selected' : '' ?>>Damage Commodity</option>
                                    <option value="Missing Inventory" <?= $formData['incident_type'] === 'Missing Inventory' ? 'selected' : '' ?>>Missing Inventory</option>
                                    <option value="Others" <?= $formData['incident_type'] === 'Others' ? 'selected' : '' ?>>Others</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="incident_datetime" class="form-label fw-bold">Date/Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control incident-input" id="incident_datetime" name="incident_datetime" value="<?= htmlspecialchars($formData['incident_datetime']) ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="location" class="form-label fw-bold">Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control incident-input" id="location" name="location" value="<?= htmlspecialchars($formData['location']) ?>" required>
                            </div>
                        </div>

                        <!-- Specifics Section -->
                        <div class="mb-4 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0">Specifics</h6>
                                <button type="button" id="addItemBtn" class="btn btn-sm btn-outline-primary">+ Add Item</button>
                            </div>
                            <div class="inventory-table-container incident-specifics-shell">
                                <div class="inventory-table-wrapper">
                                <table class="table inventory-table incident-specifics-table mb-0" id="specificsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 24%">Item</th>
                                            <th style="width: 7%">UOM</th>
                                            <th style="width: 16%">Program</th>
                                            <th style="width: 10%">PO Number</th>
                                            <th style="width: 10%">Batch Number</th>
                                            <th style="width: 10%">Exp Date</th>
                                            <th style="width: 8%">Qty</th>
                                            <th style="width: 10%" class="text-center">Action</th>
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
                                                    <input type="text" name="spec_item[]" class="form-control incident-spec-input product-input" list="specItemDatalist" value="<?= htmlspecialchars($specRow['item']) ?>" placeholder="Type or select item" required>
                                                </td>
                                                <td><input type="text" name="spec_oum[]" class="form-control incident-spec-input" value="<?= htmlspecialchars($specRow['uom']) ?>" placeholder="UOM" required></td>
                                                <td><input type="text" name="spec_program[]" class="form-control incident-spec-input" list="specProgramDatalist" value="<?= htmlspecialchars($specRow['program']) ?>" placeholder="Type or select program" required></td>
                                                <td><input type="text" name="spec_po[]" class="form-control incident-spec-input" value="<?= htmlspecialchars($specRow['po']) ?>" placeholder="PO" required></td>
                                                <td>
                                                    <datalist id="batchDatalist-<?= $rowIdx ?>"></datalist>
                                                    <input type="text" name="spec_batch[]" class="form-control incident-spec-input spec-batch-input" list="batchDatalist-<?= $rowIdx ?>" value="<?= htmlspecialchars($specRow['batch']) ?>" placeholder="Batch Number" required>
                                                </td>
                                                <td><input type="date" name="spec_exp[]" class="form-control incident-spec-input" value="<?= htmlspecialchars($specRow['exp']) ?>" required></td>
                                                <td><input type="number" name="spec_qty[]" class="form-control incident-spec-input" value="<?= htmlspecialchars($specRow['qty']) ?>" placeholder="Qty" min="1" step="1" required></td>
                                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-item-btn incident-remove-btn">Remove</button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        </div>

                        <!-- Remarks and Action Taken Section -->
                        <div class="row g-3 mb-4 pb-3 border-bottom">
                            <div class="col-12">
                                <label for="remarks" class="form-label fw-bold">Remarks <span class="text-danger">*</span></label>
                                <textarea class="form-control incident-input" id="remarks" name="remarks" rows="3" required><?= htmlspecialchars($formData['remarks']) ?></textarea>
                            </div>
                            <div class="col-12">
                                <label for="action_taken" class="form-label fw-bold">Action Taken <span class="text-danger">*</span></label>
                                <textarea class="form-control incident-input" id="action_taken" name="action_taken" rows="3" required><?= htmlspecialchars($formData['action_taken']) ?></textarea>
                            </div>
                        </div>

                        <!-- Prepared By Section -->
                        <div class="row g-3 mb-4 pb-3 border-bottom">
                            <div class="col-12">
                                <h6 class="fw-bold mb-2">Prepared By</h6>
                            </div>
                            <div class="col-md-6">
                                <label for="prepared_by_name" class="form-label form-label-sm">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control incident-input" id="prepared_by_name" name="prepared_by_name" value="<?= htmlspecialchars($formData['prepared_by_name']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="prepared_by_designation" class="form-label form-label-sm">Designation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control incident-input" id="prepared_by_designation" name="prepared_by_designation" value="<?= htmlspecialchars($formData['prepared_by_designation']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="prepared_by_date" class="form-label form-label-sm">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control incident-input" id="prepared_by_date" name="prepared_by_date" value="<?= htmlspecialchars($formData['prepared_by_date']) ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label form-label-sm">Signature</label>
                                <div class="incident-signature-line"></div>
                            </div>
                        </div>

                        <!-- Submitted To Section -->
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <h6 class="fw-bold mb-2">Submitted To</h6>
                            </div>
                            <div class="col-md-6">
                                <label for="submitted_to_name" class="form-label form-label-sm">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control incident-input" id="submitted_to_name" name="submitted_to_name" value="<?= htmlspecialchars($formData['submitted_to_name']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="submitted_to_designation" class="form-label form-label-sm">Designation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control incident-input" id="submitted_to_designation" name="submitted_to_designation" value="<?= htmlspecialchars($formData['submitted_to_designation']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="submitted_to_date" class="form-label form-label-sm">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control incident-input" id="submitted_to_date" name="submitted_to_date" value="<?= htmlspecialchars($formData['submitted_to_date']) ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label form-label-sm">Signature</label>
                                <div class="incident-signature-line"></div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 pt-3">
                            <button type="submit" class="btn btn-primary">Save Report</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function() {
            'use strict';

            var incidentForm = document.getElementById('incidentForm');

            // Add Item Button Handler
            var addItemBtn = document.getElementById('addItemBtn');
            var specificsBody = document.getElementById('specificsBody');
            var productsData = <?= json_encode(array_keys($productsByDescription)) ?>;
            var expiredProductsData = <?= json_encode(array_keys($expiredProductsByDescription)) ?>;
            var expiredBatchesByProduct = <?= json_encode($expiredBatchesByProduct) ?>;
            var allBatchesByProduct = <?= json_encode($allBatchesByProduct) ?>;
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

            document.getElementById('incident_type').addEventListener('change', function() {
                refreshItemDatalist();
                document.querySelectorAll('input[name="spec_item[]"]').forEach(function(itemInput) {
                    var row = itemInput.closest('tr');
                    if (!row) {
                        return;
                    }
                    applyBatchesForRow(row, String(itemInput.value || '').trim());
                });
            });
            refreshItemDatalist();

            addItemBtn.addEventListener('click', function() {
                var newRow = document.createElement('tr');
                newRow.className = 'spec-row';
                var batchListId = 'batchDatalist-' + (++batchDatalistIdCounter);

                newRow.innerHTML = '<td><input type="text" name="spec_item[]" class="form-control incident-spec-input product-input" list="specItemDatalist" placeholder="Type or select item" required></td>' +
                    '<td><input type="text" name="spec_oum[]" class="form-control incident-spec-input" placeholder="UOM" required></td>' +
                    '<td><input type="text" name="spec_program[]" class="form-control incident-spec-input" list="specProgramDatalist" placeholder="Type or select program" required></td>' +
                    '<td><input type="text" name="spec_po[]" class="form-control incident-spec-input" placeholder="PO Number" required></td>' +
                    '<td><datalist id="' + batchListId + '"></datalist><input type="text" name="spec_batch[]" class="form-control incident-spec-input spec-batch-input" list="' + batchListId + '" placeholder="Batch Number" required></td>' +
                    '<td><input type="date" name="spec_exp[]" class="form-control incident-spec-input" required></td>' +
                    '<td><input type="number" name="spec_qty[]" class="form-control incident-spec-input" placeholder="Qty" min="1" step="1" required></td>' +
                    '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-item-btn incident-remove-btn">Remove</button></td>';

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
                var qtyInput = row.querySelector('input[name="spec_qty[]"]');
                if (expInput && batch.expiry_date) expInput.value = batch.expiry_date;
                if (poInput && batch.po_no) poInput.value = batch.po_no;
                if (programInput && batch.program) programInput.value = batch.program;
                if (qtyInput && typeof batch.stock_quantity !== 'undefined') {
                    qtyInput.value = String(batch.stock_quantity || 0);
                }
            }

            function getBatchSetForItem(itemVal) {
                if (!itemVal) {
                    return [];
                }
                var isExpiredType = document.getElementById('incident_type').value === 'Expired Commodity';
                if (isExpiredType && expiredBatchesByProduct[itemVal]) {
                    return expiredBatchesByProduct[itemVal];
                }
                if (allBatchesByProduct[itemVal]) {
                    return allBatchesByProduct[itemVal];
                }
                return [];
            }

            function applyBatchesForRow(row, itemVal) {
                var batches = getBatchSetForItem(itemVal);
                if (!batches.length) {
                    return;
                }
                var batchInput = row.querySelector('input[name="spec_batch[]"]');
                var listId = batchInput.getAttribute('list');
                var datalist = listId ? document.getElementById(listId) : null;
                if (datalist) {
                    datalist.innerHTML = batches.map(function(b) {
                        return '<option value="' + String(b.batch_number).replace(/"/g, '&quot;') + '">';
                    }).join('');
                }
                if (batches.length >= 1 && !batchInput.value) {
                    batchInput.value = batches[0].batch_number;
                    fillBatchFields(row, batches[0]);
                } else if (batchInput.value) {
                    var currentMatch = batches.find(function(b) {
                        return String(b.batch_number) === String(batchInput.value);
                    });
                    if (currentMatch) {
                        fillBatchFields(row, currentMatch);
                    }
                }
            }

            function fillExpFromBatchForRow(row) {
                var itemInput = row.querySelector('input[name="spec_item[]"]');
                var batchInput = row.querySelector('input[name="spec_batch[]"]');
                var itemVal = String(itemInput && itemInput.value || '').trim();
                var batchVal = String(batchInput && batchInput.value || '').trim();
                if (!itemVal || !batchVal) return;
                var batches = getBatchSetForItem(itemVal);
                if (!batches.length) return;
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
                    applyBatchesForRow(row, val);
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
    <script src="assets/js/smooth_motion.js?v=20260325"></script>
</body>
</html>
