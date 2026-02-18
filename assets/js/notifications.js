(function () {
    'use strict';

    var expirySearchInput = document.getElementById('expirySearchInput');
    if (!expirySearchInput) {
        return;
    }

    expirySearchInput.addEventListener('input', function () {
        var query = expirySearchInput.value.trim().toLowerCase();
        document.querySelectorAll('.dashboard-notif-item').forEach(function (item) {
            var text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? '' : 'none';
        });
    });
})();
