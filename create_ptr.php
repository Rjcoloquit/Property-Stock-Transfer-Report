<?php
session_start();
require_once __DIR__ . '/config/rbac.php';
ptr_require_login();
ptr_require_page_access('create_ptr');
ptr_block_encoder_mutations();

$errors = [];
$success = false;
$recipientOptions = [];
$descriptionOptions = [];
$programOptions = [];
$unitOptions = [];
$productMetaByDescription = [];
$batchNumbersByDescription = [];
$batchMetaByDescription = [];
$unitOptionsByDescription = [];
$programOptionsByDescription = [];
$poOptionsByDescription = [];
$costByDescriptionAndPo = [];
$productMetaByDescriptionPo = [];
$batchNumbersByDescriptionPo = [];
$batchMetaByDescriptionPo = [];
$quantityByDescriptionAndPo = [];
$hasProductBatchesTable = false;
$hasProductPoNumberTable = false;

// Default values for sticky form
$data = [
    'record_date'     => date('Y-m-d'),
    'ptr_no'          => '',
    'recipient'       => '',
    'items'           => [],
];

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/ptr_signatories.php';
require_once __DIR__ . '/config/ptr_numbering.php';
$pdo = getConnection();
$nextPtrNo = '';
$draftToken = trim((string) ($_GET['draft'] ?? $_POST['draft_token'] ?? ''));
$isDraftEditMode = false;
$draftPtrNo = '';
$draftRowId = 0;

if ($draftToken !== '') {
    if (str_starts_with($draftToken, 'ptr:')) {
        $draftPtrNo = trim(substr($draftToken, 4));
        $isDraftEditMode = $draftPtrNo !== '';
    } elseif (str_starts_with($draftToken, 'id:')) {
        $draftRowId = (int) trim(substr($draftToken, 3));
        $isDraftEditMode = $draftRowId > 0;
    }
}

