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
            placeOfDelivery: document.getElementById('detail_place_of_delivery'),
            dateOfDelivery: document.getElementById('detail_date_of_delivery'),
            deliveryTerm: document.getElementById('detail_delivery_term'),
            paymentTerm: document.getElementById('detail_payment_term')
        };

        function textOrDash(value) {
            var clean = String(value || '').trim();
            return clean === '' ? '-' : clean;
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
            detailTargets.placeOfDelivery.textContent = textOrDash(detailsBtn.dataset.placeOfDelivery);
            detailTargets.dateOfDelivery.textContent = textOrDash(detailsBtn.dataset.dateOfDelivery);
            detailTargets.deliveryTerm.textContent = textOrDash(detailsBtn.dataset.deliveryTerm);
            detailTargets.paymentTerm.textContent = textOrDash(detailsBtn.dataset.paymentTerm);

            itemDetailsModal.show();
        });
    });
})();
