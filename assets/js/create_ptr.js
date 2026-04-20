(function () {
    'use strict';

    var config = window.createPtrConfig || {};
    var productMetaByDescription = config.productMetaByDescription || {};
    var batchNumbersByDescription = config.batchNumbersByDescription || {};
    var batchMetaByDescription = config.batchMetaByDescription || {};
    var unitOptionsByDescription = config.unitOptionsByDescription || {};
    var programOptionsByDescription = config.programOptionsByDescription || {};
    var poOptionsByDescription = config.poOptionsByDescription || {};
    var costByDescriptionAndPo = config.costByDescriptionAndPo || {};
    var productMetaByDescriptionPo = config.productMetaByDescriptionPo || {};
    var batchNumbersByDescriptionPo = config.batchNumbersByDescriptionPo || {};
    var batchMetaByDescriptionPo = config.batchMetaByDescriptionPo || {};
    var quantityByDescriptionAndPo = config.quantityByDescriptionAndPo || {};
    var hasProductBatches = !!config.hasProductBatches;
    var previewLineRows = Number.isFinite(Number(config.previewLineRows)) ? Number(config.previewLineRows) : 0;

    var canonicalDescriptionByLower = Object.keys(productMetaByDescription).reduce(function (acc, key) {
        acc[String(key).trim().toLowerCase()] = String(key).trim();
        return acc;
    }, {});

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
    var unitOptionsByDescriptionLower = Object.keys(unitOptionsByDescription).reduce(function (acc, key) {
        acc[String(key).trim().toLowerCase()] = unitOptionsByDescription[key];
        return acc;
    }, {});
    var programOptionsByDescriptionLower = Object.keys(programOptionsByDescription).reduce(function (acc, key) {
        acc[String(key).trim().toLowerCase()] = programOptionsByDescription[key];
        return acc;
    }, {});
    var poOptionsByDescriptionLower = Object.keys(poOptionsByDescription).reduce(function (acc, key) {
        acc[String(key).trim().toLowerCase()] = poOptionsByDescription[key];
        return acc;
    }, {});

    function getDescriptionKeyForInput(descriptionValue) {
        var input = String(descriptionValue || '').trim();
        if (input === '') {
            return '';
        }
        if (Object.prototype.hasOwnProperty.call(productMetaByDescription, input)) {
            return input;
        }
        var inputLower = input.toLowerCase();
        if (Object.prototype.hasOwnProperty.call(canonicalDescriptionByLower, inputLower)) {
            return canonicalDescriptionByLower[inputLower];
        }

        var bestPrefix = '';
        Object.keys(canonicalDescriptionByLower).forEach(function (knownLower) {
            if (knownLower.indexOf(inputLower) === 0 && bestPrefix === '') {
                bestPrefix = canonicalDescriptionByLower[knownLower];
            }
        });
        if (bestPrefix !== '') {
            return bestPrefix;
        }

        var bestContains = '';
        Object.keys(canonicalDescriptionByLower).forEach(function (knownLower) {
            if (bestContains === '' && knownLower.indexOf(inputLower) !== -1) {
                bestContains = canonicalDescriptionByLower[knownLower];
            }
        });
        return bestContains;
    }

    function getUnitCostByDescriptionPo(descriptionValue, poNoValue, fallbackUnitCost) {
        var descriptionKey = getDescriptionKeyForInput(descriptionValue);
        var description = String(descriptionKey || '').trim().toLowerCase();
        var poNo = String(poNoValue || '').trim().toLowerCase();
        var mapKey = description + '|' + poNo;
        if (Object.prototype.hasOwnProperty.call(costByDescriptionAndPo, mapKey)) {
            var mappedCost = Number(costByDescriptionAndPo[mapKey]);
            return Number.isFinite(mappedCost) ? mappedCost.toFixed(2) : '';
        }
        var parsedFallback = Number(fallbackUnitCost);
        return Number.isFinite(parsedFallback) ? parsedFallback.toFixed(2) : '';
    }

    function getProductMetaByDescriptionPo(descriptionValue, poNoValue) {
        var descriptionKey = getDescriptionKeyForInput(descriptionValue);
        var description = String(descriptionKey || '').trim().toLowerCase();
        var poNo = String(poNoValue || '').trim().toLowerCase();
        var mapKey = description + '|' + poNo;
        if (Object.prototype.hasOwnProperty.call(productMetaByDescriptionPo, mapKey)) {
            return productMetaByDescriptionPo[mapKey];
        }
        return null;
    }

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
    var suggestionListCounter = itemRowsBody.querySelectorAll('.item-row').length;

    function textOrDash(value) {
        var clean = String(value || '').trim();
        return clean === '' ? '-' : clean;
    }

    function toNumber(value) {
        var num = Number(value);
        return Number.isFinite(num) ? num : 0;
    }

    function getDescriptionMeta(descriptionValue) {
        var key = getDescriptionKeyForInput(descriptionValue);
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

    function getOptionsByDescription(optionsMap, optionsMapLower, descriptionValue) {
        var key = getDescriptionKeyForInput(descriptionValue);
        if (key === '') {
            return [];
        }
        if (Array.isArray(optionsMap[key])) {
            return optionsMap[key];
        }
        var lowerKey = key.toLowerCase();
        return Array.isArray(optionsMapLower[lowerKey]) ? optionsMapLower[lowerKey] : [];
    }

    function setInputSuggestions(inputEl, options) {
        if (!inputEl) {
            return;
        }
        var datalistId = inputEl.getAttribute('list');
        if (!datalistId) {
            return;
        }
        var datalistEl = document.getElementById(datalistId);
        if (!datalistEl) {
            return;
        }
        var currentValue = String(inputEl.value || '').trim();
        var optionValues = Array.isArray(options) ? options : [];
        var html = '';
        optionValues.forEach(function (optionValue) {
            var value = String(optionValue || '').trim();
            if (value === '') {
                return;
            }
            html += '<option value="' + escapeHtml(value) + '"></option>';
        });
        datalistEl.innerHTML = html;
    }

    function getBatchContextByDescriptionPo(descriptionValue, poNoValue) {
        var descriptionKey = getDescriptionKeyForInput(descriptionValue);
        var descriptionLower = String(descriptionKey || '').trim().toLowerCase();
        var poLower = String(poNoValue || '').trim().toLowerCase();

        if (descriptionLower === '') {
            return {
                batchOptions: [],
                batchMeta: {},
                totalQuantity: 0
            };
        }

        var compositeKey = descriptionLower + '|' + poLower;

        if (poLower !== '' && Object.prototype.hasOwnProperty.call(batchNumbersByDescriptionPo, compositeKey)) {
            var batchOptions = Array.isArray(batchNumbersByDescriptionPo[compositeKey]) ? batchNumbersByDescriptionPo[compositeKey] : [];
            return {
                batchOptions: batchOptions,
                batchMeta: batchMetaByDescriptionPo[compositeKey] && typeof batchMetaByDescriptionPo[compositeKey] === 'object'
                    ? batchMetaByDescriptionPo[compositeKey]
                    : {},
                totalQuantity: Number(quantityByDescriptionAndPo[compositeKey] || 0)
            };
        }

        var fallbackMeta = getDescriptionMeta(descriptionKey);
        return {
            batchOptions: Array.isArray(fallbackMeta.batchOptions) ? fallbackMeta.batchOptions : [],
            batchMeta: fallbackMeta.batchMeta && typeof fallbackMeta.batchMeta === 'object' ? fallbackMeta.batchMeta : {},
            totalQuantity: Number(quantityByDescriptionAndPo[descriptionLower + '|'] || 0)
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

    function setBatchDependentFieldsState(row, hasSelectedBatch) {
        var quantityInput = row.querySelector('.item-quantity');
        var unitInput = row.querySelector('.item-unit');
        var unitCostInput = row.querySelector('.item-unit-cost');
        var amountInput = row.querySelector('.item-amount');
        var programInput = row.querySelector('.item-program');
        var expirationInput = row.querySelector('.item-expiration');

        if (quantityInput) {
            quantityInput.disabled = !hasSelectedBatch;
            if (!hasSelectedBatch) {
                quantityInput.value = '';
                quantityInput.dataset.autofilled = '0';
            }
        }
        if (unitInput) {
            unitInput.disabled = !hasSelectedBatch;
            if (!hasSelectedBatch) {
                unitInput.value = '';
            }
        }
        if (unitCostInput) {
            unitCostInput.disabled = !hasSelectedBatch;
            if (!hasSelectedBatch) {
                unitCostInput.value = '';
            }
        }
        if (amountInput) {
            amountInput.disabled = !hasSelectedBatch;
            if (!hasSelectedBatch) {
                amountInput.value = '';
            }
        }
        if (programInput) {
            programInput.disabled = !hasSelectedBatch;
            if (!hasSelectedBatch) {
                programInput.value = '';
            }
        }
        if (expirationInput) {
            expirationInput.disabled = !hasSelectedBatch;
            if (!hasSelectedBatch) {
                expirationInput.value = '';
            }
        }
    }

    function applyRowMeta(row) {
        var descriptionSelect = row.querySelector('.item-description');
        var descriptionValue = descriptionSelect.value.trim();
        var poNoInput = row.querySelector('.item-po-number');
        var poValue = poNoInput.value.trim();
        var batchInput = row.querySelector('.item-batch-number');
        var batchValue = batchInput.value.trim();
        var quantityInput = row.querySelector('.item-quantity');
        var unitInput = row.querySelector('.item-unit');
        var unitCostInput = row.querySelector('.item-unit-cost');
        var expirationInput = row.querySelector('.item-expiration');
        var programInput = row.querySelector('.item-program');
        var meta = getDescriptionMeta(descriptionValue);
        var selectedProduct = meta.selectedProduct;
        var batchOptions = Array.isArray(meta.batchOptions) ? meta.batchOptions : [];
        var unitOptions = getOptionsByDescription(unitOptionsByDescription, unitOptionsByDescriptionLower, descriptionValue);
        var programOptions = getOptionsByDescription(programOptionsByDescription, programOptionsByDescriptionLower, descriptionValue);
        var poOptions = getOptionsByDescription(poOptionsByDescription, poOptionsByDescriptionLower, descriptionValue);

        var normalizedProgramOptions = Array.isArray(programOptions) ? programOptions.slice() : [];
        var selectedProgram = String((selectedProduct && selectedProduct.program) || '').trim();
        if (selectedProgram !== '' && !normalizedProgramOptions.includes(selectedProgram)) {
            normalizedProgramOptions.push(selectedProgram);
        }

        setInputSuggestions(unitInput, unitOptions);
        setInputSuggestions(programInput, normalizedProgramOptions);
        setInputSuggestions(poNoInput, poOptions);

        if (poNoInput.value.trim() !== '' && poOptions.length > 0) {
            var currentPoLower = poNoInput.value.trim().toLowerCase();
            var hasMatchingPo = poOptions.some(function (optionValue) {
                return String(optionValue || '').trim().toLowerCase() === currentPoLower;
            });
            if (!hasMatchingPo) {
                poNoInput.value = '';
                poValue = '';
            }
        }

        var selectedPoMeta = getProductMetaByDescriptionPo(descriptionValue, poNoInput.value);
        var batchContext = getBatchContextByDescriptionPo(descriptionValue, poNoInput.value);
        var batchOptions = Array.isArray(batchContext.batchOptions) ? batchContext.batchOptions : [];
        setInputSuggestions(batchInput, batchOptions);

        if (batchInput.value.trim() !== '' && batchOptions.length > 0) {
            var currentBatchLower = batchInput.value.trim().toLowerCase();
            var hasMatchingBatch = batchOptions.some(function (optionValue) {
                return String(optionValue || '').trim().toLowerCase() === currentBatchLower;
            });
            if (!hasMatchingBatch) {
                batchInput.value = '';
                batchValue = '';
            }
        }

        var hasSelectedBatch = batchInput.value.trim() !== '';
        setBatchDependentFieldsState(row, hasSelectedBatch);

        if (!selectedProduct) {
            unitInput.value = '';
            unitCostInput.value = '';
            expirationInput.value = '';
            poNoInput.value = '';
            programInput.value = '';
            if (quantityInput) {
                quantityInput.value = '';
                quantityInput.dataset.autofilled = '0';
                quantityInput.dataset.lastPoValue = '';
            }
            calculateRowAmount(row);
            updateGrandTotal();
            return;
        }

        if (!hasSelectedBatch) {
            calculateRowAmount(row);
            updateGrandTotal();
            return;
        }

        if (selectedPoMeta && String(selectedPoMeta.unit || '').trim() !== '') {
            unitInput.value = String(selectedPoMeta.unit).trim();
        } else if (unitInput.value.trim() === '' && unitOptions.length > 0) {
            unitInput.value = unitOptions[0];
        } else if (unitInput.value.trim() === '') {
            unitInput.value = selectedProduct.unit || '';
        }

        if (selectedPoMeta && String(selectedPoMeta.program || '').trim() !== '') {
            programInput.value = String(selectedPoMeta.program).trim();
        } else if (programInput.value.trim() === '' && normalizedProgramOptions.length > 0) {
            programInput.value = normalizedProgramOptions[0];
        } else if (programInput.value.trim() === '') {
            programInput.value = selectedProgram;
        }

        var poFallbackCost = selectedPoMeta ? selectedPoMeta.unit_cost : selectedProduct.unit_cost;
        unitCostInput.value = getUnitCostByDescriptionPo(descriptionValue, poNoInput.value, poFallbackCost);

        if (quantityInput && quantityInput.getAttribute('data-last-po-value') === null) {
            quantityInput.dataset.lastPoValue = poNoInput.value;
            if (quantityInput.getAttribute('data-autofilled') === null) {
                quantityInput.dataset.autofilled = '0';
            }
        }

        var poChanged = quantityInput && quantityInput.dataset.lastPoValue !== poNoInput.value;
        if (quantityInput && poChanged) {
            quantityInput.value = '';
            quantityInput.dataset.autofilled = '1';
        }

        var selectedBatchMeta = getBatchMetaForSelection(descriptionValue, batchValue, poNoInput.value);
        var resolvedQty = selectedBatchMeta
            ? Number(selectedBatchMeta.stock_quantity || 0)
            : Number(batchContext.totalQuantity || 0);
        if (quantityInput && resolvedQty > 0) {
            var shouldAutofillQty = quantityInput.value.trim() === '' || quantityInput.dataset.autofilled === '1';
            if (shouldAutofillQty) {
                quantityInput.value = String(Math.max(0, Math.floor(resolvedQty)));
                quantityInput.dataset.autofilled = '1';
            }
        }
        if (quantityInput) {
            quantityInput.dataset.lastPoValue = poNoInput.value;
        }

        var batchExpiration = selectedBatchMeta
            ? String(selectedBatchMeta.expiration_date || '')
            : '';
        var poExpiration = selectedPoMeta ? String(selectedPoMeta.expiration_date || '') : '';
        expirationInput.value = batchExpiration !== ''
            ? batchExpiration
            : (poExpiration !== '' ? poExpiration : (selectedProduct.expiration_date || ''));

        calculateRowAmount(row);
        updateGrandTotal();
    }

    function getBatchMetaForSelection(descriptionValue, batchValue, poNoValue) {
        var description = getDescriptionKeyForInput(descriptionValue);
        var batchNumber = String(batchValue || '').trim();
        if (description === '' || batchNumber === '') {
            return null;
        }
        var batchContext = getBatchContextByDescriptionPo(description, poNoValue);
        var batchMeta = batchContext.batchMeta && typeof batchContext.batchMeta === 'object' ? batchContext.batchMeta : {};
        if (!Object.keys(batchMeta).length) {
            var meta = getDescriptionMeta(description);
            batchMeta = meta.batchMeta && typeof meta.batchMeta === 'object' ? meta.batchMeta : {};
        }
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
            var poInput = row.querySelector('.item-po-number');
            var poNo = poInput ? poInput.value.trim() : '';
            var quantity = quantityInput ? (parseInt(quantityInput.value || '0', 10) || 0) : 0;
            var selectedBatchMeta = getBatchMetaForSelection(description, batchNumber, poNo);
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
        var rowId = suggestionListCounter++;
        var descriptionValue = data.description || '';
        var batchListId = 'rowBatchOptionsList_dynamic_' + rowId;
        var unitListId = 'rowUnitOptionsList_dynamic_' + rowId;
        var programListId = 'rowProgramOptionsList_dynamic_' + rowId;
        var poListId = 'rowPoOptionsList_dynamic_' + rowId;
        var batchOptions = batchNumbersByDescription[descriptionValue] || [];
        var batchOptionsHtml = batchOptions
            .map(function (batchNo) { return '<option value="' + escapeHtml(batchNo) + '"></option>'; })
            .join('');
        tr.innerHTML =
            '<td><input type="text" name="description[]" class="form-control item-description" list="descriptionOptionsList" value="' + escapeHtml(data.description || '') + '" placeholder="Type or select item description" required></td>' +
            '<td><input type="text" name="po_number[]" class="form-control item-po-number" list="' + poListId + '" value="' + escapeHtml(data.po_no || '') + '" placeholder="Type or select PO number" required><datalist id="' + poListId + '" class="item-po-options"></datalist></td>' +
            '<td><input type="text" name="batch_number[]" class="form-control item-batch-number" list="' + batchListId + '" value="' + escapeHtml(data.batch_number || '') + '" placeholder="Type or select batch number" required><datalist id="' + batchListId + '" class="item-batch-options">' + batchOptionsHtml + '</datalist></td>' +
            '<td><input type="number" name="quantity[]" class="form-control item-quantity" min="1" step="1" autocomplete="off" value="' + escapeHtml(data.quantity || '') + '"><div class="form-text item-stock-hint"></div></td>' +
            '<td><input type="text" name="unit[]" class="form-control item-unit" list="' + unitListId + '" value="' + escapeHtml(data.unit || '') + '" placeholder="Type or select unit"><datalist id="' + unitListId + '" class="item-unit-options"></datalist></td>' +
            '<td><input type="text" class="form-control item-unit-cost" value="' + escapeHtml(data.unit_cost || '') + '" readonly></td>' +
            '<td><input type="text" class="form-control item-amount" value="" readonly></td>' +
            '<td><input type="text" name="program[]" class="form-control item-program" list="' + programListId + '" value="' + escapeHtml(data.program || '') + '" placeholder="Type or select program"><datalist id="' + programListId + '" class="item-program-options"></datalist></td>' +
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

        var rowsToRender = Array.isArray(items) ? items : [];
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

        // Ensure common short PTRs (up to 10 items) stay on one page.
        var configuredRowsPerPage = Number(previewLineRows);
        var rowsPerPrintedPage = Math.max(10, Number.isFinite(configuredRowsPerPage) ? configuredRowsPerPage : 0);
        var blanksNeeded = 0;
        if (rowsToRender.length <= 10) {
            // For short PTRs, keep a full single-page table grid.
            blanksNeeded = rowsPerPrintedPage - rowsToRender.length;
        }

        for (var i = 0; i < blanksNeeded; i++) {
            var blank = document.createElement('tr');
            blank.innerHTML = '<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>';
            previewItemsBody.appendChild(blank);
        }
    }

    function syncRowRequiredState(row) {
        var descriptionInput = row.querySelector('.item-description');
        var batchInput = row.querySelector('.item-batch-number');
        var quantityInput = row.querySelector('.item-quantity');
        var unitInput = row.querySelector('.item-unit');
        var programInput = row.querySelector('.item-program');
        var poInput = row.querySelector('.item-po-number');

        var description = descriptionInput.value.trim();
        var batchNumber = batchInput.value.trim();
        var quantity = quantityInput.value.trim();
        var program = programInput.value.trim();
        var isActiveRow = description !== '' || batchNumber !== '' || quantity !== '' || program !== '';
        var hasSelectedBatch = batchNumber !== '';

        descriptionInput.required = isActiveRow;
        batchInput.required = isActiveRow;
        quantityInput.required = isActiveRow && hasSelectedBatch;
        unitInput.required = isActiveRow && hasSelectedBatch;
        programInput.required = isActiveRow && hasSelectedBatch;
        poInput.required = isActiveRow;
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
        if (event.target.classList.contains('item-description') || event.target.classList.contains('item-po-number') || event.target.classList.contains('item-batch-number')) {
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
        if (event.target.classList.contains('item-po-number')) {
            var poRow = event.target.closest('.item-row');
            syncRowRequiredState(poRow);
            applyRowMeta(poRow);
            refreshCreateRowStockHints();
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
            event.target.dataset.autofilled = '0';
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
            '.preview-sheet{border:1px solid #222;padding:8px;max-width:100%;box-sizing:border-box;}' +
            '.preview-sheet table{width:100%;border-collapse:collapse;font-size:11px;page-break-inside:auto;break-inside:auto;}' +
            '.preview-sheet th,.preview-sheet td{border:1px solid #222;padding:4px 6px;vertical-align:top;}' +
            '.preview-sheet thead{display:table-header-group;}' +
            '.preview-sheet tfoot{display:table-footer-group;}' +
            '.preview-sheet tr{page-break-inside:avoid;break-inside:avoid;}' +
            '#previewItemsBody tr{page-break-inside:avoid;break-inside:avoid;}' +
            '.preview-header{display:grid;grid-template-columns:48px auto 48px;align-items:center;column-gap:12px;margin-bottom:8px;justify-content:center;}' +
            '.preview-title{font-weight:700;font-size:18px;text-align:center;margin:0;}' +
            '.preview-logo-wrap{width:48px;height:48px;display:flex;align-items:center;justify-content:center;}' +
            '.preview-logo-wrap img{width:46px;height:46px;object-fit:contain;}' +
            '.preview-label{font-weight:700;}' +
            '.preview-purpose-cell{padding-top:5px;padding-bottom:5px;}' +
            '.preview-purpose-cell .preview-purpose-value{display:inline-block;font-size:1.18rem;line-height:1.28;font-weight:600;}' +
            '.create-ptr-signatory-block{margin-top:8px;break-inside:avoid;page-break-inside:avoid;}' +
            '.create-ptr-signatory-block p{display:none !important;}' +
            '.create-ptr-signatory-block,.create-ptr-signatory-block table,.signatory-table,.signatory-table tr,.signatory-table td{break-inside:avoid;page-break-inside:avoid;}' +
            '.signatory-table{width:100%;border-collapse:collapse;table-layout:fixed;}' +
            '.signatory-table td{text-align:center;vertical-align:middle;height:84px;width:50%;}' +
            '.signatory-content{display:inline-block;text-align:center;line-height:1.4;}' +
            '.signatory-label{display:block;margin-bottom:8px;}' +
            '.received-box{width:100%;display:flex;flex-direction:column;align-items:center;justify-content:space-between;min-height:82px;padding:2px 0;}' +
            '.received-top{display:flex;align-items:center;justify-content:center;}' +
            '.received-bottom{border:0;padding:0;font-size:8px;line-height:1.1;white-space:nowrap;}' +
            '.text-end{text-align:right;}' +
            '.ptr-signatory-name{border:none !important;background:transparent !important;resize:none;box-shadow:none !important;outline:none !important;width:100%;min-height:2.5em;padding:0 2px;font:inherit;text-align:center;overflow:visible;}' +
            '.ptr-print-signatory-spacer{height:0;}' +
            '.ptr-single-page .preview-sheet{padding:4px;}' +
            '.ptr-single-page .preview-sheet th,.ptr-single-page .preview-sheet td{padding:3px 5px;}' +
            '.ptr-single-page .preview-purpose-cell{padding-top:3px;padding-bottom:3px;}' +
            '.ptr-single-page .preview-purpose-cell .preview-purpose-value{line-height:1.18;}' +
            '.ptr-single-page .create-ptr-signatory-block{margin-top:2px;break-inside:auto !important;page-break-inside:auto !important;}' +
            '.ptr-single-page .create-ptr-signatory-block,.ptr-single-page .create-ptr-signatory-block table,.ptr-single-page .signatory-table,.ptr-single-page .signatory-table tr,.ptr-single-page .signatory-table td{break-inside:auto !important;page-break-inside:auto !important;}' +
            '.ptr-single-page .signatory-table td{height:48px;}' +
            '.ptr-single-page .signatory-content{line-height:1.2;}' +
            '.ptr-single-page .signatory-label{margin-bottom:4px;}' +
            '.ptr-single-page .received-box{min-height:56px;padding:0;}' +
            '.ptr-single-page .received-bottom{line-height:1;}' +
            '.ptr-single-page .preview-approved-date{margin-top:3px;}' +
            '.ptr-single-page .ptr-signatory-name{min-height:1.35em;line-height:1.1;padding:0 1px;}' +
            '@media print{.ptr-signatory-name{border:none !important;background:transparent !important;}}' +
            '</style></head><body>' + previewHtml +
            '<script>' +
            '(function(){' +
            'function getPxPerInch(){var probe=document.createElement("div");probe.style.height="1in";probe.style.width="1in";probe.style.position="absolute";probe.style.left="-9999px";document.body.appendChild(probe);var ppi=probe.getBoundingClientRect().height||96;probe.remove();return ppi;}' +
            'function getActualItemCount(){var rows=document.querySelectorAll("#previewItemsBody tr");var count=0;rows.forEach(function(row){var cells=row.querySelectorAll("td");if(!cells.length){return;}var isBlank=true;cells.forEach(function(cell){var t=(cell.textContent||"").replace(/\\u00a0/g," ").trim();if(t!==""&&t!=="-"){isBlank=false;}});if(!isBlank){count++;}});return count;}' +
            'function applySinglePageMode(){var itemCount=getActualItemCount();if(itemCount<=10){document.body.classList.add("ptr-single-page");}else{document.body.classList.remove("ptr-single-page");}}' +
            'function alignSignatoryToPageBottom(){var sheet=document.getElementById("previewPrintArea");if(!sheet){return;}var signBlock=sheet.querySelector(".create-ptr-signatory-block");if(!signBlock){return;}var oldSpacer=sheet.querySelector(".ptr-print-signatory-spacer");if(oldSpacer){oldSpacer.remove();}var bodyRows=sheet.querySelectorAll("#previewItemsBody tr").length;var minimumRowsSinglePage=Math.max(10, Number(' + String(Math.max(10, Number(previewLineRows) || 0)) + ') || 10);if(bodyRows<=minimumRowsSinglePage){return;}' +
            'var ppi=getPxPerInch();var pageHeightPx=ppi*(8.2677165354-(16/25.4));var sheetRect=sheet.getBoundingClientRect();var signRect=signBlock.getBoundingClientRect();var signTop=signRect.top-sheetRect.top;var signHeight=signRect.height;var pageStart=Math.floor(signTop/pageHeightPx)*pageHeightPx;var desiredTop=pageStart+pageHeightPx-signHeight-2;var spacerNeeded=Math.floor(desiredTop-signTop);if(spacerNeeded>6){var spacer=document.createElement("div");spacer.className="ptr-print-signatory-spacer";spacer.style.height=String(spacerNeeded)+"px";signBlock.parentNode.insertBefore(spacer,signBlock);}}' +
            'window.addEventListener("load",function(){applySinglePageMode();alignSignatoryToPageBottom();setTimeout(function(){applySinglePageMode();alignSignatoryToPageBottom();},50);setTimeout(function(){window.focus();window.print();},120);});' +
            '})();' +
            '<\/script></body></html>'
        );
        printWindow.document.close();
    });

    itemRowsBody.querySelectorAll('.item-row').forEach(function (row) {
        applyRowMeta(row);
    });
    updateGrandTotal();
    refreshCreateRowStockHints();
})();
