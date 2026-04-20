<?php

/** Strip punctuation often seen in pasted/JSON-like data so print preview stays clean. */
function ptrSanitizeForPrintPreview(string $value): string
{
    return trim(preg_replace('/[\(\)\{\}\[\]:;]/u', '', $value));
}

function ptrPrintPreviewText(?string $value, string $empty = '-'): string
{
    $cleaned = ptrSanitizeForPrintPreview((string) $value);
    return htmlspecialchars($cleaned !== '' ? $cleaned : $empty);
}

/** True when $t already looks like a PO reference (avoid storing "PO PO-123"). */
function ptrPoNoAlreadyHasPoPrefix(string $t): bool
{
    $t = trim($t);
    return $t !== '' && preg_match('/^P\.?\s*O\.?\s*($|[\/\-0-9])/i', $t) === 1;
}

/** Stock card ledger ref_no for Manage Items receipts. */
function ptrFormatStockCardPoRefNo(string $poNo): string
{
    $t = trim($poNo);
    if ($t === '') {
        return 'Manage Items';
    }
    return ptrPoNoAlreadyHasPoPrefix($t) ? $t : ('PO ' . $t);
}

/**
 * Collapse duplicate PO prefixes ("PO PO-94324" → "PO-94324") for display.
 * Loops to handle "PO PO PO-…". Does not strip "PO " before a bare number ("PO 94324" unchanged).
 * Normalizes Unicode dashes so "PO PO−94324" (minus U+2212) still matches.
 */
function ptrNormalizeDuplicatePoRefPrefix(string $value): string
{
    $t = trim((string) $value);
    if ($t === '') {
        return $t;
    }
    $t = preg_replace('/\s+/u', ' ', $t);
    // Pd = dashes; U+2212 = minus sign (Sm) — normalize so "PO PO−94324" collapses like ASCII hyphen
    $t = preg_replace('/[\p{Pd}\x{2212}]/u', '-', $t);
    $prev = '';
    while ($prev !== $t) {
        $prev = $t;
        // Second "PO" must look like a PO token (PO-…, PO 1, PO.1), not "PO" in "PORT"
        $t = preg_replace('/^PO\s+(?=PO[\s\-\/.0-9])/iu', '', $t);
        // No space between duplicate tokens: "POPO-94324"
        $t = preg_replace('/^PO(?=PO[\s\-\/.0-9])/iu', '', $t);
    }
    return $t;
}

/**
 * Full PTR landscape sheet for Transaction History print (cloned by report.js). Hidden on screen (d-none).
 * Signatory fields have no name attribute; report.js copies values from the visible editor before printing.
 */
