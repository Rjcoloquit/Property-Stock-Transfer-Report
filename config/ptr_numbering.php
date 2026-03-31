<?php

function getPtrYearMonthPrefix(?string $recordDate = null): string
{
    $dateValue = trim((string) ($recordDate ?? ''));
    if ($dateValue !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $dateValue);
        if ($date instanceof DateTime && $date->format('Y-m-d') === $dateValue) {
            return $date->format('Y - m');
        }
    }
    return date('Y - m');
}

function getNextPtrNumber(PDO $pdo, ?string $recordDate = null): string
{
    $yearMonthPrefix = getPtrYearMonthPrefix($recordDate);
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(CAST(RIGHT(ptr_no, 4) AS UNSIGNED)), 0) AS max_ptr
        FROM inventory_records
        WHERE ptr_no REGEXP '^[0-9]{4} - [0-9]{2} - [0-9]{4}$'
          AND LEFT(ptr_no, 9) = :year_month_prefix
    ");
    $stmt->execute(['year_month_prefix' => $yearMonthPrefix]);
    $row = $stmt->fetch();
    $maxPtr = isset($row['max_ptr']) ? (int) $row['max_ptr'] : 0;
    return sprintf('%s - %04d', $yearMonthPrefix, $maxPtr + 1);
}

function normalizeExistingPtrNumbers(PDO $pdo): void
{
    $legacyCount = (int) $pdo->query("
        SELECT COUNT(*)
        FROM inventory_records
        WHERE ptr_no IS NULL
           OR TRIM(ptr_no) = ''
              OR ptr_no NOT REGEXP '^[0-9]{4} - [0-9]{2} - [0-9]{4}$'
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
                'year_month' => getPtrYearMonthPrefix((string) ($row['record_date'] ?? '')),
                'ids' => [],
            ];
        }
        $groups[$groupKey]['ids'][] = $id;
    }

    $yearMonthCounters = [];
    $assignments = [];
    foreach ($groups as $group) {
        $yearMonth = (string) ($group['year_month'] ?? date('Y - m'));
        if (!isset($yearMonthCounters[$yearMonth])) {
            $yearMonthCounters[$yearMonth] = 0;
        }
        $yearMonthCounters[$yearMonth]++;
        $newPtrNo = sprintf('%s - %04d', $yearMonth, $yearMonthCounters[$yearMonth]);

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

