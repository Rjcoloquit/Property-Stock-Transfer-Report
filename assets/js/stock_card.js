(function () {
    'use strict';

    /** Match PHP ptrNormalizeDuplicatePoRefPrefix for print preview only. */
    function normalizeDuplicatePoPrefixForPrint(raw) {
        var t = String(raw || '').trim();
        if (!t) {
            return t;
        }
        t = t.replace(/\s+/gu, ' ');
        try {
            t = t.replace(/\p{Pd}|\u2212/gu, '-');
        } catch (e) {
            t = t.replace(/[\u2010\u2011\u2013\u2014\u2212\uFF0D]/g, '-');
        }
        var prev;
        do {
            prev = t;
            t = t.replace(/^PO\s+(?=PO[\s./0-9\-])/iu, '');
            t = t.replace(/^PO(?=PO[\s./0-9\-])/iu, '');
        } while (prev !== t);
        return t;
    }

    document.querySelectorAll('.stock-card-print-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-print-target');
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target) {
                return;
            }

            var printWindow = window.open('', '_blank', 'width=1100,height=800');
            if (!printWindow) {
                alert('Unable to open print window. Please allow pop-ups for this site.');
                return;
            }

            var wrapper = document.createElement('div');
            wrapper.innerHTML = target.outerHTML;
            wrapper.querySelectorAll('textarea[placeholder]').forEach(function (ta) {
                ta.removeAttribute('placeholder');
            });
            wrapper.querySelectorAll('[data-ptr-print-dedupe-po]').forEach(function (inp) {
                inp.value = normalizeDuplicatePoPrefixForPrint(inp.value);
            });
            var innerHtml = wrapper.innerHTML;
            var printableHtml = '<div class="preview-sheet">' + innerHtml + '</div>';

            printWindow.document.write(
                '<!DOCTYPE html><html><head><title>Stock Card Preview</title>' +
                    '<base href="' +
                    window.location.href +
                    '">' +
                    '<style>' +
                    '@page{size:A4 landscape;margin:8mm;}' +
                    'body{font-family:Arial,sans-serif;padding:0;margin:0;background:#fff;color:#111;}' +
                    '.preview-sheet{border:1px solid #222;padding:8px;max-width:100%;box-sizing:border-box;}' +
                    '.stock-card-sheet{width:100%;max-width:100%;box-sizing:border-box;}' +
                    '.table-responsive{overflow:visible!important;margin:0;}' +
                    '.stock-card-master-table,.stock-card-ledger-table{width:100%;border-collapse:collapse;font-size:11px;table-layout:fixed;}' +
                    '.stock-card-master-table th,.stock-card-master-table td,.stock-card-ledger-table th,.stock-card-ledger-table td{border:1px solid #222;padding:4px 6px;vertical-align:middle;line-height:1.25;box-sizing:border-box;}' +
                    '.stock-card-title-cell{text-align:center;font-weight:700;font-size:13px;}' +
                    '.stock-card-label-cell{font-size:10px;font-weight:600;}' +
                    '.stock-card-ledger-table thead th{font-size:10px;font-weight:700;}' +
                    '.stock-card-line-input{border:none!important;background:transparent!important;resize:none;box-shadow:none!important;outline:none!important;width:100%;min-height:1.1em;padding:0 2px;font:inherit;margin:0;}' +
                    '.text-center{text-align:center;}' +
                    '.text-end{text-align:right;}' +
                    '.stock-card-ledger-table td:nth-child(7) .stock-card-line-input{white-space:normal;}' +
                    '@media print{.stock-card-line-input{border:none!important;background:transparent!important;}}' +
                    '</style></head><body>' +
                    printableHtml +
                    '</body></html>'
            );
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        });
    });
})();
