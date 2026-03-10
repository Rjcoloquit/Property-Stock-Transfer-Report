(function () {
    'use strict';

    var config = window.itemListConfig || {};

    document.addEventListener('DOMContentLoaded', function () {
        if (!window.bootstrap || !window.bootstrap.Modal) {
            return;
        }

        var itemFormModalElement = document.getElementById('itemFormModal');
        if (config.showFormModal && itemFormModalElement) {
            new window.bootstrap.Modal(itemFormModalElement).show();
        }

        var itemDetailsModalElement = document.getElementById('itemDetailsModal');
        var itemDetailsModal = itemDetailsModalElement ? new window.bootstrap.Modal(itemDetailsModalElement) : null;
        var descriptionInput = document.getElementById('product_description');
        var descriptionList = document.getElementById('productDescriptionOptionsList');
        var descriptionOptions = Array.isArray(config.productDescriptionOptions) ? config.productDescriptionOptions : [];
        var detailTargets = {
            itemNo: document.getElementById('detail_item_no'),
            productDescription: document.getElementById('detail_product_description'),
            batchNumber: document.getElementById('detail_batch_number'),
            uom: document.getElementById('detail_uom'),
            stock: document.getElementById('detail_stock'),
            costPerUnit: document.getElementById('detail_cost_per_unit'),
            expiryDate: document.getElementById('detail_expiry_date'),
            program: document.getElementById('detail_program'),
            poNo: document.getElementById('detail_po_no'),
            supplier: document.getElementById('detail_supplier'),
            placeOfDelivery: document.getElementById('detail_place_of_delivery'),
            dateOfDelivery: document.getElementById('detail_date_of_delivery'),
            deliveryTerm: document.getElementById('detail_delivery_term'),
            paymentTerm: document.getElementById('detail_payment_term')
        };

        function textOrDash(value) {
            var clean = String(value || '').trim();
            return clean === '' ? '-' : clean;
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function refreshDescriptionSuggestions() {
            if (!descriptionInput || !descriptionList) {
                return;
            }
            var typedValue = String(descriptionInput.value || '').trim();
            var typedLower = typedValue.toLowerCase();
            var seen = {};
            var optionsToRender = [];

            if (typedValue !== '') {
                optionsToRender.push(typedValue);
                seen[typedLower] = true;
            }

            descriptionOptions.forEach(function (option) {
                var value = String(option || '').trim();
                if (value === '') {
                    return;
                }
                var lower = value.toLowerCase();
                if (seen[lower]) {
                    return;
                }
                if (typedLower === '' || lower.indexOf(typedLower) !== -1) {
                    optionsToRender.push(value);
                    seen[lower] = true;
                }
            });

            descriptionList.innerHTML = optionsToRender
                .slice(0, 50)
                .map(function (value) {
                    return '<option value="' + escapeHtml(value) + '"></option>';
                })
                .join('');
        }

        if (descriptionInput && descriptionList) {
            refreshDescriptionSuggestions();
            descriptionInput.addEventListener('input', refreshDescriptionSuggestions);
            descriptionInput.addEventListener('focus', refreshDescriptionSuggestions);
        }

        // Handle product recommendations based on selected description
        var productAttributesMap = config.productAttributesMap || {};
        var uomInput = document.getElementById('uom');
        var programInput = document.getElementById('program');
        var poNoInput = document.getElementById('po_no');
        var supplierInput = document.getElementById('supplier');
        var uomList = document.getElementById('uomRecommendationsList');
        var programList = document.getElementById('programRecommendationsList');
        var poNoList = document.getElementById('poNoRecommendationsList');
        var supplierList = document.getElementById('supplierRecommendationsList');

        function updateRecommendations() {
            var selectedDescription = String(descriptionInput.value || '').trim();
            var attrs = productAttributesMap[selectedDescription] || {
                uom_list: [],
                program_list: [],
                supplier_list: [],
                po_no_list: []
            };

            // Update UOM recommendations
            if (uomList) {
                uomList.innerHTML = attrs.uom_list
                    .map(function (value) {
                        return '<option value="' + escapeHtml(value) + '"></option>';
                    })
                    .join('');
            }

            // Update Program recommendations
            if (programList) {
                programList.innerHTML = attrs.program_list
                    .map(function (value) {
                        return '<option value="' + escapeHtml(value) + '"></option>';
                    })
                    .join('');
            }

            // Update PO number recommendations
            if (poNoList) {
                poNoList.innerHTML = attrs.po_no_list
                    .map(function (value) {
                        return '<option value="' + escapeHtml(value) + '"></option>';
                    })
                    .join('');
            }

            // Update Supplier recommendations
            if (supplierList) {
                supplierList.innerHTML = attrs.supplier_list
                    .map(function (value) {
                        return '<option value="' + escapeHtml(value) + '"></option>';
                    })
                    .join('');
            }
        }

        if (descriptionInput) {
            // Update recommendations when description changes
            descriptionInput.addEventListener('change', updateRecommendations);
            // Also update on input for real-time suggestions
            descriptionInput.addEventListener('input', updateRecommendations);
            // Initialize recommendations on page load if description is already filled
            updateRecommendations();
        }

        document.addEventListener('click', function (event) {
            var detailsBtn = event.target.closest('.item-details-btn');
            if (!detailsBtn || !itemDetailsModal) {
                return;
            }

            detailTargets.itemNo.textContent = textOrDash(detailsBtn.dataset.itemNo);
            detailTargets.productDescription.textContent = textOrDash(detailsBtn.dataset.productDescription);
            detailTargets.batchNumber.textContent = textOrDash(detailsBtn.dataset.batchNumber);
            detailTargets.uom.textContent = textOrDash(detailsBtn.dataset.uom);
            detailTargets.stock.textContent = textOrDash(detailsBtn.dataset.stock);
            detailTargets.costPerUnit.textContent = textOrDash(detailsBtn.dataset.costPerUnit);
            detailTargets.expiryDate.textContent = textOrDash(detailsBtn.dataset.expiryDate);
            detailTargets.program.textContent = textOrDash(detailsBtn.dataset.program);
            detailTargets.poNo.textContent = textOrDash(detailsBtn.dataset.poNo);
            detailTargets.supplier.textContent = textOrDash(detailsBtn.dataset.supplier);
            detailTargets.placeOfDelivery.textContent = textOrDash(detailsBtn.dataset.placeOfDelivery);
            detailTargets.dateOfDelivery.textContent = textOrDash(detailsBtn.dataset.dateOfDelivery);
            detailTargets.deliveryTerm.textContent = textOrDash(detailsBtn.dataset.deliveryTerm);
            detailTargets.paymentTerm.textContent = textOrDash(detailsBtn.dataset.paymentTerm);

            itemDetailsModal.show();
        });
    });
})();