function ptr_render_report_ptr_print_sheet(
    string $previewElementId,
    array $group,
    float $groupTotal,
    int $previewLineRows,
    string $preparedBody,
    string $approvedBody,
    string $issuedBody
): void {
    $root = dirname(__DIR__);
    $pgpPath = $root . DIRECTORY_SEPARATOR . 'PGP.png';
    $phoPath = $root . DIRECTORY_SEPARATOR . 'PHO.png';
    ?>
    <div
        id="<?= htmlspecialchars($previewElementId, ENT_QUOTES, 'UTF-8') ?>"
        class="preview-sheet report-ptr-preview-sheet report-ptr-print-only d-none"
        aria-hidden="true"
    >
        <div class="preview-header">
            <div class="preview-logo-wrap">
                <?php if (file_exists($pgpPath)): ?>
                    <img src="PGP.png" alt="PGP Logo">
                <?php endif; ?>
            </div>
            <div class="preview-title">Property Stock Transfer Report</div>
            <div class="preview-logo-wrap">
                <?php if (file_exists($phoPath)): ?>
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
                <td><span class="preview-label">Date:</span> <?= ptrPrintPreviewText($group['record_date'] ?? null) ?></td>
                <td><span class="preview-label">PTR No.:</span> <?= ptrPrintPreviewText($group['ptr_no'] ?? null) ?></td>
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
                    <th>Program</th>
                    <th>PO Number</th>
                </tr>
            </thead>
            <tbody>
                <?php $renderedRows = 0; ?>
                <?php foreach ($group['items'] as $record): ?>
                    <?php if ($renderedRows >= $previewLineRows) {
                        break;
                    } ?>
                    <?php
                        $descriptionValue = trim((string) ($record['description'] ?? ''));
                        $batchValue = trim((string) ($record['batch_number'] ?? ''));
                        $descriptionWithBatch = $batchValue !== '' ? $descriptionValue . ' / ' . $batchValue : $descriptionValue;
                        $descriptionWithBatch = ptrSanitizeForPrintPreview($descriptionWithBatch);
                        $quantityValue = (float) ($record['quantity'] ?? 0);
                        $unitCostValue = (float) ($record['unit_cost'] ?? 0);
                        $amountValue = $quantityValue * $unitCostValue;
                    ?>
                    <tr>
                        <td><?= ptrPrintPreviewText($record['expiration_date'] ?? null) ?></td>
                        <td><?= ptrPrintPreviewText($record['unit'] ?? null) ?></td>
                        <td><?= ptrPrintPreviewText($descriptionWithBatch !== '' ? $descriptionWithBatch : null) ?></td>
                        <td><?= htmlspecialchars((string) ($record['quantity'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars(number_format($unitCostValue, 2, '.', '')) ?></td>
                        <td><?= htmlspecialchars(number_format($amountValue, 2, '.', '')) ?></td>
                        <td><?= ptrPrintPreviewText($record['program'] ?? null) ?></td>
                        <td><?= ptrPrintPreviewText($record['po_no'] ?? null) ?></td>
                    </tr>
                    <?php $renderedRows++; ?>
                <?php endforeach; ?>
                <?php for ($i = $renderedRows; $i < $previewLineRows; $i++): ?>
                    <tr>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                    </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-end"><strong>TOTAL:</strong></td>
                    <td><?= htmlspecialchars(number_format($groupTotal, 2, '.', '')) ?></td>
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <table>
            <tr>
                <td colspan="4"><span class="preview-label">Purpose:</span><br><em>For the use of</em> <?= ptrPrintPreviewText($group['recipient'] ?? null) ?></td>
            </tr>
        </table>
        <table class="signatory-table">
            <tr>
                <td class="preview-signatory-half">
                    <div class="signatory-content">
                        <span class="preview-label signatory-label">Prepared by:</span>
                        <textarea class="ptr-signatory-name" rows="4" spellcheck="false" autocomplete="off" placeholder="Mark Anthony Borres,&#10;John Paul Joseph Opiala,&#10;Richard Roy"><?= htmlspecialchars($preparedBody) ?></textarea>
                    </div>
                </td>
                <td class="preview-signatory-half">
                    <div class="signatory-content">
                        <span class="preview-label signatory-label">Approved by:</span>
                        <textarea class="ptr-signatory-name" rows="3" spellcheck="false" autocomplete="off" placeholder="Elizabeth C. Calaor, RPh&#10;(Pharmacist II/ Head, Supply &amp; Logistics Unit)"><?= htmlspecialchars($approvedBody) ?></textarea>
                        <span class="preview-approved-date"><?= htmlspecialchars(date('m/d/Y')) ?></span>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="preview-signatory-half">
                    <div class="signatory-content">
                        <span class="preview-label signatory-label">Issued by:</span>
                        <textarea class="ptr-signatory-name ptr-signatory-name--issued" rows="3" spellcheck="false" autocomplete="off" placeholder="Jannete Ventura,&#10;Earnest John Tolentino, RPh"><?= htmlspecialchars($issuedBody) ?></textarea>
                    </div>
                </td>
                <td class="preview-signatory-half">
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
    <?php
}
