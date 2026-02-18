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
$descriptionOptions = [];
$programOptions = [];
$batchNumberOptions = [];
$batchNumbersByDescription = [];
$unitCostByDescription = [];
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );
    $batchColumnStmt = $pdo->query("SHOW COLUMNS FROM inventory_records LIKE 'batch_number'");
    if (!$batchColumnStmt || !$batchColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE inventory_records ADD COLUMN batch_number VARCHAR(100) DEFAULT NULL AFTER description');
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

            $formData['record_date'] = trim((string) ($_POST['record_date'] ?? ''));
            $formData['ptr_no'] = trim((string) ($_POST['ptr_no'] ?? ''));
            $formData['description'] = trim((string) ($_POST['description'] ?? ''));
            $formData['batch_number'] = trim((string) ($_POST['batch_number'] ?? ''));
            $formData['program'] = trim((string) ($_POST['program'] ?? ''));
            $formData['unit'] = trim((string) ($_POST['unit'] ?? ''));
            $formData['expiration_date'] = trim((string) ($_POST['expiration_date'] ?? ''));
            $formData['quantity'] = trim((string) ($_POST['quantity'] ?? ''));
            $formData['recipient'] = trim((string) ($_POST['recipient'] ?? ''));

            if ($editingId <= 0) {
                $formErrors[] = 'Invalid transaction selected for update.';
            }
            if ($formData['record_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['record_date'])) {
                $formErrors[] = 'Record date is required and must be valid.';
            }
            if ($formData['description'] === '') {
                $formErrors[] = 'Description is required.';
            }
            if ($formData['quantity'] === '' || !ctype_digit($formData['quantity']) || (int) $formData['quantity'] <= 0) {
                $formErrors[] = 'Quantity must be a positive whole number.';
            }
            if ($formData['expiration_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['expiration_date'])) {
                $formErrors[] = 'Expiration date must be a valid date.';
            }
            if (empty($formErrors)) {
                $updateStmt = $pdo->prepare('
                    UPDATE inventory_records
                    SET
                        record_date = ?,
                        ptr_no = ?,
                        description = ?,
                        batch_number = ?,
                        program = ?,
                        unit = ?,
                        expiration_date = ?,
                        quantity = ?,
                        recipient = ?
                    WHERE id = ?
                ');
                $updateStmt->execute([
                    $formData['record_date'],
                    $formData['ptr_no'] !== '' ? $formData['ptr_no'] : null,
                    $formData['description'],
                    $formData['batch_number'] !== '' ? $formData['batch_number'] : null,
                    $formData['program'] !== '' ? $formData['program'] : null,
                    $formData['unit'] !== '' ? $formData['unit'] : null,
                    $formData['expiration_date'] !== '' ? $formData['expiration_date'] : null,
                    (int) $formData['quantity'],
                    $formData['recipient'] !== '' ? $formData['recipient'] : null,
                    $editingId,
                ]);
                header('Location: ' . buildReportUrl($returnSearch, $returnDateFrom, $returnDateTo, $returnSort, 'Transaction updated.'));
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
            if (empty($addFormErrors)) {
                $insertStmt = $pdo->prepare('
                    INSERT INTO inventory_records
                        (record_date, ptr_no, description, batch_number, program, unit, expiration_date, quantity, unit_cost, recipient)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $insertStmt->execute([
                    $addFormData['record_date'],
                    $addFormData['ptr_no'] !== '' ? $addFormData['ptr_no'] : null,
                    $addFormData['description'],
                    $addFormData['batch_number'] !== '' ? $addFormData['batch_number'] : null,
                    $addFormData['program'] !== '' ? $addFormData['program'] : null,
                    $addFormData['unit'] !== '' ? $addFormData['unit'] : null,
                    $addFormData['expiration_date'] !== '' ? $addFormData['expiration_date'] : null,
                    (int) $addFormData['quantity'],
                    $addFormData['unit_cost'] !== '' ? (float) $addFormData['unit_cost'] : 0.00,
                    $addFormData['recipient'] !== '' ? $addFormData['recipient'] : null,
                ]);
                header('Location: ' . buildReportUrl($returnSearch, $returnDateFrom, $returnDateTo, $returnSort, 'Transaction added.'));
                exit;
            }

            $showAddModal = true;
            $search = $returnSearch;
            $dateFrom = $returnDateFrom;
            $dateTo = $returnDateTo;
            $sort = $returnSort;
            $orderByDirection = $sort === 'asc' ? 'ASC' : 'DESC';
        }
    }

    if (!$isEditMode && isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
        $editingId = (int) $_GET['edit'];
        if ($editingId > 0) {
            $editStmt = $pdo->prepare('
                SELECT id, record_date, ptr_no, description, batch_number, program, unit, expiration_date, quantity, recipient
                FROM inventory_records
                WHERE id = ?
                LIMIT 1
            ');
            $editStmt->execute([$editingId]);
            $editingRecord = $editStmt->fetch();
            if ($editingRecord) {
                $isEditMode = true;
                $showEditModal = true;
                $formData = [
                    'record_date' => (string) ($editingRecord['record_date'] ?? ''),
                    'ptr_no' => (string) ($editingRecord['ptr_no'] ?? ''),
                    'description' => (string) ($editingRecord['description'] ?? ''),
                    'batch_number' => (string) ($editingRecord['batch_number'] ?? ''),
                    'program' => (string) ($editingRecord['program'] ?? ''),
                    'unit' => (string) ($editingRecord['unit'] ?? ''),
                    'expiration_date' => (string) ($editingRecord['expiration_date'] ?? ''),
                    'quantity' => (string) ($editingRecord['quantity'] ?? ''),
                    'recipient' => (string) ($editingRecord['recipient'] ?? ''),
                ];
            }
        }
    }

    if (!$showAddModal && isset($_GET['add']) && ctype_digit($_GET['add'])) {
        $addingRefId = (int) $_GET['add'];
        if ($addingRefId > 0) {
            $addRefStmt = $pdo->prepare('
                SELECT record_date, ptr_no, description, batch_number, program, unit, expiration_date, quantity, unit_cost, recipient
                FROM inventory_records
                WHERE id = ?
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
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(ptr_no LIKE :q OR recipient LIKE :q OR description LIKE :q OR batch_number LIKE :q OR program LIKE :q OR unit LIKE :q)';
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
            SELECT id, expiration_date, unit, description, batch_number, quantity, unit_cost, program, recipient, ptr_no, record_date
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
    <link rel="stylesheet" href="style.css?v=20260212">
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
                    <small class="fw-normal" style="font-size: 0.72rem;">Transaction History</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="create_ptr.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Create PTR</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
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
                                <div class="report-summary-value"><?= number_format(count($records)) ?></div>
                            </div>
                            <div class="report-summary-box">
                                <div class="report-summary-label">Total PTR Groups</div>
                                <div class="report-summary-value"><?= number_format(count($groupedRecords)) ?></div>
                            </div>
                            <div class="report-summary-box">
                                <div class="report-summary-label">Grand Total Amount</div>
                                <div class="report-summary-value">PHP <?= number_format($grandTotal, 2) ?></div>
                            </div>
                        </div>
                        <?php if (empty($records)): ?>
                            <div class="alert alert-info py-2 mb-0">
                                No transaction history found.
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
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary btn-sm report-print-btn"
                                                data-print-target="<?= htmlspecialchars($groupPreviewId) ?>"
                                            >
                                                Print
                                            </button>
                                            <?php $groupRefId = isset($group['items'][0]['id']) ? (int) $group['items'][0]['id'] : 0; ?>
                                            <?php if ($groupRefId > 0): ?>
                                                <a
                                                    href="<?= htmlspecialchars(buildReportUrl($search, $dateFrom, $dateTo, $sort, '', 0, $groupRefId)) ?>"
                                                    class="btn btn-outline-primary btn-sm"
                                                >
                                                    Add
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="table-responsive report-group-table-wrap">
                                        <table class="table table-striped table-hover align-middle mb-0 report-group-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th scope="col" class="report-col-description">Description</th>
                                                    <th scope="col" class="report-col-batch">Batch Number</th>
                                                    <th scope="col" class="report-col-program">Program</th>
                                                    <th scope="col" class="report-col-unit">Unit (OUM)</th>
                                                    <th scope="col" class="report-col-expiry">Expiration Date</th>
                                                    <th scope="col" class="report-col-qty text-end">Summary of Quantity</th>
                                                    <th scope="col" class="report-col-action text-end">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($group['items'] as $record): ?>
                                                    <tr>
                                                        <td class="report-col-description report-wrap-text" title="<?= htmlspecialchars((string) ($record['description'] ?? '-')) ?>">
                                                            <?= htmlspecialchars($record['description'] ?? '-') ?>
                                                        </td>
                                                        <td class="report-col-batch report-wrap-text" title="<?= htmlspecialchars((string) ($record['batch_number'] ?? '-')) ?>">
                                                            <?= htmlspecialchars($record['batch_number'] ?? '-') ?>
                                                        </td>
                                                        <td class="report-col-program report-wrap-text" title="<?= htmlspecialchars((string) ($record['program'] ?? '-')) ?>">
                                                            <?= htmlspecialchars($record['program'] ?? '-') ?>
                                                        </td>
                                                        <td class="report-col-unit"><?= htmlspecialchars($record['unit'] ?? '-') ?></td>
                                                        <td class="report-col-expiry text-nowrap"><?= htmlspecialchars($record['expiration_date'] ?? '-') ?></td>
                                                        <td class="report-col-qty text-end text-nowrap"><?= (int) ($record['quantity'] ?? 0) ?></td>
                                                        <td class="report-col-action text-end">
                                                            <div class="d-inline-flex gap-1">
                                                                <a
                                                                    href="<?= htmlspecialchars(buildReportUrl($search, $dateFrom, $dateTo, $sort, '', (int) ($record['id'] ?? 0))) ?>"
                                                                    class="btn btn-outline-secondary btn-sm"
                                                                >
                                                                    Edit
                                                                </a>
                                                                <form method="post" action="report.php" onsubmit="return confirm('Delete this transaction?');">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="id" value="<?= (int) ($record['id'] ?? 0) ?>">
                                                                    <input type="hidden" name="return_q" value="<?= htmlspecialchars($search) ?>">
                                                                    <input type="hidden" name="return_date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                                                                    <input type="hidden" name="return_date_to" value="<?= htmlspecialchars($dateTo) ?>">
                                                                    <input type="hidden" name="return_sort" value="<?= htmlspecialchars($sort) ?>">
                                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
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
                                                    <th>Program/PO No.</th>
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
                                                    </tr>
                                                    <?php $renderedRows++; ?>
                                                <?php endforeach; ?>
                                                <?php for ($i = $renderedRows; $i < $previewLineRows; $i++): ?>
                                                    <tr>
                                                        <td>&nbsp;</td>
                                                        <td>&nbsp;</td>
                                                        <td>&nbsp;</td>
                                                        <td>&nbsp;</td>
                                                        <td>&nbsp;</td>
                                                        <td>&nbsp;</td>
                                                        <td>&nbsp;</td>
                                                    </tr>
                                                <?php endfor; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="5" class="text-end"><strong>TOTAL:</strong></td>
                                                    <td><?= htmlspecialchars(number_format($groupTotal, 2, '.', '')) ?></td>
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
                                                        <?= htmlspecialchars(date('m/d/Y')) ?>
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
                                </section>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0" id="editTransactionModalLabel">Edit Transaction</h2>
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

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="edit_record_date" class="form-label">Date</label>
                                <input type="date" id="edit_record_date" name="record_date" class="form-control" value="<?= htmlspecialchars($formData['record_date']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_ptr_no" class="form-label">PTR No.</label>
                                <input type="text" id="edit_ptr_no" name="ptr_no" class="form-control" value="<?= htmlspecialchars($formData['ptr_no']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_quantity" class="form-label">Summary of Quantity</label>
                                <input type="text" id="edit_quantity" name="quantity" class="form-control" value="<?= htmlspecialchars($formData['quantity']) ?>" inputmode="numeric" pattern="[0-9]*" required>
                            </div>
                            <div class="col-md-8">
                                <label for="edit_description" class="form-label">Description</label>
                                <input type="text" id="edit_description" name="description" class="form-control" value="<?= htmlspecialchars($formData['description']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_batch_number" class="form-label">Batch Number</label>
                                <input
                                    type="text"
                                    id="edit_batch_number"
                                    name="batch_number"
                                    class="form-control"
                                    list="reportEditBatchOptionsList"
                                    value="<?= htmlspecialchars($formData['batch_number']) ?>"
                                    placeholder="Type or select batch number"
                                >
                            </div>
                            <div class="col-md-4">
                                <label for="edit_program" class="form-label">Program</label>
                                <input
                                    type="text"
                                    id="edit_program"
                                    name="program"
                                    class="form-control"
                                    list="reportProgramOptionsList"
                                    value="<?= htmlspecialchars($formData['program']) ?>"
                                    placeholder="Type or select program"
                                >
                            </div>
                            <div class="col-md-4">
                                <label for="edit_unit" class="form-label">Unit (OUM)</label>
                                <input type="text" id="edit_unit" name="unit" class="form-control" value="<?= htmlspecialchars($formData['unit']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_expiration_date" class="form-label">Expiration Date</label>
                                <input type="date" id="edit_expiration_date" name="expiration_date" class="form-control" value="<?= htmlspecialchars($formData['expiration_date']) ?>" readonly>
                            </div>
                            <div class="col-md-8">
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
        const reportBatchNumbersByDescription = <?= json_encode($batchNumbersByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const reportUnitCostByDescription = <?= json_encode($unitCostByDescription, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const reportBatchNumbersByDescriptionLower = Object.keys(reportBatchNumbersByDescription).reduce(function (acc, key) {
            acc[String(key).trim().toLowerCase()] = reportBatchNumbersByDescription[key];
            return acc;
        }, {});
        const reportUnitCostByDescriptionLower = Object.keys(reportUnitCostByDescription).reduce(function (acc, key) {
            acc[String(key).trim().toLowerCase()] = reportUnitCostByDescription[key];
            return acc;
        }, {});

        function reportEscapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function updateReportBatchOptions(descriptionInputId, datalistId) {
            const descriptionInput = document.getElementById(descriptionInputId);
            const datalist = document.getElementById(datalistId);
            if (!descriptionInput || !datalist) {
                return;
            }
            const description = String(descriptionInput.value || '').trim();
            if (description === '') {
                datalist.innerHTML = '';
                return;
            }
            const exactOptions = reportBatchNumbersByDescription[description];
            const lowerOptions = reportBatchNumbersByDescriptionLower[description.toLowerCase()];
            const options = Array.isArray(exactOptions) ? exactOptions : (Array.isArray(lowerOptions) ? lowerOptions : []);
            datalist.innerHTML = options
                .map(function (batchNo) { return '<option value="' + reportEscapeHtml(batchNo) + '"></option>'; })
                .join('');
        }

        function updateReportUnitCost(descriptionInputId, unitCostInputId) {
            const descriptionInput = document.getElementById(descriptionInputId);
            const unitCostInput = document.getElementById(unitCostInputId);
            if (!descriptionInput || !unitCostInput) {
                return;
            }
            const description = String(descriptionInput.value || '').trim();
            if (description === '') {
                unitCostInput.value = '';
                return;
            }
            const exactUnitCost = reportUnitCostByDescription[description];
            const lowerUnitCost = reportUnitCostByDescriptionLower[description.toLowerCase()];
            const selectedUnitCost = exactUnitCost !== undefined ? exactUnitCost : lowerUnitCost;
            unitCostInput.value = selectedUnitCost !== undefined ? String(selectedUnitCost) : '';
        }

        function bindReportDescriptionDependencies(descriptionInputId, datalistId, unitCostInputId) {
            const descriptionInput = document.getElementById(descriptionInputId);
            if (!descriptionInput) {
                return;
            }
            const refresh = function () {
                updateReportBatchOptions(descriptionInputId, datalistId);
                if (unitCostInputId) {
                    updateReportUnitCost(descriptionInputId, unitCostInputId);
                }
            };
            descriptionInput.addEventListener('input', refresh);
            descriptionInput.addEventListener('change', refresh);
            refresh();
        }

        document.addEventListener('DOMContentLoaded', function () {
            bindReportDescriptionDependencies('edit_description', 'reportEditBatchOptionsList', null);
            bindReportDescriptionDependencies('add_description', 'reportAddBatchOptionsList', 'add_unit_cost');

            document.querySelectorAll('.report-print-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const targetId = btn.getAttribute('data-print-target');
                    const target = targetId ? document.getElementById(targetId) : null;
                    if (!target) {
                        return;
                    }
                    const printWindow = window.open('', '_blank', 'width=1100,height=800');
                    if (!printWindow) {
                        alert('Unable to open print window. Please allow pop-ups for this site.');
                        return;
                    }
                    const printableHtml = target.outerHTML;
                    printWindow.document.write(
                        '<!DOCTYPE html><html><head><title>PTR Preview</title>' +
                        '<base href="' + window.location.href + '">' +
                        '<style>' +
                        '@page{size:A4 landscape;margin:8mm;}' +
                        'body{font-family:Arial,sans-serif;padding:0;margin:0;background:#fff;color:#111;}' +
                        '.preview-sheet{border:1px solid #222;padding:8px;max-width:100%;}' +
                        '.preview-sheet table{width:100%;border-collapse:collapse;font-size:11px;}' +
                        '.preview-sheet th,.preview-sheet td{border:1px solid #222;padding:4px 6px;vertical-align:top;}' +
                        '.preview-header{display:grid;grid-template-columns:48px auto 48px;align-items:center;column-gap:12px;margin-bottom:8px;justify-content:center;}' +
                        '.preview-title{font-weight:700;font-size:18px;text-align:center;margin:0;}' +
                        '.preview-logo-wrap{width:48px;height:48px;display:flex;align-items:center;justify-content:center;}' +
                        '.preview-logo-wrap img{width:46px;height:46px;object-fit:contain;}' +
                        '.preview-label{font-weight:700;}' +
                        '.signatory-table td{text-align:center;vertical-align:middle;height:84px;}' +
                        '.signatory-content{display:inline-block;text-align:center;line-height:1.4;}' +
                        '.signatory-label{display:block;margin-bottom:8px;}' +
                        '.received-box{width:100%;display:flex;flex-direction:column;align-items:center;justify-content:space-between;min-height:82px;padding:2px 0;}' +
                        '.received-top{display:flex;align-items:center;justify-content:center;}' +
                        '.received-bottom{border:0;padding:0;font-size:8px;line-height:1.1;white-space:nowrap;}' +
                        '.text-end{text-align:right;}' +
                        '</style></head><body>' + printableHtml + '</body></html>'
                    );
                    printWindow.document.close();
                    printWindow.focus();
                    printWindow.print();
                });
            });
        });
    </script>
    <?php if ($showEditModal): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalElement = document.getElementById('editTransactionModal');
                if (!modalElement) {
                    return;
                }
                var qtyInput = document.getElementById('edit_quantity');
                if (qtyInput) {
                    qtyInput.addEventListener('input', function () {
                        qtyInput.value = qtyInput.value.replace(/\D+/g, '');
                    });
                }
                var modal = new bootstrap.Modal(modalElement);
                modal.show();
            });
        </script>
    <?php endif; ?>
    <?php if ($showAddModal): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var addModalElement = document.getElementById('addTransactionModal');
                if (!addModalElement) {
                    return;
                }
                var addQtyInput = document.getElementById('add_quantity');
                if (addQtyInput) {
                    addQtyInput.addEventListener('input', function () {
                        addQtyInput.value = addQtyInput.value.replace(/\D+/g, '');
                    });
                }
                var addModal = new bootstrap.Modal(addModalElement);
                addModal.show();
            });
        </script>
    <?php endif; ?>
</body>
</html>
