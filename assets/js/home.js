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
        trendChart = new Chart(chartCanvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: chartLabelBySeries[seriesKey] || 'Trend',
                    data: values,
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
                parsing: false,
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

        var dataset = trendChart.data.datasets[0];
        dataset.label = chartLabelBySeries[seriesKey] || 'Trend';
        dataset.data = values;
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

    animateCounters();
    renderTrend('transactions');
})();
