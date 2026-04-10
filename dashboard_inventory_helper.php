<?php

declare(strict_types=1);

/**
 * Align products / batch tables with what Manage Items expects, then return
 * dashboard rows (product + batch stock lines).
 *
 * @return array{hasProductsExpiryDate: bool, batchSourceTable: string}
 */
function ptr_ensure_dashboard_inventory_schema(PDO $pdo): array
{
    $hasProductsExpiryDate = false;
    $batchSourceTable = '';

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
    $supplierColumnStmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'supplier'");
    if (!$supplierColumnStmt || !$supplierColumnStmt->fetch()) {
        $pdo->exec('ALTER TABLE products ADD COLUMN supplier VARCHAR(255) DEFAULT NULL AFTER po_no');
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

    $productBatchesStmt = $pdo->query("SHOW TABLES LIKE 'product_batches'");
    if ($productBatchesStmt && $productBatchesStmt->fetch()) {
        $hasRequiredProductBatchesColumns = true;
        foreach (['product_id', 'batch_number', 'stock_quantity', 'expiry_date'] as $col) {
            $c = $pdo->query("SHOW COLUMNS FROM product_batches LIKE " . $pdo->quote($col));
            if (!$c || !$c->fetch()) {
                $hasRequiredProductBatchesColumns = false;
                break;
            }
        }
        if ($hasRequiredProductBatchesColumns) {
            $batchSourceTable = 'product_batches';
        }
    }

    $productPoNumberStmt = $pdo->query("SHOW TABLES LIKE 'product_po_number'");
    if ($productPoNumberStmt && $productPoNumberStmt->fetch()) {
        $hasRequiredProductPoColumns = true;
        foreach (['product_id', 'po_no', 'batch_number', 'stock_quantity', 'cost_per_unit'] as $col) {
            $c = $pdo->query("SHOW COLUMNS FROM product_po_number LIKE " . $pdo->quote($col));
            if (!$c || !$c->fetch()) {
                $hasRequiredProductPoColumns = false;
                break;
            }
        }
        $poExpiryStmt = $pdo->query("SHOW COLUMNS FROM product_po_number LIKE 'expiry_date'");
        if (!$poExpiryStmt || !$poExpiryStmt->fetch()) {
            $hasRequiredProductPoColumns = false;
        }
        if ($hasRequiredProductPoColumns) {
            $batchSourceTable = 'product_po_number';
        }
    }

    return [
        'hasProductsExpiryDate' => $hasProductsExpiryDate,
        'batchSourceTable' => $batchSourceTable,
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function ptr_dashboard_inventory_rows(PDO $pdo, string $search, int $browseLimit, int $searchLimit): array
{
    $meta = ptr_ensure_dashboard_inventory_schema($pdo);
    $hasProductsExpiryDate = $meta['hasProductsExpiryDate'];
    $batchSourceTable = $meta['batchSourceTable'];

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
            ' . $poNoSelectSql . ' AS po_no,
            ' . $batchSelectSql . '
        FROM products p
        ' . $batchJoinSql;

    $limit = $search !== '' ? max(1, $searchLimit) : max(1, $browseLimit);

    if ($search !== '') {
        $like = '%' . $search . '%';
        $batchSearchClause = $batchSourceTable !== ''
            ? '                OR COALESCE(b.batch_number, "") LIKE :q' . PHP_EOL
            : '';
        $poSearchClause = $batchSourceTable === 'product_po_number'
            ? '                OR COALESCE(b.po_no, p.po_no, "") LIKE :q' . PHP_EOL
            : '                OR COALESCE(p.po_no, "") LIKE :q' . PHP_EOL;

        $stmt = $pdo->prepare(
            $listSelect . '
             WHERE p.product_description LIKE :q
                OR p.uom LIKE :q
             ' . $batchSearchClause . $poSearchClause . '
             ORDER BY TRIM(LOWER(p.product_description)) ASC,
                      COALESCE(batch_number, "") ASC,
                      p.id ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['q' => $like]);
    } else {
        $stmt = $pdo->query(
            $listSelect . '
             ORDER BY TRIM(LOWER(p.product_description)) ASC,
                      COALESCE(batch_number, "") ASC,
                      p.id ASC
             LIMIT ' . (int) $limit
        );
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Distinct suggestion strings for the dashboard search (descriptions, UOM, PO, batch).
 *
 * @return list<string>
 */
function ptr_dashboard_search_suggestions(PDO $pdo, string $q, int $max): array
{
    $q = trim($q);
    if ($q === '' || strlen($q) > 100) {
        return [];
    }

    $max = max(1, min(25, $max));
    $meta = ptr_ensure_dashboard_inventory_schema($pdo);
    $batchSourceTable = $meta['batchSourceTable'];
    $like = '%' . $q . '%';
    $seen = [];
    $out = [];

    $add = static function (string $v) use (&$out, &$seen, $max): bool {
        $v = trim($v);
        if ($v === '') {
            return count($out) < $max;
        }
        $k = strtolower($v);
        if (isset($seen[$k])) {
            return count($out) < $max;
        }
        $seen[$k] = true;
        if (count($out) < $max) {
            $out[] = $v;
        }

        return count($out) < $max;
    };

    $stmt = $pdo->prepare(
        'SELECT DISTINCT TRIM(product_description) AS v FROM products
         WHERE product_description LIKE :q AND TRIM(product_description) <> ""
         ORDER BY CHAR_LENGTH(TRIM(product_description)) ASC
         LIMIT 12'
    );
    $stmt->execute(['q' => $like]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!$add((string) $row['v'])) {
            return $out;
        }
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT TRIM(uom) AS v FROM products
         WHERE uom LIKE :q AND TRIM(uom) <> ""
         LIMIT 6'
    );
    $stmt->execute(['q' => $like]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!$add((string) $row['v'])) {
            return $out;
        }
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT TRIM(po_no) AS v FROM products
         WHERE po_no LIKE :q AND TRIM(po_no) <> ""
         LIMIT 6'
    );
    $stmt->execute(['q' => $like]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!$add((string) $row['v'])) {
            return $out;
        }
    }

    if ($batchSourceTable === 'product_batches') {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT TRIM(batch_number) AS v FROM product_batches
             WHERE batch_number LIKE :q AND TRIM(batch_number) <> ""
             LIMIT 8'
        );
        $stmt->execute(['q' => $like]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!$add((string) $row['v'])) {
                return $out;
            }
        }
    } elseif ($batchSourceTable === 'product_po_number') {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT TRIM(po_no) AS v FROM product_po_number
             WHERE po_no LIKE :q AND TRIM(po_no) <> ""
             LIMIT 6'
        );
        $stmt->execute(['q' => $like]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!$add((string) $row['v'])) {
                return $out;
            }
        }
        $stmt = $pdo->prepare(
            'SELECT DISTINCT TRIM(batch_number) AS v FROM product_po_number
             WHERE batch_number LIKE :q AND TRIM(batch_number) <> ""
             LIMIT 8'
        );
        $stmt->execute(['q' => $like]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!$add((string) $row['v'])) {
                return $out;
            }
        }
    }

    return $out;
}

