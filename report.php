<?php
session_start();

// Require login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/ptr_numbering.php';

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
$search = trim($_GET['q'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$sort = strtolower(trim($_GET['sort'] ?? 'desc'));
$sort = $sort === 'asc' ? 'asc' : 'desc';
$orderByDirection = $sort === 'asc' ? 'ASC' : 'DESC';
$message = trim($_GET['msg'] ?? '');
$records = [];
$groupedRecords = [];
$error = '';
$grandTotal = 0.00;
$editingId = 0;
$isEditMode = false;
$showEditModal = false;
$formErrors = [];
$formData = [
    'record_date' => '',
    'ptr_no' => '',
    'description' => '',
    'batch_number' => '',
    'program' => '',
    'unit' => '',
    'expiration_date' => '',
    'quantity' => '',
    'recipient' => '',
];
$editingGroupItems = [];
$descriptionOptions = [];
$programOptions = [];
$unitOptions = [];
$batchNumberOptions = [];
$batchNumbersByDescription = [];
$batchMetaByDescription = [];
$unitCostByDescription = [];
$poNoByDescription = [];
$recipientOptions = [];
$hasProductBatchesTable = false;
$addingRefId = 0;
$showAddModal = false;
$addFormErrors = [];
$previewLineRows = 10;
$addFormData = [
    'record_date' => '',
    'ptr_no' => '',
    'description' => '',
    'batch_number' => '',
    'program' => '',
    'po_no' => '',
    'unit' => '',
    'expiration_date' => '',
    'quantity' => '',
    'unit_cost' => '',
    'recipient' => '',
];

function buildReportUrl(string $search, string $dateFrom, string $dateTo, string $sort, string $message = '', int $editId = 0, int $addRefId = 0): string
{
    $params = [];
    if ($search !== '') {
        $params['q'] = $search;
    }
    if ($dateFrom !== '') {
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $params['date_to'] = $dateTo;
    }
    if ($sort === 'asc') {
        $params['sort'] = 'asc';
    }
    if ($message !== '') {
        $params['msg'] = $message;
    }
    if ($editId > 0) {
        $params['edit'] = (string) $editId;
    }
    if ($addRefId > 0) {
        $params['add'] = (string) $addRefId;
    }
    return 'report.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $error = 'Date From must be a valid date.';
}
if ($error === '' && $dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $error = 'Date To must be a valid date.';
}
if ($error === '' && $dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $error = 'Date From cannot be later than Date To.';
}

try {
    $pdo = getConnection();
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

    $descriptionOptionsStmt = $pdo->query('
        SELECT DISTINCT TRIM(product_description) AS product_description
        FROM products
        WHERE product_description IS NOT NULL AND TRIM(product_description) <> ""
        ORDER BY product_description ASC
    ');
    $descriptionOptions = $descriptionOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

    $programOptionsStmt = $pdo->query('
        SELECT program_name
        FROM (
            SELECT DISTINCT TRIM(program) AS program_name
            FROM inventory_records
            WHERE program IS NOT NULL AND TRIM(program) <> ""
            UNION
            SELECT DISTINCT TRIM(program) AS program_name
            FROM products
            WHERE program IS NOT NULL AND TRIM(program) <> ""
        ) x
        ORDER BY program_name ASC
    ');
    $programOptions = $programOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

    $unitOptionsStmt = $pdo->query('
        SELECT unit_name
        FROM (
            SELECT DISTINCT TRIM(unit) AS unit_name
            FROM inventory_records
            WHERE unit IS NOT NULL AND TRIM(unit) <> ""
            UNION
            SELECT DISTINCT TRIM(uom) AS unit_name
            FROM products
            WHERE uom IS NOT NULL AND TRIM(uom) <> ""
        ) u
        ORDER BY unit_name ASC
    ');
    $unitOptions = $unitOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

    $batchSourceSelects = [
        'SELECT DISTINCT TRIM(batch_number) AS batch_no FROM inventory_records WHERE batch_number IS NOT NULL AND TRIM(batch_number) <> ""',
    ];
    $productBatchesTableStmt = $pdo->query("SHOW TABLES LIKE 'product_batches'");
    if ($productBatchesTableStmt && $productBatchesTableStmt->fetch()) {
        $hasProductBatchesTable = true;
        $batchSourceSelects[] = 'SELECT DISTINCT TRIM(batch_number) AS batch_no FROM product_batches WHERE batch_number IS NOT NULL AND TRIM(batch_number) <> ""';
    }
    $batchOptionsStmt = $pdo->query('
        SELECT batch_no
        FROM (' . implode(' UNION ', $batchSourceSelects) . ') b
        ORDER BY batch_no ASC
    ');
    $batchNumberOptions = $batchOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

    $batchByDescriptionQueries = [
        '
        SELECT TRIM(description) AS description_name, TRIM(batch_number) AS batch_no
        FROM inventory_records
        WHERE description IS NOT NULL AND TRIM(description) <> ""
          AND batch_number IS NOT NULL AND TRIM(batch_number) <> ""
        ',
    ];
    if ($hasProductBatchesTable) {
        $batchByDescriptionQueries[] = '
        SELECT TRIM(p.product_description) AS description_name, TRIM(b.batch_number) AS batch_no
        FROM product_batches b
        INNER JOIN products p ON p.id = b.product_id
        WHERE p.product_description IS NOT NULL AND TRIM(p.product_description) <> ""
          AND b.batch_number IS NOT NULL AND TRIM(b.batch_number) <> ""
        ';
    }
    $batchByDescriptionStmt = $pdo->query('
        SELECT description_name, batch_no
        FROM (' . implode(' UNION ', $batchByDescriptionQueries) . ') z
        ORDER BY description_name ASC, batch_no ASC
    ');
    $batchByDescriptionRows = $batchByDescriptionStmt->fetchAll();
    foreach ($batchByDescriptionRows as $row) {
        $descName = trim((string) ($row['description_name'] ?? ''));
        $batchNo = trim((string) ($row['batch_no'] ?? ''));
        if ($descName === '' || $batchNo === '') {
            continue;
        }
        if (!isset($batchNumbersByDescription[$descName])) {
            $batchNumbersByDescription[$descName] = [];
        }
        if (!in_array($batchNo, $batchNumbersByDescription[$descName], true)) {
            $batchNumbersByDescription[$descName][] = $batchNo;
        }
    }
    foreach ($batchNumbersByDescription as $descName => $batchList) {
        sort($batchList, SORT_NATURAL | SORT_FLAG_CASE);
        $batchNumbersByDescription[$descName] = $batchList;
    }

    if ($hasProductBatchesTable) {
        $batchMetaStmt = $pdo->query('
            SELECT
                TRIM(p.product_description) AS description_name,
                TRIM(b.batch_number) AS batch_no,
                b.id AS batch_id,
                b.stock_quantity AS stock_quantity,
                b.expiry_date AS expiry_date
            FROM product_batches b
            INNER JOIN products p ON p.id = b.product_id
            WHERE p.product_description IS NOT NULL AND TRIM(p.product_description) <> ""
              AND b.batch_number IS NOT NULL AND TRIM(b.batch_number) <> ""
            ORDER BY description_name ASC, batch_no ASC
        ');
        $batchMetaRows = $batchMetaStmt->fetchAll();
        foreach ($batchMetaRows as $batchMetaRow) {
            $descName = trim((string) ($batchMetaRow['description_name'] ?? ''));
            $batchNo = trim((string) ($batchMetaRow['batch_no'] ?? ''));
            if ($descName === '' || $batchNo === '') {
                continue;
            }
            if (!isset($batchMetaByDescription[$descName])) {
                $batchMetaByDescription[$descName] = [];
            }
            $batchMetaByDescription[$descName][$batchNo] = [
                'batch_id' => (int) ($batchMetaRow['batch_id'] ?? 0),
                'stock_quantity' => (int) ($batchMetaRow['stock_quantity'] ?? 0),
                'expiration_date' => isset($batchMetaRow['expiry_date']) && $batchMetaRow['expiry_date'] !== null
                    ? (string) $batchMetaRow['expiry_date']
                    : '',
            ];
        }
    }

    $unitCostRowsStmt = $pdo->query('
        SELECT description_name, unit_cost
        FROM (
            SELECT
                TRIM(product_description) AS description_name,
                cost_per_unit AS unit_cost,
                1 AS source_priority
            FROM products
            WHERE product_description IS NOT NULL AND TRIM(product_description) <> ""
            UNION ALL
            SELECT
                TRIM(description) AS description_name,
                unit_cost AS unit_cost,
                2 AS source_priority
            FROM inventory_records
            WHERE description IS NOT NULL AND TRIM(description) <> ""
        ) u
        ORDER BY description_name ASC, source_priority ASC
    ');
    $unitCostRows = $unitCostRowsStmt->fetchAll();
    foreach ($unitCostRows as $row) {
        $descName = trim((string) ($row['description_name'] ?? ''));
        if ($descName === '' || isset($unitCostByDescription[$descName])) {
            continue;
        }
        $unitCostByDescription[$descName] = number_format((float) ($row['unit_cost'] ?? 0), 2, '.', '');
    }

    $poNoStmt = $pdo->query('
        SELECT description_name, po_no
        FROM (
            SELECT
                TRIM(product_description) AS description_name,
                po_no,
                1 AS source_priority
            FROM products
            WHERE product_description IS NOT NULL AND TRIM(product_description) <> ""
              AND po_no IS NOT NULL AND TRIM(po_no) <> ""
            UNION ALL
            SELECT
                TRIM(description) AS description_name,
                po_no,
                2 AS source_priority
            FROM inventory_records
            WHERE description IS NOT NULL AND TRIM(description) <> ""
              AND po_no IS NOT NULL AND TRIM(po_no) <> ""
        ) p
        ORDER BY description_name ASC, source_priority ASC
    ');
    $poNoRows = $poNoStmt->fetchAll();
    foreach ($poNoRows as $row) {
        $descName = trim((string) ($row['description_name'] ?? ''));
        if ($descName === '' || isset($poNoByDescription[$descName])) {
            continue;
        }
        $poNoByDescription[$descName] = trim((string) ($row['po_no'] ?? ''));
    }

    $recipientSourceSelects = [
        'SELECT DISTINCT TRIM(recipient) AS recipient_name FROM inventory_records WHERE recipient IS NOT NULL AND TRIM(recipient) <> ""',
    ];
    $recipientsTableStmt = $pdo->query("SHOW TABLES LIKE 'recipients'");
    if ($recipientsTableStmt && $recipientsTableStmt->fetch()) {
        $recipientSourceSelects[] = 'SELECT DISTINCT TRIM(recipient_name) AS recipient_name FROM recipients WHERE recipient_name IS NOT NULL AND TRIM(recipient_name) <> ""';
    }
    $recipientOptionsStmt = $pdo->query('
        SELECT recipient_name
        FROM (' . implode(' UNION ', $recipientSourceSelects) . ') r
        ORDER BY recipient_name ASC
    ');
    $recipientOptions = $recipientOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if (in_array($action, ['delete', 'update', 'create_related'], true)) {
            $returnSearch = trim((string) ($_POST['return_q'] ?? ''));
            $returnDateFrom = trim((string) ($_POST['return_date_from'] ?? ''));
            $returnDateTo = trim((string) ($_POST['return_date_to'] ?? ''));
            $returnSort = strtolower(trim((string) ($_POST['return_sort'] ?? 'desc')));
            $returnSort = $returnSort === 'asc' ? 'asc' : 'desc';
            header('Location: ' . buildReportUrl($returnSearch, $returnDateFrom, $returnDateTo, $returnSort, 'Transaction history is locked. Use Pending PTR to edit drafts before release.'));
            exit;
        }
        if ($action === 'delete') {
            $deleteId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $returnSearch = trim((string) ($_POST['return_q'] ?? ''));
            $returnDateFrom = trim((string) ($_POST['return_date_from'] ?? ''));
            $returnDateTo = trim((string) ($_POST['return_date_to'] ?? ''));
            $returnSort = strtolower(trim((string) ($_POST['return_sort'] ?? 'desc')));
            $returnSort = $returnSort === 'asc' ? 'asc' : 'desc';

            if ($deleteId > 0) {
                $deleteStmt = $pdo->prepare('DELETE FROM inventory_records WHERE id = ?');
                $deleteStmt->execute([$deleteId]);
                header('Location: ' . buildReportUrl($returnSearch, $returnDateFrom, $returnDateTo, $returnSort, 'Transaction deleted.'));
                exit;
            }

            header('Location: ' . buildReportUrl($returnSearch, $returnDateFrom, $returnDateTo, $returnSort, 'Invalid transaction selected.'));
            exit;
        } elseif ($action === 'update') {
            $editingId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $returnSearch = trim((string) ($_POST['return_q'] ?? ''));
            $returnDateFrom = trim((string) ($_POST['return_date_from'] ?? ''));
            $returnDateTo = trim((string) ($_POST['return_date_to'] ?? ''));
            $returnSort = strtolower(trim((string) ($_POST['return_sort'] ?? 'desc')));
            $returnSort = $returnSort === 'asc' ? 'asc' : 'desc';

            $formData['recipient'] = trim((string) ($_POST['recipient'] ?? ''));

            $postedItemIds = $_POST['item_ids'] ?? [];
            $postedDescriptions = $_POST['description'] ?? [];
            $postedBatchNumbers = $_POST['batch_number'] ?? [];
            $postedPrograms = $_POST['program'] ?? [];
            $postedPoNumbers = $_POST['po_number'] ?? [];
            $postedUnits = $_POST['unit'] ?? [];
            $postedQuantities = $_POST['quantity'] ?? [];

            if ($editingId <= 0) {
                $formErrors[] = 'Invalid transaction selected for update.';
            }
            if (!is_array($postedItemIds) || empty($postedItemIds)) {
                $formErrors[] = 'No PTR items were submitted for update.';
            }

            $anchorRecord = null;
            if (empty($formErrors)) {
                $anchorStmt = $pdo->prepare('
                    SELECT id, ptr_no
                    FROM inventory_records
                    WHERE id = ? AND COALESCE(release_status, "released") = "released"
                    LIMIT 1
                ');
                $anchorStmt->execute([$editingId]);
                $anchorRecord = $anchorStmt->fetch();
                if (!$anchorRecord) {
                    $formErrors[] = 'Selected transaction no longer exists.';
                }
            }

            $allowedItemIds = [];
            $groupRowsById = [];
            if (empty($formErrors) && $anchorRecord) {
                $anchorPtrNo = trim((string) ($anchorRecord['ptr_no'] ?? ''));
                if ($anchorPtrNo === '') {
                    $formErrors[] = 'Cannot edit multiple rows because this transaction has no PTR No.';
                } else {
                    $groupRowsStmt = $pdo->prepare('
                        SELECT id, record_date, ptr_no, expiration_date, description, batch_number, program, po_no, quantity
                        FROM inventory_records
                        WHERE ptr_no = ? AND COALESCE(release_status, "released") = "released"
                        ORDER BY id ASC
                    ');
                    $groupRowsStmt->execute([$anchorPtrNo]);
                    $groupRows = $groupRowsStmt->fetchAll();
                    $allowedItemIds = array_map(
                        static function (array $row): int {
                            return (int) ($row['id'] ?? 0);
                        },
                        $groupRows
                    );
                    foreach ($groupRows as $groupRow) {
                        $rowId = (int) ($groupRow['id'] ?? 0);
                        if ($rowId > 0) {
                            $groupRowsById[$rowId] = $groupRow;
                        }
                    }
                    $formData['record_date'] = (string) ($groupRows[0]['record_date'] ?? '');
                    $formData['ptr_no'] = $anchorPtrNo;
                    if (empty($allowedItemIds)) {
                        $formErrors[] = 'No rows found for the selected PTR group.';
                    }
                }
            }

            $resolveBatchMeta = static function (array $batchMetaLookup, string $description, string $batchNumber): ?array {
                $description = trim($description);
                $batchNumber = trim($batchNumber);
                if ($description === '' || $batchNumber === '') {
                    return null;
                }
                if (isset($batchMetaLookup[$description][$batchNumber])) {
                    return $batchMetaLookup[$description][$batchNumber];
                }
                $descLower = strtolower($description);
                foreach ($batchMetaLookup as $descName => $batchRows) {
                    if (strtolower((string) $descName) !== $descLower || !is_array($batchRows)) {
                        continue;
                    }
                    foreach ($batchRows as $batchNo => $meta) {
                        if (strtolower((string) $batchNo) === strtolower($batchNumber)) {
                            return is_array($meta) ? $meta : null;
                        }
                    }
                }
                return null;
            };

            $stockAdjustmentPlan = [];
            $batchStockById = [];
            if ($hasProductBatchesTable) {
                foreach ($batchMetaByDescription as $descName => $batchRows) {
                    if (!is_array($batchRows)) {
                        continue;
                    }
                    foreach ($batchRows as $batchNo => $meta) {
                        if (!is_array($meta)) {
                            continue;
                        }
                        $batchId = (int) ($meta['batch_id'] ?? 0);
                        if ($batchId <= 0 || isset($batchStockById[$batchId])) {
                            continue;
                        }
                        $batchStockById[$batchId] = (int) ($meta['stock_quantity'] ?? 0);
                    }
                }
            }

            if (empty($formErrors)) {
                foreach ($postedItemIds as $rawItemId) {
                    $itemId = (int) $rawItemId;
                    if ($itemId <= 0 || !in_array($itemId, $allowedItemIds, true)) {
                        $formErrors[] = 'One or more rows are invalid for this PTR group.';
                        continue;
                    }

                    $descriptionValue = trim((string) ($postedDescriptions[$itemId] ?? ''));
                    $quantityValue = trim((string) ($postedQuantities[$itemId] ?? ''));
                    if ($descriptionValue === '') {
                        $formErrors[] = 'Description is required for all PTR rows.';
                    }
                    if ($quantityValue === '' || !ctype_digit($quantityValue) || (int) $quantityValue <= 0) {
                        $formErrors[] = 'Quantity must be a positive whole number for all PTR rows.';
                    }
                    $immutableExpirationDate = (string) (($groupRowsById[$itemId]['expiration_date'] ?? '') ?: '');
                    $batchNumberValue = trim((string) ($postedBatchNumbers[$itemId] ?? ''));
                    $programValue = trim((string) ($postedPrograms[$itemId] ?? ''));
                    $poNoValue = trim((string) ($postedPoNumbers[$itemId] ?? ''));
                    $unitValue = trim((string) ($postedUnits[$itemId] ?? ''));
                    $currentRow = $groupRowsById[$itemId] ?? null;
                    $originalDescription = trim((string) ($currentRow['description'] ?? ''));
                    $originalBatchNumber = trim((string) ($currentRow['batch_number'] ?? ''));
                    $originalQuantity = (int) ($currentRow['quantity'] ?? 0);

                    if ($hasProductBatchesTable) {
                        if ($batchNumberValue === '') {
                            $formErrors[] = 'Batch number is required when stock tracking is enabled.';
                        }
                        $newBatchMeta = $resolveBatchMeta($batchMetaByDescription, $descriptionValue, $batchNumberValue);
                        $oldBatchMeta = $resolveBatchMeta($batchMetaByDescription, $originalDescription, $originalBatchNumber);
                        $newBatchId = (int) ($newBatchMeta['batch_id'] ?? 0);
                        $oldBatchId = (int) ($oldBatchMeta['batch_id'] ?? 0);

                        if ($newBatchId <= 0) {
                            $formErrors[] = 'Selected batch does not exist for one or more edited rows.';
                        } else {
                            if (!isset($stockAdjustmentPlan[$newBatchId])) {
                                $stockAdjustmentPlan[$newBatchId] = 0;
                            }
                            $stockAdjustmentPlan[$newBatchId] += (int) $quantityValue;
                        }
                        if ($oldBatchId > 0) {
                            if (!isset($stockAdjustmentPlan[$oldBatchId])) {
                                $stockAdjustmentPlan[$oldBatchId] = 0;
                            }
                            $stockAdjustmentPlan[$oldBatchId] -= $originalQuantity;
                        }
                    }

                    $editingGroupItems[] = [
                        'id' => $itemId,
                        'description' => $descriptionValue,
                        'batch_number' => $batchNumberValue,
                        'program' => $programValue,
                        'po_no' => $poNoValue,
                        'unit' => $unitValue,
                        'expiration_date' => $immutableExpirationDate,
                        'quantity' => $quantityValue,
                        'original_description' => $originalDescription,
                        'original_batch_number' => $originalBatchNumber,
                        'original_quantity' => $originalQuantity,
                    ];
                }
            }

            if (empty($formErrors) && $hasProductBatchesTable) {
                foreach ($stockAdjustmentPlan as $batchId => $qtyDelta) {
                    $batchId = (int) $batchId;
                    $qtyDelta = (int) $qtyDelta;
                    if ($batchId <= 0 || $qtyDelta <= 0) {
                        continue;
                    }
                    $availableStock = (int) ($batchStockById[$batchId] ?? 0);
                    if ($qtyDelta > $availableStock) {
                        $formErrors[] = 'Insufficient remaining stock for one or more edited items.';
                        break;
                    }
                }
            }

            if (empty($formErrors)) {
                $updateStmt = $pdo->prepare('
                    UPDATE inventory_records
                    SET
                        description = ?,
                        batch_number = ?,
                        program = ?,
                        po_no = ?,
                        unit = ?,
                        quantity = ?,
                        recipient = ?
                    WHERE id = ?
                ');

                $pdo->beginTransaction();
                try {
                    if ($hasProductBatchesTable && !empty($stockAdjustmentPlan)) {
                        $stockDeductStmt = $pdo->prepare('
                            UPDATE product_batches
                            SET stock_quantity = stock_quantity - ?
                            WHERE id = ? AND stock_quantity >= ?
                        ');
                        $stockAddStmt = $pdo->prepare('
                            UPDATE product_batches
                            SET stock_quantity = stock_quantity + ?
                            WHERE id = ?
                        ');
                        foreach ($stockAdjustmentPlan as $batchId => $qtyDelta) {
                            $batchId = (int) $batchId;
                            $qtyDelta = (int) $qtyDelta;
                            if ($batchId <= 0 || $qtyDelta === 0) {
                                continue;
                            }
                            if ($qtyDelta > 0) {
                                $stockDeductStmt->execute([$qtyDelta, $batchId, $qtyDelta]);
                                if ($stockDeductStmt->rowCount() !== 1) {
                                    throw new RuntimeException('Insufficient stock while saving PTR edits.');
                                }
                            } else {
                                $stockAddStmt->execute([abs($qtyDelta), $batchId]);
                                if ($stockAddStmt->rowCount() !== 1) {
                                    throw new RuntimeException('Unable to restore stock while saving PTR edits.');
                                }
                            }
                        }
                    }

                    foreach ($editingGroupItems as $item) {
                        $updateStmt->execute([
                            $item['description'],
                            $item['batch_number'] !== '' ? $item['batch_number'] : null,
                            $item['program'] !== '' ? $item['program'] : null,
                            $item['po_no'] !== '' ? $item['po_no'] : null,
                            $item['unit'] !== '' ? $item['unit'] : null,
                            (int) $item['quantity'],
                            $formData['recipient'] !== '' ? $formData['recipient'] : null,
                            $item['id'],
                        ]);
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $formErrors[] = 'Unable to save PTR updates. Please try again.';
                }
            }

            if (empty($formErrors)) {
                header('Location: ' . buildReportUrl($returnSearch, $returnDateFrom, $returnDateTo, $returnSort, 'PTR group updated.'));
                exit;
            }

            $isEditMode = $editingId > 0;
            $showEditModal = true;
            $search = $returnSearch;
            $dateFrom = $returnDateFrom;
            $dateTo = $returnDateTo;
            $sort = $returnSort;
            $orderByDirection = $sort === 'asc' ? 'ASC' : 'DESC';
        } elseif ($action === 'create_related') {
            $addingRefId = isset($_POST['ref_id']) ? (int) $_POST['ref_id'] : 0;
            $returnSearch = trim((string) ($_POST['return_q'] ?? ''));
            $returnDateFrom = trim((string) ($_POST['return_date_from'] ?? ''));
            $returnDateTo = trim((string) ($_POST['return_date_to'] ?? ''));
            $returnSort = strtolower(trim((string) ($_POST['return_sort'] ?? 'desc')));
            $returnSort = $returnSort === 'asc' ? 'asc' : 'desc';

            $addFormData['record_date'] = trim((string) ($_POST['record_date'] ?? ''));
            $addFormData['ptr_no'] = trim((string) ($_POST['ptr_no'] ?? ''));
            $addFormData['description'] = trim((string) ($_POST['description'] ?? ''));
            $addFormData['batch_number'] = trim((string) ($_POST['batch_number'] ?? ''));
            $addFormData['program'] = trim((string) ($_POST['program'] ?? ''));
            $addFormData['po_no'] = trim((string) ($_POST['po_number'] ?? ''));
            $addFormData['unit'] = trim((string) ($_POST['unit'] ?? ''));
            $addFormData['expiration_date'] = trim((string) ($_POST['expiration_date'] ?? ''));
            $addFormData['quantity'] = trim((string) ($_POST['quantity'] ?? ''));
            $addFormData['unit_cost'] = trim((string) ($_POST['unit_cost'] ?? ''));
            $addFormData['recipient'] = trim((string) ($_POST['recipient'] ?? ''));

            if ($addFormData['record_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $addFormData['record_date'])) {
                $addFormErrors[] = 'Record date is required and must be valid.';
            }
            if ($addFormData['description'] === '') {
                $addFormErrors[] = 'Description is required.';
            }
            if ($addFormData['quantity'] === '' || !ctype_digit($addFormData['quantity']) || (int) $addFormData['quantity'] <= 0) {
                $addFormErrors[] = 'Quantity must be a positive whole number.';
            }
            if ($addFormData['unit_cost'] !== '' && (!is_numeric($addFormData['unit_cost']) || (float) $addFormData['unit_cost'] < 0)) {
                $addFormErrors[] = 'Unit cost must be a valid non-negative number.';
            }
            if ($addFormData['expiration_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $addFormData['expiration_date'])) {
                $addFormErrors[] = 'Expiration date must be a valid date.';
            }

            $resolveAddBatchMeta = static function (array $batchMetaLookup, string $description, string $batchNumber): ?array {
                $description = trim($description);
                $batchNumber = trim($batchNumber);
                if ($description === '' || $batchNumber === '') {
                    return null;
                }
                if (isset($batchMetaLookup[$description][$batchNumber])) {
                    return $batchMetaLookup[$description][$batchNumber];
                }
                $descLower = strtolower($description);
                foreach ($batchMetaLookup as $descName => $batchRows) {
                    if (strtolower((string) $descName) !== $descLower || !is_array($batchRows)) {
                        continue;
                    }
                    foreach ($batchRows as $batchNo => $meta) {
                        if (strtolower((string) $batchNo) === strtolower($batchNumber)) {
                            return is_array($meta) ? $meta : null;
                        }
                    }
                }
                return null;
            };

            $addBatchId = 0;
            if (empty($addFormErrors) && $hasProductBatchesTable) {
                if ($addFormData['batch_number'] === '') {
                    $addFormErrors[] = 'Batch number is required when stock tracking is enabled.';
                } else {
                    $selectedBatchMeta = $resolveAddBatchMeta(
                        $batchMetaByDescription,
                        $addFormData['description'],
                        $addFormData['batch_number']
                    );
                    $addBatchId = (int) ($selectedBatchMeta['batch_id'] ?? 0);
                    $availableStock = (int) ($selectedBatchMeta['stock_quantity'] ?? 0);
                    $requestedQty = (int) $addFormData['quantity'];
                    if ($addBatchId <= 0) {
                        $addFormErrors[] = 'Selected batch does not exist for the selected item.';
                    } elseif ($requestedQty > $availableStock) {
                        $addFormErrors[] = 'Insufficient remaining stock. Available: ' . $availableStock . ', requested: ' . $requestedQty . '.';
                    }
                }
            }

            if (empty($addFormErrors)) {
                $insertStmt = $pdo->prepare('
                    INSERT INTO inventory_records
                        (record_date, ptr_no, description, batch_number, program, po_no, unit, expiration_date, quantity, unit_cost, recipient)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $pdo->beginTransaction();
                try {
                    if ($hasProductBatchesTable && $addBatchId > 0) {
                        $stockUpdateStmt = $pdo->prepare('
                            UPDATE product_batches
                            SET stock_quantity = stock_quantity - ?
                            WHERE id = ? AND stock_quantity >= ?
                        ');
                        $deductQty = (int) $addFormData['quantity'];
                        $stockUpdateStmt->execute([$deductQty, $addBatchId, $deductQty]);
                        if ($stockUpdateStmt->rowCount() !== 1) {
                            throw new RuntimeException('Insufficient stock while adding transaction.');
                        }
                    }

                    $insertStmt->execute([
                        $addFormData['record_date'],
                        $addFormData['ptr_no'] !== '' ? $addFormData['ptr_no'] : null,
                        $addFormData['description'],
                        $addFormData['batch_number'] !== '' ? $addFormData['batch_number'] : null,
                        $addFormData['program'] !== '' ? $addFormData['program'] : null,
                        $addFormData['po_no'] !== '' ? $addFormData['po_no'] : null,
                        $addFormData['unit'] !== '' ? $addFormData['unit'] : null,
                        $addFormData['expiration_date'] !== '' ? $addFormData['expiration_date'] : null,
                        (int) $addFormData['quantity'],
                        $addFormData['unit_cost'] !== '' ? (float) $addFormData['unit_cost'] : 0.00,
                        $addFormData['recipient'] !== '' ? $addFormData['recipient'] : null,
                    ]);

                    $pdo->commit();
                    header('Location: ' . buildReportUrl($returnSearch, $returnDateFrom, $returnDateTo, $returnSort, 'Transaction added.'));
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $addFormErrors[] = $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Unable to add transaction item right now.';
                }
            }

            $showAddModal = true;
            $search = $returnSearch;
            $dateFrom = $returnDateFrom;
            $dateTo = $returnDateTo;
            $sort = $returnSort;
            $orderByDirection = $sort === 'asc' ? 'ASC' : 'DESC';
        }
    }

    if (false && !$isEditMode && isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
        $editingId = (int) $_GET['edit'];
        if ($editingId > 0) {
            $editStmt = $pdo->prepare('
                SELECT id, record_date, ptr_no, description, batch_number, program, po_no, unit, expiration_date, quantity, recipient
                FROM inventory_records
                WHERE id = ? AND COALESCE(release_status, "released") = "released"
                LIMIT 1
            ');
            $editStmt->execute([$editingId]);
            $editingRecord = $editStmt->fetch();
            if ($editingRecord) {
                $editPtrNo = trim((string) ($editingRecord['ptr_no'] ?? ''));
                $isEditMode = true;
                $showEditModal = true;
                $formData = [
                    'record_date' => (string) ($editingRecord['record_date'] ?? ''),
                    'ptr_no' => (string) ($editingRecord['ptr_no'] ?? ''),
                    'recipient' => (string) ($editingRecord['recipient'] ?? ''),
                ];
                if ($editPtrNo !== '') {
                    $editGroupStmt = $pdo->prepare('
                        SELECT id, record_date, ptr_no, description, batch_number, program, po_no, unit, expiration_date, quantity, recipient
                        FROM inventory_records
                        WHERE ptr_no = ? AND COALESCE(release_status, "released") = "released"
                        ORDER BY id ASC
                    ');
                    $editGroupStmt->execute([$editPtrNo]);
                    $editingGroupItems = array_map(
                        static function (array $row): array {
                            $row['original_description'] = (string) ($row['description'] ?? '');
                            $row['original_batch_number'] = (string) ($row['batch_number'] ?? '');
                            $row['original_quantity'] = (string) ($row['quantity'] ?? '0');
                            return $row;
                        },
                        $editGroupStmt->fetchAll()
                    );
                } else {
                    $editingRecord['original_description'] = (string) ($editingRecord['description'] ?? '');
                    $editingRecord['original_batch_number'] = (string) ($editingRecord['batch_number'] ?? '');
                    $editingRecord['original_quantity'] = (string) ($editingRecord['quantity'] ?? '0');
                    $editingGroupItems = [$editingRecord];
                }
            }
        }
    }

    if (false && !$showAddModal && isset($_GET['add']) && ctype_digit($_GET['add'])) {
        $addingRefId = (int) $_GET['add'];
        if ($addingRefId > 0) {
            $addRefStmt = $pdo->prepare('
                SELECT record_date, ptr_no, description, batch_number, program, po_no, unit, expiration_date, quantity, unit_cost, recipient
                FROM inventory_records
                WHERE id = ? AND COALESCE(release_status, "released") = "released"
                LIMIT 1
            ');
            $addRefStmt->execute([$addingRefId]);
            $refRecord = $addRefStmt->fetch();
            if ($refRecord) {
                $showAddModal = true;
                $addFormData = [
                    'record_date' => (string) ($refRecord['record_date'] ?? ''),
                    'ptr_no' => (string) ($refRecord['ptr_no'] ?? ''),
                    'description' => '',
                    'batch_number' => '',
                    'program' => (string) ($refRecord['program'] ?? ''),
                    'po_no' => (string) ($refRecord['po_no'] ?? ''),
                    'unit' => (string) ($refRecord['unit'] ?? ''),
                    'expiration_date' => (string) ($refRecord['expiration_date'] ?? ''),
                    'quantity' => (string) ($refRecord['quantity'] ?? ''),
                    'unit_cost' => (string) ($refRecord['unit_cost'] ?? ''),
                    'recipient' => (string) ($refRecord['recipient'] ?? ''),
                ];
            }
        }
    }

    if ($error === '') {
        $where = ['COALESCE(release_status, "released") = "released"'];
        $params = [];

        if ($search !== '') {
            $where[] = '(ptr_no LIKE :q OR recipient LIKE :q OR description LIKE :q OR batch_number LIKE :q OR program LIKE :q OR po_no LIKE :q OR unit LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        if ($dateFrom !== '') {
            $where[] = 'record_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'record_date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql = '
            SELECT id, expiration_date, unit, description, batch_number, quantity, unit_cost, program, po_no, recipient, ptr_no, record_date
            FROM inventory_records
        ';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY record_date ' . $orderByDirection . ', id ' . $orderByDirection;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        foreach ($records as $record) {
            $grandTotal += (float) ($record['quantity'] ?? 0) * (float) ($record['unit_cost'] ?? 0);
            $ptrNo = trim((string) ($record['ptr_no'] ?? ''));
            $groupKey = $ptrNo !== '' ? $ptrNo : '__NO_PTR__';
            if (!isset($groupedRecords[$groupKey])) {
                $groupedRecords[$groupKey] = [
                    'ptr_no' => $ptrNo !== '' ? $ptrNo : '-',
                    'record_date' => (string) ($record['record_date'] ?? '-'),
                    'recipient' => (string) ($record['recipient'] ?? '-'),
                    'items' => [],
                ];
            }
            $groupedRecords[$groupKey]['items'][] = $record;
        }
    }
} catch (PDOException $e) {
    $error = 'Unable to load transaction report right now. Please check your database setup.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Report - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260305">
</head>
<body class="report-page">
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
                    <small class="fw-normal">Transaction History</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Home</a>
                <a href="current_stock_report.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Stock Report</a>
                <a href="incident_report.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Incident Report</a>
                <a href="create_ptr.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Create PTR</a>
                <a href="pending_transactions.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Pending</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Log out</a>
            </div>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <div class="card app-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h1 class="h5 mb-0">Transaction History</h1>
                    </div>

                    <form method="get" action="report.php" class="row g-2 mb-3">
                        <div class="col-lg-4 col-md-12">
                            <input
                                type="text"
                                name="q"
                                class="form-control"
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search PTR no., recipient, item, or program"
                            >
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <input
                                type="date"
                                name="date_from"
                                class="form-control"
                                value="<?= htmlspecialchars($dateFrom) ?>"
                                aria-label="Date from"
                            >
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <input
                                type="date"
                                name="date_to"
                                class="form-control"
                                value="<?= htmlspecialchars($dateTo) ?>"
                                aria-label="Date to"
                            >
                        </div>
                        <div class="col-lg-2 col-md-2">
                            <select name="sort" class="form-select" aria-label="Sort order">
                                <option value="desc" <?= $sort === 'desc' ? 'selected' : '' ?>>Newest</option>
                                <option value="asc" <?= $sort === 'asc' ? 'selected' : '' ?>>Oldest</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </form>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 mb-0"><?= htmlspecialchars($error) ?></div>
                    <?php else: ?>
                        <?php if ($message !== ''): ?>
                            <div class="alert alert-success py-2 mb-2"><?= htmlspecialchars($message) ?></div>
                        <?php endif; ?>
                        <div class="report-summary-grid mb-3">
                            <div class="report-summary-box">
                                <div class="report-summary-label">Total Transactions</div>
                                <div class="report-summary-value"><?= number_format(count($groupedRecords)) ?></div>
                            </div>
                            <div class="report-summary-box">
                                <div class="report-summary-label">Grand Total Amount</div>
                                <div class="report-summary-value">PHP <?= number_format($grandTotal, 2) ?></div>
                            </div>
                        </div>
                        <?php if (empty($records)): ?>
                            <div class="inventory-empty-state">
                                <div class="inventory-empty-state-icon">📄</div>
                                <div><strong>No transaction history found.</strong></div>
                            </div>
                        <?php else: ?>
                            <?php $groupIndex = 0; ?>
                            <?php foreach ($groupedRecords as $group): ?>
                                <?php $groupSectionId = 'ptrGroupPrint_' . $groupIndex; ?>
                                <?php $groupPreviewId = 'ptrGroupPreview_' . $groupIndex; ?>
                                <?php $groupIndex++; ?>
                                <?php
                                    $groupTotal = 0.0;
                                    foreach ($group['items'] as $groupItem) {
                                        $groupTotal += (float) ($groupItem['quantity'] ?? 0) * (float) ($groupItem['unit_cost'] ?? 0);
                                    }
                                ?>
                                <section class="report-group-block" id="<?= htmlspecialchars($groupSectionId) ?>">
                                    <div class="report-group-head">
                                        <div class="report-group-head-meta">
                                            <span class="report-group-chip"><strong>PTR No.:</strong> <?= htmlspecialchars($group['ptr_no']) ?></span>
                                            <span class="report-group-chip"><strong>Date:</strong> <?= htmlspecialchars($group['record_date']) ?></span>
                                            <span class="report-group-chip report-group-chip-recipient"><strong>Recipient:</strong> <?= htmlspecialchars($group['recipient']) ?></span>
                                        </div>
                                        <div class="report-group-head-actions">
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary btn-sm report-print-btn"
                                                data-print-target="<?= htmlspecialchars($groupPreviewId) ?>"
                                            >
                                                Print
                                            </button>
                                        </div>
                                    </div>
                                    <div class="inventory-table-container">
                                        <div class="inventory-table-wrapper">
                                            <table class="table inventory-table">
                                            <colgroup>
                                                <col class="report-col-description">
                                                <col class="report-col-batch">
                                                <col class="report-col-program">
                                                <col class="report-col-po">
                                                <col class="report-col-unit">
                                                <col class="report-col-expiry">
                                                <col class="report-col-qty">
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th class="report-col-description">Description</th>
                                                    <th class="report-col-batch text-center">Batch No.</th>
                                                    <th class="report-col-program">Program</th>
                                                    <th class="report-col-po text-center">PO No.</th>
                                                    <th class="report-col-unit text-center">Unit</th>
                                                    <th class="report-col-expiry text-center">Exp. Date</th>
                                                    <th class="report-col-qty text-end">Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($group['items'] as $record): ?>
                                                    <tr>
                                                        <td class="report-col-description" title="<?= htmlspecialchars((string) ($record['description'] ?? '-')) ?>">
                                                            <?= htmlspecialchars($record['description'] ?? '-') ?>
                                                        </td>
                                                        <td class="report-col-batch text-center"><?= htmlspecialchars($record['batch_number'] ?? '-') ?></td>
                                                        <td class="report-col-program"><?= htmlspecialchars($record['program'] ?? '-') ?></td>
                                                        <td class="report-col-po text-center"><?= htmlspecialchars($record['po_no'] ?? '-') ?></td>
                                                        <td class="report-col-unit text-center"><?= htmlspecialchars($record['unit'] ?? '-') ?></td>
                                                        <td class="report-col-expiry text-center"><?= htmlspecialchars($record['expiration_date'] ?? '-') ?></td>
                                                        <td class="report-col-qty text-end"><?= (int) ($record['quantity'] ?? 0) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                    <div id="<?= htmlspecialchars($groupPreviewId) ?>" class="preview-sheet d-none">
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
                                                <td><span class="preview-label">Date:</span> <?= htmlspecialchars((string) ($group['record_date'] ?? '-')) ?></td>
                                                <td><span class="preview-label">PTR No.:</span> <?= htmlspecialchars((string) ($group['ptr_no'] ?? '-')) ?></td>
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
                                            <tbody>
                                                <?php $renderedRows = 0; ?>
                                                <?php foreach ($group['items'] as $record): ?>
                                                    <?php if ($renderedRows >= $previewLineRows) { break; } ?>
                                                    <?php
                                                        $descriptionValue = trim((string) ($record['description'] ?? ''));
                                                        $batchValue = trim((string) ($record['batch_number'] ?? ''));
                                                        $descriptionWithBatch = $batchValue !== '' ? $descriptionValue . ' / ' . $batchValue : $descriptionValue;
                                                        $quantityValue = (float) ($record['quantity'] ?? 0);
                                                        $unitCostValue = (float) ($record['unit_cost'] ?? 0);
                                                        $amountValue = $quantityValue * $unitCostValue;
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars((string) ($record['expiration_date'] ?? '-')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($record['unit'] ?? '-')) ?></td>
                                                        <td><?= htmlspecialchars($descriptionWithBatch !== '' ? $descriptionWithBatch : '-') ?></td>
                                                        <td><?= htmlspecialchars((string) ($record['quantity'] ?? '-')) ?></td>
                                                        <td><?= htmlspecialchars(number_format($unitCostValue, 2, '.', '')) ?></td>
                                                        <td><?= htmlspecialchars(number_format($amountValue, 2, '.', '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($record['program'] ?? '-')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($record['po_no'] ?? '-')) ?></td>
                                                    </tr>
                                                    <?php $renderedRows++; ?>
                                                <?php endforeach; ?>
                                                <?php for ($i = $renderedRows; $i < $previewLineRows; $i++): ?>
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
                                                    <td><?= htmlspecialchars(number_format($groupTotal, 2, '.', '')) ?></td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                        <table>
                                            <tr>
                                                <td colspan="4"><span class="preview-label">Purpose:</span><br><em>(For the use of)</em> <?= htmlspecialchars((string) ($group['recipient'] ?? '-')) ?></td>
                                            </tr>
                                        </table>
                                        <table class="signatory-table">
                                            <tr>
                                                <td class="preview-signatory-half">
                                                    <div class="signatory-content">
                                                        <span class="preview-label signatory-label">Prepared by:</span>
                                                        <textarea class="ptr-signatory-name" rows="3" placeholder="Mark Anthony Borres, John Paul Joseph Opiala, Richard Roy"></textarea>
                                                    </div>
                                                </td>
                                                <td class="preview-signatory-half">
                                                    <div class="signatory-content">
                                                        <span class="preview-label signatory-label">Approved by:</span>
                                                        <textarea class="ptr-signatory-name" rows="3" placeholder="Elizabeth C. Calaor, RPh&#10;(Pharmacist II/ Head, Supply &amp; Logistics Unit)"></textarea>
                                                        <span class="preview-approved-date"><?= htmlspecialchars(date('m/d/Y')) ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="preview-signatory-half">
                                                    <div class="signatory-content">
                                                        <span class="preview-label signatory-label">Issued by:</span>
                                                        <textarea class="ptr-signatory-name" rows="2" placeholder="Jannete Ventura, Earnest John Tolentino, RPh"></textarea>
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
                                </section>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="editTransactionModalLabel">Edit PTR Group</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($formErrors)): ?>
                        <?php foreach ($formErrors as $formError): ?>
                            <div class="alert alert-danger py-2 mb-2"><?= htmlspecialchars($formError) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <form method="post" action="report.php">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= (int) $editingId ?>">
                        <input type="hidden" name="return_q" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="return_date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                        <input type="hidden" name="return_date_to" value="<?= htmlspecialchars($dateTo) ?>">
                        <input type="hidden" name="return_sort" value="<?= htmlspecialchars($sort) ?>">

                        <div class="row g-3 mb-3 report-edit-meta">
                            <div class="col-md-4">
                                <label for="edit_record_date" class="form-label">Date</label>
                                <input type="date" id="edit_record_date" name="record_date" class="form-control" value="<?= htmlspecialchars($formData['record_date']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_ptr_no" class="form-label">PTR No.</label>
                                <input type="text" id="edit_ptr_no" name="ptr_no" class="form-control" value="<?= htmlspecialchars($formData['ptr_no']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_recipient" class="form-label">Recipient</label>
                                <input
                                    type="text"
                                    id="edit_recipient"
                                    name="recipient"
                                    class="form-control"
                                    list="reportRecipientOptionsList"
                                    value="<?= htmlspecialchars($formData['recipient']) ?>"
                                    placeholder="Type or select recipient"
                                >
                            </div>
                        </div>
                        <div class="table-responsive report-edit-table-wrap">
                            <table class="table table-sm table-striped align-middle mb-0 report-edit-table">
                                <colgroup>
                                    <col class="report-edit-col-description">
                                    <col class="report-edit-col-batch">
                                    <col class="report-edit-col-program">
                                    <col class="report-edit-col-po">
                                    <col class="report-edit-col-unit">
                                    <col class="report-edit-col-exp">
                                    <col class="report-edit-col-qty">
                                </colgroup>
                                <thead class="table-light">
                                    <tr>
                                        <th class="report-edit-col-description">Description</th>
                                        <th class="report-edit-col-batch text-center">Batch Number</th>
                                        <th class="report-edit-col-program">Program</th>
                                        <th class="report-edit-col-po text-center">PO Number</th>
                                        <th class="report-edit-col-unit text-center">Unit</th>
                                        <th class="report-edit-col-exp text-center">Expiration Date</th>
                                        <th class="report-edit-col-qty text-center">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($editingGroupItems as $item): ?>
                                        <?php $itemId = (int) ($item['id'] ?? 0); ?>
                                        <?php if ($itemId <= 0) { continue; } ?>
                                        <?php $itemBatchListId = 'reportEditBatchOptionsList_' . $itemId; ?>
                                        <?php $rowBatches = $batchNumbersByDescription[(string) ($item['description'] ?? '')] ?? []; ?>
                                        <tr
                                            class="edit-group-row"
                                            data-item-id="<?= $itemId ?>"
                                            data-original-description="<?= htmlspecialchars((string) ($item['original_description'] ?? $item['description'] ?? '')) ?>"
                                            data-original-batch-number="<?= htmlspecialchars((string) ($item['original_batch_number'] ?? $item['batch_number'] ?? '')) ?>"
                                            data-original-quantity="<?= (int) ($item['original_quantity'] ?? $item['quantity'] ?? 0) ?>"
                                        >
                                            <td>
                                                <input type="hidden" name="item_ids[]" value="<?= $itemId ?>">
                                                <input
                                                    type="text"
                                                    name="description[<?= $itemId ?>]"
                                                    class="form-control form-control-sm edit-group-description report-edit-input"
                                                    list="reportDescriptionOptionsList"
                                                    value="<?= htmlspecialchars((string) ($item['description'] ?? '')) ?>"
                                                    required
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="batch_number[<?= $itemId ?>]"
                                                    class="form-control form-control-sm edit-group-batch report-edit-input text-center"
                                                    list="<?= htmlspecialchars($itemBatchListId) ?>"
                                                    value="<?= htmlspecialchars((string) ($item['batch_number'] ?? '')) ?>"
                                                >
                                                <datalist id="<?= htmlspecialchars($itemBatchListId) ?>">
                                                    <?php foreach ($rowBatches as $batchNumberOption): ?>
                                                        <option value="<?= htmlspecialchars((string) $batchNumberOption) ?>"></option>
                                                    <?php endforeach; ?>
                                                </datalist>
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="program[<?= $itemId ?>]"
                                                    class="form-control form-control-sm report-edit-input"
                                                    list="reportProgramOptionsList"
                                                    value="<?= htmlspecialchars((string) ($item['program'] ?? '')) ?>"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="po_number[<?= $itemId ?>]"
                                                    class="form-control form-control-sm edit-group-po-number report-edit-input text-center"
                                                    value="<?= htmlspecialchars((string) ($item['po_no'] ?? '')) ?>"
                                                    placeholder="PO No."
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="unit[<?= $itemId ?>]"
                                                    class="form-control form-control-sm report-edit-input text-center"
                                                    list="reportUnitOptionsList"
                                                    value="<?= htmlspecialchars((string) ($item['unit'] ?? '')) ?>"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="date"
                                                    name="expiration_date[<?= $itemId ?>]"
                                                    class="form-control form-control-sm report-edit-input text-center"
                                                    value="<?= htmlspecialchars((string) ($item['expiration_date'] ?? '')) ?>"
                                                    readonly
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    type="text"
                                                    name="quantity[<?= $itemId ?>]"
                                                    class="form-control form-control-sm text-center edit-group-quantity report-edit-input"
                                                    value="<?= htmlspecialchars((string) ($item['quantity'] ?? '')) ?>"
                                                    inputmode="numeric"
                                                    pattern="[0-9]*"
                                                    required
                                                >
                                                <div class="form-text edit-group-stock-hint"></div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="<?= htmlspecialchars(buildReportUrl($search, $dateFrom, $dateTo, $sort)) ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="addTransactionModalLabel">Add Transaction Item</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($addFormErrors)): ?>
                        <?php foreach ($addFormErrors as $addFormError): ?>
                            <div class="alert alert-danger py-2 mb-2"><?= htmlspecialchars($addFormError) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <form method="post" action="report.php">
                        <input type="hidden" name="action" value="create_related">
                        <input type="hidden" name="ref_id" value="<?= (int) $addingRefId ?>">
                        <input type="hidden" name="return_q" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="return_date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                        <input type="hidden" name="return_date_to" value="<?= htmlspecialchars($dateTo) ?>">
                        <input type="hidden" name="return_sort" value="<?= htmlspecialchars($sort) ?>">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="add_record_date" class="form-label">Date</label>
                                <input type="date" id="add_record_date" name="record_date" class="form-control" value="<?= htmlspecialchars($addFormData['record_date']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="add_ptr_no" class="form-label">PTR No.</label>
                                <input type="text" id="add_ptr_no" name="ptr_no" class="form-control" value="<?= htmlspecialchars($addFormData['ptr_no']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="add_quantity" class="form-label">Summary of Quantity</label>
                                <input type="text" id="add_quantity" name="quantity" class="form-control" value="<?= htmlspecialchars($addFormData['quantity']) ?>" inputmode="numeric" pattern="[0-9]*" required>
                                <div class="form-text" id="add_stock_hint"></div>
                            </div>
                            <div class="col-md-8">
                                <label for="add_description" class="form-label">Description</label>
                                <input
                                    type="text"
                                    id="add_description"
                                    name="description"
                                    class="form-control"
                                    list="reportDescriptionOptionsList"
                                    value="<?= htmlspecialchars($addFormData['description']) ?>"
                                    placeholder="Type or select item description"
                                    required
                                >
                            </div>
                            <div class="col-md-4">
                                <label for="add_batch_number" class="form-label">Batch Number</label>
                                <input
                                    type="text"
                                    id="add_batch_number"
                                    name="batch_number"
                                    class="form-control"
                                    list="reportAddBatchOptionsList"
                                    value="<?= htmlspecialchars($addFormData['batch_number']) ?>"
                                    placeholder="Type or select batch number"
                                >
                            </div>
                            <div class="col-md-4">
                                <label for="add_program" class="form-label">Program</label>
                                <input
                                    type="text"
                                    id="add_program"
                                    name="program"
                                    class="form-control"
                                    list="reportProgramOptionsList"
                                    value="<?= htmlspecialchars($addFormData['program']) ?>"
                                    placeholder="Type or select program"
                                >
                            </div>
                            <div class="col-md-4">
                                <label for="add_po_number" class="form-label">PO Number</label>
                                <input
                                    type="text"
                                    id="add_po_number"
                                    name="po_number"
                                    class="form-control"
                                    value="<?= htmlspecialchars($addFormData['po_no']) ?>"
                                    placeholder="PO Number"
                                >
                            </div>
                            <div class="col-md-4">
                                <label for="add_unit" class="form-label">Unit (OUM)</label>
                                <input type="text" id="add_unit" name="unit" class="form-control" value="<?= htmlspecialchars($addFormData['unit']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="add_unit_cost" class="form-label">Unit Cost</label>
                                <input type="number" id="add_unit_cost" name="unit_cost" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($addFormData['unit_cost']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="add_expiration_date" class="form-label">Expiration Date</label>
                                <input type="date" id="add_expiration_date" name="expiration_date" class="form-control" value="<?= htmlspecialchars($addFormData['expiration_date']) ?>" readonly>
                            </div>
                            <div class="col-md-8">
                                <label for="add_recipient" class="form-label">Recipient</label>
                                <input
                                    type="text"
                                    id="add_recipient"
                                    name="recipient"
                                    class="form-control"
                                    list="reportRecipientOptionsList"
                                    value="<?= htmlspecialchars($addFormData['recipient']) ?>"
                                    placeholder="Type or select recipient"
                                >
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">Add Transaction</button>
                            <a href="<?= htmlspecialchars(buildReportUrl($search, $dateFrom, $dateTo, $sort)) ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                    <datalist id="reportDescriptionOptionsList">
                        <?php foreach ($descriptionOptions as $descriptionOption): ?>
                            <option value="<?= htmlspecialchars((string) $descriptionOption) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <datalist id="reportProgramOptionsList">
                        <?php foreach ($programOptions as $programOption): ?>
                            <option value="<?= htmlspecialchars((string) $programOption) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <datalist id="reportUnitOptionsList">
                        <?php foreach ($unitOptions as $unitOption): ?>
                            <option value="<?= htmlspecialchars((string) $unitOption) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <datalist id="reportEditBatchOptionsList">
                        <?php foreach ($batchNumberOptions as $batchOption): ?>
                            <option value="<?= htmlspecialchars((string) $batchOption) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <datalist id="reportAddBatchOptionsList">
                        <?php foreach ($batchNumberOptions as $batchOption): ?>
                            <option value="<?= htmlspecialchars((string) $batchOption) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <datalist id="reportRecipientOptionsList">
                        <?php foreach ($recipientOptions as $recipientOption): ?>
                            <option value="<?= htmlspecialchars((string) $recipientOption) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.reportConfig = {
            batchNumbersByDescription: <?= json_encode($batchNumbersByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            batchMetaByDescription: <?= json_encode($batchMetaByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            unitCostByDescription: <?= json_encode($unitCostByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            poNoByDescription: <?= json_encode($poNoByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            hasProductBatches: <?= $hasProductBatchesTable ? 'true' : 'false' ?>,
            showEditModal: <?= $showEditModal ? 'true' : 'false' ?>,
            showAddModal: <?= $showAddModal ? 'true' : 'false' ?>,
        };
    </script>
    <script src="assets/js/smooth_motion.js?v=20260325"></script>
    <script src="assets/js/report.js"></script>
</body>
</html>
