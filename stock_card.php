<?php
session_start();
require_once __DIR__ . '/config/rbac.php';
ptr_require_login();
ptr_require_page_access('stock_card');
ptr_block_encoder_mutations();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/print_preview_helpers.php';

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
$message = trim((string) ($_GET['msg'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$selectedCardId = isset($_GET['card_id']) ? (int) $_GET['card_id'] : 0;
$error = '';

$cards = [];
$searchOptions = [];
$formData = [
    'po_contract_no' => '',
    'supplier' => '',
    'item_description' => '',
    'expiry_date' => '',
    'dosage_strength' => '',
    'uom' => '',
    'sku_code' => '',
    'entity_name' => '',
    'fund_cluster' => '',
    'unit_cost' => '',
    'mode_of_procurement' => '',
    'end_user_program' => '',
    'batch_no' => '',
];
$ledgerRows = [];

try {
    $pdo = getConnection();
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

    $hasProductBatchesTable = false;
    $productBatchesStmt = $pdo->query("SHOW TABLES LIKE 'product_batches'");
    if ($productBatchesStmt && $productBatchesStmt->fetch()) {
        $hasProductBatchesTable = true;
    }

    $hasProductsExpiryDate = false;
    $productsExpiryColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'expiry_date'");
    if ($productsExpiryColumnStmt && $productsExpiryColumnStmt->fetch()) {
        $hasProductsExpiryDate = true;
    }

    $params = [];
    $where = ['source_type = "release"'];
    if ($search !== '') {
        $where[] = '(item_description LIKE :q OR batch_no LIKE :q OR po_contract_no LIKE :q OR end_user_program LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }
    $listSql = '
        SELECT id, po_contract_no, item_description, batch_no, uom, end_user_program, created_at
        FROM stock_cards
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY created_at DESC, id DESC
    ';
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);
    $cards = $listStmt->fetchAll();

    $searchOptionsStmt = $pdo->query('
        SELECT option_value
        FROM (
            SELECT DISTINCT TRIM(item_description) AS option_value
            FROM stock_cards
            WHERE source_type = "release"
              AND item_description IS NOT NULL
              AND TRIM(item_description) <> ""
            UNION
            SELECT DISTINCT TRIM(batch_no) AS option_value
            FROM stock_cards
            WHERE source_type = "release"
              AND batch_no IS NOT NULL
              AND TRIM(batch_no) <> ""
            UNION
            SELECT DISTINCT TRIM(po_contract_no) AS option_value
            FROM stock_cards
            WHERE source_type = "release"
              AND po_contract_no IS NOT NULL
              AND TRIM(po_contract_no) <> ""
            UNION
            SELECT DISTINCT TRIM(end_user_program) AS option_value
            FROM stock_cards
            WHERE source_type = "release"
              AND end_user_program IS NOT NULL
              AND TRIM(end_user_program) <> ""
        ) options
        ORDER BY option_value ASC
    ');
    $searchOptions = $searchOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

    if ($selectedCardId <= 0 && !empty($cards)) {
        $selectedCardId = (int) ($cards[0]['id'] ?? 0);
    }

    if ($selectedCardId > 0) {
        $cardStmt = $pdo->prepare('
            SELECT
                id,
                po_contract_no,
                supplier,
                item_description,
                dosage_form,
                dosage_strength,
                uom,
                sku_code,
                entity_name,
                fund_cluster,
                unit_cost,
                mode_of_procurement,
                end_user_program,
                batch_no,
                ledger_rows
            FROM stock_cards
            WHERE id = ? AND source_type = "release"
            LIMIT 1
        ');
        $cardStmt->execute([$selectedCardId]);
        $selectedCard = $cardStmt->fetch();

        if ($selectedCard) {
            foreach ($formData as $key => $value) {
                $formData[$key] = (string) ($selectedCard[$key] ?? '');
            }

            $itemDescription = trim($formData['item_description']);
            $batchNo = trim($formData['batch_no']);
            if ($hasProductBatchesTable && $itemDescription !== '' && $batchNo !== '') {
                $expiryLookupStmt = $pdo->prepare('SELECT b.expiry_date
                    FROM product_batches b
                    INNER JOIN products p ON p.id = b.product_id
                    WHERE LOWER(TRIM(p.product_description)) = LOWER(?)
                      AND LOWER(TRIM(b.batch_number)) = LOWER(?)
                    ORDER BY b.id ASC
                    LIMIT 1');
                $expiryLookupStmt->execute([$itemDescription, $batchNo]);
                $batchExpiry = $expiryLookupStmt->fetchColumn();
                if ($batchExpiry !== false && $batchExpiry !== null) {
                    $formData['expiry_date'] = (string) $batchExpiry;
                }
            }
            if ($formData['expiry_date'] === '' && $hasProductsExpiryDate && $itemDescription !== '') {
                $productExpiryStmt = $pdo->prepare('SELECT expiry_date
                    FROM products
                    WHERE LOWER(TRIM(product_description)) = LOWER(?)
                      AND expiry_date IS NOT NULL
                    ORDER BY id ASC
                    LIMIT 1');
                $productExpiryStmt->execute([$itemDescription]);
                $productExpiry = $productExpiryStmt->fetchColumn();
                if ($productExpiry !== false && $productExpiry !== null) {
                    $formData['expiry_date'] = (string) $productExpiry;
                }
            }
            if (trim($formData['entity_name']) === '') {
                $formData['entity_name'] = 'PHO';
            }
            if (trim($formData['fund_cluster']) === '') {
                $formData['fund_cluster'] = 'PHO';
            }
            $unitCostValue = is_numeric($formData['unit_cost']) ? (float) $formData['unit_cost'] : 0.0;
            $decodedRows = json_decode((string) ($selectedCard['ledger_rows'] ?? ''), true);
            if (is_array($decodedRows)) {
                $runningBalance = null;
                foreach ($decodedRows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $issuedRaw = (string) ($row['issued'] ?? '');
                    $issuedNumeric = (float) str_replace(',', '', trim($issuedRaw));
                    $receivedRaw = (string) ($row['received'] ?? '');
                    $receivedNumeric = (float) str_replace(',', '', trim($receivedRaw));
                    $storedBalanceRaw = (string) ($row['balance'] ?? '');
                    $storedBalance = (float) str_replace(',', '', trim($storedBalanceRaw));

                    if ($runningBalance === null) {
                        $startingBalance = $storedBalance - $receivedNumeric + $issuedNumeric;
                        $runningBalance = max(0, $startingBalance);
                    }
                    $runningBalance = max(0, $runningBalance + $receivedNumeric - $issuedNumeric);

                    $storedTotalCost = (string) ($row['total_cost'] ?? '');
                    $computedTotalCost = $issuedNumeric > 0
                        ? number_format($issuedNumeric * $unitCostValue, 2, '.', '')
                        : $storedTotalCost;
                    $ledgerRows[] = [
                        'entry_date' => (string) ($row['entry_date'] ?? ''),
                        'received' => $receivedRaw,
                        'issued' => $issuedRaw,
                        'balance' => number_format($runningBalance, 2, '.', ''),
                        'total_cost' => $computedTotalCost,
                        'ref_no' => ptrNormalizeDuplicatePoRefPrefix((string) ($row['ref_no'] ?? '')),
                        'remarks' => (string) ($row['remarks'] ?? ''),
                    ];
                }
            }
        }
    }
} catch (PDOException $e) {
    $error = 'Unable to load released stock cards right now.';
}

while (count($ledgerRows) < 18) {
    $ledgerRows[] = [
        'entry_date' => '',
        'received' => '',
        'issued' => '',
        'balance' => '',
        'total_cost' => '',
        'ref_no' => '',
        'remarks' => '',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Card - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260305">
</head>
<body class="stock-card-page">
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
                    <small class="fw-normal">Stock Card (Released PTR)</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="current_stock_report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Stock Report</a>
                <a href="pending_transactions.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Pending</a>
                <a href="report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Report</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
            </div>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <div class="card app-card mb-3 stock-card-list-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h1 class="h5 mb-0">Released PTR Stock Cards</h1>
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm stock-card-print-btn"
                            data-print-target="stockCardPrintPreview"
                        >
                            Print
                        </button>
                    </div>

                    <form method="get" action="stock_card.php" class="row g-2 mb-3">
                        <div class="col-md-10">
                            <input
                                type="text"
                                name="q"
                                class="form-control"
                                list="stockCardSearchOptions"
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="Type to search (item, batch, PTR ref, or end user)"
                            >
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-outline-secondary">Search</button>
                        </div>
                    </form>
                    <datalist id="stockCardSearchOptions">
                        <?php foreach ($searchOptions as $option): ?>
                            <option value="<?= htmlspecialchars((string) $option) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>

                    <?php if ($message !== ''): ?>
                        <div class="alert alert-success py-2 mb-2"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 mb-0"><?= htmlspecialchars($error) ?></div>
                    <?php elseif (empty($cards)): ?>
                        <div class="alert alert-info py-2 mb-0">No released PTR stock cards yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>PTR Ref</th>
                                        <th>Item Description</th>
                                        <th>Batch</th>
                                        <th>Unit</th>
                                        <th>End User</th>
                                        <th>Updated</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cards as $card): ?>
                                        <?php $cardId = (int) ($card['id'] ?? 0); ?>
                                        <tr>
                                            <td><?= htmlspecialchars(ptrNormalizeDuplicatePoRefPrefix((string) ($card['po_contract_no'] ?? '')) ?: '-') ?></td>
                                            <td><?= htmlspecialchars((string) ($card['item_description'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($card['batch_no'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($card['uom'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($card['end_user_program'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string) ($card['created_at'] ?? '-')) ?></td>
                                            <td class="text-center">
                                                <a href="stock_card.php?card_id=<?= $cardId ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="btn btn-outline-secondary btn-sm <?= $cardId === $selectedCardId ? 'active' : '' ?>">Open</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card app-card stock-card-print-card">
                <div class="card-body">
                    <div id="stockCardPrintPreview" class="stock-card-sheet">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0 stock-card-master-table">
                                <colgroup>
                                    <col class="stock-master-col-label">
                                    <col class="stock-master-col-value">
                                    <col class="stock-master-col-label">
                                    <col class="stock-master-col-value">
                                </colgroup>
                                <tbody>
                                    <tr>
                                        <td colspan="2" class="stock-card-title-cell">STOCK CARD</td>
                                        <th class="stock-card-label-cell">Stock Keeping Unit (SKU) Code:</th>
                                        <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($formData['sku_code'], '') ?>" readonly></td>
                                    </tr>
                                    <tr>
                                        <th class="stock-card-label-cell">P.O. / Contract #:</th>
                                        <td><input type="text" class="stock-card-line-input" data-ptr-print-dedupe-po value="<?= ptrPrintPreviewText(ptrNormalizeDuplicatePoRefPrefix($formData['po_contract_no']), '') ?>" readonly></td>
                                        <th class="stock-card-label-cell">Entity Name:</th>
                                        <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($formData['entity_name'], '') ?>" readonly></td>
                                    </tr>
                                    <tr>
                                        <th class="stock-card-label-cell">Supplier:</th>
                                        <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($formData['supplier'], '') ?>" readonly></td>
                                        <th class="stock-card-label-cell">Fund Cluster:</th>
                                        <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($formData['fund_cluster'], '') ?>" readonly></td>
                                    </tr>
                                    <tr>
                                        <th class="stock-card-label-cell">Item Description:</th>
                                        <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($formData['item_description'], '') ?>" readonly></td>
                                        <th class="stock-card-label-cell">Unit Cost:</th>
                                        <td><input type="text" class="stock-card-line-input text-end" value="<?= ptrPrintPreviewText($formData['unit_cost'], '') ?>" readonly></td>
                                    </tr>
                                    <tr>
                                        <th class="stock-card-label-cell">Expiry Date:</th>
                                        <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($formData['expiry_date'], '') ?>" readonly></td>
                                        <th class="stock-card-label-cell">Mode of Procurement:</th>
                                        <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($formData['mode_of_procurement'], '') ?>" readonly></td>
                                    </tr>
                                    <tr>
                                        <th class="stock-card-label-cell">Dosage Strength:</th>
                                        <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($formData['dosage_strength'], '') ?>" readonly></td>
                                        <th class="stock-card-label-cell">End User (Program):</th>
                                        <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($formData['end_user_program'], '') ?>" readonly></td>
                                    </tr>
                                    <tr>
                                        <th class="stock-card-label-cell">Unit of Measure:</th>
                                        <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($formData['uom'], '') ?>" readonly></td>
                                        <th class="stock-card-label-cell">Batch No.:</th>
                                        <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($formData['batch_no'], '') ?>" readonly></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-responsive mt-2">
                            <table class="table table-bordered table-sm mb-0 stock-card-ledger-table">
                                <colgroup>
                                    <col class="stock-col-date">
                                    <col class="stock-col-qty">
                                    <col class="stock-col-qty">
                                    <col class="stock-col-qty">
                                    <col class="stock-col-cost">
                                    <col class="stock-col-ref">
                                    <col class="stock-col-remarks">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="stock-col-date text-center">Date</th>
                                        <th colspan="4" class="text-center">Quantity</th>
                                        <th rowspan="2" class="stock-col-ref text-center">DR/SI/RIS/PTR/BL No.</th>
                                        <th rowspan="2" class="stock-col-remarks text-center">Recipient / Remarks</th>
                                    </tr>
                                    <tr>
                                        <th class="stock-col-qty text-center">Received</th>
                                        <th class="stock-col-qty text-center">Issued</th>
                                        <th class="stock-col-qty text-center">Balance</th>
                                        <th class="stock-col-cost text-center">Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ledgerRows as $row): ?>
                                        <tr>
                                            <td class="text-center"><input type="text" class="stock-card-line-input text-center" value="<?= ptrPrintPreviewText($row['entry_date'] ?? null, '') ?>" readonly></td>
                                            <td class="text-center"><input type="text" class="stock-card-line-input text-center" value="<?= ptrPrintPreviewText($row['received'] ?? null, '') ?>" readonly></td>
                                            <td class="text-center"><input type="text" class="stock-card-line-input text-center" value="<?= ptrPrintPreviewText($row['issued'] ?? null, '') ?>" readonly></td>
                                            <td class="text-center"><input type="text" class="stock-card-line-input text-center" value="<?= ptrPrintPreviewText($row['balance'] ?? null, '') ?>" readonly></td>
                                            <td class="text-end"><input type="text" class="stock-card-line-input text-end" value="<?= ptrPrintPreviewText($row['total_cost'] ?? null, '') ?>" readonly></td>
                                            <td class="text-center"><input type="text" class="stock-card-line-input text-center" data-ptr-print-dedupe-po value="<?= ptrPrintPreviewText($row['ref_no'] ?? null, '') ?>" readonly></td>
                                            <td><input type="text" class="stock-card-line-input" value="<?= ptrPrintPreviewText($row['remarks'] ?? null, '') ?>" readonly></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="assets/js/smooth_motion.js?v=20260325"></script>
    <script src="assets/js/stock_card.js?v=20260411"></script>
</body>
</html>

