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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );

    $expiryColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'expiry_date'");
    if ($expiryColumnStmt && $expiryColumnStmt->fetch()) {
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
    $productBatchesStmt = $pdo->query("SHOW TABLES LIKE 'product_batches'");
    if ($productBatchesStmt && $productBatchesStmt->fetch()) {
        $hasProductBatchesTable = true;
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
        } elseif ($action === 'create' || $action === 'update') {
            $formData['product_description'] = trim($_POST['product_description'] ?? '');
            $formData['batch_number'] = trim($_POST['batch_number'] ?? '');
            $formData['uom'] = trim($_POST['uom'] ?? '');
            $formData['stock'] = trim($_POST['stock'] ?? '0');
            $formData['cost_per_unit'] = trim($_POST['cost_per_unit'] ?? '');
            $formData['expiry_date'] = trim($_POST['expiry_date'] ?? '');
            $formData['program'] = trim($_POST['program'] ?? '');
            $formData['po_no'] = trim($_POST['po_no'] ?? '');
            $formData['place_of_delivery'] = trim($_POST['place_of_delivery'] ?? '');
            $formData['date_of_delivery'] = trim($_POST['date_of_delivery'] ?? '');
            $formData['delivery_term'] = trim($_POST['delivery_term'] ?? '');
            $formData['payment_term'] = trim($_POST['payment_term'] ?? '');

            if ($formData['product_description'] === '') {
                $errors[] = 'Description is required.';
            }
            if ($formData['uom'] === '') {
                $errors[] = 'UOM is required.';
            }
            if ($formData['cost_per_unit'] === '' || !is_numeric($formData['cost_per_unit']) || (float) $formData['cost_per_unit'] < 0) {
                $errors[] = 'Cost per unit must be a valid non-negative number.';
            }
            if ($hasProductBatchesTable && $formData['batch_number'] === '') {
                $errors[] = 'Batch number is required.';
            }
            if ($hasProductBatchesTable && ($formData['stock'] === '' || !ctype_digit($formData['stock']))) {
                $errors[] = 'Stock must be a valid non-negative whole number.';
            }
            if ($hasProductsExpiryDate && $formData['expiry_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['expiry_date'])) {
                $errors[] = 'Expiry date must be a valid date.';
            }
            if ($formData['date_of_delivery'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['date_of_delivery'])) {
                $errors[] = 'Date of delivery must be a valid date.';
            }

            if (empty($errors)) {
                if ($action === 'create') {
                    $newProductId = null;
                    if ($hasProductsExpiryDate) {
                        $insertStmt = $pdo->prepare(
                            'INSERT INTO products (
                                product_description,
                                uom,
                                cost_per_unit,
                                expiry_date,
                                program,
                                po_no,
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
                            $formData['expiry_date'] !== '' ? $formData['expiry_date'] : null,
                            $formData['program'] !== '' ? $formData['program'] : null,
                            $formData['po_no'] !== '' ? $formData['po_no'] : null,
                            $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                            $formData['date_of_delivery'] !== '' ? $formData['date_of_delivery'] : null,
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
                                place_of_delivery,
                                date_of_delivery,
                                delivery_term,
                                payment_term
                            )
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        $insertStmt->execute([
                            $formData['product_description'],
                            $formData['uom'],
                            (float) $formData['cost_per_unit'],
                            $formData['program'] !== '' ? $formData['program'] : null,
                            $formData['po_no'] !== '' ? $formData['po_no'] : null,
                            $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                            $formData['date_of_delivery'] !== '' ? $formData['date_of_delivery'] : null,
                            $formData['delivery_term'] !== '' ? $formData['delivery_term'] : null,
                            $formData['payment_term'] !== '' ? $formData['payment_term'] : null,
                        ]);
                        $newProductId = (int) $pdo->lastInsertId();
                    }
                    if ($hasProductBatchesTable && $newProductId > 0 && $formData['batch_number'] !== '') {
                        $batchInsertStmt = $pdo->prepare(
                            'INSERT INTO product_batches (product_id, batch_number, stock_quantity, expiry_date)
                             VALUES (?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE
                                 stock_quantity = VALUES(stock_quantity),
                                 expiry_date = VALUES(expiry_date)'
                        );
                        $batchInsertStmt->execute([
                            $newProductId,
                            $formData['batch_number'],
                            (int) $formData['stock'],
                            $formData['expiry_date'] !== '' ? $formData['expiry_date'] : null,
                        ]);
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
                                place_of_delivery,
                                date_of_delivery,
                                delivery_term,
                                payment_term,
                                added_by
                            )
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $historyStmt->execute([
                        $newProductId > 0 ? $newProductId : null,
                        $formData['product_description'],
                        $formData['uom'],
                        (float) $formData['cost_per_unit'],
                        $formData['expiry_date'] !== '' ? $formData['expiry_date'] : null,
                        $formData['program'] !== '' ? $formData['program'] : null,
                        $formData['po_no'] !== '' ? $formData['po_no'] : null,
                        $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                        $formData['date_of_delivery'] !== '' ? $formData['date_of_delivery'] : null,
                        $formData['delivery_term'] !== '' ? $formData['delivery_term'] : null,
                        $formData['payment_term'] !== '' ? $formData['payment_term'] : null,
                        $username,
                    ]);
                    header('Location: ' . buildItemListUrl($returnSearch, $returnSort, 'Item added.'));
                    exit;
                }

                $updateId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
                if ($updateId > 0) {
                    $originalBatchNumber = trim((string) ($_POST['original_batch_number'] ?? ''));
                    if ($hasProductsExpiryDate) {
                        $updateStmt = $pdo->prepare(
                            'UPDATE products
                             SET product_description = ?,
                                 uom = ?,
                                 cost_per_unit = ?,
                                 expiry_date = ?,
                                 program = ?,
                                 po_no = ?,
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
                            $formData['expiry_date'] !== '' ? $formData['expiry_date'] : null,
                            $formData['program'] !== '' ? $formData['program'] : null,
                            $formData['po_no'] !== '' ? $formData['po_no'] : null,
                            $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                            $formData['date_of_delivery'] !== '' ? $formData['date_of_delivery'] : null,
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
                            $formData['place_of_delivery'] !== '' ? $formData['place_of_delivery'] : null,
                            $formData['date_of_delivery'] !== '' ? $formData['date_of_delivery'] : null,
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
                                $formData['expiry_date'] !== '' ? $formData['expiry_date'] : null,
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
                                $formData['expiry_date'] !== '' ? $formData['expiry_date'] : null,
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
        if ($editingId > 0) {
            if ($hasProductBatchesTable) {
                $editSelect = $hasProductsExpiryDate
                    ? 'SELECT
                           p.id,
                           p.product_description,
                           p.uom,
                           p.cost_per_unit,
                           COALESCE(b.expiry_date, p.expiry_date) AS expiry_date,
                           p.program,
                           p.po_no,
                           p.place_of_delivery,
                           p.date_of_delivery,
                           p.delivery_term,
                           p.payment_term,
                           b.batch_number,
                           COALESCE(b.stock_quantity, 0) AS stock
                       FROM products p
                       LEFT JOIN product_batches b ON b.product_id = p.id
                       WHERE p.id = ? AND (? = "" OR b.batch_number = ?)
                       ORDER BY b.batch_number ASC
                       LIMIT 1'
                    : 'SELECT
                           p.id,
                           p.product_description,
                           p.uom,
                           p.cost_per_unit,
                           b.expiry_date AS expiry_date,
                           p.program,
                           p.po_no,
                           p.place_of_delivery,
                           p.date_of_delivery,
                           p.delivery_term,
                           p.payment_term,
                           b.batch_number,
                           COALESCE(b.stock_quantity, 0) AS stock
                       FROM products p
                       LEFT JOIN product_batches b ON b.product_id = p.id
                       WHERE p.id = ? AND (? = "" OR b.batch_number = ?)
                       ORDER BY b.batch_number ASC
                       LIMIT 1';
                $editStmt = $pdo->prepare($editSelect);
                $editStmt->execute([$editingId, $editingBatchNumber, $editingBatchNumber]);
            } else {
                $editSelect = $hasProductsExpiryDate
                    ? 'SELECT id, product_description, uom, cost_per_unit, expiry_date, program, po_no, place_of_delivery, date_of_delivery, delivery_term, payment_term, NULL AS batch_number, 0 AS stock FROM products WHERE id = ? LIMIT 1'
                    : 'SELECT id, product_description, uom, cost_per_unit, NULL AS expiry_date, program, po_no, place_of_delivery, date_of_delivery, delivery_term, payment_term, NULL AS batch_number, 0 AS stock FROM products WHERE id = ? LIMIT 1';
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
    if ($hasProductBatchesTable) {
        $batchJoinSql = 'LEFT JOIN product_batches b ON b.product_id = p.id';
        $batchSelectSql = 'b.batch_number AS batch_number, COALESCE(b.stock_quantity, 0) AS stock';
        $expirySelectSql = $hasProductsExpiryDate
            ? 'COALESCE(b.expiry_date, p.expiry_date)'
            : 'b.expiry_date';
    }
    $listSelect = '
        SELECT
            p.id,
            p.product_description,
            p.uom,
            p.cost_per_unit,
            ' . $expirySelectSql . ' AS expiry_date,
            p.program,
            p.po_no,
            p.place_of_delivery,
            p.date_of_delivery,
            p.delivery_term,
            p.payment_term,
            ' . $batchSelectSql . '
        FROM products p
        ' . $batchJoinSql;

    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmt = $pdo->prepare(
            $listSelect . '
             WHERE p.product_description LIKE :q
                OR p.uom LIKE :q
                OR COALESCE(batch_number, "") LIKE :q
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

    $historyStmt = $pdo->query('
        SELECT id, product_id, product_description, uom, cost_per_unit, expiry_date, program, added_by, added_at
        FROM item_add_history
        ORDER BY added_at DESC, id DESC
        LIMIT 50
    ');
    $itemAddHistory = $historyStmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Unable to load items right now. Please check your database setup.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item List - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260219">
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
                    <small class="fw-normal" style="font-size: 0.72rem;">Manage Items</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
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
                        <button type="button" class="btn btn-outline-neutral-black item-list-uniform-btn" data-bs-toggle="modal" data-bs-target="#itemFormModal">
                            Add Item
                        </button>
                    </div>

                    <form method="get" action="item_list.php" class="row g-2 mb-3">
                        <div class="col-md-9">
                            <input
                                type="text"
                                name="q"
                                class="form-control"
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search description, batch number, or unit"
                            >
                        </div>
                        <div class="col-md-3">
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
                        <div class="mb-2 small text-muted">Total items: <?= count($items) ?></div>
                        <?php if (empty($items)): ?>
                            <div class="alert alert-info py-2 mb-0">
                                No items found<?= $search !== '' ? ' for "' . htmlspecialchars($search) . '"' : '' ?>.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col" class="text-nowrap">No.</th>
                                            <th scope="col" class="text-nowrap">Product Description</th>
                                            <th scope="col" class="text-nowrap text-center">Batch Number</th>
                                            <th scope="col" class="text-nowrap text-center">UOM</th>
                                            <th scope="col" class="text-nowrap">Stock</th>
                                            <th scope="col" class="text-nowrap text-center">Cost Per Unit</th>
                                            <th scope="col" class="text-nowrap">Expiry Date</th>
                                            <th scope="col" class="text-end text-nowrap">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $index => $item): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($item['product_description'] ?? '') ?></td>
                                                <td class="text-center"><?= htmlspecialchars((string) ($item['batch_number'] ?? '-')) ?></td>
                                                <td class="text-center"><?= htmlspecialchars($item['uom'] ?? '-') ?></td>
                                                <td><?= (int) ($item['stock'] ?? 0) ?></td>
                                                <td class="text-center"><?= number_format((float) $item['cost_per_unit'], 2) ?></td>
                                                <td><?= htmlspecialchars($item['expiry_date'] ?? '-') ?></td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex gap-1">
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-neutral-black item-list-action-btn item-details-btn"
                                                            data-item-no="<?= (int) ($index + 1) ?>"
                                                            data-product-description="<?= htmlspecialchars((string) ($item['product_description'] ?? ''), ENT_QUOTES) ?>"
                                                            data-batch-number="<?= htmlspecialchars((string) ($item['batch_number'] ?? ''), ENT_QUOTES) ?>"
                                                            data-uom="<?= htmlspecialchars((string) ($item['uom'] ?? ''), ENT_QUOTES) ?>"
                                                            data-stock="<?= (int) ($item['stock'] ?? 0) ?>"
                                                            data-cost-per-unit="<?= htmlspecialchars(number_format((float) ($item['cost_per_unit'] ?? 0), 2, '.', ''), ENT_QUOTES) ?>"
                                                            data-expiry-date="<?= htmlspecialchars((string) ($item['expiry_date'] ?? ''), ENT_QUOTES) ?>"
                                                            data-program="<?= htmlspecialchars((string) ($item['program'] ?? ''), ENT_QUOTES) ?>"
                                                            data-po-no="<?= htmlspecialchars((string) ($item['po_no'] ?? ''), ENT_QUOTES) ?>"
                                                            data-place-of-delivery="<?= htmlspecialchars((string) ($item['place_of_delivery'] ?? ''), ENT_QUOTES) ?>"
                                                            data-date-of-delivery="<?= htmlspecialchars((string) ($item['date_of_delivery'] ?? ''), ENT_QUOTES) ?>"
                                                            data-delivery-term="<?= htmlspecialchars((string) ($item['delivery_term'] ?? ''), ENT_QUOTES) ?>"
                                                            data-payment-term="<?= htmlspecialchars((string) ($item['payment_term'] ?? ''), ENT_QUOTES) ?>"
                                                        >
                                                            Full Details
                                                        </button>
                                                        <a
                                                            href="<?= htmlspecialchars(buildItemListUrl($search, $sort, '', (int) $item['id'], (string) ($item['batch_number'] ?? ''))) ?>"
                                                            class="btn btn-outline-neutral-black item-list-action-btn"
                                                        >
                                                            Edit
                                                        </a>
                                                        <form method="post" action="item_list.php<?= $search !== '' || $sort === 'desc' ? '?' . http_build_query(['q' => $search, 'sort' => $sort === 'desc' ? 'desc' : null]) : '' ?>" onsubmit="return confirm('Delete this item?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                                            <input type="hidden" name="return_q" value="<?= htmlspecialchars($search) ?>">
                                                            <input type="hidden" name="return_sort" value="<?= htmlspecialchars($sort) ?>">
                                                            <button type="submit" class="btn btn-outline-neutral-black item-list-action-btn">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
                                        <th>ID</th>
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
                                            <td><?= (int) ($history['id'] ?? 0) ?></td>
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
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="itemFormModalLabel"><?= $isEditMode ? 'Edit Item' : 'Add New Item' ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($errors)): ?>
                        <?php foreach ($errors as $e): ?>
                            <div class="alert alert-danger py-2 mb-2"><?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <form method="post" action="item_list.php<?= $search !== '' || $sort === 'desc' ? '?' . http_build_query(['q' => $search, 'sort' => $sort === 'desc' ? 'desc' : null]) : '' ?>">
                        <input type="hidden" name="action" value="<?= $isEditMode ? 'update' : 'create' ?>">
                        <input type="hidden" name="return_q" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="return_sort" value="<?= htmlspecialchars($sort) ?>">
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="id" value="<?= (int) $editingId ?>">
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="product_description" class="form-label">Product Description</label>
                                <textarea
                                    id="product_description"
                                    name="product_description"
                                    class="form-control"
                                    rows="2"
                                    required
                                ><?= htmlspecialchars($formData['product_description']) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="batch_number" class="form-label">Batch Number</label>
                                <input
                                    type="text"
                                    id="batch_number"
                                    name="batch_number"
                                    class="form-control"
                                    value="<?= htmlspecialchars($formData['batch_number']) ?>"
                                    <?= $hasProductBatchesTable ? 'required' : '' ?>
                                >
                                <?php if ($isEditMode): ?>
                                    <input type="hidden" name="original_batch_number" value="<?= htmlspecialchars($formData['batch_number']) ?>">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="uom" class="form-label">UOM</label>
                                <input
                                    type="text"
                                    id="uom"
                                    name="uom"
                                    class="form-control"
                                    value="<?= htmlspecialchars($formData['uom']) ?>"
                                    required
                                >
                            </div>
                            <div class="col-md-6">
                                <label for="stock" class="form-label">Stock</label>
                                <input
                                    type="number"
                                    id="stock"
                                    name="stock"
                                    class="form-control"
                                    min="0"
                                    step="1"
                                    value="<?= htmlspecialchars($formData['stock']) ?>"
                                    <?= $hasProductBatchesTable ? 'required' : '' ?>
                                >
                            </div>
                            <div class="col-md-6">
                                <label for="cost_per_unit" class="form-label">Cost Per Unit</label>
                                <input
                                    type="number"
                                    id="cost_per_unit"
                                    name="cost_per_unit"
                                    class="form-control"
                                    step="0.01"
                                    min="0"
                                    value="<?= htmlspecialchars($formData['cost_per_unit']) ?>"
                                    required
                                >
                            </div>
                            <div class="col-md-6">
                                <label for="expiry_date" class="form-label">Expiry Date</label>
                                <input
                                    type="date"
                                    id="expiry_date"
                                    name="expiry_date"
                                    class="form-control"
                                    value="<?= htmlspecialchars($formData['expiry_date']) ?>"
                                >
                            </div>
                            <div class="col-md-6">
                                <label for="po_no" class="form-label">PO Number</label>
                                <input
                                    type="text"
                                    id="po_no"
                                    name="po_no"
                                    class="form-control"
                                    value="<?= htmlspecialchars($formData['po_no']) ?>"
                                    placeholder="PO Number"
                                >
                            </div>
                            <div class="col-md-6">
                                <label for="place_of_delivery" class="form-label">Place of Delivery</label>
                                <input
                                    type="text"
                                    id="place_of_delivery"
                                    name="place_of_delivery"
                                    class="form-control"
                                    value="<?= htmlspecialchars($formData['place_of_delivery']) ?>"
                                    placeholder="Place of Delivery"
                                >
                            </div>
                            <div class="col-md-6">
                                <label for="date_of_delivery" class="form-label">Date of Delivery</label>
                                <input
                                    type="date"
                                    id="date_of_delivery"
                                    name="date_of_delivery"
                                    class="form-control"
                                    value="<?= htmlspecialchars($formData['date_of_delivery']) ?>"
                                >
                            </div>
                            <div class="col-md-6">
                                <label for="delivery_term" class="form-label">Delivery Term</label>
                                <input
                                    type="text"
                                    id="delivery_term"
                                    name="delivery_term"
                                    class="form-control"
                                    value="<?= htmlspecialchars($formData['delivery_term']) ?>"
                                    placeholder="Delivery Term"
                                >
                            </div>
                            <div class="col-md-6">
                                <label for="payment_term" class="form-label">Payment Term</label>
                                <input
                                    type="text"
                                    id="payment_term"
                                    name="payment_term"
                                    class="form-control"
                                    value="<?= htmlspecialchars($formData['payment_term']) ?>"
                                    placeholder="Payment Term"
                                >
                            </div>
                        </div>
                        <input type="hidden" name="program" value="<?= htmlspecialchars($formData['program']) ?>">
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-outline-neutral-black item-list-uniform-btn">
                                <?= $isEditMode ? 'Update Item' : 'Add New Item' ?>
                            </button>
                            <?php if ($isEditMode): ?>
                                <a href="<?= htmlspecialchars(buildItemListUrl($search, $sort)) ?>" class="btn btn-outline-neutral-black item-list-uniform-btn">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="itemDetailsModal" tabindex="-1" aria-labelledby="itemDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="itemDetailsModalLabel">Item Full Details</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <tbody>
                                <tr>
                                    <th style="width: 34%">No.</th>
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
                                    <th>Program</th>
                                    <td id="detail_program">-</td>
                                </tr>
                                <tr>
                                    <th>PO Number</th>
                                    <td id="detail_po_no">-</td>
                                </tr>
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
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.itemListConfig = {
            showFormModal: <?= $showFormModal ? 'true' : 'false' ?>
        };
    </script>
    <script src="assets/js/item_list.js"></script>
</body>
</html>
