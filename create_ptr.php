<?php
session_start();

// Require login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = false;
$recipientOptions = [];
$descriptionOptions = [];
$programOptions = [];
$unitOptions = [];
$productMetaByDescription = [];
$batchNumbersByDescription = [];
$batchMetaByDescription = [];
$hasProductBatchesTable = false;

// Default values for sticky form
$data = [
    'record_date'     => date('Y-m-d'),
    'ptr_no'          => '',
    'recipient'       => '',
    'items'           => [],
];

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/ptr_numbering.php';
$pdo = getConnection();
$nextPtrNo = '';

function createBlankItem(): array
{
    return [
        'batch_id'        => 0,
        'description'     => '',
        'batch_number'    => '',
        'quantity'        => '',
        'unit'            => '',
        'unit_cost'       => '',
        'program'         => '',
        'po_no'           => '',
        'expiration_date' => '',
    ];
}

try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS inventory_records (
            id INT NOT NULL AUTO_INCREMENT,
            expiration_date DATE DEFAULT NULL,
            unit VARCHAR(50) DEFAULT NULL,
            description TEXT,
            batch_number VARCHAR(100) DEFAULT NULL,
            quantity INT DEFAULT 0,
            unit_cost DECIMAL(10,2) DEFAULT 0.00,
            program VARCHAR(255) DEFAULT NULL,
            recipient VARCHAR(255) DEFAULT NULL,
            ptr_no VARCHAR(50) DEFAULT NULL,
            record_date DATE DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );

    $batchColumnStmt = $pdo->query("SHOW COLUMNS FROM inventory_records LIKE 'batch_number'");
    if (!$batchColumnStmt || !$batchColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE inventory_records ADD COLUMN batch_number VARCHAR(100) DEFAULT NULL AFTER description');
    }
    $batchIdColumnStmt = $pdo->query("SHOW COLUMNS FROM inventory_records LIKE 'batch_id'");
    if (!$batchIdColumnStmt || !$batchIdColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE inventory_records ADD COLUMN batch_id INT DEFAULT NULL AFTER batch_number');
    }
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

    normalizeExistingPtrNumbers($pdo);
    $nextPtrNo = getNextPtrNumber($pdo, $data['record_date']);
    $data['ptr_no'] = (string) $nextPtrNo;
    $data['items'] = [createBlankItem()];

    $recipientStmt = $pdo->query('SELECT recipient_name FROM recipients ORDER BY recipient_name ASC');
    $recipientOptions = $recipientStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $errors[] = 'Could not load recipient options. Please check the recipients table.';
}

