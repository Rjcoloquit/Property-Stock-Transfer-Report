<?php
session_start();

// Require login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
$search = trim($_GET['q'] ?? '');
$message = trim($_GET['msg'] ?? '');
$sort = strtolower(trim($_GET['sort'] ?? 'asc'));
$sort = $sort === 'desc' ? 'desc' : 'asc';
$orderByDirection = $sort === 'desc' ? 'DESC' : 'ASC';
$items = [];
$itemAddHistory = [];
$productDescriptionOptions = [];
$error = '';
$errors = [];

$formData = [
    'product_description' => '',
    'batch_number' => '',
    'uom' => '',
    'stock' => '0',
    'cost_per_unit' => '',
    'expiry_date' => '',
    'program' => '',
    'po_no' => '',
    'supplier' => '',
    'place_of_delivery' => '',
    'date_of_delivery' => '',
    'delivery_term' => '',
    'payment_term' => '',
];

$editingId = 0;
$isEditMode = false;
$showFormModal = false;
$hasProductsExpiryDate = false;
$hasProductBatchesTable = false;
$hasProductPoNumberTable = false;
$batchSourceTable = '';

function buildItemListUrl(string $search, string $sort, string $message = '', int $editId = 0, string $editBatch = ''): string
{
    $params = [];
    if ($search !== '') {
        $params['q'] = $search;
    }
    if ($sort === 'desc') {
        $params['sort'] = 'desc';
    }
    if ($message !== '') {
        $params['msg'] = $message;
    }
    if ($editId > 0) {
        $params['edit'] = (string) $editId;
    }
    if ($editId > 0 && $editBatch !== '') {
        $params['edit_batch'] = $editBatch;
    }
    return 'item_list.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

function formatDateForDisplayInput($dateValue): string
{
    $dateValue = trim((string) $dateValue);
    if ($dateValue === '') {
        return '';
    }

    $isoDate = DateTime::createFromFormat('Y-m-d', $dateValue);
    if ($isoDate && $isoDate->format('Y-m-d') === $dateValue) {
        return $isoDate->format('m/d/Y');
    }

    return $dateValue;
}

function normalizeDateInputToIso(string $rawDate, string $fieldLabel, array &$errors): ?string
{
    $rawDate = trim($rawDate);
    if ($rawDate === '') {
        return null;
    }

    $usDate = DateTime::createFromFormat('m/d/Y', $rawDate);
    if ($usDate && $usDate->format('m/d/Y') === $rawDate) {
        return $usDate->format('Y-m-d');
    }

    // Keep support for ISO values to avoid breaking pre-filled/browser-autofilled dates.
    $isoDate = DateTime::createFromFormat('Y-m-d', $rawDate);
    if ($isoDate && $isoDate->format('Y-m-d') === $rawDate) {
        return $isoDate->format('Y-m-d');
    }

    $errors[] = $fieldLabel . ' must be a valid date in mm/dd/yyyy format.';
    return null;
}

function appendReceivedStockCardEntry(
    PDO $pdo,
    string $username,
    string $description,
    string $uom,
    string $batchNumber,
    float $unitCost,
    string $program,
    string $poNo,
    string $supplier,
    int $receivedQty,
    string $entryDate = ''
): void {
    $description = trim($description);
    $uom = trim($uom);
    $batchNumber = trim($batchNumber);
    if ($description === '' || $uom === '' || $batchNumber === '' || $receivedQty <= 0) {
        return;
    }

    $itemKey = strtolower($description)
        . '|' . strtolower($uom)
        . '|' . strtolower($batchNumber)
        . '|' . strtolower(trim($program))
        . '|' . strtolower(trim($poNo));
    $readStmt = $pdo->prepare('
        SELECT id, ledger_rows
        FROM stock_cards
        WHERE item_key = ? AND source_type = "release"
        ORDER BY id ASC
        LIMIT 1
    ');
    $readStmt->execute([$itemKey]);
    $existingCard = $readStmt->fetch();

    $ledgerRows = [];
    $cardId = 0;
    if ($existingCard) {
        $cardId = (int) ($existingCard['id'] ?? 0);
        $decodedRows = json_decode((string) ($existingCard['ledger_rows'] ?? ''), true);
        if (is_array($decodedRows)) {
            $ledgerRows = $decodedRows;
        }
    }

    $lastBalance = 0.0;
    if (!empty($ledgerRows)) {
        $lastLedgerRow = $ledgerRows[count($ledgerRows) - 1];
        $lastBalanceRaw = (string) ($lastLedgerRow['balance'] ?? '0');
        $lastBalance = (float) str_replace(',', '', trim($lastBalanceRaw));
    }
    $newBalance = $lastBalance + $receivedQty;
    $entryDate = trim($entryDate) !== '' ? $entryDate : date('Y-m-d');

    $ledgerRows[] = [
        'entry_date' => $entryDate,
        'received' => (string) $receivedQty,
        'issued' => '0',
        'balance' => number_format($newBalance, 2, '.', ''),
        'total_cost' => number_format($newBalance * $unitCost, 2, '.', ''),
        'ref_no' => $poNo !== '' ? ('PO ' . $poNo) : 'Manage Items',
        'remarks' => (trim($supplier) !== '' ? (trim($supplier) . '/') : '') . 'Stock received via Manage Items',
    ];
    $ledgerJson = json_encode($ledgerRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($cardId > 0) {
        $updateStmt = $pdo->prepare('
            UPDATE stock_cards
            SET
                po_contract_no = ?,
                supplier = ?,
                item_description = ?,
                dosage_form = ?,
                uom = ?,
                unit_cost = ?,
                end_user_program = ?,
                batch_no = ?,
                entity_name = "PHO",
                fund_cluster = "PHO",
                ledger_rows = ?
            WHERE id = ?
        ');
        $updateStmt->execute([
            $poNo !== '' ? $poNo : null,
            $supplier !== '' ? $supplier : null,
            $description,
            $uom,
            $uom,
            $unitCost,
            $program !== '' ? $program : null,
            $batchNumber,
            $ledgerJson,
            $cardId,
        ]);
        return;
    }

    $insertStmt = $pdo->prepare('
        INSERT INTO stock_cards
        (
            po_contract_no,
            supplier,
            item_description,
            dosage_form,
            uom,
            unit_cost,
            end_user_program,
            batch_no,
            entity_name,
            fund_cluster,
            ledger_rows,
            item_key,
            source_type,
            created_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, "PHO", "PHO", ?, ?, "release", ?)
    ');
    $insertStmt->execute([
        $poNo !== '' ? $poNo : null,
        $supplier !== '' ? $supplier : null,
        $description,
        $uom,
        $uom,
        $unitCost,
        $program !== '' ? $program : null,
        $batchNumber,
        $ledgerJson,
        $itemKey,
        $username,
    ]);
}

try {
    $pdo = getConnection();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INT NOT NULL AUTO_INCREMENT,
            product_description TEXT,
            uom VARCHAR(50) DEFAULT NULL,
            cost_per_unit DECIMAL(12,2) DEFAULT 0.00,
            expiry_date DATE DEFAULT NULL,
            program VARCHAR(255) DEFAULT NULL,
            po_no VARCHAR(100) DEFAULT NULL,
            place_of_delivery VARCHAR(255) DEFAULT NULL,
            date_of_delivery DATE DEFAULT NULL,
            delivery_term VARCHAR(255) DEFAULT NULL,
            payment_term VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS item_add_history (
            id INT NOT NULL AUTO_INCREMENT,
            product_id INT DEFAULT NULL,
            product_description TEXT,
            uom VARCHAR(50) DEFAULT NULL,
            cost_per_unit DECIMAL(12,2) DEFAULT 0.00,
            expiry_date DATE DEFAULT NULL,
            program VARCHAR(255) DEFAULT NULL,
            po_no VARCHAR(100) DEFAULT NULL,
            place_of_delivery VARCHAR(255) DEFAULT NULL,
            date_of_delivery DATE DEFAULT NULL,
            delivery_term VARCHAR(255) DEFAULT NULL,
            payment_term VARCHAR(255) DEFAULT NULL,
            added_by VARCHAR(150) DEFAULT NULL,
            added_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    // Some ALTER statements below use AFTER <column>. If the DB schema is older,
    // ensure these base columns exist first to avoid "Unknown column ..." errors.
    $costPerUnitBaseColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'cost_per_unit'");
    if (!$costPerUnitBaseColumnStmt || !$costPerUnitBaseColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN cost_per_unit DECIMAL(12,2) DEFAULT 0.00');
    }

    $expiryColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'expiry_date'");
    if ($expiryColumnStmt && $expiryColumnStmt->fetch()) {
        $hasProductsExpiryDate = true;
    } else {
        $pdo->exec('ALTER TABLE products ADD COLUMN expiry_date DATE DEFAULT NULL AFTER cost_per_unit');
        $hasProductsExpiryDate = true;
    }
    $poNoColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'po_no'");
    if (!$poNoColumnStmt || !$poNoColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN po_no VARCHAR(100) DEFAULT NULL AFTER program');
    }
    $placeDeliveryColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'place_of_delivery'");
    if (!$placeDeliveryColumnStmt || !$placeDeliveryColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN place_of_delivery VARCHAR(255) DEFAULT NULL AFTER po_no');
    }
    $dateDeliveryColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'date_of_delivery'");
    if (!$dateDeliveryColumnStmt || !$dateDeliveryColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN date_of_delivery DATE DEFAULT NULL AFTER place_of_delivery');
    }
    $deliveryTermColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'delivery_term'");
    if (!$deliveryTermColumnStmt || !$deliveryTermColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN delivery_term VARCHAR(255) DEFAULT NULL AFTER date_of_delivery');
    }
    $paymentTermColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'payment_term'");
    if (!$paymentTermColumnStmt || !$paymentTermColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN payment_term VARCHAR(255) DEFAULT NULL AFTER delivery_term');
    }
    $supplierColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'supplier'");
    if (!$supplierColumnStmt || !$supplierColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN supplier VARCHAR(255) DEFAULT NULL AFTER po_no');
    }

    // Ensure columns used by the main list query exist (helps on older/partially migrated DBs)
    $uomColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'uom'");
    if (!$uomColumnStmt || !$uomColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN uom VARCHAR(50) DEFAULT NULL');
    }

    $costPerUnitColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'cost_per_unit'");
    if (!$costPerUnitColumnStmt || !$costPerUnitColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN cost_per_unit DECIMAL(12,2) DEFAULT 0.00');
    }

    $programColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'program'");
    if (!$programColumnStmt || !$programColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN program VARCHAR(255) DEFAULT NULL');
    }

    $historyPoNoColumnStmt = $pdo->query("SHOW COLUMNS FROM item_add_history LIKE 'po_no'");
    if (!$historyPoNoColumnStmt || !$historyPoNoColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE item_add_history ADD COLUMN po_no VARCHAR(100) DEFAULT NULL AFTER program');
    }
    $historyPlaceDeliveryColumnStmt = $pdo->query("SHOW COLUMNS FROM item_add_history LIKE 'place_of_delivery'");
    if (!$historyPlaceDeliveryColumnStmt || !$historyPlaceDeliveryColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE item_add_history ADD COLUMN place_of_delivery VARCHAR(255) DEFAULT NULL AFTER po_no');
    }
    $historyDateDeliveryColumnStmt = $pdo->query("SHOW COLUMNS FROM item_add_history LIKE 'date_of_delivery'");
    if (!$historyDateDeliveryColumnStmt || !$historyDateDeliveryColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE item_add_history ADD COLUMN date_of_delivery DATE DEFAULT NULL AFTER place_of_delivery');
    }
    $historyDeliveryTermColumnStmt = $pdo->query("SHOW COLUMNS FROM item_add_history LIKE 'delivery_term'");
    if (!$historyDeliveryTermColumnStmt || !$historyDeliveryTermColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE item_add_history ADD COLUMN delivery_term VARCHAR(255) DEFAULT NULL AFTER date_of_delivery');
    }
    $historyPaymentTermColumnStmt = $pdo->query("SHOW COLUMNS FROM item_add_history LIKE 'payment_term'");
    if (!$historyPaymentTermColumnStmt || !$historyPaymentTermColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE item_add_history ADD COLUMN payment_term VARCHAR(255) DEFAULT NULL AFTER delivery_term');
    }
    $historySupplierColumnStmt = $pdo->query("SHOW COLUMNS FROM item_add_history LIKE 'supplier'");
    if (!$historySupplierColumnStmt || !$historySupplierColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE item_add_history ADD COLUMN supplier VARCHAR(255) DEFAULT NULL AFTER po_no');
    }
    $productBatchesStmt = $pdo->query("SHOW TABLES LIKE 'product_batches'");
    if ($productBatchesStmt && $productBatchesStmt->fetch()) {
        // Only enable the join when the required columns exist
        // (prevents SELECT failures that trigger the generic "unable to load items" message)
        $hasRequiredProductBatchesColumns = true;

        $productIdColumnStmt = $pdo->query("SHOW COLUMNS FROM product_batches LIKE 'product_id'");
        if (!$productIdColumnStmt || !$productIdColumnStmt->fetch()) {
            $hasRequiredProductBatchesColumns = false;
        }

        $batchNumberColumnStmt = $pdo->query("SHOW COLUMNS FROM product_batches LIKE 'batch_number'");
        if (!$batchNumberColumnStmt || !$batchNumberColumnStmt->fetch()) {
            $hasRequiredProductBatchesColumns = false;
        }

        $stockQuantityColumnStmt = $pdo->query("SHOW COLUMNS FROM product_batches LIKE 'stock_quantity'");
        if (!$stockQuantityColumnStmt || !$stockQuantityColumnStmt->fetch()) {
            $hasRequiredProductBatchesColumns = false;
        }

        $expiryDateColumnStmt = $pdo->query("SHOW COLUMNS FROM product_batches LIKE 'expiry_date'");
        if (!$expiryDateColumnStmt || !$expiryDateColumnStmt->fetch()) {
            $hasRequiredProductBatchesColumns = false;
        }

        if ($hasRequiredProductBatchesColumns) {
            $hasProductBatchesTable = true;
            $batchSourceTable = 'product_batches';
        }
    }
    $productPoNumberStmt = $pdo->query("SHOW TABLES LIKE 'product_po_number'");
    if ($productPoNumberStmt && $productPoNumberStmt->fetch()) {
        $hasRequiredProductPoColumns = true;

        $poProductIdColumnStmt = $pdo->query("SHOW COLUMNS FROM product_po_number LIKE 'product_id'");
        if (!$poProductIdColumnStmt || !$poProductIdColumnStmt->fetch()) {
            $hasRequiredProductPoColumns = false;
        }

        $poNoColumnStmt = $pdo->query("SHOW COLUMNS FROM product_po_number LIKE 'po_no'");
        if (!$poNoColumnStmt || !$poNoColumnStmt->fetch()) {
            $hasRequiredProductPoColumns = false;
        }

        $poBatchNumberColumnStmt = $pdo->query("SHOW COLUMNS FROM product_po_number LIKE 'batch_number'");
        if (!$poBatchNumberColumnStmt || !$poBatchNumberColumnStmt->fetch()) {
            $hasRequiredProductPoColumns = false;
        }

        $poStockColumnStmt = $pdo->query("SHOW COLUMNS FROM product_po_number LIKE 'stock_quantity'");
        if (!$poStockColumnStmt || !$poStockColumnStmt->fetch()) {
            $hasRequiredProductPoColumns = false;
        }

        $poCostColumnStmt = $pdo->query("SHOW COLUMNS FROM product_po_number LIKE 'cost_per_unit'");
        if (!$poCostColumnStmt || !$poCostColumnStmt->fetch()) {
            $hasRequiredProductPoColumns = false;
        }

        if ($hasRequiredProductPoColumns) {
            $hasProductPoNumberTable = true;
            $batchSourceTable = 'product_po_number';
        }
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS stock_cards (
            id INT NOT NULL AUTO_INCREMENT,
            po_contract_no VARCHAR(255) DEFAULT NULL,
            supplier VARCHAR(255) DEFAULT NULL,
            item_description TEXT,
            dosage_form VARCHAR(255) DEFAULT NULL,
            dosage_strength VARCHAR(255) DEFAULT NULL,
            uom VARCHAR(100) DEFAULT NULL,
            sku_code VARCHAR(150) DEFAULT NULL,
            entity_name VARCHAR(255) DEFAULT NULL,
            fund_cluster VARCHAR(255) DEFAULT NULL,
            unit_cost DECIMAL(12,2) DEFAULT NULL,
            mode_of_procurement VARCHAR(255) DEFAULT NULL,
            end_user_program VARCHAR(255) DEFAULT NULL,
            batch_no VARCHAR(120) DEFAULT NULL,
            ledger_rows LONGTEXT,
            item_key VARCHAR(400) DEFAULT NULL,
            source_type VARCHAR(30) NOT NULL DEFAULT "manual",
            created_by VARCHAR(150) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
    $itemKeyColumnStmt = $pdo->query("SHOW COLUMNS FROM stock_cards LIKE 'item_key'");
    if (!$itemKeyColumnStmt || !$itemKeyColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE stock_cards ADD COLUMN item_key VARCHAR(400) DEFAULT NULL AFTER ledger_rows');
    }
    $sourceTypeColumnStmt = $pdo->query("SHOW COLUMNS FROM stock_cards LIKE 'source_type'");
    if (!$sourceTypeColumnStmt || !$sourceTypeColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE stock_cards ADD COLUMN source_type VARCHAR(30) NOT NULL DEFAULT "manual" AFTER item_key');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $returnSearch = trim($_POST['return_q'] ?? '');
        $returnSort = strtolower(trim($_POST['return_sort'] ?? 'asc'));
        $returnSort = $returnSort === 'desc' ? 'desc' : 'asc';

        if ($action === 'delete') {
            $deleteId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            if ($deleteId > 0) {
                $deleteStmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
                $deleteStmt->execute([$deleteId]);
                header('Location: ' . buildItemListUrl($returnSearch, $returnSort, 'Item deleted.'));
                exit;
            }
            $errors[] = 'Invalid item selected for delete.';
        } elseif ($action === 'full_reset') {
            try {
                // Reset child tables first, then parent tables.
                if ($hasProductBatchesTable) {
                    $pdo->exec('DELETE FROM product_batches');
                    $pdo->exec('ALTER TABLE product_batches AUTO_INCREMENT = 1');
                }
                if ($hasProductPoNumberTable) {
                    $pdo->exec('DELETE FROM product_po_number');
                    $pdo->exec('ALTER TABLE product_po_number AUTO_INCREMENT = 1');
                }

                $pdo->exec('DELETE FROM item_add_history');
                $pdo->exec('ALTER TABLE item_add_history AUTO_INCREMENT = 1');

                $pdo->exec('DELETE FROM products');
                $pdo->exec('ALTER TABLE products AUTO_INCREMENT = 1');

                // Remove stock cards generated from Manage Items receipts only.
                $pdo->exec('DELETE FROM stock_cards WHERE source_type = "release"');
                header('Location: ' . buildItemListUrl('', 'asc', 'Full reset completed. Item data and history were cleared, and IDs were reset.'));
                exit;
            } catch (Throwable $resetError) {
                $errors[] = 'Full reset failed. Please try again.';
            }
        } elseif ($action === 'create' || $action === 'update') {
            $formData['product_description'] = trim($_POST['product_description'] ?? '');
            $formData['batch_number'] = trim($_POST['batch_number'] ?? '');
            $formData['uom'] = trim($_POST['uom'] ?? '');
            $formData['stock'] = trim($_POST['stock'] ?? '0');
            $formData['cost_per_unit'] = trim($_POST['cost_per_unit'] ?? '');
            $formData['expiry_date'] = trim($_POST['expiry_date'] ?? '');
            $formData['program'] = trim($_POST['program'] ?? '');
            $formData['po_no'] = trim($_POST['po_no'] ?? '');
            $formData['supplier'] = trim($_POST['supplier'] ?? '');
            $formData['place_of_delivery'] = trim($_POST['place_of_delivery'] ?? '');
            $formData['date_of_delivery'] = trim($_POST['date_of_delivery'] ?? '');
            $formData['delivery_term'] = trim($_POST['delivery_term'] ?? '');
            $formData['payment_term'] = trim($_POST['payment_term'] ?? '');
            $normalizedExpiryDate = null;
            $normalizedDateOfDelivery = null;

            if ($formData['product_description'] === '') {
                $errors[] = 'Description is required.';
            }
            if ($formData['uom'] === '') {
                $errors[] = 'UOM is required.';
            }
            if ($formData['cost_per_unit'] === '' || !is_numeric($formData['cost_per_unit']) || (float) $formData['cost_per_unit'] < 0) {
                $errors[] = 'Cost per unit must be a valid non-negative number.';
            }
            $normalizedExpiryDate = normalizeDateInputToIso($formData['expiry_date'], 'Expiry date', $errors);
            $normalizedDateOfDelivery = normalizeDateInputToIso($formData['date_of_delivery'], 'Date of delivery', $errors);
            $requiresBatchTracking = $hasProductBatchesTable || $hasProductPoNumberTable;
            if ($requiresBatchTracking && $formData['batch_number'] === '') {
                $errors[] = 'Batch number is required.';
            }
            if ($requiresBatchTracking && ($formData['stock'] === '' || !ctype_digit($formData['stock']) || (int) $formData['stock'] <= 0)) {
                $errors[] = 'Stock must be a valid positive whole number (greater than 0).';
            }
            if ($hasProductPoNumberTable && trim($formData['po_no']) === '') {
                $errors[] = 'PO Number is required.';
            }
            if ($action === 'create' && trim($formData['po_no']) !== '' && trim($formData['product_description']) !== '') {
                $incomingPo = trim($formData['po_no']);
                $incomingDescription = trim($formData['product_description']);

                $existingPoProductStmt = $pdo->prepare('
                    SELECT id, product_description
                    FROM products
                    WHERE LOWER(TRIM(COALESCE(po_no, ""))) = LOWER(?)
                    ORDER BY id ASC
                    LIMIT 1
                ');
                $existingPoProductStmt->execute([$incomingPo]);
                $existingPoProduct = $existingPoProductStmt->fetch();

                if (!$existingPoProduct && $hasProductPoNumberTable) {
                    $existingPoFromMappingStmt = $pdo->prepare('
                        SELECT p.id, p.product_description
                        FROM product_po_number pp
                        INNER JOIN products p ON p.id = pp.product_id
                        WHERE LOWER(TRIM(COALESCE(pp.po_no, ""))) = LOWER(?)
                        ORDER BY pp.id ASC
                        LIMIT 1
                    ');
                    $existingPoFromMappingStmt->execute([$incomingPo]);
                    $existingPoProduct = $existingPoFromMappingStmt->fetch();
                }

                if ($existingPoProduct) {
                    $existingDescription = trim((string) ($existingPoProduct['product_description'] ?? ''));
                    if (strcasecmp($existingDescription, $incomingDescription) !== 0) {
                        $errors[] = 'PO Number "' . $incomingPo . '" already exists for "'
                            . $existingDescription
                            . '". Please use a different PO Number.';
                    }
                }
            }
            if ($action === 'create' && $normalizedExpiryDate !== null && $normalizedExpiryDate < date('Y-m-d')) {
                $errors[] = 'Expired items are not allowed in Add Item.';
            }
            if ($normalizedDateOfDelivery !== null && $normalizedDateOfDelivery > date('Y-m-d')) {
                $errors[] = 'Date of delivery cannot be in the future.';
            }

            if (empty($errors)) {
                if ($action === 'create') {
                    if ($batchSourceTable !== '' && $formData['batch_number'] !== '') {
                        $poMatchExpr = $batchSourceTable === 'product_po_number'
                            ? 'COALESCE(b.po_no, p.po_no, "")'
                            : 'COALESCE(p.po_no, "")';
                        $descriptionLookup = $pdo->prepare('
                            SELECT p.id
                            FROM products p
                            INNER JOIN ' . $batchSourceTable . ' b ON b.product_id = p.id
                            WHERE LOWER(TRIM(p.product_description)) = LOWER(?)
                              AND LOWER(TRIM(COALESCE(p.program, ""))) = LOWER(?)
                              AND LOWER(TRIM(' . $poMatchExpr . ')) = LOWER(?)
                              AND LOWER(TRIM(b.batch_number)) = LOWER(?)
                            ORDER BY p.id ASC
                            LIMIT 1
                        ');
                        $descriptionLookup->execute([
                            trim($formData['product_description']),
                            trim($formData['program']),
                            trim($formData['po_no']),
                            trim($formData['batch_number']),
                        ]);
                    } else {
                        $descriptionLookup = $pdo->prepare('
                            SELECT id
                            FROM products
                            WHERE LOWER(TRIM(product_description)) = LOWER(?)
                              AND LOWER(TRIM(COALESCE(program, ""))) = LOWER(?)
                              AND LOWER(TRIM(COALESCE(po_no, ""))) = LOWER(?)
                            ORDER BY id ASC
                            LIMIT 1
                        ');
                        $descriptionLookup->execute([
                            trim($formData['product_description']),
                            trim($formData['program']),
                            trim($formData['po_no']),
                        ]);
                    }
                    $existingProduct = $descriptionLookup->fetch();

                    $newProductId = (int) ($existingProduct['id'] ?? 0);
                    $isExistingItem = $newProductId > 0;
                    $didIncreaseStock = false;

                    if ($isExistingItem) {
                        if ($hasProductsExpiryDate) {
                            $updateExistingStmt = $pdo->prepare(
                                'UPDATE products
                                 SET uom = ?,
                                     cost_per_unit = ?,
                                     expiry_date = ?,
                                     program = ?,
                                     po_no = ?,
                                     supplier = ?,
                                     place_of_delivery = ?,
                                     date_of_delivery = ?,
                                     delivery_term = ?,
                                     payment_term = ?
                                 WHERE id = ?'
                            );
                            $updateExistingStmt->execute([
                                $formData['uom'],
                                (float) $formData['cost_per_unit'],
                                $normalizedExpiryDate,
                                $formData['program'] !== '' ? $formData['program'] : null,
                                $formData['po_no'] !== '' ? $formData['po_no'] : null,
                                $formData['supplier'] !== '' ? $formData['supplier'] : null,
                                $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                                $normalizedDateOfDelivery,
                                $formData['delivery_term'] !== '' ? $formData['delivery_term'] : null,
                                $formData['payment_term'] !== '' ? $formData['payment_term'] : null,
                                $newProductId,
                            ]);
                        } else {
                            $updateExistingStmt = $pdo->prepare(
                                'UPDATE products
                                 SET uom = ?,
                                     cost_per_unit = ?,
                                     program = ?,
                                     po_no = ?,
                                     supplier = ?,
                                     place_of_delivery = ?,
                                     date_of_delivery = ?,
                                     delivery_term = ?,
                                     payment_term = ?
                                 WHERE id = ?'
                            );
                            $updateExistingStmt->execute([
                                $formData['uom'],
                                (float) $formData['cost_per_unit'],
                                $formData['program'] !== '' ? $formData['program'] : null,
                                $formData['po_no'] !== '' ? $formData['po_no'] : null,
                                $formData['supplier'] !== '' ? $formData['supplier'] : null,
                                $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                                $normalizedDateOfDelivery,
                                $formData['delivery_term'] !== '' ? $formData['delivery_term'] : null,
                                $formData['payment_term'] !== '' ? $formData['payment_term'] : null,
                                $newProductId,
                            ]);
                        }
                    } else {
                        if ($hasProductsExpiryDate) {
                            $insertStmt = $pdo->prepare(
                                'INSERT INTO products (
                                    product_description,
                                    uom,
                                    cost_per_unit,
                                    expiry_date,
                                    program,
                                    po_no,
                                    supplier,
                                    place_of_delivery,
                                    date_of_delivery,
                                    delivery_term,
                                    payment_term
                                )
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                            );
                            $insertStmt->execute([
                                $formData['product_description'],
                                $formData['uom'],
                                (float) $formData['cost_per_unit'],
                                $normalizedExpiryDate,
                                $formData['program'] !== '' ? $formData['program'] : null,
                                $formData['po_no'] !== '' ? $formData['po_no'] : null,
                                $formData['supplier'] !== '' ? $formData['supplier'] : null,
                                $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                                $normalizedDateOfDelivery,
                                $formData['delivery_term'] !== '' ? $formData['delivery_term'] : null,
                                $formData['payment_term'] !== '' ? $formData['payment_term'] : null,
                            ]);
                            $newProductId = (int) $pdo->lastInsertId();
                        } else {
                            $insertStmt = $pdo->prepare(
                                'INSERT INTO products (
                                    product_description,
                                    uom,
                                    cost_per_unit,
                                    program,
                                    po_no,
                                    supplier,
                                    place_of_delivery,
                                    date_of_delivery,
                                    delivery_term,
                                    payment_term
                                )
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                            );
                            $insertStmt->execute([
                                $formData['product_description'],
                                $formData['uom'],
                                (float) $formData['cost_per_unit'],
                                $formData['program'] !== '' ? $formData['program'] : null,
                                $formData['po_no'] !== '' ? $formData['po_no'] : null,
                                $formData['supplier'] !== '' ? $formData['supplier'] : null,
                                $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                                $normalizedDateOfDelivery,
                                $formData['delivery_term'] !== '' ? $formData['delivery_term'] : null,
                                $formData['payment_term'] !== '' ? $formData['payment_term'] : null,
                            ]);
                            $newProductId = (int) $pdo->lastInsertId();
                        }
                    }

                    if ($hasProductBatchesTable && $newProductId > 0 && $formData['batch_number'] !== '') {
                        $batchInsertStmt = $pdo->prepare(
                            'INSERT INTO product_batches (product_id, batch_number, stock_quantity, expiry_date)
                             VALUES (?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE
                                 stock_quantity = stock_quantity + VALUES(stock_quantity),
                                 expiry_date = COALESCE(VALUES(expiry_date), expiry_date)'
                        );
                        $batchInsertStmt->execute([
                            $newProductId,
                            $formData['batch_number'],
                            (int) $formData['stock'],
                                $normalizedExpiryDate,
                        ]);
                        appendReceivedStockCardEntry(
                            $pdo,
                            $username,
                            $formData['product_description'],
                            $formData['uom'],
                            $formData['batch_number'],
                            (float) $formData['cost_per_unit'],
                            $formData['program'],
                            $formData['po_no'],
                            $formData['supplier'],
                            (int) $formData['stock'],
                            $normalizedDateOfDelivery ?? ''
                        );
                        $didIncreaseStock = true;
                    }
                    if ($hasProductPoNumberTable && $newProductId > 0 && $formData['batch_number'] !== '' && $formData['po_no'] !== '') {
                        $poFindStmt = $pdo->prepare('
                            SELECT id
                            FROM product_po_number
                            WHERE product_id = ?
                              AND LOWER(TRIM(po_no)) = LOWER(?)
                              AND LOWER(TRIM(batch_number)) = LOWER(?)
                            LIMIT 1
                        ');
                        $poFindStmt->execute([
                            $newProductId,
                            $formData['po_no'],
                            $formData['batch_number'],
                        ]);
                        $existingPoRow = $poFindStmt->fetch();

                        if ($existingPoRow) {
                            $poUpdateStmt = $pdo->prepare('
                                UPDATE product_po_number
                                SET
                                    product_id = ?,
                                    po_no = ?,
                                    batch_number = ?,
                                    cost_per_unit = ?,
                                    stock_quantity = stock_quantity + ?,
                                    expiry_date = COALESCE(?, expiry_date)
                                WHERE id = ?
                            ');
                            $poUpdateStmt->execute([
                                $newProductId,
                                $formData['po_no'],
                                $formData['batch_number'],
                                (float) $formData['cost_per_unit'],
                                (int) $formData['stock'],
                                $normalizedExpiryDate,
                                (int) ($existingPoRow['id'] ?? 0),
                            ]);
                        } else {
                            $poInsertStmt = $pdo->prepare(
                                'INSERT INTO product_po_number (product_id, po_no, batch_number, cost_per_unit, stock_quantity, expiry_date)
                                 VALUES (?, ?, ?, ?, ?, ?)'
                            );
                            $poInsertStmt->execute([
                                $newProductId,
                                $formData['po_no'],
                                $formData['batch_number'],
                                (float) $formData['cost_per_unit'],
                                (int) $formData['stock'],
                                $normalizedExpiryDate,
                            ]);
                        }
                    }

                    $historyStmt = $pdo->prepare(
                        'INSERT INTO item_add_history
                            (
                                product_id,
                                product_description,
                                uom,
                                cost_per_unit,
                                expiry_date,
                                program,
                                po_no,
                                supplier,
                                place_of_delivery,
                                date_of_delivery,
                                delivery_term,
                                payment_term,
                                added_by
                            )
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $historyStmt->execute([
                        $newProductId > 0 ? $newProductId : null,
                        $formData['product_description'],
                        $formData['uom'],
                        (float) $formData['cost_per_unit'],
                        $normalizedExpiryDate,
                        $formData['program'] !== '' ? $formData['program'] : null,
                        $formData['po_no'] !== '' ? $formData['po_no'] : null,
                        $formData['supplier'] !== '' ? $formData['supplier'] : null,
                        $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                        $normalizedDateOfDelivery,
                        $formData['delivery_term'] !== '' ? $formData['delivery_term'] : null,
                        $formData['payment_term'] !== '' ? $formData['payment_term'] : null,
                        $username,
                    ]);
                    if ($isExistingItem && $didIncreaseStock) {
                        $successMessage = 'Existing item found. Stock quantity was added to current inventory.';
                    } elseif ($isExistingItem) {
                        $successMessage = 'Existing item found. Item details were updated.';
                    } else {
                        $successMessage = 'Item added.';
                    }
                    header('Location: ' . buildItemListUrl($returnSearch, $returnSort, $successMessage));
                    exit;
                }

                $updateId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
                if ($updateId > 0) {
                    $originalBatchNumber = trim((string) ($_POST['original_batch_number'] ?? ''));
                    $originalPoNo = trim((string) ($_POST['original_po_no'] ?? ''));
                    $previousStockQty = 0;
                    if ($batchSourceTable !== '' && $formData['batch_number'] !== '') {
                        $lookupBatch = $originalBatchNumber !== '' ? $originalBatchNumber : $formData['batch_number'];
                        $prevStockStmt = $pdo->prepare('
                            SELECT stock_quantity
                            FROM ' . $batchSourceTable . '
                            WHERE product_id = ? AND batch_number = ?
                            LIMIT 1
                        ');
                        $prevStockStmt->execute([$updateId, $lookupBatch]);
                        $prevStockRow = $prevStockStmt->fetch();
                        $previousStockQty = (int) ($prevStockRow['stock_quantity'] ?? 0);
                    }
                    if ($hasProductsExpiryDate) {
                        $updateStmt = $pdo->prepare(
                            'UPDATE products
                             SET product_description = ?,
                                 uom = ?,
                                 cost_per_unit = ?,
                                 expiry_date = ?,
                                 program = ?,
                                 po_no = ?,
                                 supplier = ?,
                                 place_of_delivery = ?,
                                 date_of_delivery = ?,
                                 delivery_term = ?,
                                 payment_term = ?
                             WHERE id = ?'
                        );
                        $updateStmt->execute([
                            $formData['product_description'],
                            $formData['uom'],
                            (float) $formData['cost_per_unit'],
                            $normalizedExpiryDate,
                            $formData['program'] !== '' ? $formData['program'] : null,
                            $formData['po_no'] !== '' ? $formData['po_no'] : null,
                            $formData['supplier'] !== '' ? $formData['supplier'] : null,
                            $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                            $normalizedDateOfDelivery,
                            $formData['delivery_term'] !== '' ? $formData['delivery_term'] : null,
                            $formData['payment_term'] !== '' ? $formData['payment_term'] : null,
                            $updateId,
                        ]);
                    } else {
                        $updateStmt = $pdo->prepare(
                            'UPDATE products
                             SET product_description = ?,
                                 uom = ?,
                                 cost_per_unit = ?,
                                 program = ?,
                                 po_no = ?,
                                 supplier = ?,
                                 place_of_delivery = ?,
                                 date_of_delivery = ?,
                                 delivery_term = ?,
                                 payment_term = ?
                             WHERE id = ?'
                        );
                        $updateStmt->execute([
                            $formData['product_description'],
                            $formData['uom'],
                            (float) $formData['cost_per_unit'],
                            $formData['program'] !== '' ? $formData['program'] : null,
                            $formData['po_no'] !== '' ? $formData['po_no'] : null,
                            $formData['supplier'] !== '' ? $formData['supplier'] : null,
                            $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                            $normalizedDateOfDelivery,
                            $formData['delivery_term'] !== '' ? $formData['delivery_term'] : null,
                            $formData['payment_term'] !== '' ? $formData['payment_term'] : null,
                            $updateId,
                        ]);
                    }
                    if ($hasProductBatchesTable && $formData['batch_number'] !== '') {
                        if ($originalBatchNumber !== '') {
                            $batchUpdateStmt = $pdo->prepare(
                                'UPDATE product_batches
                                 SET batch_number = ?, stock_quantity = ?, expiry_date = ?
                                 WHERE product_id = ? AND batch_number = ?'
                            );
                            $batchUpdateStmt->execute([
                                $formData['batch_number'],
                                (int) $formData['stock'],
                                $normalizedExpiryDate,
                                $updateId,
                                $originalBatchNumber,
                            ]);
                        } else {
                            $batchInsertStmt = $pdo->prepare(
                                'INSERT INTO product_batches (product_id, batch_number, stock_quantity, expiry_date)
                                 VALUES (?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE
                                     stock_quantity = VALUES(stock_quantity),
                                     expiry_date = VALUES(expiry_date)'
                            );
                            $batchInsertStmt->execute([
                                $updateId,
                                $formData['batch_number'],
                                (int) $formData['stock'],
                                $normalizedExpiryDate,
                            ]);
                        }
                        $newStockQty = (int) $formData['stock'];
                        $receivedDelta = $newStockQty - $previousStockQty;
                        if ($receivedDelta > 0) {
                            appendReceivedStockCardEntry(
                                $pdo,
                                $username,
                                $formData['product_description'],
                                $formData['uom'],
                                $formData['batch_number'],
                                (float) $formData['cost_per_unit'],
                                $formData['program'],
                                $formData['po_no'],
                                $formData['supplier'],
                                $receivedDelta,
                                $normalizedDateOfDelivery ?? ''
                            );
                        }
                    }
                    if ($hasProductPoNumberTable && $formData['batch_number'] !== '' && $formData['po_no'] !== '') {
                        $existingPoRow = false;

                        if ($originalBatchNumber !== '' || $originalPoNo !== '') {
                            $poFindOriginalStmt = $pdo->prepare('
                                SELECT id
                                FROM product_po_number
                                WHERE product_id = ?
                                  AND (? = "" OR LOWER(TRIM(po_no)) = LOWER(?))
                                  AND (? = "" OR LOWER(TRIM(batch_number)) = LOWER(?))
                                ORDER BY id ASC
                                LIMIT 1
                            ');
                            $poFindOriginalStmt->execute([
                                $updateId,
                                $originalPoNo,
                                $originalPoNo,
                                $originalBatchNumber,
                                $originalBatchNumber,
                            ]);
                            $existingPoRow = $poFindOriginalStmt->fetch();
                        }

                        if (!$existingPoRow) {
                            $poFindStmt = $pdo->prepare('
                                SELECT id
                                FROM product_po_number
                                WHERE product_id = ?
                                  AND LOWER(TRIM(po_no)) = LOWER(?)
                                  AND LOWER(TRIM(batch_number)) = LOWER(?)
                                LIMIT 1
                            ');
                            $poFindStmt->execute([
                                $updateId,
                                $formData['po_no'],
                                $formData['batch_number'],
                            ]);
                            $existingPoRow = $poFindStmt->fetch();
                        }

                        if ($existingPoRow) {
                            $poUpdateStmt = $pdo->prepare('
                                UPDATE product_po_number
                                SET
                                    product_id = ?,
                                    po_no = ?,
                                    batch_number = ?,
                                    cost_per_unit = ?,
                                    stock_quantity = ?,
                                    expiry_date = ?
                                WHERE id = ?
                            ');
                            $poUpdateStmt->execute([
                                $updateId,
                                $formData['po_no'],
                                $formData['batch_number'],
                                (float) $formData['cost_per_unit'],
                                (int) $formData['stock'],
                                $normalizedExpiryDate,
                                (int) ($existingPoRow['id'] ?? 0),
                            ]);
                        } else {
                            $poInsertStmt = $pdo->prepare(
                                'INSERT INTO product_po_number (product_id, po_no, batch_number, cost_per_unit, stock_quantity, expiry_date)
                                 VALUES (?, ?, ?, ?, ?, ?)'
                            );
                            $poInsertStmt->execute([
                                $updateId,
                                $formData['po_no'],
                                $formData['batch_number'],
                                (float) $formData['cost_per_unit'],
                                (int) $formData['stock'],
                                $normalizedExpiryDate,
                            ]);
                        }
                    }
                    header('Location: ' . buildItemListUrl($returnSearch, $returnSort, 'Item updated.'));
                    exit;
                }

                $errors[] = 'Invalid item selected for update.';
            }

            if ($action === 'update') {
                $editingId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
                $isEditMode = $editingId > 0;
            }
        }
    }

    if (!$isEditMode && isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
        $editingId = (int) $_GET['edit'];
        $editingBatchNumber = trim((string) ($_GET['edit_batch'] ?? ''));
        $editingPoNo = trim((string) ($_GET['edit_po'] ?? ''));
        if ($editingId > 0) {
            if ($batchSourceTable !== '') {
                $editCostPerUnitExpr = $batchSourceTable === 'product_po_number'
                    ? 'COALESCE(b.cost_per_unit, p.cost_per_unit)'
                    : 'p.cost_per_unit';
                $editPoNoExpr = $batchSourceTable === 'product_po_number'
                    ? 'COALESCE(b.po_no, p.po_no)'
                    : 'p.po_no';
                $editSelect = $hasProductsExpiryDate
                    ? 'SELECT
                           p.id,
                           p.product_description,
                           p.uom,
                           ' . $editCostPerUnitExpr . ' AS cost_per_unit,
                           COALESCE(b.expiry_date, p.expiry_date) AS expiry_date,
                           p.program,
                           ' . $editPoNoExpr . ' AS po_no,
                           p.supplier,
                           p.place_of_delivery,
                           p.date_of_delivery,
                           p.delivery_term,
                           p.payment_term,
                           b.batch_number,
                           COALESCE(b.stock_quantity, 0) AS stock
                       FROM products p
                       LEFT JOIN ' . $batchSourceTable . ' b ON b.product_id = p.id
                                             WHERE p.id = ?
                                                 AND (? = "" OR b.batch_number = ?)
                                                 AND (? = "" OR LOWER(TRIM(COALESCE(b.po_no, p.po_no, ""))) = LOWER(?))
                       ORDER BY b.batch_number ASC
                       LIMIT 1'
                    : 'SELECT
                           p.id,
                           p.product_description,
                           p.uom,
                           ' . $editCostPerUnitExpr . ' AS cost_per_unit,
                           b.expiry_date AS expiry_date,
                           p.program,
                           ' . $editPoNoExpr . ' AS po_no,
                           p.supplier,
                           p.place_of_delivery,
                           p.date_of_delivery,
                           p.delivery_term,
                           p.payment_term,
                           b.batch_number,
                           COALESCE(b.stock_quantity, 0) AS stock
                       FROM products p
                       LEFT JOIN ' . $batchSourceTable . ' b ON b.product_id = p.id
                       WHERE p.id = ?
                         AND (? = "" OR b.batch_number = ?)
                         AND (? = "" OR LOWER(TRIM(COALESCE(b.po_no, p.po_no, ""))) = LOWER(?))
                       ORDER BY b.batch_number ASC
                       LIMIT 1';
                $editStmt = $pdo->prepare($editSelect);
                $editStmt->execute([
                    $editingId,
                    $editingBatchNumber,
                    $editingBatchNumber,
                    $editingPoNo,
                    $editingPoNo,
                ]);
            } else {
                $editSelect = $hasProductsExpiryDate
                    ? 'SELECT id, product_description, uom, cost_per_unit, expiry_date, program, po_no, supplier, place_of_delivery, date_of_delivery, delivery_term, payment_term, NULL AS batch_number, 0 AS stock FROM products WHERE id = ? LIMIT 1'
                    : 'SELECT id, product_description, uom, cost_per_unit, NULL AS expiry_date, program, po_no, supplier, place_of_delivery, date_of_delivery, delivery_term, payment_term, NULL AS batch_number, 0 AS stock FROM products WHERE id = ? LIMIT 1';
                $editStmt = $pdo->prepare($editSelect);
                $editStmt->execute([$editingId]);
            }
            $editingItem = $editStmt->fetch();

            if ($editingItem) {
                $isEditMode = true;
                $formData = [
                    'product_description' => (string) ($editingItem['product_description'] ?? ''),
                    'batch_number' => (string) ($editingItem['batch_number'] ?? ''),
                    'uom' => (string) ($editingItem['uom'] ?? ''),
                    'stock' => (string) ($editingItem['stock'] ?? '0'),
                    'cost_per_unit' => (string) ($editingItem['cost_per_unit'] ?? ''),
                    'expiry_date' => (string) ($editingItem['expiry_date'] ?? ''),
                    'program' => (string) ($editingItem['program'] ?? ''),
                    'po_no' => (string) ($editingItem['po_no'] ?? ''),
                    'supplier' => (string) ($editingItem['supplier'] ?? ''),
                    'place_of_delivery' => (string) ($editingItem['place_of_delivery'] ?? ''),
                    'date_of_delivery' => (string) ($editingItem['date_of_delivery'] ?? ''),
                    'delivery_term' => (string) ($editingItem['delivery_term'] ?? ''),
                    'payment_term' => (string) ($editingItem['payment_term'] ?? ''),
                ];
            }
        }
    }
    $showFormModal = $isEditMode || !empty($errors);

    $batchJoinSql = '';
    $batchSelectSql = 'NULL AS batch_number, 0 AS stock';
    $expirySelectSql = $hasProductsExpiryDate ? 'p.expiry_date' : 'NULL';
    $costPerUnitSelectSql = 'p.cost_per_unit';
    $poNoSelectSql = 'p.po_no';
    if ($batchSourceTable !== '') {
        $batchJoinSql = 'LEFT JOIN ' . $batchSourceTable . ' b ON b.product_id = p.id';
        $batchSelectSql = 'b.batch_number AS batch_number, COALESCE(b.stock_quantity, 0) AS stock';
        $expirySelectSql = $hasProductsExpiryDate
            ? 'COALESCE(b.expiry_date, p.expiry_date)'
            : 'b.expiry_date';
        if ($batchSourceTable === 'product_po_number') {
            $costPerUnitSelectSql = 'COALESCE(b.cost_per_unit, p.cost_per_unit)';
            $poNoSelectSql = 'COALESCE(b.po_no, p.po_no)';
        }
    }
    $listSelect = '
        SELECT
            p.id,
            p.product_description,
            p.uom,
            ' . $costPerUnitSelectSql . ' AS cost_per_unit,
            ' . $expirySelectSql . ' AS expiry_date,
            p.program,
            ' . $poNoSelectSql . ' AS po_no,
            p.supplier,
            p.place_of_delivery,
            p.date_of_delivery,
            p.delivery_term,
            p.payment_term,
            ' . $batchSelectSql . '
        FROM products p
        ' . $batchJoinSql;

    if ($search !== '') {
        $like = '%' . $search . '%';
        $batchSearchClause = $batchSourceTable !== ''
            ? '                OR COALESCE(b.batch_number, "") LIKE :q' . PHP_EOL
            : '';
        $stmt = $pdo->prepare(
            $listSelect . '
             WHERE p.product_description LIKE :q
                OR p.uom LIKE :q
             ' . $batchSearchClause . '
             ORDER BY TRIM(LOWER(p.product_description)) ' . $orderByDirection . ',
                      COALESCE(batch_number, "") ASC,
                      p.id ASC'
        );
        $stmt->execute(['q' => $like]);
    } else {
        $stmt = $pdo->query(
            $listSelect . '
             ORDER BY TRIM(LOWER(p.product_description)) ' . $orderByDirection . ',
                      COALESCE(batch_number, "") ASC,
                      p.id ASC'
        );
    }

    $items = $stmt->fetchAll();

    try {
        $descriptionOptionsStmt = $pdo->query('
            SELECT DISTINCT TRIM(product_description) AS description_name
            FROM products
            WHERE product_description IS NOT NULL AND TRIM(product_description) <> ""
            ORDER BY description_name ASC
        ');
        $productDescriptionOptions = $descriptionOptionsStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('item_list.php: descriptionOptions query failed: ' . $e->getMessage());
        $productDescriptionOptions = [];
    }

    // Fetch all products with their attributes for recommendations
    $productAttributesMap = [];
    try {
        $allProductsStmt = $pdo->query('
            SELECT DISTINCT product_description, uom, program, po_no, supplier
            FROM products
            WHERE product_description IS NOT NULL AND TRIM(product_description) <> ""
            ORDER BY product_description ASC
        ');
        $allProducts = $allProductsStmt->fetchAll();

        // Build a map of product descriptions to their attributes
        foreach ($allProducts as $product) {
            $desc = trim($product['product_description'] ?? '');
            if ($desc !== '') {
                if (!isset($productAttributesMap[$desc])) {
                    $productAttributesMap[$desc] = [
                        'uom_list' => [],
                        'program_list' => [],
                        'supplier_list' => [],
                        'po_no_list' => []
                    ];
                }
                if (!empty($product['uom'])) {
                    $productAttributesMap[$desc]['uom_list'][] = $product['uom'];
                }
                if (!empty($product['program'])) {
                    $productAttributesMap[$desc]['program_list'][] = $product['program'];
                }
                if (!empty($product['supplier'])) {
                    $productAttributesMap[$desc]['supplier_list'][] = $product['supplier'];
                }
                if (!empty($product['po_no'])) {
                    $productAttributesMap[$desc]['po_no_list'][] = $product['po_no'];
                }
            }
        }

        // Deduplicate and sort the lists
        foreach ($productAttributesMap as &$attrs) {
            $attrs['uom_list'] = array_values(array_unique($attrs['uom_list']));
            $attrs['program_list'] = array_values(array_unique($attrs['program_list']));
            $attrs['supplier_list'] = array_values(array_unique($attrs['supplier_list']));
            $attrs['po_no_list'] = array_values(array_unique($attrs['po_no_list']));
            sort($attrs['uom_list']);
            sort($attrs['program_list']);
            sort($attrs['supplier_list']);
            sort($attrs['po_no_list']);
        }
        unset($attrs);
    } catch (PDOException $e) {
        error_log('item_list.php: recommendation products query failed: ' . $e->getMessage());
        $productAttributesMap = [];
    }

    // Load latest item-add history (optional; don't break the whole page)
    try {
        $historyStmt = $pdo->query('
            SELECT id, product_id, product_description, uom, cost_per_unit, expiry_date, program, added_by, added_at
            FROM item_add_history
            ORDER BY added_at DESC, id DESC
            LIMIT 50
        ');
        $itemAddHistory = $historyStmt->fetchAll();
    } catch (PDOException $e) {
        error_log('item_list.php: item_add_history query failed: ' . $e->getMessage());
        $itemAddHistory = [];
    }
} catch (PDOException $e) {
    error_log('item_list.php DB error: ' . $e->getMessage());
    $error = 'Unable to load items right now. Please check your database setup.';

    // Local debugging helper. Avoid exposing raw SQL errors by default.
    if (($_GET['debug'] ?? '') === '1') {
        $error .= ' (' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . ')';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item List - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260407k">
</head>
<body>
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
                    <small class="fw-normal">Manage Items</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="current_stock_report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Stock Report</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
            </div>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <div class="card app-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h1 class="h5 mb-0">Item List</h1>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="inventory-add-btn" data-bs-toggle="modal" data-bs-target="#itemFormModal">
                                + Add Item
                            </button>
                            <form method="post" action="item_list.php" onsubmit="return confirm('This will permanently remove all items and item add history, then reset IDs to 1. Continue?');" class="m-0">
                                <input type="hidden" name="action" value="full_reset">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Full Reset</button>
                            </form>
                        </div>
                    </div>

                    <form method="get" action="item_list.php" class="row g-2 mb-3">
                        <div class="col-md-9 inventory-search-bar">
                            <input
                                type="text"
                                name="q"
                                class="form-control"
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search product description, batch number, or unit of measure"
                            >
                        </div>
                        <div class="col-md-3 inventory-sort-select">
                            <select name="sort" class="form-select" aria-label="Sort order" onchange="this.form.submit()">
                                <option value="asc" <?= $sort === 'asc' ? 'selected' : '' ?>>Arrange: Ascending</option>
                                <option value="desc" <?= $sort === 'desc' ? 'selected' : '' ?>>Arrange: Descending</option>
                            </select>
                        </div>
                    </form>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 mb-0"><?= htmlspecialchars($error) ?></div>
                    <?php else: ?>
                        <?php if ($message !== ''): ?>
                            <div class="alert alert-success py-2"><?= htmlspecialchars($message) ?></div>
                        <?php endif; ?>
                        <?php if (empty($items)): ?>
                            <div class="inventory-empty-state">
                                <div class="inventory-empty-state-icon">📦</div>
                                <div>
                                    <strong>No items found</strong><?= $search !== '' ? ' for "' . htmlspecialchars($search) . '"' : '' ?>.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="inventory-table-container">
                                <div class="inventory-stats">
                                    Total Items: <span class="inventory-stats-value"><?= count($items) ?></span>
                                </div>
                                <div class="inventory-table-wrapper">
                                    <table class="table inventory-table">
                                        <thead>
                                            <tr>
                                                <th scope="col" class="col-no">No.</th>
                                                <th scope="col" class="col-description">Product Description</th>
                                                <th scope="col" class="col-po-no">PO Number</th>
                                                <th scope="col" class="col-batch">Batch Number</th>
                                                <th scope="col" class="col-uom">UOM</th>
                                                <th scope="col" class="col-stock">Stock</th>
                                                <th scope="col" class="col-cost">Cost Per Unit</th>
                                                <th scope="col" class="col-expiry">Expiry Date</th>
                                                <th scope="col" class="col-actions">Actions</th>
                                            </tr>
                                        </thead>
                                    <tbody>
                                        <?php foreach ($items as $index => $item): ?>
                                            <tr>
                                                <td class="col-no"><?= $index + 1 ?></td>
                                                <td class="col-description"><?= htmlspecialchars($item['product_description'] ?? '') ?></td>
                                                <td class="col-po-no"><?= htmlspecialchars((string) ($item['po_no'] ?? '-')) ?></td>
                                                <td class="col-batch"><?= htmlspecialchars((string) ($item['batch_number'] ?? '-')) ?></td>
                                                <td class="col-uom"><?= htmlspecialchars($item['uom'] ?? '-') ?></td>
                                                <td class="col-stock"><?= number_format((int) ($item['stock'] ?? 0)) ?></td>
                                                <td class="col-cost"><span class="inventory-currency"><?= number_format((float) $item['cost_per_unit'], 2) ?></span></td>
                                                <td class="col-expiry"><?= htmlspecialchars($item['expiry_date'] ?? '-') ?></td>
                                                <td class="col-actions">
                                                    <div class="d-inline-flex gap-1">
                                                        <button
                                                            type="button"
                                                            class="inventory-action-btn inventory-btn-details item-details-btn"
                                                            data-item-no="<?= (int) ($index + 1) ?>"
                                                            data-product-description="<?= htmlspecialchars((string) ($item['product_description'] ?? ''), ENT_QUOTES) ?>"
                                                            data-batch-number="<?= htmlspecialchars((string) ($item['batch_number'] ?? ''), ENT_QUOTES) ?>"
                                                            data-uom="<?= htmlspecialchars((string) ($item['uom'] ?? ''), ENT_QUOTES) ?>"
                                                            data-stock="<?= (int) ($item['stock'] ?? 0) ?>"
                                                            data-cost-per-unit="<?= htmlspecialchars(number_format((float) ($item['cost_per_unit'] ?? 0), 2, '.', ''), ENT_QUOTES) ?>"
                                                            data-expiry-date="<?= htmlspecialchars((string) ($item['expiry_date'] ?? ''), ENT_QUOTES) ?>"
                                                            data-program="<?= htmlspecialchars((string) ($item['program'] ?? ''), ENT_QUOTES) ?>"
                                                            data-po-no="<?= htmlspecialchars((string) ($item['po_no'] ?? ''), ENT_QUOTES) ?>"
                                                            data-supplier="<?= htmlspecialchars((string) ($item['supplier'] ?? ''), ENT_QUOTES) ?>"
                                                            data-place-of-delivery="<?= htmlspecialchars((string) ($item['place_of_delivery'] ?? ''), ENT_QUOTES) ?>"
                                                            data-date-of-delivery="<?= htmlspecialchars((string) ($item['date_of_delivery'] ?? ''), ENT_QUOTES) ?>"
                                                            data-delivery-term="<?= htmlspecialchars((string) ($item['delivery_term'] ?? ''), ENT_QUOTES) ?>"
                                                            data-payment-term="<?= htmlspecialchars((string) ($item['payment_term'] ?? ''), ENT_QUOTES) ?>"
                                                        >
                                                            Details
                                                        </button>
                                                        <a
                                                            href="<?= htmlspecialchars(buildItemListUrl($search, $sort, '', (int) $item['id'], (string) ($item['batch_number'] ?? '')) . ((string) ($item['po_no'] ?? '') !== '' ? '&edit_po=' . urlencode((string) $item['po_no']) : '')) ?>"
                                                            class="inventory-action-btn inventory-btn-edit"
                                                        >
                                                            Edit
                                                        </a>
                                                        <form method="post" action="item_list.php<?= $search !== '' || $sort === 'desc' ? '?' . http_build_query(['q' => $search, 'sort' => $sort === 'desc' ? 'desc' : null]) : '' ?>" onsubmit="return confirm('Delete this item?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                            <input type="hidden" name="return_q" value="<?= htmlspecialchars($search) ?>">
                                                            <input type="hidden" name="return_sort" value="<?= htmlspecialchars($sort) ?>">
                                                            <button type="submit" class="inventory-action-btn inventory-btn-delete">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card app-card mt-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="h6 mb-0">Item Add History</h2>
                        <span class="small text-muted">Latest 50 added items</span>
                    </div>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 mb-0"><?= htmlspecialchars($error) ?></div>
                    <?php elseif (empty($itemAddHistory)): ?>
                        <div class="alert alert-info py-2 mb-0">No item add history yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Description</th>
                                        <th>UOM</th>
                                        <th class="text-center">Cost</th>
                                        <th>Expiry Date</th>
                                        <th>Program</th>
                                        <th>Added By</th>
                                        <th>Added At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itemAddHistory as $history): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($history['product_id'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($history['product_description'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($history['uom'] ?? '-')) ?></td>
                                            <td class="text-center"><?= number_format((float) ($history['cost_per_unit'] ?? 0), 2) ?></td>
                                            <td><?= htmlspecialchars((string) ($history['expiry_date'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($history['program'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($history['added_by'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($history['added_at'] ?? '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="itemFormModal" tabindex="-1" aria-labelledby="itemFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content item-form-modal-content">
                <div class="modal-header border-0 pb-0">
                    <h2 class="modal-title h5 mb-0 fw-semibold" id="itemFormModalLabel"><?= $isEditMode ? 'Edit Item' : 'Add New Item' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger py-2 mb-3">
                            <?php foreach ($errors as $e): ?>
                                <div><?= htmlspecialchars($e) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="item_list.php<?= $search !== '' || $sort === 'desc' ? '?' . http_build_query(['q' => $search, 'sort' => $sort === 'desc' ? 'desc' : null]) : '' ?>" id="itemFormModalForm">
                        <input type="hidden" name="action" value="<?= $isEditMode ? 'update' : 'create' ?>">
                        <input type="hidden" name="return_q" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="return_sort" value="<?= htmlspecialchars($sort) ?>">
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="id" value="<?= (int) $editingId ?>">
                            <input type="hidden" name="original_po_no" value="<?= htmlspecialchars($formData['po_no']) ?>">
                        <?php endif; ?>

                        <section class="item-form-section mb-4">
                            <h3 class="item-form-section-title">Item details</h3>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="product_description" class="form-label item-form-label">Product description <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        id="product_description"
                                        name="product_description"
                                        class="form-control item-form-input"
                                        list="productDescriptionOptionsList"
                                        required
                                        placeholder="Type or select existing item description"
                                        value="<?= htmlspecialchars($formData['product_description']) ?>"
                                    >
                                    <datalist id="productDescriptionOptionsList">
                                        <?php foreach ($productDescriptionOptions as $descriptionOption): ?>
                                            <option value="<?= htmlspecialchars((string) $descriptionOption) ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="form-text">If item already exists, stock quantity will be added to current stock.</div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <label for="batch_number" class="form-label item-form-label">Batch number <?= ($hasProductBatchesTable || $hasProductPoNumberTable) ? '<span class="text-danger">*</span>' : '' ?></label>
                                    <input
                                        type="text"
                                        id="batch_number"
                                        name="batch_number"
                                        class="form-control item-form-input"
                                        value="<?= htmlspecialchars($formData['batch_number']) ?>"
                                        placeholder="Batch no."
                                        <?= ($hasProductBatchesTable || $hasProductPoNumberTable) ? 'required' : '' ?>
                                    >
                                    <?php if ($isEditMode): ?>
                                        <input type="hidden" name="original_batch_number" value="<?= htmlspecialchars($formData['batch_number']) ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <label for="uom" class="form-label item-form-label">Unit of measure (UOM) <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        id="uom"
                                        name="uom"
                                        class="form-control item-form-input"
                                        list="uomRecommendationsList"
                                        value="<?= htmlspecialchars($formData['uom']) ?>"
                                        placeholder="e.g. Box, Bottle, Unit"
                                        required
                                    >
                                    <datalist id="uomRecommendationsList">
                                    </datalist>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <label for="program" class="form-label item-form-label">Program</label>
                                    <input
                                        type="text"
                                        id="program"
                                        name="program"
                                        class="form-control item-form-input"
                                        list="programRecommendationsList"
                                        value="<?= htmlspecialchars($formData['program']) ?>"
                                        placeholder="e.g. EPI, MCH, TB"
                                    >
                                    <datalist id="programRecommendationsList">
                                    </datalist>
                                </div>
                            </div>
                        </section>

                        <section class="item-form-section mb-4">
                            <h3 class="item-form-section-title">Inventory &amp; pricing</h3>
                            <div class="row g-3">
                                <div class="col-sm-6 col-md-4">
                                    <label for="stock" class="form-label item-form-label">Stock quantity <?= ($hasProductBatchesTable || $hasProductPoNumberTable) ? '<span class="text-danger">*</span>' : '' ?></label>
                                    <input
                                        type="number"
                                        id="stock"
                                        name="stock"
                                        class="form-control item-form-input"
                                        min="1"
                                        step="1"
                                        value="<?= htmlspecialchars($formData['stock']) ?>"
                                        placeholder="1"
                                        <?= ($hasProductBatchesTable || $hasProductPoNumberTable) ? 'required' : '' ?>
                                    >
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <label for="cost_per_unit" class="form-label item-form-label">Cost per unit <span class="text-danger">*</span></label>
                                    <input
                                        type="number"
                                        id="cost_per_unit"
                                        name="cost_per_unit"
                                        class="form-control item-form-input"
                                        step="0.01"
                                        min="0"
                                        value="<?= htmlspecialchars($formData['cost_per_unit']) ?>"
                                        placeholder="0.00"
                                        required
                                    >
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <label for="expiry_date" class="form-label item-form-label">Expiry date</label>
                                    <input
                                        type="text"
                                        id="expiry_date"
                                        name="expiry_date"
                                        class="form-control item-form-input js-flatpickr-date"
                                        placeholder="mm/dd/yyyy"
                                        value="<?= htmlspecialchars($formData['expiry_date']) ?>"
                                    >
                                </div>
                            </div>
                        </section>

                        <section class="item-form-section mb-4">
                            <h3 class="item-form-section-title">Procurement</h3>
                            <div class="row g-3">
                                <div class="col-sm-6 col-md-4">
                                    <label for="po_no" class="form-label item-form-label">PO number</label>
                                    <input
                                        type="text"
                                        id="po_no"
                                        name="po_no"
                                        class="form-control item-form-input"
                                        list="poNoRecommendationsList"
                                        value="<?= htmlspecialchars($formData['po_no']) ?>"
                                        placeholder="Purchase order number"
                                    >
                                    <datalist id="poNoRecommendationsList">
                                    </datalist>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <label for="supplier" class="form-label item-form-label">Supplier</label>
                                    <input
                                        type="text"
                                        id="supplier"
                                        name="supplier"
                                        class="form-control item-form-input"
                                        list="supplierRecommendationsList"
                                        value="<?= htmlspecialchars($formData['supplier']) ?>"
                                        placeholder="Supplier name"
                                    >
                                    <datalist id="supplierRecommendationsList">
                                    </datalist>
                                </div>
                            </div>
                        </section>

                        <section class="item-form-section mb-4">
                            <h3 class="item-form-section-title">Delivery</h3>
                            <div class="row g-3">
                                <div class="col-sm-6 col-md-6">
                                    <label for="place_of_delivery" class="form-label item-form-label">Place of delivery</label>
                                    <input
                                        type="text"
                                        id="place_of_delivery"
                                        name="place_of_delivery"
                                        class="form-control item-form-input"
                                        value="<?= htmlspecialchars($formData['place_of_delivery']) ?>"
                                        placeholder="Place of delivery"
                                    >
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label for="date_of_delivery" class="form-label item-form-label">Date of delivery</label>
                                    <input
                                        type="text"
                                        id="date_of_delivery"
                                        name="date_of_delivery"
                                        class="form-control item-form-input js-flatpickr-date"
                                        placeholder="mm/dd/yyyy"
                                        value="<?= htmlspecialchars($formData['date_of_delivery']) ?>"
                                        data-max-date="<?= date('Y-m-d') ?>"
                                    >
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <label for="delivery_term" class="form-label item-form-label">Delivery term</label>
                                    <input
                                        type="text"
                                        id="delivery_term"
                                        name="delivery_term"
                                        class="form-control item-form-input"
                                        value="<?= htmlspecialchars($formData['delivery_term']) ?>"
                                        placeholder="Term"
                                    >
                                </div>
                                <div class="col-sm-6 col-md-6">
                                    <label for="payment_term" class="form-label item-form-label">Payment term</label>
                                    <input
                                        type="text"
                                        id="payment_term"
                                        name="payment_term"
                                        class="form-control item-form-input"
                                        value="<?= htmlspecialchars($formData['payment_term']) ?>"
                                        placeholder="Payment term"
                                    >
                                </div>
                            </div>
                        </section>
                    </form>
                </div>
                <div class="modal-footer border-top bg-light px-4 py-3">
                    <?php if ($isEditMode): ?>
                        <a href="<?= htmlspecialchars(buildItemListUrl($search, $sort)) ?>" class="btn btn-outline-secondary item-list-uniform-btn">Cancel</a>
                    <?php endif; ?>
                    <button type="submit" form="itemFormModalForm" class="btn btn-primary item-list-uniform-btn ms-auto">
                        <?= $isEditMode ? 'Update item' : 'Add item' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="itemDetailsModal" tabindex="-1" aria-labelledby="itemDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content item-details-modal-content">
                <div class="modal-header border-0 pb-2">
                    <div>
                        <h2 class="modal-title h5 mb-1 fw-semibold" id="itemDetailsModalLabel">Item Full Details</h2>
                        <p class="mb-0 text-muted small">Comprehensive view of item, inventory, procurement, and delivery fields.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <section class="item-details-section">
                                <h3 class="item-details-section-title">Item Details</h3>
                                <table class="table table-sm align-middle mb-0 item-details-table">
                                    <tbody>
                                        <tr>
                                            <th>No.</th>
                                            <td id="detail_item_no">-</td>
                                        </tr>
                                        <tr>
                                            <th>Product Description</th>
                                            <td id="detail_product_description">-</td>
                                        </tr>
                                        <tr>
                                            <th>Batch Number</th>
                                            <td id="detail_batch_number">-</td>
                                        </tr>
                                        <tr>
                                            <th>UOM</th>
                                            <td id="detail_uom">-</td>
                                        </tr>
                                        <tr>
                                            <th>Program</th>
                                            <td id="detail_program">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </section>
                        </div>
                        <div class="col-lg-6">
                            <section class="item-details-section">
                                <h3 class="item-details-section-title">Inventory &amp; Procurement</h3>
                                <table class="table table-sm align-middle mb-0 item-details-table">
                                    <tbody>
                                        <tr>
                                            <th>Stock</th>
                                            <td id="detail_stock">-</td>
                                        </tr>
                                        <tr>
                                            <th>Cost Per Unit</th>
                                            <td id="detail_cost_per_unit">-</td>
                                        </tr>
                                        <tr>
                                            <th>Expiry Date</th>
                                            <td id="detail_expiry_date">-</td>
                                        </tr>
                                        <tr>
                                            <th>PO Number</th>
                                            <td id="detail_po_no">-</td>
                                        </tr>
                                        <tr>
                                            <th>Supplier</th>
                                            <td id="detail_supplier">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </section>
                        </div>
                        <div class="col-12">
                            <section class="item-details-section">
                                <h3 class="item-details-section-title">Delivery</h3>
                                <table class="table table-sm align-middle mb-0 item-details-table">
                                    <tbody>
                                        <tr>
                                            <th>Place of Delivery</th>
                                            <td id="detail_place_of_delivery">-</td>
                                        </tr>
                                        <tr>
                                            <th>Date of Delivery</th>
                                            <td id="detail_date_of_delivery">-</td>
                                        </tr>
                                        <tr>
                                            <th>Delivery Term</th>
                                            <td id="detail_delivery_term">-</td>
                                        </tr>
                                        <tr>
                                            <th>Payment Term</th>
                                            <td id="detail_payment_term">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </section>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary item-list-uniform-btn ms-auto" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        window.itemListConfig = {
            showFormModal: <?= $showFormModal ? 'true' : 'false' ?>,
            productDescriptionOptions: <?= json_encode(array_values($productDescriptionOptions), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            productAttributesMap: <?= json_encode($productAttributesMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        };

        document.addEventListener('DOMContentLoaded', function () {
            if (typeof flatpickr !== 'function') {
                return;
            }

            function formatDigitsToUsDate(value) {
                var digits = String(value || '').replace(/\D/g, '').slice(0, 8);
                if (digits.length <= 2) {
                    return digits;
                }
                if (digits.length <= 4) {
                    return digits.slice(0, 2) + '/' + digits.slice(2);
                }
                return digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4);
            }

            function parseUsDateToIso(rawValue) {
                var value = String(rawValue || '').trim();
                if (value === '') {
                    return null;
                }

                var month;
                var day;
                var year;
                var parts = value.split('/');

                if (parts.length === 3) {
                    month = parseInt(parts[0], 10);
                    day = parseInt(parts[1], 10);
                    year = parseInt(parts[2], 10);
                    if (parts[2].length !== 4) {
                        return null;
                    }
                } else {
                    var digits = value.replace(/\D/g, '');
                    if (digits.length !== 8) {
                        return null;
                    }
                    month = parseInt(digits.slice(0, 2), 10);
                    day = parseInt(digits.slice(2, 4), 10);
                    year = parseInt(digits.slice(4, 8), 10);
                }

                if (!month || !day || !year || month < 1 || month > 12 || day < 1 || day > 31) {
                    return null;
                }

                var checkDate = new Date(year, month - 1, day);
                if (
                    checkDate.getFullYear() !== year
                    || checkDate.getMonth() !== month - 1
                    || checkDate.getDate() !== day
                ) {
                    return null;
                }

                var mm = String(month).padStart(2, '0');
                var dd = String(day).padStart(2, '0');
                return year + '-' + mm + '-' + dd;
            }

            function enforceMaxDate(isoDate, maxDateIso) {
                if (!isoDate || !maxDateIso) {
                    return isoDate;
                }
                return isoDate > maxDateIso ? null : isoDate;
            }

            document.querySelectorAll('.js-flatpickr-date').forEach(function (input) {
                flatpickr(input, {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'm/d/Y',
                    allowInput: true,
                    disableMobile: true,
                    maxDate: input.dataset.maxDate || null,
                    onReady: function (_, __, instance) {
                        var visibleInput = instance.altInput || instance.input;
                        visibleInput.setAttribute('placeholder', 'mm/dd/yyyy');
                        visibleInput.setAttribute('inputmode', 'numeric');
                        visibleInput.setAttribute('autocomplete', 'off');

                        visibleInput.addEventListener('input', function () {
                            var masked = formatDigitsToUsDate(visibleInput.value);
                            if (visibleInput.value !== masked) {
                                visibleInput.value = masked;
                            }
                            visibleInput.setCustomValidity('');
                        });

                        var commitTypedDate = function () {
                            var typedIso = parseUsDateToIso(visibleInput.value);
                            typedIso = enforceMaxDate(typedIso, input.dataset.maxDate || '');
                            if (visibleInput.value.trim() === '') {
                                instance.clear();
                                visibleInput.setCustomValidity('');
                                return;
                            }
                            if (!typedIso) {
                                visibleInput.setCustomValidity('Enter date as mm/dd/yyyy.');
                                return;
                            }
                            visibleInput.setCustomValidity('');
                            instance.setDate(typedIso, true, 'Y-m-d');
                        };

                        visibleInput.addEventListener('blur', commitTypedDate);
                        visibleInput.addEventListener('keydown', function (event) {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                commitTypedDate();
                                instance.close();
                            }
                        });
                    },
                    onChange: function (_, __, instance) {
                        var visibleInput = instance.altInput || instance.input;
                        visibleInput.setCustomValidity('');
                    }
                });
            });
        });
    </script>
    <script src="assets/js/smooth_motion.js?v=20260325"></script>
    <script src="assets/js/item_list.js"></script>
</body>
</html>
