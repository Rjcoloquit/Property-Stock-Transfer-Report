<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/ptr_numbering.php';

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
$message = trim((string) ($_GET['msg'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$error = '';
$pendingGroups = [];

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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );
    $itemKeyColumnStmt = $pdo->query("SHOW COLUMNS FROM stock_cards LIKE 'item_key'");
    if (!$itemKeyColumnStmt || !$itemKeyColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE stock_cards ADD COLUMN item_key VARCHAR(400) DEFAULT NULL AFTER ledger_rows');
    }
    $sourceTypeColumnStmt = $pdo->query("SHOW COLUMNS FROM stock_cards LIKE 'source_type'");
    if (!$sourceTypeColumnStmt || !$sourceTypeColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE stock_cards ADD COLUMN source_type VARCHAR(30) NOT NULL DEFAULT "manual" AFTER item_key');
    }

    normalizeExistingPtrNumbers($pdo);

    $hasProductBatchesTable = false;
    $productBatchesStmt = $pdo->query("SHOW TABLES LIKE 'product_batches'");
    if ($productBatchesStmt && $productBatchesStmt->fetch()) {
        $hasProductBatchesTable = true;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string) ($_POST['action'] ?? ''));
        if ($action === 'release') {
            $releaseToken = trim((string) ($_POST['release_token'] ?? ''));
            $releasePtrNo = '';
            $releaseId = 0;
            if (str_starts_with($releaseToken, 'ptr:')) {
                $releasePtrNo = trim(substr($releaseToken, 4));
            } elseif (str_starts_with($releaseToken, 'id:')) {
                $releaseId = (int) trim(substr($releaseToken, 3));
            }

            if ($releasePtrNo === '' && $releaseId <= 0) {
                throw new RuntimeException('Invalid pending PTR selected for release.');
            }

            $pdo->beginTransaction();
            try {
                if ($releasePtrNo !== '') {
                    $pendingRowsStmt = $pdo->prepare('
                        SELECT id, ptr_no, record_date, recipient, description, batch_number, batch_id, unit, quantity, unit_cost, program, supplier
                        FROM inventory_records
                        WHERE ptr_no = ? AND COALESCE(release_status, "released") = "pending"
                        ORDER BY id ASC
                        FOR UPDATE
                    ');
                    $pendingRowsStmt->execute([$releasePtrNo]);
                } else {
                    $pendingRowsStmt = $pdo->prepare('
                        SELECT id, ptr_no, record_date, recipient, description, batch_number, batch_id, unit, quantity, unit_cost, program, supplier
                        FROM inventory_records
                        WHERE id = ? AND COALESCE(release_status, "released") = "pending"
                        ORDER BY id ASC
                        FOR UPDATE
                    ');
                    $pendingRowsStmt->execute([$releaseId]);
                }
                $pendingRows = $pendingRowsStmt->fetchAll();
                if (empty($pendingRows)) {
                    throw new RuntimeException('Selected PTR is no longer pending.');
                }

                if (!$hasProductBatchesTable) {
                    throw new RuntimeException('Cannot release PTR because stock table is missing.');
                }
                if ($hasProductBatchesTable) {
                    $batchMetaByDescription = [];
                    $batchMetaStmt = $pdo->query('
                        SELECT
                            TRIM(p.product_description) AS description_name,
                            TRIM(b.batch_number) AS batch_no,
                            b.id AS batch_id,
                            b.stock_quantity AS stock_quantity
                        FROM product_batches b
                        INNER JOIN products p ON p.id = b.product_id
                        WHERE p.product_description IS NOT NULL AND TRIM(p.product_description) <> ""
                          AND b.batch_number IS NOT NULL AND TRIM(b.batch_number) <> ""
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
                        ];
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

                    $stockDeductionPlan = [];
                    $releaseCardRows = [];
                    foreach ($pendingRows as $row) {
                        $description = trim((string) ($row['description'] ?? ''));
                        $batchNumber = trim((string) ($row['batch_number'] ?? ''));
                        $rowBatchId = (int) ($row['batch_id'] ?? 0);
                        $unitValue = trim((string) ($row['unit'] ?? ''));
                        $quantity = (int) ($row['quantity'] ?? 0);
                        if ($batchNumber === '') {
                            throw new RuntimeException('Batch number is required before release.');
                        }
                        if ($quantity <= 0) {
                            throw new RuntimeException('Pending row quantity is invalid.');
                        }
                        $batchId = $rowBatchId;
                        if ($batchId <= 0) {
                            $batchMeta = $resolveBatchMeta($batchMetaByDescription, $description, $batchNumber);
                            $batchId = (int) ($batchMeta['batch_id'] ?? 0);
                        }
                        if ($batchId <= 0) {
                            throw new RuntimeException('Unable to resolve stock batch for one or more pending rows.');
                        }

                        $stockDeductionPlan[$batchId] = ($stockDeductionPlan[$batchId] ?? 0) + $quantity;

                        $itemKey = strtolower($description) . '|' . strtolower($unitValue) . '|' . strtolower($batchNumber);
                        if (!isset($releaseCardRows[$itemKey])) {
                            $releaseCardRows[$itemKey] = [
                                'item_key' => $itemKey,
                                'description' => $description,
                                'unit' => $unitValue,
                                'batch_no' => $batchNumber,
                                'program' => trim((string) ($row['program'] ?? '')),
                                'supplier' => trim((string) ($row['supplier'] ?? '')),
                                'unit_cost' => (float) ($row['unit_cost'] ?? 0),
                                'record_date' => (string) ($row['record_date'] ?? ''),
                                'ptr_no' => (string) ($row['ptr_no'] ?? ''),
                                'recipient' => trim((string) ($row['recipient'] ?? '')),
                                'batch_id' => $batchId,
                                'issued_qty' => 0,
                            ];
                        }
                        $releaseCardRows[$itemKey]['issued_qty'] += $quantity;
                    }

                    if (!empty($stockDeductionPlan)) {
                        $stockReadStmt = $pdo->prepare('SELECT stock_quantity FROM product_batches WHERE id = ? FOR UPDATE');
                        foreach ($stockDeductionPlan as $batchId => $deductQty) {
                            $batchId = (int) $batchId;
                            $deductQty = (int) $deductQty;
                            if ($batchId <= 0 || $deductQty <= 0) {
                                continue;
                            }
                            $stockReadStmt->execute([$batchId]);
                            $stockRow = $stockReadStmt->fetch();
                            $availableStock = (int) ($stockRow['stock_quantity'] ?? -1);
                            if ($availableStock < 0 || $deductQty > $availableStock) {
                                throw new RuntimeException('Insufficient stock while releasing this PTR.');
                            }
                        }

                        $stockUpdateStmt = $pdo->prepare('
                            UPDATE product_batches
                            SET stock_quantity = stock_quantity - ?
                            WHERE id = ? AND stock_quantity >= ?
                        ');
                        foreach ($stockDeductionPlan as $batchId => $deductQty) {
                            $batchId = (int) $batchId;
                            $deductQty = (int) $deductQty;
                            if ($batchId <= 0 || $deductQty <= 0) {
                                continue;
                            }
                            $stockUpdateStmt->execute([$deductQty, $batchId, $deductQty]);
                            if ($stockUpdateStmt->rowCount() !== 1) {
                                throw new RuntimeException('Insufficient stock while releasing this PTR.');
                            }
                        }
                    }

                    $firstAffectedCardId = 0;
                    if (!empty($releaseCardRows)) {
                        $readCardStmt = $pdo->prepare('
                            SELECT id, ledger_rows
                            FROM stock_cards
                            WHERE item_key = ? AND source_type = "release"
                            ORDER BY id ASC
                            LIMIT 1
                            FOR UPDATE
                        ');
                        $insertCardStmt = $pdo->prepare('
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
                        $updateCardStmt = $pdo->prepare('
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
                        $readBatchStockStmt = $pdo->prepare('SELECT stock_quantity FROM product_batches WHERE id = ? LIMIT 1');

                        foreach ($releaseCardRows as $card) {
                            $itemKey = (string) ($card['item_key'] ?? '');
                            if ($itemKey === '') {
                                continue;
                            }

                            $readCardStmt->execute([$itemKey]);
                            $existingCard = $readCardStmt->fetch();
                            $ledgerRows = [];
                            $cardId = 0;
                            if ($existingCard) {
                                $cardId = (int) ($existingCard['id'] ?? 0);
                                $decodedRows = json_decode((string) ($existingCard['ledger_rows'] ?? ''), true);
                                if (is_array($decodedRows)) {
                                    $ledgerRows = $decodedRows;
                                }
                            }

                            $unitCost = (float) ($card['unit_cost'] ?? 0);
                            $issuedQty = (int) ($card['issued_qty'] ?? 0);
                            $remainingStock = 0.0;
                            if (!empty($ledgerRows)) {
                                $lastLedgerRow = $ledgerRows[count($ledgerRows) - 1];
                                $previousBalance = (float) ($lastLedgerRow['balance'] ?? 0);
                                $remainingStock = max(0, $previousBalance - $issuedQty);
                            } else {
                                $batchId = (int) ($card['batch_id'] ?? 0);
                                if ($batchId > 0) {
                                    $readBatchStockStmt->execute([$batchId]);
                                    $batchStockRow = $readBatchStockStmt->fetch();
                                    $remainingStock = (float) ($batchStockRow['stock_quantity'] ?? 0);
                                }
                            }
                            $ledgerRows[] = [
                                'entry_date' => (string) ($card['record_date'] ?? ''),
                                'received' => '0',
                                'issued' => (string) $issuedQty,
                                'balance' => number_format($remainingStock, 2, '.', ''),
                                'total_cost' => number_format($remainingStock * $unitCost, 2, '.', ''),
                                'ref_no' => (string) ($card['ptr_no'] ?? ''),
                                'remarks' => (string) ($card['recipient'] ?? ''),
                            ];
                            $ledgerJson = json_encode($ledgerRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                            if ($cardId > 0) {
                                $updateCardStmt->execute([
                                    ((string) ($card['ptr_no'] ?? '')) !== '' ? ('PTR ' . (string) $card['ptr_no']) : null,
                                    ((string) ($card['supplier'] ?? '')) !== '' ? (string) $card['supplier'] : null,
                                    (string) ($card['description'] ?? ''),
                                    ((string) ($card['unit'] ?? '')) !== '' ? (string) $card['unit'] : null,
                                    ((string) ($card['unit'] ?? '')) !== '' ? (string) $card['unit'] : null,
                                    $unitCost,
                                    ((string) ($card['program'] ?? '')) !== '' ? (string) $card['program'] : null,
                                    ((string) ($card['batch_no'] ?? '')) !== '' ? (string) $card['batch_no'] : null,
                                    $ledgerJson,
                                    $cardId,
                                ]);
                                if ($firstAffectedCardId <= 0) {
                                    $firstAffectedCardId = $cardId;
                                }
                            } else {
                                $insertCardStmt->execute([
                                    ((string) ($card['ptr_no'] ?? '')) !== '' ? ('PTR ' . (string) $card['ptr_no']) : null,
                                    ((string) ($card['supplier'] ?? '')) !== '' ? (string) $card['supplier'] : null,
                                    (string) ($card['description'] ?? ''),
                                    ((string) ($card['unit'] ?? '')) !== '' ? (string) $card['unit'] : null,
                                    ((string) ($card['unit'] ?? '')) !== '' ? (string) $card['unit'] : null,
                                    $unitCost,
                                    ((string) ($card['program'] ?? '')) !== '' ? (string) $card['program'] : null,
                                    ((string) ($card['batch_no'] ?? '')) !== '' ? (string) $card['batch_no'] : null,
                                    $ledgerJson,
                                    $itemKey,
                                    $username,
                                ]);
                                if ($firstAffectedCardId <= 0) {
                                    $firstAffectedCardId = (int) $pdo->lastInsertId();
                                }
                            }
                        }
                    }
                }

                if ($releasePtrNo !== '') {
                    $releaseStmt = $pdo->prepare('
                        UPDATE inventory_records
                        SET release_status = "released", released_at = NOW()
                        WHERE ptr_no = ? AND COALESCE(release_status, "released") = "pending"
                    ');
                    $releaseStmt->execute([$releasePtrNo]);
                } else {
                    $releaseStmt = $pdo->prepare('
                        UPDATE inventory_records
                        SET release_status = "released", released_at = NOW()
                        WHERE id = ? AND COALESCE(release_status, "released") = "pending"
                    ');
                    $releaseStmt->execute([$releaseId]);
                }

                if ($releaseStmt->rowCount() <= 0) {
                    throw new RuntimeException('No pending rows were released.');
                }

                $pdo->commit();
                $releasedPtrNo = $releasePtrNo !== '' ? $releasePtrNo : (string) ($pendingRows[0]['ptr_no'] ?? '');
                $redirectMsg = 'Released PTR moved to Stock Card' . ($releasedPtrNo !== '' ? ' ' . $releasedPtrNo : '') . '.';
                $redirectUrl = 'stock_card.php?msg=' . urlencode($redirectMsg);
                if ($firstAffectedCardId > 0) {
                    $redirectUrl .= '&card_id=' . $firstAffectedCardId;
                }
                header('Location: ' . $redirectUrl);
                exit;
            } catch (Throwable $releaseError) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $releaseError;
            }
        }
    }

    $params = [];
    $where = ['COALESCE(release_status, "released") = "pending"'];
    if ($search !== '') {
        $where[] = '(ptr_no LIKE :q OR recipient LIKE :q OR description LIKE :q OR batch_number LIKE :q OR program LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }

    $sql = '
        SELECT id, record_date, ptr_no, recipient, description, batch_number, batch_id, program, po_no, unit, quantity, unit_cost, expiration_date
        FROM inventory_records
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY record_date DESC, id DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $ptrNo = trim((string) ($row['ptr_no'] ?? ''));
        $groupKey = $ptrNo !== '' ? 'ptr:' . $ptrNo : 'id:' . (int) ($row['id'] ?? 0);
        if (!isset($pendingGroups[$groupKey])) {
            $pendingGroups[$groupKey] = [
                'release_token' => $groupKey,
                'ptr_no' => $ptrNo !== '' ? $ptrNo : '-',
                'record_date' => (string) ($row['record_date'] ?? '-'),
                'recipient' => (string) ($row['recipient'] ?? '-'),
                'items' => [],
                'total_qty' => 0,
                'total_amount' => 0.0,
            ];
        }
        $pendingGroups[$groupKey]['items'][] = $row;
        $pendingGroups[$groupKey]['total_qty'] += (int) ($row['quantity'] ?? 0);
        $pendingGroups[$groupKey]['total_amount'] += (float) ($row['quantity'] ?? 0) * (float) ($row['unit_cost'] ?? 0);
    }
} catch (Throwable $e) {
    $error = $e->getMessage() !== '' ? $e->getMessage() : 'Unable to process pending transactions right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Transactions - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260305">
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
                    <small class="fw-normal">Pending Transactions</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Report</a>
                <a href="incident_report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Incident Report</a>
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
                        <h1 class="h5 mb-0">Pending PTR Transactions</h1>
                        <form method="get" action="pending_transactions.php" class="d-flex gap-2">
                            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search PTR, recipient, item" style="min-width: 280px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </form>
                    </div>

                    <?php if ($message !== ''): ?>
                        <div class="alert alert-success py-2 mb-2"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 mb-0"><?= htmlspecialchars($error) ?></div>
                    <?php elseif (empty($pendingGroups)): ?>
                        <div class="inventory-empty-state">
                            <div class="inventory-empty-state-icon">📎</div>
                            <div><strong>No pending PTR transactions found.</strong></div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingGroups as $group): ?>
                            <section class="report-group-block">
                                <div class="report-group-head">
                                    <div class="report-group-head-meta">
                                        <span class="report-group-chip"><strong>PTR No.:</strong> <?= htmlspecialchars($group['ptr_no']) ?></span>
                                        <span class="report-group-chip"><strong>Date:</strong> <?= htmlspecialchars($group['record_date']) ?></span>
                                        <span class="report-group-chip report-group-chip-recipient"><strong>Recipient:</strong> <?= htmlspecialchars($group['recipient']) ?></span>
                                        <span class="report-group-chip"><strong>Total Qty:</strong> <?= (int) $group['total_qty'] ?></span>
                                        <span class="report-group-chip"><strong>Total Amount:</strong> PHP <?= number_format((float) $group['total_amount'], 2) ?></span>
                                    </div>
                                    <div class="report-group-head-actions">
                                        <form method="post" action="pending_transactions.php" onsubmit="return confirm('Release this PTR? This will deduct current stock and make it printable.');" class="d-inline">
                                            <input type="hidden" name="action" value="release">
                                            <input type="hidden" name="release_token" value="<?= htmlspecialchars((string) $group['release_token']) ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">Release</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="inventory-table-container mb-4">
                                    <div class="inventory-table-wrapper">
                                        <table class="table inventory-table">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Batch</th>
                                                <th>Program</th>
                                                <th>PO No.</th>
                                                <th class="text-center">Unit</th>
                                                <th class="text-center">Exp. Date</th>
                                                <th class="text-end">Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($group['items'] as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) ($item['description'] ?? '-')) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars((string) ($item['batch_number'] ?? '-')) ?></td>
                                                    <td><?= htmlspecialchars((string) ($item['program'] ?? '-')) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars((string) ($item['po_no'] ?? '-')) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars((string) ($item['unit'] ?? '-')) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars((string) ($item['expiration_date'] ?? '-')) ?></td>
                                                    <td class="text-end"><?= (int) ($item['quantity'] ?? 0) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            </section>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>