try {
    $hasProductsExpiryDate = false;

    $expiryColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'expiry_date'");
    if ($expiryColumnStmt && $expiryColumnStmt->fetch()) {
        $hasProductsExpiryDate = true;
    }

    $productBatchesStmt = $pdo->query("SHOW TABLES LIKE 'product_batches'");
    if ($productBatchesStmt && $productBatchesStmt->fetch()) {
        $hasProductBatchesTable = true;
    }

    if ($hasProductsExpiryDate) {
        $descriptionStmt = $pdo->query('
            SELECT id, product_description, uom, cost_per_unit, program, po_no, expiry_date
            FROM products
            WHERE product_description IS NOT NULL AND TRIM(product_description) <> ""
            ORDER BY id DESC
        ');
    } elseif ($hasProductBatchesTable) {
        $descriptionStmt = $pdo->query('
            SELECT
                p.id,
                p.product_description,
                p.uom,
                p.cost_per_unit,
                p.program,
                p.po_no,
                b.expiry_date AS expiry_date
            FROM products p
            LEFT JOIN (
                SELECT product_id, MIN(expiry_date) AS expiry_date
                FROM product_batches
                WHERE expiry_date IS NOT NULL
                GROUP BY product_id
            ) b ON b.product_id = p.id
            WHERE p.product_description IS NOT NULL AND TRIM(p.product_description) <> ""
            ORDER BY p.id DESC
        ');
    } else {
        $descriptionStmt = $pdo->query('
            SELECT id, product_description, uom, cost_per_unit, program, po_no, NULL AS expiry_date
            FROM products
            WHERE product_description IS NOT NULL AND TRIM(product_description) <> ""
            ORDER BY id DESC
        ');
    }
    $productRows = $descriptionStmt->fetchAll();

    foreach ($productRows as $row) {
        $description = trim((string)$row['product_description']);
        if ($description === '' || isset($productMetaByDescription[$description])) {
            continue;
        }

        $productMetaByDescription[$description] = [
            'unit'             => $row['uom'] !== null ? (string)$row['uom'] : '',
            'unit_cost'        => (string)$row['cost_per_unit'],
            'program'          => $row['program'] !== null ? (string)$row['program'] : '',
            'po_no'            => isset($row['po_no']) && $row['po_no'] !== null ? (string)$row['po_no'] : '',
            'expiration_date'  => isset($row['expiry_date']) && $row['expiry_date'] !== null ? (string)$row['expiry_date'] : '',
        ];
    }

    $descriptionOptions = array_keys($productMetaByDescription);
    sort($descriptionOptions, SORT_NATURAL | SORT_FLAG_CASE);
    $programOptions = [];
    foreach ($productRows as $row) {
        $program = trim((string) ($row['program'] ?? ''));
        if ($program !== '' && !in_array($program, $programOptions, true)) {
            $programOptions[] = $program;
        }
        $unit = trim((string) ($row['uom'] ?? ''));
        if ($unit !== '' && !in_array($unit, $unitOptions, true)) {
            $unitOptions[] = $unit;
        }
    }
    sort($programOptions, SORT_NATURAL | SORT_FLAG_CASE);

    $inventoryUnitStmt = $pdo->query('
        SELECT DISTINCT TRIM(unit) AS unit_name
        FROM inventory_records
        WHERE unit IS NOT NULL AND TRIM(unit) <> ""
        ORDER BY unit_name ASC
    ');
    $inventoryUnits = $inventoryUnitStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($inventoryUnits as $inventoryUnit) {
        $unit = trim((string) $inventoryUnit);
        if ($unit !== '' && !in_array($unit, $unitOptions, true)) {
            $unitOptions[] = $unit;
        }
    }
    sort($unitOptions, SORT_NATURAL | SORT_FLAG_CASE);

    if ($hasProductBatchesTable) {
        $batchStmt = $pdo->query('
            SELECT p.product_description, b.id AS batch_id, b.batch_number, b.expiry_date, b.stock_quantity
            FROM product_batches b
            INNER JOIN products p ON p.id = b.product_id
            WHERE b.batch_number IS NOT NULL AND TRIM(b.batch_number) <> ""
              AND p.product_description IS NOT NULL AND TRIM(p.product_description) <> ""
            ORDER BY p.product_description ASC, b.batch_number ASC
        ');
        $batchRows = $batchStmt->fetchAll();

        foreach ($batchRows as $batchRow) {
            $desc = trim((string) ($batchRow['product_description'] ?? ''));
            $batchNo = trim((string) ($batchRow['batch_number'] ?? ''));
            if ($desc === '' || $batchNo === '') {
                continue;
            }
            if (!isset($batchNumbersByDescription[$desc])) {
                $batchNumbersByDescription[$desc] = [];
            }
            if (!in_array($batchNo, $batchNumbersByDescription[$desc], true)) {
                $batchNumbersByDescription[$desc][] = $batchNo;
            }

            if (!isset($batchMetaByDescription[$desc])) {
                $batchMetaByDescription[$desc] = [];
            }
            $batchMetaByDescription[$desc][$batchNo] = [
                'batch_id' => (int) ($batchRow['batch_id'] ?? 0),
                'expiration_date' => isset($batchRow['expiry_date']) && $batchRow['expiry_date'] !== null
                    ? (string) $batchRow['expiry_date']
                    : '',
                'stock_quantity' => (int) ($batchRow['stock_quantity'] ?? 0),
            ];
        }

        foreach ($batchNumbersByDescription as $desc => $batchNumbers) {
            sort($batchNumbers, SORT_NATURAL | SORT_FLAG_CASE);
            $batchNumbersByDescription[$desc] = $batchNumbers;
        }
    }
} catch (PDOException $e) {
    $errors[] = 'Could not load item descriptions. Please check the products table.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['record_date'] = isset($_POST['record_date']) ? trim((string) $_POST['record_date']) : '';
    $data['recipient'] = isset($_POST['recipient']) ? trim((string) $_POST['recipient']) : '';
    $data['ptr_no'] = getNextPtrNumber($pdo, $data['record_date']);
    $data['items'] = [];

    $descriptions = isset($_POST['description']) && is_array($_POST['description']) ? $_POST['description'] : [];
    $batchNumbers = isset($_POST['batch_number']) && is_array($_POST['batch_number']) ? $_POST['batch_number'] : [];
    $units = isset($_POST['unit']) && is_array($_POST['unit']) ? $_POST['unit'] : [];
    $programs = isset($_POST['program']) && is_array($_POST['program']) ? $_POST['program'] : [];
    $poNumbers = isset($_POST['po_number']) && is_array($_POST['po_number']) ? $_POST['po_number'] : [];
    $quantities = isset($_POST['quantity']) && is_array($_POST['quantity']) ? $_POST['quantity'] : [];
    $rowCount = max(count($descriptions), count($batchNumbers), count($units), count($programs), count($poNumbers), count($quantities));
    $stockDeductionPlan = [];

    if ($data['record_date'] === '') {
        $errors[] = 'Record date is required.';
    }
    if ($data['recipient'] === '') {
        $errors[] = 'Recipient is required.';
    }

    for ($i = 0; $i < $rowCount; $i++) {
        $description = trim((string) ($descriptions[$i] ?? ''));
        $batchNumber = trim((string) ($batchNumbers[$i] ?? ''));
        $unitRaw = trim((string) ($units[$i] ?? ''));
        $programRaw = trim((string) ($programs[$i] ?? ''));
        $poNoRaw = trim((string) ($poNumbers[$i] ?? ''));
        $quantityRaw = trim((string) ($quantities[$i] ?? ''));

        if ($description === '' && $batchNumber === '' && $quantityRaw === '') {
            continue;
        }

        $item = createBlankItem();
        $item['description'] = $description;
        $item['batch_number'] = $batchNumber;
        $item['unit'] = $unitRaw;
        $item['program'] = $programRaw;
        $item['po_no'] = $poNoRaw;
        $item['quantity'] = $quantityRaw;
        $item['batch_id'] = 0;

        if ($description === '') {
            $errors[] = 'Item description is required on row ' . ($i + 1) . '.';
            $data['items'][] = $item;
            continue;
        }
        if (!isset($productMetaByDescription[$description])) {
            $errors[] = 'Please select a valid item description on row ' . ($i + 1) . '.';
            $data['items'][] = $item;
            continue;
        }
        if ($programRaw === '') {
            $errors[] = 'program is required before entering';
            $selectedProduct = $productMetaByDescription[$description];
            $item['unit'] = $unitRaw !== '' ? $unitRaw : $selectedProduct['unit'];
            $item['unit_cost'] = number_format((float) $selectedProduct['unit_cost'], 2, '.', '');
            $batchExpiration = '';
            if ($batchNumber !== '' && isset($batchMetaByDescription[$description][$batchNumber])) {
                $batchExpiration = (string) ($batchMetaByDescription[$description][$batchNumber]['expiration_date'] ?? '');
            }
            $item['expiration_date'] = $batchExpiration !== '' ? $batchExpiration : $selectedProduct['expiration_date'];
            $data['items'][] = $item;
            continue;
        }
        if ($quantityRaw === '' || !ctype_digit($quantityRaw) || (int) $quantityRaw <= 0) {
            $errors[] = 'Quantity must be a positive whole number on row ' . ($i + 1) . '.';
            $selectedProduct = $productMetaByDescription[$description];
            $item['unit'] = $unitRaw !== '' ? $unitRaw : $selectedProduct['unit'];
            $item['unit_cost'] = number_format((float) $selectedProduct['unit_cost'], 2, '.', '');
            $item['expiration_date'] = $selectedProduct['expiration_date'];
            $data['items'][] = $item;
            continue;
        }

        $selectedProduct = $productMetaByDescription[$description];
        $item['unit'] = $unitRaw !== '' ? $unitRaw : $selectedProduct['unit'];
        $item['unit_cost'] = number_format((float) $selectedProduct['unit_cost'], 2, '.', '');
        $batchExpiration = '';
        if ($batchNumber !== '' && isset($batchMetaByDescription[$description][$batchNumber])) {
            $batchExpiration = (string) ($batchMetaByDescription[$description][$batchNumber]['expiration_date'] ?? '');
        }
        $item['expiration_date'] = $batchExpiration !== '' ? $batchExpiration : $selectedProduct['expiration_date'];

        if ($hasProductBatchesTable) {
            if ($batchNumber === '') {
                $errors[] = 'Batch number is required on row ' . ($i + 1) . '.';
                $data['items'][] = $item;
                continue;
            }
            if (!isset($batchMetaByDescription[$description][$batchNumber])) {
                $errors[] = 'Selected batch does not exist for the item on row ' . ($i + 1) . '.';
                $data['items'][] = $item;
                continue;
            }

            $batchMeta = $batchMetaByDescription[$description][$batchNumber];
            $batchId = (int) ($batchMeta['batch_id'] ?? 0);
            $availableStock = (int) ($batchMeta['stock_quantity'] ?? 0);
            $requestedQty = (int) $quantityRaw;
            if ($batchId <= 0) {
                $errors[] = 'Unable to resolve stock batch for row ' . ($i + 1) . '.';
                $data['items'][] = $item;
                continue;
            }
            $plannedQty = ($stockDeductionPlan[$batchId] ?? 0) + $requestedQty;
            if ($plannedQty > $availableStock) {
                $errors[] = 'Insufficient stock for "' . $description . '" batch "' . $batchNumber . '" on row ' . ($i + 1) . '. Available: ' . $availableStock . ', requested: ' . $plannedQty . '.';
                $data['items'][] = $item;
                continue;
            }

            $stockDeductionPlan[$batchId] = $plannedQty;
            $item['batch_id'] = $batchId;
        }

        $data['items'][] = $item;
    }

    if (empty($data['items'])) {
        $errors[] = 'Add at least one item before saving.';
        $data['items'] = [createBlankItem()];
    }

    if (empty($errors)) {
        try {
            $nextPtrNo = getNextPtrNumber($pdo, $data['record_date']);
            $data['ptr_no'] = (string) $nextPtrNo;

            $stmt = $pdo->prepare('
                INSERT INTO inventory_records
                    (
                        expiration_date,
                        unit,
                        description,
                        batch_number,
                        batch_id,
                        quantity,
                        unit_cost,
                        program,
                        po_no,
                        recipient,
                        ptr_no,
                        record_date,
                        release_status,
                        released_at
                    )
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $pdo->beginTransaction();
            // Allow custom recipient entries and keep suggestions updated.
            if ($data['recipient'] !== '') {
                $recipientInsertStmt = $pdo->prepare('INSERT IGNORE INTO recipients (recipient_name) VALUES (?)');
                $recipientInsertStmt->execute([$data['recipient']]);
            }

            foreach ($data['items'] as $item) {
                $stmt->execute([
                    $item['expiration_date'] !== '' ? $item['expiration_date'] : null,
                    $item['unit'] !== '' ? $item['unit'] : null,
                    $item['description'],
                    $item['batch_number'] !== '' ? $item['batch_number'] : null,
                    (int) ($item['batch_id'] ?? 0) > 0 ? (int) $item['batch_id'] : null,
                    (int) $item['quantity'],
                    (float) $item['unit_cost'],
                    $item['program'] !== '' ? $item['program'] : null,
                    $item['po_no'] !== '' ? $item['po_no'] : null,
                    $data['recipient'],
                    $data['ptr_no'],
                    $data['record_date'],
                    'pending',
                    null,
                ]);
            }
            $pdo->commit();
            header('Location: pending_transactions.php?msg=' . urlencode('PTR saved as pending. Release it to deduct stock and print.'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Failed to save PTR. Please try again.';
        }
    }
}

if (empty($data['items'])) {
    $data['items'] = [createBlankItem()];
}

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
$totalAmountValue = 0.00;
foreach ($data['items'] as $item) {
    if (!is_numeric($item['quantity']) || !is_numeric($item['unit_cost'])) {
        continue;
    }
    $totalAmountValue += (float) $item['quantity'] * (float) $item['unit_cost'];
}
$totalAmountValue = number_format($totalAmountValue, 2, '.', '');
$previewLineRows = 10;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Property Stock Transfer Report</title>
    <link rel="stylesheet" href="style.css?v=20260212">
    <style>
        .preview-sheet {
            border: 1px solid #222;
            padding: 10px;
            background: #fff;
            max-width: 100%;
        }
        .preview-sheet table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        .preview-sheet th,
        .preview-sheet td {
            border: 1px solid #222;
            padding: 4px 6px;
            vertical-align: top;
        }
        .preview-header {
            display: grid;
            grid-template-columns: 52px auto 52px;
            align-items: center;
            column-gap: 12px;
            margin-bottom: 8px;
            justify-content: center;
        }
        .preview-logo-wrap {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .preview-logo-wrap img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        .preview-title {
            font-weight: 700;
            font-size: 1.1rem;
            text-align: center;
            margin: 0;
        }
        .preview-label {
            font-weight: 700;
        }
        .signatory-table td {
            text-align: center;
            vertical-align: middle;
            height: 92px;
        }
        .signatory-content {
            display: inline-block;
            text-align: center;
            line-height: 1.4;
        }
        .signatory-label {
            display: block;
            margin-bottom: 8px;
        }
        .received-box {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            min-height: 100px;
            padding: 4px 0;
        }
        .received-top {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .received-bottom {
            border: 0;
            padding: 0;
            font-size: 0.62rem;
            line-height: 1.1;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <header class="navbar navbar-expand-lg navbar-light bg-white app-header px-3 px-md-4">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h6 app-header-title d-flex align-items-center gap-2">
                <?php if (file_exists(__DIR__ . '/PHO.png')): ?>
                    <a href="home.php" class="app-header-logo-link" aria-label="Go to homepage">
                        <img src="PHO.png" alt="Palawan Health Office Logo" class="app-logo-circle" style="height: 40px; width: 40px;">
                    </a>
                <?php endif; ?>
                <span class="d-inline-flex flex-column lh-sm">
                    <span>Provincial Health Office</span>
                    <small class="fw-normal" style="font-size: 0.72rem;">Create Property Stock Transfer Report</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip">
                    <?= htmlspecialchars($username) ?>
                </span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Report</a>
                <a href="pending_transactions.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Pending</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
            </div>
        </div>
    </header>
    <main class="py-4">
        <div class="container">
            <div class="card app-card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Property Stock Transfer Report</h2>

                    <div class="mb-3">
                        <?php if (!empty($errors)): ?>
                            <?php foreach ($errors as $e): ?>
                                <div class="alert alert-danger py-2 mb-2"><?= htmlspecialchars($e) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success py-2 mb-0">Report saved successfully.</div>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="create_ptr.php" autocomplete="off" novalidate id="ptrForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="record_date" class="form-label">Record Date</label>
                                <input
                                    type="date"
                                    id="record_date"
                                    name="record_date"
                                    class="form-control"
                                    value="<?= htmlspecialchars($data['record_date']) ?>"
                                    required
                                >
                            </div>
                            <div class="col-md-6">
                                <label for="ptr_no" class="form-label">PTR No.</label>
                                <input
                                    type="text"
                                    id="ptr_no"
                                    name="ptr_no"
                                    class="form-control"
                                    value="<?= htmlspecialchars($data['ptr_no']) ?>"
                                    readonly
                                >
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <label for="recipient" class="form-label">Recipient</label>
                                <input
                                    type="text"
                                    id="recipient"
                                    name="recipient"
                                    class="form-control"
                                    list="recipientOptionsList"
                                    value="<?= htmlspecialchars($data['recipient']) ?>"
                                    placeholder="Type or select recipient"
                                    required
                                >
                            </div>
                        </div>
                        <datalist id="recipientOptionsList">
                            <?php foreach ($recipientOptions as $recipientName): ?>
                                <option value="<?= htmlspecialchars($recipientName) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>

                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <label class="form-label mb-0">Items</label>
                            <button type="button" id="addItemBtn" class="btn btn-outline-primary btn-sm">Add Item</button>
                        </div>
                        <div class="table-responsive mt-2">
                            <table class="table table-bordered align-middle mb-0" id="itemRowsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Description</th>
                                        <th style="width: 12%">Batch Number</th>
                                        <th style="width: 9%">Qty</th>
                                        <th style="width: 9%">Unit</th>
                                        <th style="width: 11%">Unit Cost</th>
                                        <th style="width: 11%">Amount</th>
                                        <th style="width: 14%">Program</th>
                                        <th style="width: 10%">PO Number</th>
                                        <th style="width: 11%">Expiration</th>
                                        <th style="width: 8%" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="itemRowsBody">
                                    <?php foreach ($data['items'] as $index => $item): ?>
                                        <?php
                                            $batchListId = 'batchOptionsList_' . $index;
                                            $rowBatches = $batchNumbersByDescription[(string) ($item['description'] ?? '')] ?? [];
                                        ?>
                                        <tr class="item-row">
                                            <td>
                                                <input
                                                    type="text"
                                                    name="description[]"
                                                    class="form-control item-description"
                                                    list="descriptionOptionsList"
                                                    value="<?= htmlspecialchars((string) ($item['description'] ?? '')) ?>"
                                                    placeholder="Type or select item description"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="batch_number[]"
                                                    class="form-control item-batch-number"
                                                    list="<?= htmlspecialchars($batchListId) ?>"
                                                    value="<?= htmlspecialchars((string) ($item['batch_number'] ?? '')) ?>"
                                                    placeholder="Batch no."
                                                >
                                                <datalist id="<?= htmlspecialchars($batchListId) ?>" class="item-batch-options">
                                                    <?php foreach ($rowBatches as $batchNumberOption): ?>
                                                        <option value="<?= htmlspecialchars($batchNumberOption) ?>"></option>
                                                    <?php endforeach; ?>
                                                </datalist>
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="quantity[]"
                                                    class="form-control item-quantity"
                                                    inputmode="numeric"
                                                    pattern="[0-9]*"
                                                    autocomplete="off"
                                                    value="<?= htmlspecialchars((string) ($item['quantity'] ?? '')) ?>"
                                                >
                                                <div class="form-text item-stock-hint"></div>
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="unit[]"
                                                    class="form-control item-unit"
                                                    list="unitOptionsList"
                                                    value="<?= htmlspecialchars((string) ($item['unit'] ?? '')) ?>"
                                                    placeholder="Type or select unit"
                                                >
                                            </td>
                                            <td><input type="text" class="form-control item-unit-cost" value="<?= htmlspecialchars((string) ($item['unit_cost'] ?? '')) ?>" readonly></td>
                                            <td><input type="text" class="form-control item-amount" value="" readonly></td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="program[]"
                                                    class="form-control item-program"
                                                    list="programOptionsList"
                                                    value="<?= htmlspecialchars((string) ($item['program'] ?? '')) ?>"
                                                    placeholder="Type or select program"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="po_number[]"
                                                    class="form-control item-po-number"
                                                    value="<?= htmlspecialchars((string) ($item['po_no'] ?? '')) ?>"
                                                    placeholder="PO Number"
                                                >
                                            </td>
                                            <td><input type="date" class="form-control item-expiration" value="<?= htmlspecialchars((string) ($item['expiration_date'] ?? '')) ?>" readonly></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn">Remove</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <datalist id="descriptionOptionsList">
                            <?php foreach ($descriptionOptions as $descriptionOption): ?>
                                <option value="<?= htmlspecialchars($descriptionOption) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <datalist id="programOptionsList">
                            <?php foreach ($programOptions as $programOption): ?>
                                <option value="<?= htmlspecialchars($programOption) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <datalist id="unitOptionsList">
                            <?php foreach ($unitOptions as $unitOption): ?>
                                <option value="<?= htmlspecialchars($unitOption) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>

                        <div class="row g-3 mt-1">
                            <div class="col-md-4 ms-auto">
                                <label for="grand_total" class="form-label">Grand Total</label>
                                <input
                                    type="text"
                                    id="grand_total"
                                    class="form-control"
                                    value="<?= htmlspecialchars($totalAmountValue) ?>"
                                    readonly
                                >
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <button type="button" id="nextPreviewBtn" class="btn btn-primary">Next</button>
                            <a href="home.php" class="btn btn-link">Back to Home</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5 mb-0" id="previewModalLabel">PTR Preview</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="previewPrintArea" class="preview-sheet">
                        <div class="preview-header">
                            <div class="preview-logo-wrap">
                                <?php if (file_exists(__DIR__ . '/PGP.png')): ?>
                                    <img src="PGP.png" alt="PGP Logo">
                                <?php endif; ?>
                            </div>
                            <div class="preview-title">Property Stock Transfer Report</div>
                            <div class="preview-logo-wrap">
                                <?php if (file_exists(__DIR__ . '/PHO.png')): ?>
                                    <img src="PHO.png" alt="PHO Logo">
                                <?php endif; ?>
                            </div>
                        </div>
                        <table>
                            <tr>
                                <td colspan="4"><span class="preview-label">Entity Name:</span> Provincial Government of Palawan</td>
                                <td><span class="preview-label">Fund Cluster:</span></td>
                                <td><span class="preview-label">ELMIS CI No.:</span></td>
                            </tr>
                            <tr>
                                <td colspan="4"><span class="preview-label">Division:</span> Supply & Logistics Unit</td>
                                <td colspan="2"><span class="preview-label">Data Responsibility Center Code:</span></td>
                            </tr>
                            <tr>
                                <td colspan="4"><span class="preview-label">Office:</span> Provincial Health Office</td>
                                <td><span class="preview-label">Date:</span> <span id="previewDate">-</span></td>
                                <td><span class="preview-label">PTR No.:</span> <span id="previewPtrNo">-</span></td>
                            </tr>
                        </table>
                        <table>
                            <thead>
                                <tr>
                                    <th>Expiration Date</th>
                                    <th>Unit</th>
                                    <th>Description / Lot No.</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Amount</th>
                                    <th>Program</th>
                                    <th>PO Number</th>
                                </tr>
                            </thead>
                            <tbody id="previewItemsBody">
                                <?php for ($i = 0; $i < $previewLineRows; $i++): ?>
                                    <tr>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="text-end"><strong>TOTAL:</strong></td>
                                    <td id="previewTotal">0.00</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <table>
                            <tr>
                                <td colspan="4"><span class="preview-label">Purpose:</span><br><em>(For the use of)</em> <span id="previewRecipient">-</span></td>
                            </tr>
                        </table>
                        <table class="signatory-table">
                            <tr>
                                <td style="width:50%">
                                    <div class="signatory-content">
                                        <span class="preview-label signatory-label">Prepared by:</span>
                                        Mark Anthony Borres<br>
                                        John Paul Joseph Opiala<br>
                                        Richard Ray
                                    </div>
                                </td>
                                <td style="width:50%">
                                    <div class="signatory-content">
                                        <span class="preview-label signatory-label">Approved by:</span>
                                        Elizabeth C. Calaor, RPh<br>
                                        (Pharmacist II/ Head, Supply & Logistics Unit)<br>
                                        <span id="previewApprovedDate"><?= htmlspecialchars(date('m/d/Y')) ?></span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="width:50%">
                                    <div class="signatory-content">
                                        <span class="preview-label signatory-label">Issued by:</span>
                                        Jannete Ventura<br>
                                        Earnest John Tolentino, RPh
                                    </div>
                                </td>
                                <td style="width:50%">
                                    <div class="received-box">
                                        <div class="received-top">
                                            <span class="preview-label">Received by:</span>
                                        </div>
                                        <div class="received-bottom">
                                            Name, Position, Signature &amp; Date
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="printPreviewBtn" class="btn btn-outline-secondary">Print</button>
                    <button type="submit" form="ptrForm" class="btn btn-primary">Save Report</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.createPtrConfig = {
            productMetaByDescription: <?= json_encode($productMetaByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            batchNumbersByDescription: <?= json_encode($batchNumbersByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            batchMetaByDescription: <?= json_encode($batchMetaByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            hasProductBatches: <?= $hasProductBatchesTable ? 'true' : 'false' ?>,
            previewLineRows: <?= (int) $previewLineRows ?>,
        };
    </script>
    <script src="assets/js/create_ptr.js"></script>
</body>
</html>

