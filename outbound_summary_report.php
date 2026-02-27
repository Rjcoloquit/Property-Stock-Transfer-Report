<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
$error = '';
$rows = [];

try {
    $pdo = getConnection();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS inventory_records (
            id INT NOT NULL AUTO_INCREMENT,
            expiration_date DATE DEFAULT NULL,
            unit VARCHAR(50) DEFAULT NULL,
            description TEXT,
            quantity INT DEFAULT 0,
            unit_cost DECIMAL(10,2) DEFAULT 0.00,
            program VARCHAR(255) DEFAULT NULL,
            recipient VARCHAR(255) DEFAULT NULL,
            ptr_no VARCHAR(50) DEFAULT NULL,
            record_date DATE DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
    );
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

    $stmt = $pdo->query(
        'SELECT
            program,
            po_no,
            COALESCE(DATE(released_at), record_date) AS date_released,
            description,
            expiration_date,
            quantity,
            unit,
            unit_cost,
            (quantity * unit_cost) AS total_cost,
            recipient,
            ptr_no
        FROM inventory_records
        WHERE COALESCE(release_status, "released") = "released"
        ORDER BY COALESCE(released_at, record_date) DESC, id DESC'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Unable to load outbound summary report right now.';
}

function formatMoney($value): string
{
    return number_format((float) $value, 2, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outbound Summary Report</title>
    <link rel="stylesheet" href="style.css?v=20260227">
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
                    <small class="fw-normal" style="font-size: 0.72rem;">Outbound Summary Report</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Home</a>
                <a href="report.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Report</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link">Log out</a>
            </div>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <div class="card app-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h5 mb-0">Outbound Summary Report</h1>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="table-responsive" style="font-size: 0.85rem;">
                        <table class="table table-sm table-striped align-middle mb-0" style="font-size: 0.8rem;">
                            <thead class="table-light">
                                <tr style="font-size: 0.75rem;">
                                    <th style="padding: 0.35rem 0.5rem;">End-user</th>
                                    <th style="padding: 0.35rem 0.5rem;">PO #</th>
                                    <th style="padding: 0.35rem 0.5rem;">Date Released</th>
                                    <th style="padding: 0.35rem 0.5rem;">Item Description</th>
                                    <th style="padding: 0.35rem 0.5rem;">Exp Date</th>
                                    <th class="text-end" style="padding: 0.35rem 0.5rem;">Qty</th>
                                    <th style="padding: 0.35rem 0.5rem;">UOM</th>
                                    <th class="text-end" style="padding: 0.35rem 0.5rem;">Unit Cost</th>
                                    <th class="text-end" style="padding: 0.35rem 0.5rem;">Total Cost</th>
                                    <th style="padding: 0.35rem 0.5rem;">Recipient</th>
                                    <th style="padding: 0.35rem 0.5rem;">PTR #</th>
                                </tr>
                            </thead>
                            <tbody style="font-size: 0.75rem;">
                                <?php if (empty($rows)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center text-muted" style="padding: 0.35rem 0.5rem;">No outbound records found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr style="border-bottom: 0.5px solid #dee2e6;">
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['program'] ?? '-')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['po_no'] ?? '-')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['date_released'] ?? '-')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['description'] ?? '-')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['expiration_date'] ?? '-')) ?></td>
                                            <td class="text-end" style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['quantity'] ?? '0')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['unit'] ?? '-')) ?></td>
                                            <td class="text-end" style="padding: 0.35rem 0.5rem;"><?= formatMoney($row['unit_cost'] ?? 0) ?></td>
                                            <td class="text-end" style="padding: 0.35rem 0.5rem;"><?= formatMoney($row['total_cost'] ?? 0) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['recipient'] ?? '-')) ?></td>
                                            <td style="padding: 0.35rem 0.5rem;"><?= htmlspecialchars((string) ($row['ptr_no'] ?? '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
