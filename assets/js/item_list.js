(function () {
    'use strict';

    var config = window.itemListConfig || {};
    if (!config.showFormModal) {
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var modalElement = document.getElementById('itemFormModal');
        if (!modalElement || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }
        new window.bootstrap.Modal(modalElement).show();
    });
})();
