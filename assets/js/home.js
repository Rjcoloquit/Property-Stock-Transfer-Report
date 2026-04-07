(function () {
    'use strict';

    var config = window.homeConfig || {};
    var trendData = config.trendData || {};
    var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var chartLabelBySeries = {
        transactions: 'Transactions',
        quantity: 'Quantity',
        amount: 'Amount'
    };
    var legendBySeries = {
        transactions: 'Daily count of saved inventory rows (last 7 days).',
        quantity: 'Total quantity released per day (last 7 days).',
        amount: 'Estimated released amount per day (last 7 days).'
    };

    function easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }

    function animateCounters() {
        if (prefersReducedMotion) {
            document.querySelectorAll('[data-animate]').forEach(function (el) {
                var targetValue = Number(el.getAttribute('data-animate'));
                if (Number.isFinite(targetValue)) {
                    el.textContent = Math.round(targetValue).toLocaleString();
                }
            });
            return;
        }

        document.querySelectorAll('[data-animate]').forEach(function (el) {
            var target = Number(el.getAttribute('data-animate'));
            if (!Number.isFinite(target)) {
                return;
            }
            var duration = 760;
            var start = performance.now();

            function frame(now) {
                var progress = Math.min((now - start) / duration, 1);
                var current = Math.round(target * easeOutCubic(progress));
                el.textContent = current.toLocaleString();
                if (progress < 1) {
                    requestAnimationFrame(frame);
                }
            }
            requestAnimationFrame(frame);
        });
    }

    var trendChart = null;
    var currentSeriesKey = 'transactions';

    function setLegend(seriesKey) {
        var legendEl = document.getElementById('trendLegend');
        if (!legendEl) {
            return;
        }
        legendEl.textContent = legendBySeries[seriesKey] || '';
    }

    function setActiveToggle(seriesKey) {
        document.querySelectorAll('.trend-toggle').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-series') === seriesKey);
        });
    }

    function createTrendChart(chartCanvas, labels, values, seriesKey) {
        var normalizedLabels = Array.isArray(labels) ? labels : [];
        var normalizedValues = Array.isArray(values)
            ? values.map(function (value) {
                var numericValue = Number(value);
                return Number.isFinite(numericValue) ? numericValue : 0;
            })
            : [];

        trendChart = new Chart(chartCanvas, {
            type: 'bar',
            data: {
                labels: normalizedLabels,
                datasets: [{
                    label: chartLabelBySeries[seriesKey] || 'Trend',
                    data: normalizedValues,
                    backgroundColor: 'rgba(40, 128, 75, 0.80)',
                    borderColor: 'rgba(32, 102, 59, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
                    maxBarThickness: 36
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                normalized: true,
                devicePixelRatio: Math.min(window.devicePixelRatio || 1, 2),
                resizeDelay: 90,
                animation: prefersReducedMotion ? false : {
                    duration: 280,
                    easing: 'easeOutCubic'
                },
                transitions: {
                    active: {
                        animation: {
                            duration: prefersReducedMotion ? 0 : 220
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        animation: {
                            duration: prefersReducedMotion ? 0 : 120
                        },
                        callbacks: {
                            label: function (ctx) {
                                var v = Number(ctx.raw || 0);
                                return (chartLabelBySeries[currentSeriesKey] || 'Value') + ': ' + v.toLocaleString(undefined, { maximumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(40, 128, 75, 0.12)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    function updateTrendChart(seriesKey, values) {
        if (!trendChart) {
            return;
        }

        var normalizedValues = Array.isArray(values)
            ? values.map(function (value) {
                var numericValue = Number(value);
                return Number.isFinite(numericValue) ? numericValue : 0;
            })
            : [];

        var dataset = trendChart.data.datasets[0];
        dataset.label = chartLabelBySeries[seriesKey] || 'Trend';
        dataset.data = normalizedValues;
        trendChart.update(prefersReducedMotion ? 'none' : 'active');
    }

    function renderTrend(seriesKey) {
        var chartCanvas = document.getElementById('trendChartCanvas');
        var labels = trendData.labels || [];
        var values = trendData[seriesKey] || [];

        if (!chartCanvas || typeof Chart === 'undefined') {
            return;
        }

        if (currentSeriesKey === seriesKey && trendChart) {
            return;
        }

        if (!trendChart) {
            createTrendChart(chartCanvas, labels, values, seriesKey);
        } else {
            updateTrendChart(seriesKey, values);
        }

        currentSeriesKey = seriesKey;
        setLegend(seriesKey);
        setActiveToggle(seriesKey);
    }

    document.querySelectorAll('.trend-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            renderTrend(btn.getAttribute('data-series'));
        });
    });

    var txFilterInput = document.getElementById('txFilterInput');
    var recentTxBody = document.getElementById('recentTxBody');
    if (txFilterInput && recentTxBody) {
        var filterFrame = null;
        function applyTxFilter() {
            var query = txFilterInput.value.trim().toLowerCase();
            recentTxBody.querySelectorAll('tr').forEach(function (row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        txFilterInput.addEventListener('input', function () {
            if (filterFrame !== null) {
                cancelAnimationFrame(filterFrame);
            }
            filterFrame = requestAnimationFrame(function () {
                applyTxFilter();
                filterFrame = null;
            });
        });
    }

    var itemSearchInput = document.getElementById('item_q');
    var itemSuggestList = document.getElementById('itemSuggestList');
    var itemSearchForm = document.querySelector('.dashboard-item-search-form');
    if (itemSearchInput) {
        var suggestTimer = null;
        var suggestFetchSeq = 0;

        function hideItemSuggest() {
            if (itemSuggestList) {
                itemSuggestList.classList.add('d-none');
                itemSuggestList.innerHTML = '';
            }
            itemSearchInput.setAttribute('aria-expanded', 'false');
        }

        function showItemSuggest(items) {
            if (!itemSuggestList || !Array.isArray(items) || items.length === 0) {
                hideItemSuggest();
                return;
            }
            itemSuggestList.innerHTML = '';
            items.forEach(function (text, i) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'dashboard-item-suggest-item';
                btn.setAttribute('role', 'option');
                btn.id = 'item-suggest-opt-' + i;
                btn.textContent = text;
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                });
                btn.addEventListener('click', function () {
                    itemSearchInput.value = text;
                    hideItemSuggest();
                    if (itemSearchForm) {
                        itemSearchForm.submit();
                    }
                });
                itemSuggestList.appendChild(btn);
            });
            itemSuggestList.classList.remove('d-none');
            itemSearchInput.setAttribute('aria-expanded', 'true');
        }

        function loadItemSuggest() {
            var v = itemSearchInput.value.trim();
            if (v === '') {
                hideItemSuggest();
                return;
            }
            var seq = ++suggestFetchSeq;
            fetch('item_search_suggest.php?q=' + encodeURIComponent(v), { credentials: 'same-origin' })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (seq !== suggestFetchSeq) {
                        return;
                    }
                    showItemSuggest(Array.isArray(data) ? data : []);
                })
                .catch(function () {
                    if (seq === suggestFetchSeq) {
                        hideItemSuggest();
                    }
                });
        }

        itemSearchInput.addEventListener('input', function () {
            if (itemSearchInput.value.trim() === '') {
                hideItemSuggest();
                try {
                    var url = new URL(window.location.href);
                    if (!url.searchParams.has('item_q')) {
                        return;
                    }
                    url.searchParams.delete('item_q');
                    var qs = url.searchParams.toString();
                    var next = url.pathname + (qs ? '?' + qs : '') + url.hash;
                    window.location.replace(next);
                } catch (e) {
                    window.location.replace('home.php');
                }
                return;
            }
            if (suggestTimer) {
                clearTimeout(suggestTimer);
            }
            suggestTimer = setTimeout(function () {
                suggestTimer = null;
                loadItemSuggest();
            }, 200);
        });

        itemSearchInput.addEventListener('focus', function () {
            if (itemSearchInput.value.trim() !== '') {
                loadItemSuggest();
            }
        });

        itemSearchInput.addEventListener('blur', function () {
            setTimeout(hideItemSuggest, 200);
        });

        itemSearchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hideItemSuggest();
            }
        });
    }

    if (prefersReducedMotion) {
        animateCounters();
    } else {
        /* Align with dashboard stat card entrance (style.css) */
        window.setTimeout(animateCounters, 320);
    }
    renderTrend('transactions');
})();
