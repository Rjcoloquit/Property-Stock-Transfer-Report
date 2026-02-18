<?php

function getPtrMonthPrefix(?string $recordDate = null): string
{
    $dateValue = trim((string) ($recordDate ?? ''));
    if ($dateValue !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $dateValue);
        if ($date instanceof DateTime && $date->format('Y-m-d') === $dateValue) {
            return $date->format('m');
        }
    }
    return date('m');
}

function getNextPtrNumber(PDO $pdo, ?string $recordDate = null): string
{
    $monthPrefix = getPtrMonthPrefix($recordDate);
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(CAST(SUBSTRING(ptr_no, 4) AS UNSIGNED)), 0) AS max_ptr
        FROM inventory_records
        WHERE ptr_no REGEXP '^[0-9]{2}/[0-9]{4}$'
          AND SUBSTRING(ptr_no, 1, 2) = :month_prefix
    ");
    $stmt->execute(['month_prefix' => $monthPrefix]);
    $row = $stmt->fetch();
    $maxPtr = isset($row['max_ptr']) ? (int) $row['max_ptr'] : 0;
    return sprintf('%s/%04d', $monthPrefix, $maxPtr + 1);
}

function normalizeExistingPtrNumbers(PDO $pdo): void
{
    $legacyCount = (int) $pdo->query("
        SELECT COUNT(*)
        FROM inventory_records
        WHERE ptr_no IS NULL
           OR TRIM(ptr_no) = ''
           OR ptr_no NOT REGEXP '^[0-9]{2}/[0-9]{4}$'
    ")->fetchColumn();

    if ($legacyCount === 0) {
        return;
    }

    $rowsStmt = $pdo->query("
        SELECT id, ptr_no, record_date
        FROM inventory_records
        ORDER BY record_date ASC, id ASC
    ");
    $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        return;
    }

    $groups = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $rawPtrNo = trim((string) ($row['ptr_no'] ?? ''));
        $groupKey = $rawPtrNo !== '' ? 'ptr:' . $rawPtrNo : 'row:' . $id;
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'month' => getPtrMonthPrefix((string) ($row['record_date'] ?? '')),
                'ids' => [],
            ];
        }
        $groups[$groupKey]['ids'][] = $id;
    }

    $monthCounters = [];
    $assignments = [];
    foreach ($groups as $group) {
        $month = (string) ($group['month'] ?? date('m'));
        if (!isset($monthCounters[$month])) {
            $monthCounters[$month] = 0;
        }
        $monthCounters[$month]++;
        $newPtrNo = sprintf('%s/%04d', $month, $monthCounters[$month]);

        foreach ($group['ids'] as $id) {
            $assignments[(int) $id] = $newPtrNo;
        }
    }

    if (empty($assignments)) {
        return;
    }

    $updateStmt = $pdo->prepare('UPDATE inventory_records SET ptr_no = ? WHERE id = ?');
    $pdo->beginTransaction();
    try {
        foreach ($assignments as $id => $newPtrNo) {
            $updateStmt->execute([$newPtrNo, (int) $id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

