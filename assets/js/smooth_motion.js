(function () {
    "use strict";

    var root = document.documentElement;
    var prefersReducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    // Keep native scroll physics (wheel/trackpad/touch momentum) untouched.
    root.style.scrollBehavior = "auto";

    if (prefersReducedMotion) {
        return;
    }

    document.addEventListener("click", function (event) {
        var anchor = event.target.closest('a[href^="#"]');
        if (!anchor) {
            return;
        }

        var hash = anchor.getAttribute("href");
        if (!hash || hash.length <= 1) {
            return;
        }

        var target = document.getElementById(hash.slice(1));
        if (!target) {
            return;
        }

        event.preventDefault();
        target.scrollIntoView({ behavior: "smooth", block: "start" });

        if (window.history && window.history.pushState) {
            window.history.pushState(null, "", hash);
        }
    });
})();
