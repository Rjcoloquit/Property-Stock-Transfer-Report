(function () {
    'use strict';

    var config = window.createPtrConfig || {};
    var productMetaByDescription = config.productMetaByDescription || {};
    var batchNumbersByDescription = config.batchNumbersByDescription || {};
    var batchMetaByDescription = config.batchMetaByDescription || {};
    var hasProductBatches = !!config.hasProductBatches;
    var previewLineRows = Number.isFinite(Number(config.previewLineRows)) ? Number(config.previewLineRows) : 0;

    var productMetaByDescriptionLower = Object.keys(productMetaByDescription).reduce(function (acc, key) {
        acc[String(key).trim().toLowerCase()] = productMetaByDescription[key];
        return acc;
    }, {});
    var batchNumbersByDescriptionLower = Object.keys(batchNumbersByDescription).reduce(function (acc, key) {
        acc[String(key).trim().toLowerCase()] = batchNumbersByDescription[key];
        return acc;
    }, {});
    var batchMetaByDescriptionLower = Object.keys(batchMetaByDescription).reduce(function (acc, key) {
        acc[String(key).trim().toLowerCase()] = batchMetaByDescription[key];
        return acc;
    }, {});

    var ptrForm = document.getElementById('ptrForm');
    var nextPreviewBtn = document.getElementById('nextPreviewBtn');
    var addItemBtn = document.getElementById('addItemBtn');
    var itemRowsBody = document.getElementById('itemRowsBody');
    var grandTotalInput = document.getElementById('grand_total');
    var previewItemsBody = document.getElementById('previewItemsBody');
    var previewModalElement = document.getElementById('previewModal');
    var recordDateInput = document.getElementById('record_date');
    var ptrNoInput = document.getElementById('ptr_no');
    var recipientInput = document.getElementById('recipient');
    var printPreviewBtn = document.getElementById('printPreviewBtn');

    if (
        !ptrForm ||
        !nextPreviewBtn ||
        !addItemBtn ||
        !itemRowsBody ||
        !grandTotalInput ||
        !previewItemsBody ||
        !previewModalElement ||
        !recordDateInput ||
        !ptrNoInput ||
        !recipientInput ||
        !printPreviewBtn ||
        !window.bootstrap ||
        !window.bootstrap.Modal
    ) {
        return;
    }

    var previewModal = new window.bootstrap.Modal(previewModalElement);
    var batchListCounter = itemRowsBody.querySelectorAll('.item-row').length;

    function textOrDash(value) {
        var clean = String(value || '').trim();
        return clean === '' ? '-' : clean;
    }

    function toNumber(value) {
        var num = Number(value);
        return Number.isFinite(num) ? num : 0;
    }

    function getDescriptionMeta(descriptionValue) {
        var key = String(descriptionValue || '').trim();
        if (key === '') {
            return {
                selectedProduct: null,
                batchOptions: [],
                batchMeta: {}
            };
        }

        var exactProduct = productMetaByDescription[key];
        var exactBatchOptions = batchNumbersByDescription[key];
        var exactBatchMeta = batchMetaByDescription[key];
        if (exactProduct) {
            return {
                selectedProduct: exactProduct,
                batchOptions: Array.isArray(exactBatchOptions) ? exactBatchOptions : [],
                batchMeta: exactBatchMeta && typeof exactBatchMeta === 'object' ? exactBatchMeta : {}
            };
        }

        var lowerKey = key.toLowerCase();
        return {
            selectedProduct: productMetaByDescriptionLower[lowerKey] || null,
            batchOptions: Array.isArray(batchNumbersByDescriptionLower[lowerKey]) ? batchNumbersByDescriptionLower[lowerKey] : [],
            batchMeta: batchMetaByDescriptionLower[lowerKey] && typeof batchMetaByDescriptionLower[lowerKey] === 'object'
                ? batchMetaByDescriptionLower[lowerKey]
                : {}
        };
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function calculateRowAmount(row) {
        var quantityInput = row.querySelector('.item-quantity');
        var unitCostInput = row.querySelector('.item-unit-cost');
        var amountInput = row.querySelector('.item-amount');
        var quantity = toNumber(quantityInput.value);
        var unitCost = toNumber(unitCostInput.value);

        if (quantity <= 0 || unitCost < 0) {
            amountInput.value = '';
            return 0;
        }

        var amount = quantity * unitCost;
        amountInput.value = amount.toFixed(2);
        return amount;
    }

    function updateGrandTotal() {
        var total = 0;
        itemRowsBody.querySelectorAll('.item-row').forEach(function (row) {
            total += calculateRowAmount(row);
        });
        grandTotalInput.value = total.toFixed(2);
    }

    function applyRowMeta(row) {
        var descriptionSelect = row.querySelector('.item-description');
        var descriptionValue = descriptionSelect.value.trim();
        var batchInput = row.querySelector('.item-batch-number');
        var batchValue = batchInput.value.trim();
        var batchListId = batchInput.getAttribute('list');
        var batchDatalist = batchListId ? document.getElementById(batchListId) : null;
        var unitInput = row.querySelector('.item-unit');
        var unitCostInput = row.querySelector('.item-unit-cost');
        var expirationInput = row.querySelector('.item-expiration');
        var poNoInput = row.querySelector('.item-po-number');
        var meta = getDescriptionMeta(descriptionValue);
        var selectedProduct = meta.selectedProduct;
        var batchOptions = meta.batchOptions;
        var batchMeta = meta.batchMeta;

        if (batchDatalist) {
            batchDatalist.innerHTML = batchOptions
                .map(function (batchNo) { return '<option value="' + escapeHtml(batchNo) + '"></option>'; })
                .join('');
        }

        if (!selectedProduct) {
            unitInput.value = '';
            unitInput.dataset.autoFilled = '0';
            unitCostInput.value = '';
            expirationInput.value = '';
            if (poNoInput) {
                poNoInput.value = '';
                poNoInput.dataset.autoFilled = '0';
            }
            calculateRowAmount(row);
            updateGrandTotal();
            return;
        }

        if (unitInput.value.trim() === '' || unitInput.dataset.autoFilled === '1') {
            unitInput.value = selectedProduct.unit || '';
            unitInput.dataset.autoFilled = '1';
        }
        if (poNoInput && (poNoInput.value.trim() === '' || poNoInput.dataset.autoFilled === '1')) {
            poNoInput.value = selectedProduct.po_no || '';
            poNoInput.dataset.autoFilled = '1';
        }
        var parsedUnitCost = Number(selectedProduct.unit_cost);
        unitCostInput.value = Number.isFinite(parsedUnitCost) ? parsedUnitCost.toFixed(2) : '';
        var selectedBatchMeta = getBatchMetaForSelection(descriptionValue, batchValue);
        var batchExpiration = selectedBatchMeta
            ? String(selectedBatchMeta.expiration_date || '')
            : '';
        expirationInput.value = batchExpiration !== '' ? batchExpiration : (selectedProduct.expiration_date || '');
        calculateRowAmount(row);
        updateGrandTotal();
    }

    function getBatchMetaForSelection(descriptionValue, batchValue) {
        var description = String(descriptionValue || '').trim();
        var batchNumber = String(batchValue || '').trim();
        if (description === '' || batchNumber === '') {
            return null;
        }
        var meta = getDescriptionMeta(description);
        var batchMeta = meta.batchMeta && typeof meta.batchMeta === 'object' ? meta.batchMeta : {};
        if (batchMeta[batchNumber]) {
            return batchMeta[batchNumber];
        }
        var batchLower = batchNumber.toLowerCase();
        for (var key in batchMeta) {
            if (Object.prototype.hasOwnProperty.call(batchMeta, key) && String(key).toLowerCase() === batchLower) {
                return batchMeta[key];
            }
        }
        return null;
    }

    function refreshCreateRowStockHints() {
        var rows = Array.from(itemRowsBody.querySelectorAll('.item-row'));
        if (!hasProductBatches) {
            rows.forEach(function (row) {
                var quantityInput = row.querySelector('.item-quantity');
                var hintEl = row.querySelector('.item-stock-hint');
                if (!quantityInput || !hintEl) {
                    return;
                }
                quantityInput.removeAttribute('max');
                quantityInput.setCustomValidity('');
                hintEl.textContent = '';
            });
            return;
        }

        var plannedByBatchId = {};
        var rowStates = rows.map(function (row) {
            var descriptionInput = row.querySelector('.item-description');
            var batchInput = row.querySelector('.item-batch-number');
            var quantityInput = row.querySelector('.item-quantity');
            var description = descriptionInput ? descriptionInput.value.trim() : '';
            var batchNumber = batchInput ? batchInput.value.trim() : '';
            var quantity = quantityInput ? (parseInt(quantityInput.value || '0', 10) || 0) : 0;
            var selectedBatchMeta = getBatchMetaForSelection(description, batchNumber);
            var batchId = selectedBatchMeta ? (parseInt(selectedBatchMeta.batch_id || '0', 10) || 0) : 0;
            var availableStock = selectedBatchMeta ? (parseInt(selectedBatchMeta.stock_quantity || '0', 10) || 0) : 0;
            if (batchId > 0) {
                plannedByBatchId[batchId] = (plannedByBatchId[batchId] || 0) + quantity;
            }
            return {
                quantityInput: quantityInput,
                hintEl: row.querySelector('.item-stock-hint'),
                batchNumber: batchNumber,
                quantity: quantity,
                batchId: batchId,
                availableStock: availableStock
            };
        });

        rowStates.forEach(function (state) {
            if (!state.quantityInput || !state.hintEl) {
                return;
            }
            state.quantityInput.setCustomValidity('');
            if (state.batchNumber === '') {
                state.quantityInput.removeAttribute('max');
                state.hintEl.textContent = '';
                return;
            }
            if (state.batchId <= 0) {
                state.quantityInput.removeAttribute('max');
                state.hintEl.textContent = 'Selected batch is not valid for this item.';
                return;
            }
            var otherPlanned = (plannedByBatchId[state.batchId] || 0) - state.quantity;
            var remainingForRow = Math.max(0, state.availableStock - otherPlanned);
            state.quantityInput.setAttribute('max', String(remainingForRow));
            state.hintEl.textContent = 'Remaining stock available: ' + remainingForRow;
            if (state.quantity > remainingForRow) {
                state.quantityInput.setCustomValidity('Quantity exceeds remaining stock.');
            }
        });
    }

    function createItemRow(itemData) {
        var data = itemData || {};
        var tr = document.createElement('tr');
        tr.className = 'item-row';
        var descriptionValue = data.description || '';
        var batchListId = 'batchOptionsList_dynamic_' + batchListCounter++;
        var batchOptions = batchNumbersByDescription[descriptionValue] || [];
        var batchOptionsHtml = batchOptions
            .map(function (batchNo) { return '<option value="' + escapeHtml(batchNo) + '"></option>'; })
            .join('');
        tr.innerHTML =
            '<td><input type="text" name="description[]" class="form-control item-description" list="descriptionOptionsList" value="' + escapeHtml(data.description || '') + '" placeholder="Type or select item description"></td>' +
            '<td><input type="text" name="batch_number[]" class="form-control item-batch-number" list="' + batchListId + '" value="' + escapeHtml(data.batch_number || '') + '" placeholder="Batch no."><datalist id="' + batchListId + '" class="item-batch-options">' + batchOptionsHtml + '</datalist></td>' +
            '<td><input type="text" name="quantity[]" class="form-control item-quantity" inputmode="numeric" pattern="[0-9]*" autocomplete="off" value="' + escapeHtml(data.quantity || '') + '"><div class="form-text item-stock-hint"></div></td>' +
            '<td><input type="text" name="unit[]" class="form-control item-unit" list="unitOptionsList" value="' + escapeHtml(data.unit || '') + '" placeholder="Type or select unit"></td>' +
            '<td><input type="text" class="form-control item-unit-cost" value="' + escapeHtml(data.unit_cost || '') + '" readonly></td>' +
            '<td><input type="text" class="form-control item-amount" value="" readonly></td>' +
            '<td><input type="text" name="program[]" class="form-control item-program" list="programOptionsList" value="' + escapeHtml(data.program || '') + '" placeholder="Type or select program"></td>' +
            '<td><input type="text" name="po_number[]" class="form-control item-po-number" value="' + escapeHtml(data.po_no || '') + '" placeholder="PO Number"></td>' +
            '<td><input type="date" class="form-control item-expiration" value="' + escapeHtml(data.expiration_date || '') + '" readonly></td>' +
            '<td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm remove-item-btn">Remove</button></td>';
        return tr;
    }

    function getEnteredItems() {
        var items = [];
        itemRowsBody.querySelectorAll('.item-row').forEach(function (row) {
            var description = row.querySelector('.item-description').value.trim();
            var batchNumber = row.querySelector('.item-batch-number').value.trim();
            var quantity = row.querySelector('.item-quantity').value.trim();
            var unit = row.querySelector('.item-unit').value.trim();
            var unitCost = row.querySelector('.item-unit-cost').value.trim();
            var amount = row.querySelector('.item-amount').value.trim();
            var program = row.querySelector('.item-program').value.trim();
            var poNumber = row.querySelector('.item-po-number') ? row.querySelector('.item-po-number').value.trim() : '';
            var expiration = row.querySelector('.item-expiration').value.trim();

            if (description === '' && batchNumber === '' && quantity === '') {
                return;
            }

            items.push({
                description: description,
                batch_number: batchNumber,
                quantity: quantity,
                unit: unit,
                unit_cost: unitCost,
                amount: amount,
                program: program,
                po_no: poNumber,
                expiration_date: expiration
            });
        });
        return items;
    }

    function renderPreviewItems(items) {
        previewItemsBody.innerHTML = '';

        var rowsToRender = items.slice(0, previewLineRows);
        rowsToRender.forEach(function (item) {
            var tr = document.createElement('tr');
            var descriptionWithBatch = item.batch_number
                ? textOrDash(item.description) + ' / ' + item.batch_number
                : textOrDash(item.description);
            tr.innerHTML =
                '<td>' + escapeHtml(textOrDash(item.expiration_date)) + '</td>' +
                '<td>' + escapeHtml(textOrDash(item.unit)) + '</td>' +
                '<td>' + escapeHtml(descriptionWithBatch) + '</td>' +
                '<td>' + escapeHtml(textOrDash(item.quantity)) + '</td>' +
                '<td>' + escapeHtml(textOrDash(item.unit_cost)) + '</td>' +
                '<td>' + escapeHtml(textOrDash(item.amount)) + '</td>' +
                '<td>' + escapeHtml(textOrDash(item.program)) + '</td>' +
                '<td>' + escapeHtml(textOrDash(item.po_no)) + '</td>';
            previewItemsBody.appendChild(tr);
        });

        for (var i = rowsToRender.length; i < previewLineRows; i++) {
            var blank = document.createElement('tr');
            blank.innerHTML = '<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>';
            previewItemsBody.appendChild(blank);
        }
    }

    function syncRowRequiredState(row) {
        var descriptionInput = row.querySelector('.item-description');
        var batchInput = row.querySelector('.item-batch-number');
        var quantityInput = row.querySelector('.item-quantity');
        var programInput = row.querySelector('.item-program');

        var description = descriptionInput.value.trim();
        var batchNumber = batchInput.value.trim();
        var quantity = quantityInput.value.trim();
        var program = programInput.value.trim();
        var isActiveRow = description !== '' || batchNumber !== '' || quantity !== '' || program !== '';

        descriptionInput.required = isActiveRow;
        programInput.required = isActiveRow;
        descriptionInput.setCustomValidity('');
        programInput.setCustomValidity('');

        return {
            descriptionInput: descriptionInput,
            programInput: programInput,
            isActiveRow: isActiveRow,
            description: description,
            program: program
        };
    }

    function validateProgramsBeforeNext() {
        var rows = itemRowsBody.querySelectorAll('.item-row');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var state = syncRowRequiredState(row);
            if (!state.isActiveRow) {
                continue;
            }
            if (state.description === '') {
                state.descriptionInput.reportValidity();
                return false;
            }
            if (state.program === '') {
                state.programInput.reportValidity();
                return false;
            }
        }
        return true;
    }

    function fillPreview() {
        var enteredItems = getEnteredItems();
        document.getElementById('previewDate').textContent = textOrDash(recordDateInput.value);
        document.getElementById('previewPtrNo').textContent = textOrDash(ptrNoInput.value);
        document.getElementById('previewRecipient').textContent = textOrDash(recipientInput.value);
        renderPreviewItems(enteredItems);
        document.getElementById('previewTotal').textContent = textOrDash(grandTotalInput.value || '0.00');
        return enteredItems.length > 0;
    }

    nextPreviewBtn.addEventListener('click', function () {
        if (!ptrForm.reportValidity()) {
            return;
        }
        if (!validateProgramsBeforeNext()) {
            return;
        }
        if (!fillPreview()) {
            var firstRow = itemRowsBody.querySelector('.item-row');
            if (!firstRow) {
                return;
            }
            var firstDescriptionInput = firstRow.querySelector('.item-description');
            if (!firstDescriptionInput) {
                return;
            }
            firstDescriptionInput.required = true;
            firstDescriptionInput.reportValidity();
            firstDescriptionInput.required = false;
            return;
        }
        previewModal.show();
    });

    addItemBtn.addEventListener('click', function () {
        itemRowsBody.appendChild(createItemRow());
        refreshCreateRowStockHints();
    });

    itemRowsBody.addEventListener('change', function (event) {
        if (event.target.classList.contains('item-description') || event.target.classList.contains('item-batch-number')) {
            applyRowMeta(event.target.closest('.item-row'));
            refreshCreateRowStockHints();
        }
    });

    itemRowsBody.addEventListener('input', function (event) {
        if (event.target.classList.contains('item-description')) {
            var row = event.target.closest('.item-row');
            syncRowRequiredState(row);
            applyRowMeta(row);
            refreshCreateRowStockHints();
            return;
        }
        if (event.target.classList.contains('item-program')) {
            syncRowRequiredState(event.target.closest('.item-row'));
            return;
        }
        if (event.target.classList.contains('item-unit')) {
            event.target.dataset.autoFilled = '0';
            return;
        }
        if (event.target.classList.contains('item-po-number')) {
            event.target.dataset.autoFilled = '0';
            return;
        }
        if (event.target.classList.contains('item-batch-number')) {
            var batchRow = event.target.closest('.item-row');
            syncRowRequiredState(batchRow);
            applyRowMeta(batchRow);
            refreshCreateRowStockHints();
            return;
        }
        if (event.target.classList.contains('item-quantity')) {
            event.target.value = event.target.value.replace(/\D+/g, '');
            var qtyRow = event.target.closest('.item-row');
            syncRowRequiredState(qtyRow);
            calculateRowAmount(qtyRow);
            updateGrandTotal();
            refreshCreateRowStockHints();
        }
    });

    itemRowsBody.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-item-btn')) {
            return;
        }
        var rows = itemRowsBody.querySelectorAll('.item-row');
        var row = event.target.closest('.item-row');
        if (rows.length <= 1) {
            row.querySelector('.item-description').value = '';
            row.querySelector('.item-batch-number').value = '';
            row.querySelector('.item-program').value = '';
            var poInput = row.querySelector('.item-po-number');
            if (poInput) { poInput.value = ''; }
            row.querySelector('.item-quantity').value = '';
            applyRowMeta(row);
            refreshCreateRowStockHints();
            return;
        }
        row.remove();
        updateGrandTotal();
        refreshCreateRowStockHints();
    });

    printPreviewBtn.addEventListener('click', function () {
        fillPreview();
        var previewHtml = document.getElementById('previewPrintArea').outerHTML;
        var printWindow = window.open('', '_blank', 'width=1100,height=800');
        if (!printWindow) {
            alert('Unable to open print window. Please allow pop-ups for this site.');
            return;
        }

        printWindow.document.write(
            '<!DOCTYPE html><html><head><title>PTR Preview</title>' +
            '<base href="' + window.location.href + '">' +
            '<style>' +
            '@page{size:A4 landscape;margin:8mm;}' +
            'body{font-family:Arial,sans-serif;padding:0;margin:0;background:#fff;color:#111;}' +
            '.preview-sheet{border:1px solid #222;padding:8px;max-width:100%;}' +
            '.preview-sheet table{width:100%;border-collapse:collapse;font-size:11px;}' +
            '.preview-sheet th,.preview-sheet td{border:1px solid #222;padding:4px 6px;vertical-align:top;}' +
            '.preview-header{display:grid;grid-template-columns:48px auto 48px;align-items:center;column-gap:12px;margin-bottom:8px;justify-content:center;}' +
            '.preview-title{font-weight:700;font-size:18px;text-align:center;margin:0;}' +
            '.preview-logo-wrap{width:48px;height:48px;display:flex;align-items:center;justify-content:center;}' +
            '.preview-logo-wrap img{width:46px;height:46px;object-fit:contain;}' +
            '.preview-label{font-weight:700;}' +
            '.signatory-table td{text-align:center;vertical-align:middle;height:84px;}' +
            '.signatory-content{display:inline-block;text-align:center;line-height:1.4;}' +
            '.signatory-label{display:block;margin-bottom:8px;}' +
            '.received-box{width:100%;display:flex;flex-direction:column;align-items:center;justify-content:space-between;min-height:82px;padding:2px 0;}' +
            '.received-top{display:flex;align-items:center;justify-content:center;}' +
            '.received-bottom{border:0;padding:0;font-size:8px;line-height:1.1;white-space:nowrap;}' +
            '.text-end{text-align:right;}' +
            '.ptr-signatory-name{border:none !important;background:transparent !important;resize:none;box-shadow:none !important;outline:none !important;width:100%;min-height:2.5em;padding:0 2px;font:inherit;text-align:center;overflow:visible;}' +
            '@media print{.ptr-signatory-name{border:none !important;background:transparent !important;}}' +
            '</style></head><body>' + previewHtml + '</body></html>'
        );
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    });

    itemRowsBody.querySelectorAll('.item-row').forEach(function (row) {
        applyRowMeta(row);
    });
    updateGrandTotal();
    refreshCreateRowStockHints();
})();