function createBlankItem(): array
{
    return [
        'batch_id'        => 0,
        'description'     => '',
        'batch_number'    => '',
        'supplier'        => '',
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
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
    $supplierColumnStmt = $pdo->query("SHOW COLUMNS FROM inventory_records LIKE 'supplier'");
    if (!$supplierColumnStmt || !$supplierColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE inventory_records ADD COLUMN supplier VARCHAR(255) DEFAULT NULL AFTER po_no');
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
    $productPoNumberStmt = $pdo->query("SHOW TABLES LIKE 'product_po_number'");
    if ($productPoNumberStmt && $productPoNumberStmt->fetch()) {
        $hasProductPoNumberTable = true;
    }

    $productsSupplierStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'supplier'");
    if (!$productsSupplierStmt || !$productsSupplierStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN supplier VARCHAR(255) DEFAULT NULL AFTER po_no');
    }

    if ($hasProductsExpiryDate) {
        $descriptionStmt = $pdo->query('
            SELECT id, product_description, uom, cost_per_unit, program, po_no, supplier, expiry_date
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
                p.supplier,
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
            SELECT id, product_description, uom, cost_per_unit, program, po_no, supplier, NULL AS expiry_date
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
            'supplier'         => isset($row['supplier']) && $row['supplier'] !== null ? (string)$row['supplier'] : '',
            'expiration_date'  => isset($row['expiry_date']) && $row['expiry_date'] !== null ? (string)$row['expiry_date'] : '',
        ];
    }

    $poNumberLoadSucceeded = false;
    try {
        $poNumberStmt = $pdo->query('
            SELECT
                p.product_description,
                ppn.po_no,
                ppn.cost_per_unit,
                ppn.stock_quantity,
                ppn.expiry_date,
                ppn.batch_number,
                pr.uom,
                pr.program,
                pr.supplier
            FROM product_po_number ppn
            INNER JOIN products pr ON pr.id = ppn.product_id
            INNER JOIN products p ON p.id = ppn.product_id
            WHERE p.product_description IS NOT NULL AND TRIM(p.product_description) <> ""
              AND ppn.po_no IS NOT NULL AND TRIM(ppn.po_no) <> ""
              AND COALESCE(ppn.stock_quantity, 0) > 0
            ORDER BY p.product_description ASC, ppn.po_no ASC
        ');
        $poNumberRows = $poNumberStmt->fetchAll();
        $poNumberLoadSucceeded = true;

        foreach ($poNumberRows as $row) {
            $description = trim((string) ($row['product_description'] ?? ''));
            $poNo = trim((string) ($row['po_no'] ?? ''));
            if ($description === '' || $poNo === '') {
                continue;
            }

            if (!isset($poOptionsByDescription[$description])) {
                $poOptionsByDescription[$description] = [];
            }
            if (!in_array($poNo, $poOptionsByDescription[$description], true)) {
                $poOptionsByDescription[$description][] = $poNo;
            }

            $descriptionPoKey = strtolower($description) . '|' . strtolower($poNo);
            $productMetaByDescriptionPo[$descriptionPoKey] = [
                'unit' => trim((string) ($row['uom'] ?? '')),
                'unit_cost' => number_format((float) ($row['cost_per_unit'] ?? 0), 2, '.', ''),
                'program' => trim((string) ($row['program'] ?? '')),
                'po_no' => $poNo,
                'supplier' => trim((string) ($row['supplier'] ?? '')),
                'expiration_date' => isset($row['expiry_date']) && $row['expiry_date'] !== null
                    ? (string) $row['expiry_date']
                    : '',
            ];
            $costByDescriptionAndPo[$descriptionPoKey] = number_format((float) ($row['cost_per_unit'] ?? 0), 2, '.', '');
            $quantityByDescriptionAndPo[$descriptionPoKey] = (int) ($row['stock_quantity'] ?? 0);
        }

        foreach ($poOptionsByDescription as $description => $options) {
            sort($options, SORT_NATURAL | SORT_FLAG_CASE);
            $poOptionsByDescription[$description] = $options;
        }
    } catch (PDOException $e) {
        $errors[] = 'Could not load PO numbers. Please check the product_po_number table.';
    }

    foreach ($productRows as $row) {
        $description = trim((string) ($row['product_description'] ?? ''));
        if ($description === '') {
            continue;
        }

        if (!isset($unitOptionsByDescription[$description])) {
            $unitOptionsByDescription[$description] = [];
        }
        if (!isset($programOptionsByDescription[$description])) {
            $programOptionsByDescription[$description] = [];
        }

        $unit = trim((string) ($row['uom'] ?? ''));
        if ($unit !== '' && !in_array($unit, $unitOptionsByDescription[$description], true)) {
            $unitOptionsByDescription[$description][] = $unit;
        }

        $program = trim((string) ($row['program'] ?? ''));
        if ($program !== '' && !in_array($program, $programOptionsByDescription[$description], true)) {
            $programOptionsByDescription[$description][] = $program;
        }
    }

    foreach ($unitOptionsByDescription as $description => $options) {
        sort($options, SORT_NATURAL | SORT_FLAG_CASE);
        $unitOptionsByDescription[$description] = $options;
    }
    foreach ($programOptionsByDescription as $description => $options) {
        sort($options, SORT_NATURAL | SORT_FLAG_CASE);
        $programOptionsByDescription[$description] = $options;
    }

    if ($hasProductPoNumberTable && $poNumberLoadSucceeded) {
        $allowedDescriptions = array_keys($poOptionsByDescription);
        if ($allowedDescriptions === []) {
            $productMetaByDescription = [];
            $poOptionsByDescription = [];
            $productMetaByDescriptionPo = [];
            $costByDescriptionAndPo = [];
            $quantityByDescriptionAndPo = [];
        } else {
            $allowedFlip = array_flip($allowedDescriptions);
            $productMetaByDescription = array_intersect_key($productMetaByDescription, $allowedFlip);
            $poOptionsByDescription = array_intersect_key($poOptionsByDescription, $allowedFlip);
            $allowedDescLower = [];
            foreach (array_keys($productMetaByDescription) as $d) {
                $allowedDescLower[strtolower(trim($d))] = true;
            }
            foreach (array_keys($productMetaByDescriptionPo) as $poMapKey) {
                $pipe = strpos($poMapKey, '|');
                $descLower = $pipe !== false ? substr($poMapKey, 0, $pipe) : $poMapKey;
                if (!isset($allowedDescLower[$descLower])) {
                    unset($productMetaByDescriptionPo[$poMapKey], $costByDescriptionAndPo[$poMapKey], $quantityByDescriptionAndPo[$poMapKey]);
                }
            }
        }
        $unitOptionsByDescription = array_intersect_key($unitOptionsByDescription, $productMetaByDescription);
        $programOptionsByDescription = array_intersect_key($programOptionsByDescription, $productMetaByDescription);
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

    if ($hasProductPoNumberTable) {
        $poNumberBatchStmt = $pdo->query('
            SELECT
                p.product_description,
                ppn.po_no,
                ppn.batch_number,
                ppn.expiry_date,
                ppn.stock_quantity,
                ppn.id AS batch_id
            FROM product_po_number ppn
            INNER JOIN products p ON p.id = ppn.product_id
            WHERE ppn.batch_number IS NOT NULL AND TRIM(ppn.batch_number) <> ""
              AND p.product_description IS NOT NULL AND TRIM(p.product_description) <> ""
              AND COALESCE(ppn.stock_quantity, 0) > 0
            ORDER BY p.product_description ASC, ppn.batch_number ASC
        ');
        $batchRows = $poNumberBatchStmt->fetchAll();

        foreach ($batchRows as $batchRow) {
            $desc = trim((string) ($batchRow['product_description'] ?? ''));
            $poNo = trim((string) ($batchRow['po_no'] ?? ''));
            $batchNo = trim((string) ($batchRow['batch_number'] ?? ''));
            if ($desc === '' || $batchNo === '') {
                continue;
            }

            $descriptionPoKey = strtolower($desc) . '|' . strtolower($poNo);
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

            if (!isset($batchNumbersByDescriptionPo[$descriptionPoKey])) {
                $batchNumbersByDescriptionPo[$descriptionPoKey] = [];
            }
            if (!in_array($batchNo, $batchNumbersByDescriptionPo[$descriptionPoKey], true)) {
                $batchNumbersByDescriptionPo[$descriptionPoKey][] = $batchNo;
            }

            if (!isset($batchMetaByDescriptionPo[$descriptionPoKey])) {
                $batchMetaByDescriptionPo[$descriptionPoKey] = [];
            }
            $batchMetaByDescriptionPo[$descriptionPoKey][$batchNo] = [
                'batch_id' => (int) ($batchRow['batch_id'] ?? 0),
                'expiration_date' => isset($batchRow['expiry_date']) && $batchRow['expiry_date'] !== null
                    ? (string) $batchRow['expiry_date']
                    : '',
                'stock_quantity' => (int) ($batchRow['stock_quantity'] ?? 0),
            ];

            if (!isset($quantityByDescriptionAndPo[$descriptionPoKey])) {
                $quantityByDescriptionAndPo[$descriptionPoKey] = 0;
            }
            $quantityByDescriptionAndPo[$descriptionPoKey] += (int) ($batchRow['stock_quantity'] ?? 0);
        }

        foreach ($batchNumbersByDescription as $desc => $batchNumbers) {
            sort($batchNumbers, SORT_NATURAL | SORT_FLAG_CASE);
            $batchNumbersByDescription[$desc] = $batchNumbers;
        }

        foreach ($batchNumbersByDescriptionPo as $descriptionPoKey => $batchNumbers) {
            sort($batchNumbers, SORT_NATURAL | SORT_FLAG_CASE);
            $batchNumbersByDescriptionPo[$descriptionPoKey] = $batchNumbers;
        }
    }
} catch (PDOException $e) {
    $errors[] = 'Could not load item descriptions. Please check the products table.';
}

$resolveUnitCostByDescriptionPo = static function (string $description, string $poNo, array $productMetaByDescription, array $costByDescriptionAndPo): string {
    $mapKey = strtolower(trim($description)) . '|' . strtolower(trim($poNo));
    if (isset($costByDescriptionAndPo[$mapKey])) {
        return (string) $costByDescriptionAndPo[$mapKey];
    }
    $fallback = $productMetaByDescription[$description]['unit_cost'] ?? '0';
    return number_format((float) $fallback, 2, '.', '');
};

if ($isDraftEditMode) {
    try {
        if ($draftPtrNo !== '') {
            $draftRowsStmt = $pdo->prepare('
                SELECT id, record_date, ptr_no, recipient, description, batch_number, batch_id, supplier, quantity, unit, unit_cost, program, po_no, expiration_date
                FROM inventory_records
                WHERE ptr_no = ? AND COALESCE(release_status, "released") = "pending"
                ORDER BY id ASC
            ');
            $draftRowsStmt->execute([$draftPtrNo]);
        } else {
            $draftRowsStmt = $pdo->prepare('
                SELECT id, record_date, ptr_no, recipient, description, batch_number, batch_id, supplier, quantity, unit, unit_cost, program, po_no, expiration_date
                FROM inventory_records
                WHERE id = ? AND COALESCE(release_status, "released") = "pending"
                ORDER BY id ASC
            ');
            $draftRowsStmt->execute([$draftRowId]);
        }

        $draftRows = $draftRowsStmt->fetchAll();
        if (empty($draftRows)) {
            $errors[] = 'Pending PTR draft not found or already released.';
            $isDraftEditMode = false;
            $draftToken = '';
        } else {
            $firstDraftRow = $draftRows[0];
            $data['record_date'] = (string) ($firstDraftRow['record_date'] ?? $data['record_date']);
            $data['ptr_no'] = (string) ($firstDraftRow['ptr_no'] ?? '');
            $data['recipient'] = (string) ($firstDraftRow['recipient'] ?? '');
            $data['items'] = [];

            foreach ($draftRows as $row) {
                $data['items'][] = [
                    'batch_id' => (int) ($row['batch_id'] ?? 0),
                    'description' => (string) ($row['description'] ?? ''),
                    'batch_number' => (string) ($row['batch_number'] ?? ''),
                    'supplier' => (string) ($row['supplier'] ?? ''),
                    'quantity' => (string) ($row['quantity'] ?? ''),
                    'unit' => (string) ($row['unit'] ?? ''),
                    'unit_cost' => number_format((float) ($row['unit_cost'] ?? 0), 2, '.', ''),
                    'program' => (string) ($row['program'] ?? ''),
                    'po_no' => (string) ($row['po_no'] ?? ''),
                    'expiration_date' => (string) ($row['expiration_date'] ?? ''),
                ];
            }
        }
    } catch (PDOException $e) {
        $errors[] = 'Unable to load pending PTR draft for editing right now.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_ptr_signatories') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        ptr_ensure_signatories_table($pdo);
        $ptrNoSave = trim((string) ($_POST['ptr_no'] ?? ''));
        if ($ptrNoSave === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'PTR number is required to save signatory names.']);
            exit;
        }
        ptr_save_signatories_for_ptr(
            $pdo,
            $ptrNoSave,
            (string) ($_POST['ptr_prepared_by'] ?? ''),
            (string) ($_POST['ptr_approved_by'] ?? ''),
            (string) ($_POST['ptr_issued_by'] ?? '')
        );
        echo json_encode(['ok' => true, 'message' => 'Signatory names saved for PTR ' . $ptrNoSave . '.']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Could not save signatory names.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedDraftToken = trim((string) ($_POST['draft_token'] ?? ''));
    $isDraftEditMode = false;
    $draftPtrNo = '';
    $draftRowId = 0;
    if ($postedDraftToken !== '') {
        if (str_starts_with($postedDraftToken, 'ptr:')) {
            $draftPtrNo = trim(substr($postedDraftToken, 4));
            $isDraftEditMode = $draftPtrNo !== '';
        } elseif (str_starts_with($postedDraftToken, 'id:')) {
            $draftRowId = (int) trim(substr($postedDraftToken, 3));
            $isDraftEditMode = $draftRowId > 0;
        }
    }

    $data['record_date'] = isset($_POST['record_date']) ? trim((string) $_POST['record_date']) : '';
    $data['recipient'] = isset($_POST['recipient']) ? trim((string) $_POST['recipient']) : '';
    if ($isDraftEditMode) {
        $data['ptr_no'] = trim((string) ($_POST['existing_ptr_no'] ?? ''));
    } else {
        $data['ptr_no'] = getNextPtrNumber($pdo, $data['record_date']);
    }
    $data['items'] = [];

    $descriptions = isset($_POST['description']) && is_array($_POST['description']) ? $_POST['description'] : [];
    $batchNumbers = isset($_POST['batch_number']) && is_array($_POST['batch_number']) ? $_POST['batch_number'] : [];
    $units = isset($_POST['unit']) && is_array($_POST['unit']) ? $_POST['unit'] : [];
    $programs = isset($_POST['program']) && is_array($_POST['program']) ? $_POST['program'] : [];
    $poNumbers = isset($_POST['po_number']) && is_array($_POST['po_number']) ? $_POST['po_number'] : [];
    $quantities = isset($_POST['quantity']) && is_array($_POST['quantity']) ? $_POST['quantity'] : [];
    $rowCount = max(count($descriptions), count($batchNumbers), count($units), count($programs), count($poNumbers), count($quantities));
    $stockDeductionPlan = [];
    $itemCombinationTracker = [];
    $batchLookupByCompositeStmt = null;

    if ($hasProductBatchesTable) {
        $batchLookupByCompositeStmt = $pdo->prepare('
            SELECT b.id AS batch_id, b.stock_quantity
            FROM product_batches b
            INNER JOIN products p ON p.id = b.product_id
            WHERE LOWER(TRIM(p.product_description)) = LOWER(?)
              AND LOWER(TRIM(b.batch_number)) = LOWER(?)
              AND LOWER(TRIM(COALESCE(p.program, ""))) = LOWER(?)
              AND LOWER(TRIM(COALESCE(p.po_no, ""))) = LOWER(?)
            ORDER BY b.id ASC
            LIMIT 1
        ');
    }

    if ($data['record_date'] === '') {
        $errors[] = 'Record date is required.';
    }
    if ($data['recipient'] === '') {
        $errors[] = 'Recipient is required.';
    } elseif (!in_array($data['recipient'], $recipientOptions, true)) {
        $errors[] = 'Please select a valid recipient from the list.';
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

        $combinationKey = strtolower($description)
            . '|' . strtolower($batchNumber)
            . '|' . strtolower($programRaw)
            . '|' . strtolower($poNoRaw);

        if ($description === '') {
            $errors[] = 'Item description is required on row ' . ($i + 1) . '.';
            $data['items'][] = $item;
            continue;
        }
        if ($batchNumber === '') {
            $errors[] = 'Batch number is required on row ' . ($i + 1) . '.';
            $data['items'][] = $item;
            continue;
        }
        if ($unitRaw === '') {
            $errors[] = 'Unit is required on row ' . ($i + 1) . '.';
            $data['items'][] = $item;
            continue;
        }
        if (!isset($productMetaByDescription[$description])) {
            $errors[] = 'Please select a valid item description on row ' . ($i + 1) . '.';
            $data['items'][] = $item;
            continue;
        }
        if ($programRaw === '') {
            $errors[] = 'Program is required on row ' . ($i + 1) . '.';
            $selectedProduct = $productMetaByDescription[$description];
            $item['unit'] = $unitRaw !== '' ? $unitRaw : $selectedProduct['unit'];
            $item['unit_cost'] = $resolveUnitCostByDescriptionPo($description, $poNoRaw, $productMetaByDescription, $costByDescriptionAndPo);
            $batchExpiration = '';
            if ($batchNumber !== '' && isset($batchMetaByDescription[$description][$batchNumber])) {
                $batchExpiration = (string) ($batchMetaByDescription[$description][$batchNumber]['expiration_date'] ?? '');
            }
            $item['expiration_date'] = $batchExpiration !== '' ? $batchExpiration : $selectedProduct['expiration_date'];
            $item['supplier'] = $selectedProduct['supplier'] ?? '';
            $data['items'][] = $item;
            continue;
        }
        if ($poNoRaw === '') {
            $errors[] = 'PO number is required on row ' . ($i + 1) . '.';
            $data['items'][] = $item;
            continue;
        }
        if (isset($unitOptionsByDescription[$description])
            && !empty($unitOptionsByDescription[$description])
            && !in_array($unitRaw, $unitOptionsByDescription[$description], true)
        ) {
            $errors[] = 'Please select a valid unit for the selected item on row ' . ($i + 1) . '.';
            $data['items'][] = $item;
            continue;
        }
        if (isset($programOptionsByDescription[$description])
            && !empty($programOptionsByDescription[$description])
            && !in_array($programRaw, $programOptionsByDescription[$description], true)
        ) {
            $errors[] = 'Please select a valid program for the selected item on row ' . ($i + 1) . '.';
            $data['items'][] = $item;
            continue;
        }
        if (isset($poOptionsByDescription[$description])
            && !empty($poOptionsByDescription[$description])
            && !in_array($poNoRaw, $poOptionsByDescription[$description], true)
        ) {
            $errors[] = 'Please select a valid PO number for the selected item on row ' . ($i + 1) . '.';
            $data['items'][] = $item;
            continue;
        }
        if ($quantityRaw === '' || !ctype_digit($quantityRaw) || (int) $quantityRaw <= 0) {
            $errors[] = 'Quantity must be a positive whole number on row ' . ($i + 1) . '.';
            $selectedProduct = $productMetaByDescription[$description];
            $item['unit'] = $unitRaw !== '' ? $unitRaw : $selectedProduct['unit'];
            $item['unit_cost'] = $resolveUnitCostByDescriptionPo($description, $poNoRaw, $productMetaByDescription, $costByDescriptionAndPo);
            $item['expiration_date'] = $selectedProduct['expiration_date'];
            $item['supplier'] = $selectedProduct['supplier'] ?? '';
            $data['items'][] = $item;
            continue;
        }

        if (isset($itemCombinationTracker[$combinationKey])) {
            $errors[] = 'Duplicate row detected on row ' . ($i + 1) . '. Item + Batch + Program + PO Number must be unique per PTR.';
            $data['items'][] = $item;
            continue;
        }
        $itemCombinationTracker[$combinationKey] = true;

        $selectedProduct = $productMetaByDescription[$description];
        $item['unit'] = $unitRaw !== '' ? $unitRaw : $selectedProduct['unit'];
        $item['unit_cost'] = $resolveUnitCostByDescriptionPo($description, $poNoRaw, $productMetaByDescription, $costByDescriptionAndPo);
        $item['supplier'] = $selectedProduct['supplier'] ?? '';
        $batchExpiration = '';
        if ($batchNumber !== '' && isset($batchMetaByDescription[$description][$batchNumber])) {
            $batchExpiration = (string) ($batchMetaByDescription[$description][$batchNumber]['expiration_date'] ?? '');
        }
        $item['expiration_date'] = $batchExpiration !== '' ? $batchExpiration : $selectedProduct['expiration_date'];

        if ($item['expiration_date'] !== '' && $item['expiration_date'] < date('Y-m-d')) {
            $errors[] = 'Cannot include expired item on row ' . ($i + 1) . '. Use Incident Report for expired releases.';
            $data['items'][] = $item;
            continue;
        }

        if ($hasProductBatchesTable) {
            if (!$batchLookupByCompositeStmt) {
                $errors[] = 'Unable to validate item batch right now.';
                $data['items'][] = $item;
                continue;
            }

            $batchLookupByCompositeStmt->execute([
                $description,
                $batchNumber,
                $programRaw,
                $poNoRaw,
            ]);
            $batchMeta = $batchLookupByCompositeStmt->fetch();
            if (!$batchMeta) {
                $errors[] = 'Selected item combination (Item + Batch + Program + PO Number) does not exist on row ' . ($i + 1) . '.';
                $data['items'][] = $item;
                continue;
            }

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
            if (!$isDraftEditMode) {
                $nextPtrNo = getNextPtrNumber($pdo, $data['record_date']);
                $data['ptr_no'] = (string) $nextPtrNo;
            }

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
                        supplier,
                        recipient,
                        ptr_no,
                        record_date,
                        release_status,
                        released_at
                    )
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $pdo->beginTransaction();
            if ($isDraftEditMode) {
                if ($draftPtrNo !== '') {
                    $deleteDraftStmt = $pdo->prepare('
                        DELETE FROM inventory_records
                        WHERE ptr_no = ? AND COALESCE(release_status, "released") = "pending"
                    ');
                    $deleteDraftStmt->execute([$draftPtrNo]);
                } elseif ($draftRowId > 0) {
                    $deleteDraftStmt = $pdo->prepare('
                        DELETE FROM inventory_records
                        WHERE id = ? AND COALESCE(release_status, "released") = "pending"
                    ');
                    $deleteDraftStmt->execute([$draftRowId]);
                }
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
                    $item['supplier'] !== '' ? $item['supplier'] : null,
                    $data['recipient'],
                    $data['ptr_no'],
                    $data['record_date'],
                    'pending',
                    null,
                ]);
            }
            $pdo->commit();
            if ($isDraftEditMode) {
                header('Location: pending_transactions.php?msg=' . urlencode('Pending PTR draft updated. You can release when final.'));
            } else {
                header('Location: pending_transactions.php?msg=' . urlencode('PTR saved as pending. Release it to deduct stock and print.'));
            }
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

ptr_ensure_signatories_table($pdo);
$sigDefaults = ptr_signatory_defaults();
$sigPtrNo = trim((string) ($data['ptr_no'] ?? ''));
$sigLoaded = $sigPtrNo !== '' ? ptr_load_signatories_for_ptr($pdo, $sigPtrNo) : null;
$previewSignatoryPrepared = $sigLoaded !== null ? $sigLoaded['prepared_by'] : $sigDefaults['prepared_by'];
$previewSignatoryApproved = $sigLoaded !== null ? $sigLoaded['approved_by'] : $sigDefaults['approved_by'];
$previewSignatoryIssued = $sigLoaded !== null ? $sigLoaded['issued_by'] : $sigDefaults['issued_by'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isDraftEditMode ? 'Edit Pending PTR Draft' : 'Create Property Stock Transfer Report' ?></title>
    <link rel="stylesheet" href="style.css?v=20260305">
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
        .ptr-signatory-name {
            display: block;
            width: 100%;
            min-width: 0;
            margin: 0;
            padding: 2px 4px;
            font-family: inherit;
            font-size: inherit;
            line-height: 1.4;
            text-align: center;
            border: 1px solid #ccc;
            background: #fafafa;
            resize: vertical;
            box-sizing: border-box;
        }
        .ptr-signatory-name::placeholder {
            color: #888;
        }
        .ptr-signatory-name:focus {
            outline: none;
            border-color: #0d6efd;
            background: #fff;
        }
        .create-ptr-signatory-block {
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid #dee2e6;
        }
        .preview-approved-date {
            display: block;
            margin-top: 6px;
            font-size: 0.9em;
        }
        .ptr-signatory-name--issued {
            font-size: 0.95em;
        }
    </style>
</head>
<body class="create-ptr-page">
    <header class="navbar navbar-expand-lg navbar-light bg-white app-header px-3 px-md-4">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h6 app-header-title d-flex align-items-center gap-2">
                <?php if (file_exists(__DIR__ . '/PHO.png')): ?>
                    <a href="home.php" class="app-header-logo-link" aria-label="Go to homepage">
                        <img src="PHO.png" alt="Palawan Health Office Logo" class="app-logo-circle app-logo-md">
                    </a>
                <?php endif; ?>
                <span class="d-inline-flex flex-column lh-sm">
                    <span>Provincial Health Office</span>
                    <small class="fw-normal"><?= $isDraftEditMode ? 'Edit Pending PTR Draft' : 'Create Property Stock Transfer Report' ?></small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip">
                    <?= htmlspecialchars($username) ?>
                </span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="current_stock_report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Stock Report</a>
                <a href="report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Report</a>
                <a href="incident_report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Incident Report</a>
                <a href="pending_transactions.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Pending</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
            </div>
        </div>
    </header>
    <main class="py-4">
        <div class="container">
            <div class="card app-card">
                <div class="card-body">
                    <div class="ptr-form-title-row">
                        <h2 class="h5 mb-0"><?= $isDraftEditMode ? 'Pending PTR Draft Editor' : 'Property Stock Transfer Report' ?></h2>
                        <?php if ($isDraftEditMode): ?>
                            <span class="ptr-mode-badge">Draft Mode</span>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <?php if (!empty($errors)): ?>
                            <?php foreach ($errors as $e): ?>
                                <div class="alert alert-danger py-2 mb-2"><?= htmlspecialchars($e) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($isDraftEditMode): ?>
                            <section class="ptr-draft-info-card" aria-label="Draft information">
                                <div class="ptr-draft-info-title">Pending Draft Information</div>
                                <div class="ptr-draft-info-grid">
                                    <div class="ptr-draft-chip"><strong>PTR No.:</strong> <?= htmlspecialchars((string) ($data['ptr_no'] !== '' ? $data['ptr_no'] : '-')) ?></div>
                                    <div class="ptr-draft-chip"><strong>Status:</strong> Pending</div>
                                    <div class="ptr-draft-chip"><strong>Action:</strong> Save changes, then release from Pending Transactions.</div>
                                </div>
                            </section>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success py-2 mb-0">Report saved successfully.</div>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="create_ptr.php" autocomplete="off" novalidate id="ptrForm">
                        <?php if ($isDraftEditMode): ?>
                            <input type="hidden" name="draft_token" value="<?= htmlspecialchars($draftToken) ?>">
                            <input type="hidden" name="existing_ptr_no" value="<?= htmlspecialchars((string) $data['ptr_no']) ?>">
                        <?php endif; ?>
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
                                <select
                                    id="recipient"
                                    name="recipient"
                                    class="form-control"
                                    required
                                >
                                    <option value="">Select recipient</option>
                                    <?php foreach ($recipientOptions as $recipientName): ?>
                                        <option value="<?= htmlspecialchars($recipientName) ?>" <?= ((string) $data['recipient'] === (string) $recipientName) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($recipientName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <label class="form-label mb-0">Items</label>
                            <button type="button" id="addItemBtn" class="btn btn-outline-primary btn-sm">Add Item</button>
                        </div>
                        <div class="table-responsive mt-2">
                            <table class="table table-bordered align-middle mb-0 ptr-items-table" id="itemRowsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ptr-col-desc">Description</th>
                                        <th class="ptr-col-po">PO Number</th>
                                        <th class="ptr-col-batch">Batch Number</th>
                                        <th class="ptr-col-qty">Qty</th>
                                        <th class="ptr-col-unit">Unit</th>
                                        <th class="ptr-col-unit-cost">Unit Cost</th>
                                        <th class="ptr-col-amount">Amount</th>
                                        <th class="ptr-col-program">Program</th>
                                        <th class="ptr-col-exp">Expiration</th>
                                        <th class="ptr-col-action text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="itemRowsBody">
                                    <?php foreach ($data['items'] as $index => $item): ?>
                                        <?php
                                            $rowBatchListId = 'rowBatchOptionsList_' . $index;
                                            $rowUnitListId = 'rowUnitOptionsList_' . $index;
                                            $rowProgramListId = 'rowProgramOptionsList_' . $index;
                                            $rowPoListId = 'rowPoOptionsList_' . $index;
                                            $rowBatches = $batchNumbersByDescription[(string) ($item['description'] ?? '')] ?? [];
                                            $rowUnits = $unitOptionsByDescription[(string) ($item['description'] ?? '')] ?? [];
                                            $rowPrograms = $programOptionsByDescription[(string) ($item['description'] ?? '')] ?? [];
                                            $rowPoNumbers = $poOptionsByDescription[(string) ($item['description'] ?? '')] ?? [];
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
                                                    required
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="po_number[]"
                                                    class="form-control item-po-number"
                                                    list="<?= htmlspecialchars($rowPoListId) ?>"
                                                    value="<?= htmlspecialchars((string) ($item['po_no'] ?? '')) ?>"
                                                    placeholder="Type or select PO number"
                                                    required
                                                >
                                                <datalist id="<?= htmlspecialchars($rowPoListId) ?>" class="item-po-options">
                                                    <?php foreach ($rowPoNumbers as $rowPoOption): ?>
                                                        <option value="<?= htmlspecialchars($rowPoOption) ?>"></option>
                                                    <?php endforeach; ?>
                                                </datalist>
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="batch_number[]"
                                                    class="form-control item-batch-number"
                                                    list="<?= htmlspecialchars($rowBatchListId) ?>"
                                                    value="<?= htmlspecialchars((string) ($item['batch_number'] ?? '')) ?>"
                                                    placeholder="Type or select batch number"
                                                    required
                                                >
                                                <datalist id="<?= htmlspecialchars($rowBatchListId) ?>" class="item-batch-options">
                                                    <?php foreach ($rowBatches as $batchNumberOption): ?>
                                                        <option value="<?= htmlspecialchars($batchNumberOption) ?>"></option>
                                                    <?php endforeach; ?>
                                                </datalist>
                                            </td>
                                            <td>
                                                <input
                                                    type="number"
                                                    name="quantity[]"
                                                    class="form-control item-quantity"
                                                    min="1"
                                                    step="1"
                                                    autocomplete="off"
                                                    value="<?= htmlspecialchars((string) ($item['quantity'] ?? '')) ?>"
                                                    required
                                                >
                                                <div class="form-text item-stock-hint"></div>
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="unit[]"
                                                    class="form-control item-unit"
                                                    list="<?= htmlspecialchars($rowUnitListId) ?>"
                                                    value="<?= htmlspecialchars((string) ($item['unit'] ?? '')) ?>"
                                                    placeholder="Type or select unit"
                                                    required
                                                >
                                                <datalist id="<?= htmlspecialchars($rowUnitListId) ?>" class="item-unit-options">
                                                    <?php foreach ($rowUnits as $rowUnitOption): ?>
                                                        <option value="<?= htmlspecialchars($rowUnitOption) ?>"></option>
                                                    <?php endforeach; ?>
                                                </datalist>
                                            </td>
                                            <td><input type="text" class="form-control item-unit-cost" value="<?= htmlspecialchars((string) ($item['unit_cost'] ?? '')) ?>" readonly></td>
                                            <td><input type="text" class="form-control item-amount" value="" readonly></td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="program[]"
                                                    class="form-control item-program"
                                                    list="<?= htmlspecialchars($rowProgramListId) ?>"
                                                    value="<?= htmlspecialchars((string) ($item['program'] ?? '')) ?>"
                                                    placeholder="Type or select program"
                                                    required
                                                >
                                                <datalist id="<?= htmlspecialchars($rowProgramListId) ?>" class="item-program-options">
                                                    <?php foreach ($rowPrograms as $rowProgramOption): ?>
                                                        <option value="<?= htmlspecialchars($rowProgramOption) ?>"></option>
                                                    <?php endforeach; ?>
                                                </datalist>
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
                <div class="modal-header flex-wrap gap-2">
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
                                <td colspan="4" class="preview-purpose-cell"><span class="preview-label">Purpose:</span><br><span class="preview-purpose-value"><em>(For the use of)</em> <span id="previewRecipient">-</span></span></td>
                            </tr>
                        </table>
                        <div id="previewSignatoryBlock" class="create-ptr-signatory-block">
                        <p class="small text-muted mb-2">Optional: edit <strong>Prepared by</strong>, <strong>Approved by</strong>, and <strong>Issued by</strong> before printing. Defaults are filled in; change only if needed.</p>
                        <table class="signatory-table">
                            <tr>
                                <td class="preview-signatory-half">
                                    <div class="signatory-content">
                                        <span class="preview-label signatory-label">Prepared by:</span>
                                        <textarea id="previewPreparedBy" class="ptr-signatory-name" name="ptr_prepared_by" rows="4" spellcheck="false" autocomplete="off" placeholder="Mark Anthony Borres,&#10;John Paul Joseph Opiala,&#10;Richard Roy"><?= htmlspecialchars($previewSignatoryPrepared) ?></textarea>
                                    </div>
                                </td>
                                <td class="preview-signatory-half">
                                    <div class="signatory-content">
                                        <span class="preview-label signatory-label">Approved by:</span>
                                        <textarea id="previewApprovedBy" class="ptr-signatory-name" name="ptr_approved_by" rows="3" spellcheck="false" autocomplete="off" placeholder="Elizabeth C. Calaor, RPh&#10;(Pharmacist II/ Head, Supply &amp; Logistics Unit)"><?= htmlspecialchars($previewSignatoryApproved) ?></textarea>
                                        <span id="previewApprovedDate" class="preview-approved-date"><?= htmlspecialchars(date('m/d/Y')) ?></span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="preview-signatory-half">
                                    <div class="signatory-content">
                                        <span class="preview-label signatory-label">Issued by:</span>
                                        <textarea id="previewIssuedBy" class="ptr-signatory-name ptr-signatory-name--issued" name="ptr_issued_by" rows="3" spellcheck="false" autocomplete="off" placeholder="Jannete Ventura,&#10;Earnest John Tolentino, RPh"><?= htmlspecialchars($previewSignatoryIssued) ?></textarea>
                                    </div>
                                </td>
                                <td class="preview-signatory-half">
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
                </div>
                <div class="modal-footer flex-wrap gap-2">
                    <button type="button" id="printPreviewBtn" class="btn btn-outline-secondary">Print</button>
                    <button type="submit" form="ptrForm" class="btn btn-primary"><?= $isDraftEditMode ? 'Save Draft Changes' : 'Save Report' ?></button>
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
            unitOptionsByDescription: <?= json_encode($unitOptionsByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            programOptionsByDescription: <?= json_encode($programOptionsByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            poOptionsByDescription: <?= json_encode($poOptionsByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            costByDescriptionAndPo: <?= json_encode($costByDescriptionAndPo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            productMetaByDescriptionPo: <?= json_encode($productMetaByDescriptionPo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            batchNumbersByDescriptionPo: <?= json_encode($batchNumbersByDescriptionPo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            batchMetaByDescriptionPo: <?= json_encode($batchMetaByDescriptionPo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            quantityByDescriptionAndPo: <?= json_encode($quantityByDescriptionAndPo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            hasProductBatches: <?= ($hasProductBatchesTable || $hasProductPoNumberTable) ? 'true' : 'false' ?>,
            previewLineRows: <?= (int) $previewLineRows ?>,
        };
    </script>
    <script src="assets/js/smooth_motion.js?v=20260325"></script>
    <script src="assets/js/create_ptr.js?v=<?= urlencode((string) @filemtime(__DIR__ . '/assets/js/create_ptr.js')) ?>"></script>
</body>
</html>