/**
 * Full stock list for Current Stock Report with optional filters (AND logic).
 *
 * @param array{description?: string, program?: string} $filters
 * @return list<array{product_description: string, program: string|null, uom: string, batch_number: string|null, stock: float|int, expiry_date: string|null}>
 */
function ptr_current_stock_report_rows(PDO $pdo, array $filters): array
{
    $meta = ptr_ensure_dashboard_inventory_schema($pdo);
    $hasProductsExpiryDate = $meta['hasProductsExpiryDate'];
    $batchSourceTable = $meta['batchSourceTable'];

    $batchJoinSql = '';
    $batchSelectSql = 'NULL AS batch_number, 0 AS stock';
    $expirySelectSql = $hasProductsExpiryDate ? 'p.expiry_date' : 'NULL';
    if ($batchSourceTable !== '') {
        $batchJoinSql = 'LEFT JOIN ' . $batchSourceTable . ' b ON b.product_id = p.id';
        $batchSelectSql = 'b.batch_number AS batch_number, COALESCE(b.stock_quantity, 0) AS stock';
        $expirySelectSql = $hasProductsExpiryDate
            ? 'COALESCE(b.expiry_date, p.expiry_date)'
            : 'b.expiry_date';
    }

    $sql = '
        SELECT
            p.product_description,
            p.program AS program,
            p.uom,
            ' . $batchSelectSql . ',
            ' . $expirySelectSql . ' AS expiry_date
        FROM products p
        ' . $batchJoinSql;

    $wheres = [];
    $params = [];

    $fd = trim((string) ($filters['description'] ?? ''));
    if ($fd !== '') {
        $wheres[] = 'p.product_description LIKE :f_desc';
        $params['f_desc'] = '%' . $fd . '%';
    }

    $fp = trim((string) ($filters['program'] ?? ''));
    if ($fp !== '') {
        $wheres[] = 'COALESCE(p.program, \'\') LIKE :f_program';
        $params['f_program'] = '%' . $fp . '%';
    }

    if ($wheres !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $wheres);
    }

    $sql .= '
        ORDER BY TRIM(LOWER(p.product_description)) ASC,
                 COALESCE(batch_number, \'\') ASC,
                 p.id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
