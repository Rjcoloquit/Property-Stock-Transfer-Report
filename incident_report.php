<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Report - Supply</title>
    <link rel="stylesheet" href="style.css?v=20260219">
    <style>
        .incident-report-page .ir-label { font-weight: 600; margin-bottom: 0.25rem; }
        .incident-report-page .ir-input { border: none; border-bottom: 1px solid #333; width: 100%; background: transparent; }
        .incident-report-page .ir-row { margin-bottom: 1rem; }
        .incident-report-page .ir-title { font-size: 1.25rem; font-weight: 700; text-align: center; margin: 1.25rem 0; }
        .incident-report-page .ir-textarea { width: 100%; min-height: 70px; border: 1px solid #dee2e6; border-radius: 0.25rem; padding: 0.5rem; resize: vertical; }
        .incident-report-page .ir-sign-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1.5rem; }
        .incident-report-page .ir-sig-block .ir-sig-line { border-bottom: 1px solid #333; min-height: 28px; margin-bottom: 0.35rem; }
        .incident-report-page .ir-sig-block .ir-sig-label { font-size: 0.85rem; color: #555; margin-top: 0.5rem; }
        .incident-report-page .ir-specs-table { width: 100%; border-collapse: collapse; margin-top: 0.25rem; }
        .incident-report-page .ir-specs-table th { text-align: left; padding: 0.35rem 0.5rem; border: 1px solid #dee2e6; font-size: 0.85rem; }
        .incident-report-page .ir-specs-table td { padding: 0.35rem 0.5rem; border: 1px solid #dee2e6; }
        .incident-report-page .ir-specs-table input { border: none; width: 100%; background: transparent; }
        @media print { .incident-report-page .no-print { display: none !important; } }
    </style>
</head>
<body class="incident-report-page report-page">
    <header class="navbar navbar-expand-lg navbar-light bg-white app-header px-3 px-md-4 no-print">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h6 app-header-title d-flex align-items-center gap-2">
                <?php if (file_exists(__DIR__ . '/PHO.png')): ?>
                    <a href="home.php" class="app-header-logo-link" aria-label="Go to homepage">
                        <img src="PHO.png" alt="Palawan Health Office Logo" class="app-logo-circle" style="height: 40px; width: 40px;">
                    </a>
                <?php endif; ?>
                <span class="d-inline-flex flex-column lh-sm">
                    <span>Provincial Health Office</span>
                    <small class="fw-normal" style="font-size: 0.72rem;">Incident Report</small>
                </span>
            </span>
            <div class="app-header-actions">
                <span class="app-user-chip"><?= htmlspecialchars($username) ?></span>
                <a href="home.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Home</a>
                <a href="report.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Report</a>
                <a href="create_ptr.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Create PTR</a>
                <a href="pending_transactions.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Pending</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm app-header-action-link report-header-btn">Log out</a>
            </div>
        </div>
    </header>

    <main class="py-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h1 class="h5 mb-0">Incident Report</h1>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print();">Print</button>
            </div>

            <div class="card app-card">
                <div class="card-body">
                    <div class="ir-row">
                        <div class="ir-label">NAME OF OFFICE</div>
                        <input type="text" class="ir-input" name="name_of_office" form="incidentForm">
                    </div>
                    <div class="ir-row">
                        <div class="ir-label">ADDRESS</div>
                        <input type="text" class="ir-input" name="address" form="incidentForm">
                    </div>

                    <h2 class="ir-title">INCIDENT REPORT</h2>
                    <div class="ir-row">
                        <div class="ir-label">NO:</div>
                        <input type="text" class="ir-input" name="incident_no" form="incidentForm">
                    </div>

                    <div class="ir-row">
                        <div class="ir-label">INCIDENT TYPE:</div>
                        <input type="text" class="ir-input" name="incident_type" form="incidentForm">
                    </div>
                    <div class="ir-row">
                        <div class="ir-label">LOCATION:</div>
                        <input type="text" class="ir-input" name="location" form="incidentForm">
                    </div>

                    <div class="ir-row">
                        <div class="ir-label">SPECIFICS:</div>
                        <div class="table-responsive">
                            <table class="ir-specs-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>OUM</th>
                                        <th>Program</th>
                                        <th>PO #</th>
                                        <th>Batch #</th>
                                        <th>Exp date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="spec_item[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_oum[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_program[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_po[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_batch[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_exp[]" form="incidentForm"></td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="spec_item[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_oum[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_program[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_po[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_batch[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_exp[]" form="incidentForm"></td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="spec_item[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_oum[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_program[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_po[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_batch[]" form="incidentForm"></td>
                                        <td><input type="text" name="spec_exp[]" form="incidentForm"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="ir-row">
                        <div class="ir-label">PERSONS INVOLVED:</div>
                        <input type="text" class="ir-input" name="persons_involved" form="incidentForm">
                    </div>

                    <div class="ir-row">
                        <div class="ir-label">CHRONOLOGY OF EVENTS:</div>
                        <textarea class="ir-textarea" name="chronology" form="incidentForm" rows="4"></textarea>
                    </div>

                    <div class="ir-row">
                        <div class="ir-label">FOLLOW-UP ACTIONS:</div>
                        <textarea class="ir-textarea" name="followup_actions" form="incidentForm" rows="3"></textarea>
                    </div>

                    <div class="ir-row">
                        <div class="ir-label">WITNESS(ES) &amp; DESIGNATION (IF ANY):</div>
                        <textarea class="ir-textarea" name="witnesses" form="incidentForm" rows="2"></textarea>
                    </div>

                    <div class="ir-row">
                        <div class="ir-label">CONTACT DETAILS:</div>
                        <textarea class="ir-textarea" name="contact_details" form="incidentForm" rows="2"></textarea>
                    </div>

                    <div class="ir-sign-grid">
                        <div class="ir-sig-block">
                            <div class="ir-label">PREPARED BY:</div>
                            <div class="ir-sig-line" style="min-height: 36px;"></div>
                            <div class="ir-sig-label">SIGNATURE:</div>
                            <input type="text" class="ir-input mt-1" name="prepared_by_name" form="incidentForm">
                            <div class="ir-sig-label">NAME:</div>
                            <input type="text" class="ir-input mt-1" name="prepared_by_designation" form="incidentForm">
                            <div class="ir-sig-label">DESIGNATION:</div>
                            <input type="date" class="ir-input mt-1" name="prepared_by_date" form="incidentForm">
                            <div class="ir-sig-label">DATE:</div>
                        </div>
                        <div class="ir-sig-block">
                            <div class="ir-label">SUBMITTED TO:</div>
                            <div class="ir-sig-line" style="min-height: 36px;"></div>
                            <div class="ir-sig-label">SIGNATURE:</div>
                            <input type="text" class="ir-input mt-1" name="submitted_to_name" form="incidentForm">
                            <div class="ir-sig-label">NAME:</div>
                            <input type="text" class="ir-input mt-1" name="submitted_to_designation" form="incidentForm">
                            <div class="ir-sig-label">DESIGNATION:</div>
                            <input type="date" class="ir-input mt-1" name="submitted_to_date" form="incidentForm">
                            <div class="ir-sig-label">DATE:</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <form id="incidentForm" method="post" action="incident_report.php" class="d-none" aria-hidden="true"></form>
</body>
</html>
