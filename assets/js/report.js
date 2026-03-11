(function () {
    'use strict';

    var config = window.reportConfig || {};
    var reportBatchNumbersByDescription = config.batchNumbersByDescription || {};
    var reportBatchMetaByDescription = config.batchMetaByDescription || {};
    var reportUnitCostByDescription = config.unitCostByDescription || {};
    var reportPoNoByDescription = config.poNoByDescription || {};
    var reportHasProductBatches = !!config.hasProductBatches;
    var reportShowEditModal = !!config.showEditModal;
    var reportShowAddModal = !!config.showAddModal;

    var reportBatchNumbersByDescriptionLower = Object.keys(reportBatchNumbersByDescription).reduce(function (acc, key) {
        acc[String(key).trim().toLowerCase()] = reportBatchNumbersByDescription[key];
        return acc;
    }, {});
    var reportBatchMetaByDescriptionLower = Object.keys(reportBatchMetaByDescription).reduce(function (acc, key) {
        acc[String(key).trim().toLowerCase()] = reportBatchMetaByDescription[key];
        return acc;
    }, {});
    var reportUnitCostByDescriptionLower = Object.keys(reportUnitCostByDescription).reduce(function (acc, key) {
        acc[String(key).trim().toLowerCase()] = reportUnitCostByDescription[key];
        return acc;
    }, {});
    var reportPoNoByDescriptionLower = Object.keys(reportPoNoByDescription).reduce(function (acc, key) {
        acc[String(key).trim().toLowerCase()] = reportPoNoByDescription[key];
        return acc;
    }, {});

    function reportEscapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function updateReportBatchOptions(descriptionInputId, datalistId) {
        var descriptionInput = document.getElementById(descriptionInputId);
        var datalist = document.getElementById(datalistId);
        if (!descriptionInput || !datalist) {
            return;
        }

        var description = String(descriptionInput.value || '').trim();
        if (description === '') {
            datalist.innerHTML = '';
            return;
        }

        var exactOptions = reportBatchNumbersByDescription[description];
        var lowerOptions = reportBatchNumbersByDescriptionLower[description.toLowerCase()];
        var options = Array.isArray(exactOptions) ? exactOptions : (Array.isArray(lowerOptions) ? lowerOptions : []);
        datalist.innerHTML = options
            .map(function (batchNo) { return '<option value="' + reportEscapeHtml(batchNo) + '"></option>'; })
            .join('');
    }

    function updateReportUnitCost(descriptionInputId, unitCostInputId) {
        var descriptionInput = document.getElementById(descriptionInputId);
        var unitCostInput = document.getElementById(unitCostInputId);
        if (!descriptionInput || !unitCostInput) {
            return;
        }

        var description = String(descriptionInput.value || '').trim();
        if (description === '') {
            unitCostInput.value = '';
            return;
        }

        var exactUnitCost = reportUnitCostByDescription[description];
        var lowerUnitCost = reportUnitCostByDescriptionLower[description.toLowerCase()];
        var selectedUnitCost = exactUnitCost !== undefined ? exactUnitCost : lowerUnitCost;
        unitCostInput.value = selectedUnitCost !== undefined ? String(selectedUnitCost) : '';
    }

    function getReportBatchMeta(descriptionValue, batchNumberValue) {
        var description = String(descriptionValue || '').trim();
        var batchNumber = String(batchNumberValue || '').trim();
        if (description === '' || batchNumber === '') {
            return null;
        }

        var exactRows = reportBatchMetaByDescription[description];
        if (exactRows && typeof exactRows === 'object' && exactRows[batchNumber]) {
            return exactRows[batchNumber];
        }

        var lowerRows = reportBatchMetaByDescriptionLower[description.toLowerCase()];
        if (!lowerRows || typeof lowerRows !== 'object') {
            return null;
        }

        var batchLower = batchNumber.toLowerCase();
        return Object.keys(lowerRows).reduce(function (found, key) {
            if (found) {
                return found;
            }
            return String(key).toLowerCase() === batchLower ? lowerRows[key] : null;
        }, null);
    }

    function updateReportPoNo(descriptionInputId, poNoInputId) {
        var descriptionInput = document.getElementById(descriptionInputId);
        var poNoInput = document.getElementById(poNoInputId);
        if (!descriptionInput || !poNoInput) {
            return;
        }
        if (poNoInput.dataset.autoFilled === '0') {
            return;
        }
        var description = String(descriptionInput.value || '').trim();
        if (description === '') {
            poNoInput.value = '';
            poNoInput.dataset.autoFilled = '0';
            return;
        }
        var exactPoNo = reportPoNoByDescription[description];
        var lowerPoNo = reportPoNoByDescriptionLower[description.toLowerCase()];
        var selectedPoNo = exactPoNo !== undefined ? exactPoNo : lowerPoNo;
        poNoInput.value = selectedPoNo !== undefined ? String(selectedPoNo) : '';
        poNoInput.dataset.autoFilled = '1';
    }

    function bindReportDescriptionDependencies(descriptionInputId, datalistId, unitCostInputId, poNoInputId) {
        var descriptionInput = document.getElementById(descriptionInputId);
        if (!descriptionInput) {
            return;
        }

        var refresh = function () {
            updateReportBatchOptions(descriptionInputId, datalistId);
            if (unitCostInputId) {
                updateReportUnitCost(descriptionInputId, unitCostInputId);
            }
            if (poNoInputId) {
                updateReportPoNo(descriptionInputId, poNoInputId);
            }
        };

        descriptionInput.addEventListener('input', refresh);
        descriptionInput.addEventListener('change', refresh);

        var poNoInput = poNoInputId ? document.getElementById(poNoInputId) : null;
        if (poNoInput) {
            poNoInput.addEventListener('input', function () {
                poNoInput.dataset.autoFilled = '0';
            });
        }

        refresh();
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindReportDescriptionDependencies('add_description', 'reportAddBatchOptionsList', 'add_unit_cost', 'add_po_number');

        var addDescriptionInput = document.getElementById('add_description');
        var addBatchInput = document.getElementById('add_batch_number');
        var addQtyInput = document.getElementById('add_quantity');
        var addStockHint = document.getElementById('add_stock_hint');

        var refreshAddStockHint = function () {
            if (!addQtyInput || !addStockHint) {
                return;
            }

            addQtyInput.setCustomValidity('');
            if (!reportHasProductBatches) {
                addQtyInput.removeAttribute('max');
                addStockHint.textContent = '';
                return;
            }

            var descValue = addDescriptionInput ? String(addDescriptionInput.value || '').trim() : '';
            var batchValue = addBatchInput ? String(addBatchInput.value || '').trim() : '';
            var selectedMeta = getReportBatchMeta(descValue, batchValue);

            if (!selectedMeta) {
                addQtyInput.removeAttribute('max');
                addStockHint.textContent = batchValue === '' ? '' : 'Selected batch is not valid for this item.';
                return;
            }

            var availableStock = parseInt(String(selectedMeta.stock_quantity || '0'), 10) || 0;
            var quantityValue = parseInt(String(addQtyInput.value || '0'), 10) || 0;
            addQtyInput.setAttribute('max', String(availableStock));
            addStockHint.textContent = 'Remaining stock available: ' + availableStock;
            if (quantityValue > availableStock) {
                addQtyInput.setCustomValidity('Quantity exceeds remaining stock.');
            }
        };

        if (addDescriptionInput) {
            addDescriptionInput.addEventListener('input', refreshAddStockHint);
            addDescriptionInput.addEventListener('change', refreshAddStockHint);
        }
        if (addBatchInput) {
            addBatchInput.addEventListener('input', refreshAddStockHint);
            addBatchInput.addEventListener('change', refreshAddStockHint);
        }
        if (addQtyInput) {
            addQtyInput.addEventListener('input', function () {
                addQtyInput.value = addQtyInput.value.replace(/\D+/g, '');
                refreshAddStockHint();
            });
        }
        refreshAddStockHint();

        var editRows = Array.from(document.querySelectorAll('.edit-group-row'));
        var refreshEditRowStocks = function () {
            if (!reportHasProductBatches) {
                editRows.forEach(function (row) {
                    var qtyInput = row.querySelector('.edit-group-quantity');
                    var hintEl = row.querySelector('.edit-group-stock-hint');
                    if (!qtyInput || !hintEl) {
                        return;
                    }
                    qtyInput.removeAttribute('max');
                    qtyInput.setCustomValidity('');
                    hintEl.textContent = '';
                });
                return;
            }

            var totalOldByBatchId = {};
            var totalNewByBatchId = {};
            var rowStates = editRows.map(function (row) {
                var descriptionInput = row.querySelector('.edit-group-description');
                var batchInput = row.querySelector('.edit-group-batch');
                var qtyInput = row.querySelector('.edit-group-quantity');
                var originalDescription = row.getAttribute('data-original-description') || '';
                var originalBatchNumber = row.getAttribute('data-original-batch-number') || '';
                var originalQty = parseInt(row.getAttribute('data-original-quantity') || '0', 10) || 0;
                var currentDescription = descriptionInput ? String(descriptionInput.value || '').trim() : '';
                var currentBatch = batchInput ? String(batchInput.value || '').trim() : '';
                var currentQty = qtyInput ? (parseInt(String(qtyInput.value || '0'), 10) || 0) : 0;
                var oldMeta = getReportBatchMeta(originalDescription, originalBatchNumber);
                var newMeta = getReportBatchMeta(currentDescription, currentBatch);
                var oldBatchId = oldMeta ? (parseInt(String(oldMeta.batch_id || '0'), 10) || 0) : 0;
                var newBatchId = newMeta ? (parseInt(String(newMeta.batch_id || '0'), 10) || 0) : 0;
                var newStock = newMeta ? (parseInt(String(newMeta.stock_quantity || '0'), 10) || 0) : 0;

                if (oldBatchId > 0) {
                    totalOldByBatchId[oldBatchId] = (totalOldByBatchId[oldBatchId] || 0) + originalQty;
                }
                if (newBatchId > 0) {
                    totalNewByBatchId[newBatchId] = (totalNewByBatchId[newBatchId] || 0) + currentQty;
                }

                return {
                    qtyInput: qtyInput,
                    hintEl: row.querySelector('.edit-group-stock-hint'),
                    currentBatch: currentBatch,
                    currentQty: currentQty,
                    newBatchId: newBatchId,
                    newStock: newStock
                };
            });

            rowStates.forEach(function (state) {
                if (!state.qtyInput || !state.hintEl) {
                    return;
                }
                state.qtyInput.setCustomValidity('');
                if (state.newBatchId <= 0) {
                    state.qtyInput.removeAttribute('max');
                    state.hintEl.textContent = state.currentBatch === '' ? '' : 'Selected batch is not valid for this item.';
                    return;
                }

                var oldTotal = totalOldByBatchId[state.newBatchId] || 0;
                var otherPlanned = (totalNewByBatchId[state.newBatchId] || 0) - state.currentQty;
                var maxAllowed = Math.max(0, state.newStock + oldTotal - otherPlanned);
                state.qtyInput.setAttribute('max', String(maxAllowed));
                state.hintEl.textContent = 'Remaining stock available for this row: ' + maxAllowed;
                if (state.currentQty > maxAllowed) {
                    state.qtyInput.setCustomValidity('Quantity exceeds remaining stock.');
                }
            });
        };

        editRows.forEach(function (row) {
            var descriptionInput = row.querySelector('.edit-group-description');
            var batchInput = row.querySelector('.edit-group-batch');
            var qtyInput = row.querySelector('.edit-group-quantity');
            var poNoInput = row.querySelector('.edit-group-po-number');
            if (!descriptionInput || !batchInput) {
                return;
            }

            var batchListId = batchInput.getAttribute('list');
            var batchDatalist = batchListId ? document.getElementById(batchListId) : null;
            if (!batchDatalist) {
                return;
            }

            var refreshBatchList = function () {
                var description = String(descriptionInput.value || '').trim();
                var exactOptions = reportBatchNumbersByDescription[description];
                var lowerOptions = reportBatchNumbersByDescriptionLower[description.toLowerCase()];
                var options = Array.isArray(exactOptions) ? exactOptions : (Array.isArray(lowerOptions) ? lowerOptions : []);
                batchDatalist.innerHTML = options
                    .map(function (batchNo) { return '<option value="' + reportEscapeHtml(batchNo) + '"></option>'; })
                    .join('');
                if (poNoInput && (poNoInput.value.trim() === '' || poNoInput.dataset.autoFilled === '1')) {
                    var exactPoNo = reportPoNoByDescription[description];
                    var lowerPoNo = reportPoNoByDescriptionLower[description.toLowerCase()];
                    var selectedPoNo = exactPoNo !== undefined ? exactPoNo : lowerPoNo;
                    poNoInput.value = selectedPoNo !== undefined ? String(selectedPoNo) : '';
                    poNoInput.dataset.autoFilled = '1';
                }
                refreshEditRowStocks();
            };

            descriptionInput.addEventListener('input', refreshBatchList);
            descriptionInput.addEventListener('change', refreshBatchList);
            batchInput.addEventListener('input', refreshEditRowStocks);
            batchInput.addEventListener('change', refreshEditRowStocks);
            if (qtyInput) {
                qtyInput.addEventListener('input', function () {
                    qtyInput.value = qtyInput.value.replace(/\D+/g, '');
                    refreshEditRowStocks();
                });
            }
            if (poNoInput) {
                poNoInput.addEventListener('input', function () {
                    poNoInput.dataset.autoFilled = '0';
                });
            }
            refreshBatchList();
        });
        refreshEditRowStocks();

        document.querySelectorAll('.report-print-btn').forEach(function (btn) {
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

                var printableHtml = target.outerHTML;
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
                    '</style></head><body>' + printableHtml + '</body></html>'
                );
                printWindow.document.close();
                printWindow.focus();
                printWindow.print();
            });
        });

        if (reportShowEditModal) {
            var modalElement = document.getElementById('editTransactionModal');
            if (modalElement && window.bootstrap && window.bootstrap.Modal) {
                document.querySelectorAll('.edit-group-quantity').forEach(function (qtyInput) {
                    qtyInput.addEventListener('input', function () {
                        qtyInput.value = qtyInput.value.replace(/\D+/g, '');
                    });
                });
                new window.bootstrap.Modal(modalElement).show();
            }
        }

        if (reportShowAddModal) {
            var addModalElement = document.getElementById('addTransactionModal');
            if (addModalElement && window.bootstrap && window.bootstrap.Modal) {
                var addModalQtyInput = document.getElementById('add_quantity');
                if (addModalQtyInput) {
                    addModalQtyInput.addEventListener('input', function () {
                        addModalQtyInput.value = addModalQtyInput.value.replace(/\D+/g, '');
                    });
                }
                new window.bootstrap.Modal(addModalElement).show();
            }
        }
    });
})();
