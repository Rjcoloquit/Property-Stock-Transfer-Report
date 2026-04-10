<?php

declare(strict_types=1);

/**
 * PTR print preview signatory lines (Prepared / Approved / Issued by), keyed by ptr_no.
 */

function ptr_signatory_defaults(): array
{
    return [
        'prepared_by' => "Mark Anthony Borres, \nJohn Paul Joseph Opiala, \nRichard Roy",
        'approved_by' => "Elizabeth C. Calaor, RPh\n(Pharmacist II/ Head, Supply & Logistics Unit)",
        'issued_by' => "Jannete Ventura, \nEarnest John Tolentino, RPh",
    ];
}

function ptr_ensure_signatories_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ptr_print_signatories (
            ptr_no VARCHAR(50) NOT NULL,
            prepared_by TEXT,
            approved_by TEXT,
            issued_by TEXT,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ptr_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
}

/**
 * @return array{prepared_by: string, approved_by: string, issued_by: string}|null
 */
function ptr_load_signatories_for_ptr(PDO $pdo, string $ptrNo): ?array
{
    $ptrNo = trim($ptrNo);
    if ($ptrNo === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT prepared_by, approved_by, issued_by FROM ptr_print_signatories WHERE ptr_no = ? LIMIT 1'
    );
    $stmt->execute([$ptrNo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'prepared_by' => (string) ($row['prepared_by'] ?? ''),
        'approved_by' => (string) ($row['approved_by'] ?? ''),
        'issued_by' => (string) ($row['issued_by'] ?? ''),
    ];
}

/**
 * @return array<string, array{prepared_by: string, approved_by: string, issued_by: string}>
 */
function ptr_load_all_signatories_map(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT ptr_no, prepared_by, approved_by, issued_by FROM ptr_print_signatories');
    if (!$stmt) {
        return [];
    }
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = trim((string) ($row['ptr_no'] ?? ''));
        if ($key === '') {
            continue;
        }
        $out[$key] = [
            'prepared_by' => (string) ($row['prepared_by'] ?? ''),
            'approved_by' => (string) ($row['approved_by'] ?? ''),
            'issued_by' => (string) ($row['issued_by'] ?? ''),
        ];
    }
    return $out;
}

function ptr_save_signatories_for_ptr(
    PDO $pdo,
    string $ptrNo,
    string $preparedBy,
    string $approvedBy,
    string $issuedBy
): void {
    $ptrNo = trim($ptrNo);
    if ($ptrNo === '') {
        throw new InvalidArgumentException('ptr_no is required');
    }
    $stmt = $pdo->prepare(
        'INSERT INTO ptr_print_signatories (ptr_no, prepared_by, approved_by, issued_by)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            prepared_by = VALUES(prepared_by),
            approved_by = VALUES(approved_by),
            issued_by = VALUES(issued_by)'
    );
    $stmt->execute([$ptrNo, $preparedBy, $approvedBy, $issuedBy]);
}
