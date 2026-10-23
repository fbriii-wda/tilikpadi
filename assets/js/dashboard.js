document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.sidebar-wrapper');
    const toggleBtn = document.querySelector('.sidebar-toggle-btn');
    
    let overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show', sidebar.classList.contains('show'));
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }


    const monthNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    const monthShortNames = [
        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    const severityColors = {
        ringan: '#2E7D32',
        sedang: '#FBC02D',
        parah: '#EF6C00'
    };
    let selectedDashboardYear = new Date().getFullYear();
    let scanChart = null;
    let severityChart = null;

    const scanChartEl = document.querySelector('#scan-chart');
    if (scanChartEl) {
        scanChart = new ApexCharts(scanChartEl, {
            series: [{ name: 'Pemindaian', data: Array(12).fill(0) }],
            chart: {
                type: 'bar',
                height: 220,
                toolbar: { show: false },
                zoom: { enabled: false },
                fontFamily: 'Plus Jakarta Sans, sans-serif'
            },
            colors: ['#2E7D32'],
            states: { hover: { filter: { type: 'none' } } },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '48%',
                    borderRadius: 3
                }
            },
            dataLabels: { enabled: false },
            legend: { show: false },
            grid: {
                borderColor: '#E0E6E0',
                strokeDashArray: 4,
                xaxis: { lines: { show: false } }
            },
            xaxis: {
                categories: monthNames,
                labels: {
                    formatter: value => {
                        const monthIndex = monthNames.indexOf(value);
                        return monthIndex === -1 ? value : monthShortNames[monthIndex];
                    },
                    style: {
                        colors: '#616161',
                        fontSize: '11px',
                        fontWeight: 500
                    }
                },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                min: 0,
                forceNiceScale: true,
                labels: { formatter: value => Math.round(value).toString() }
            },
            noData: { text: 'Belum ada data pemindaian' },
            tooltip: {
                y: {
                    formatter: value => `${value} pemindaian`
                },
                theme: 'dark'
            }
        });
        scanChart.render();
    }

    const severityChartEl = document.querySelector('#severity-chart');
    if (severityChartEl) {
        severityChart = new ApexCharts(severityChartEl, {
            series: [0, 0, 0],
            chart: {
                type: 'donut',
                height: 250,
                fontFamily: 'Plus Jakarta Sans, sans-serif'
            },
            labels: ['Ringan', 'Sedang', 'Parah'],
            colors: [severityColors.ringan, severityColors.sedang, severityColors.parah],
            states: { hover: { filter: { type: 'none' } } },
            legend: { show: false },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                fontSize: '12px',
                                fontWeight: 500,
                                color: '#616161',
                                offsetY: -8
                            },
                            value: {
                                show: true,
                                fontSize: '26px',
                                fontWeight: 800,
                                color: '#1B1B1B',
                                offsetY: 8,
                                formatter: value => Math.round(value).toLocaleString('id-ID')
                            },
                            total: {
                                show: true,
                                label: 'Total Scan',
                                fontSize: '11px',
                                fontWeight: 500,
                                color: '#616161',
                                formatter: chart => chart.globals.seriesTotals
                                    .reduce((total, count) => total + count, 0)
                                    .toLocaleString('id-ID')
                            }
                        }
                    }
                }
            },
            noData: { text: 'Belum ada data pemindaian' },
            tooltip: {
                y: {
                    formatter: value => `${value} pemindaian`
                },
                theme: 'dark'
            }
        });
        severityChart.render();
    }

    const statValues = document.querySelectorAll('[data-stat-value]');
    const diseaseStatNote = document.querySelector('[data-stat-note="diseaseTypes"]');
    const userStatsNotes = document.querySelectorAll(
        '[data-stat-note="registeredUsers"], [data-stat-note="activeUsers"]'
    );
    const userStatsNoteDefaults = new Map(
        Array.from(userStatsNotes).map(function (note) {
            return [note.dataset.statNote, note.querySelector('span')?.textContent || ''];
        })
    );
    const numberFormatter = new Intl.NumberFormat('id-ID');
    const scanTotal = document.querySelector('[data-scan-total]');
    const scanYearLabel = document.querySelector('[data-scan-year-label]');
    const recentDetections = document.querySelector('#recent-detections');
    const severitySummary = document.querySelector('#severity-summary');
    const severityChartLegend = document.querySelector('#severity-chart-legend');

    const setStatValue = function (key, value, note) {
        const element = document.querySelector(`[data-stat-value="${key}"]`);
        if (element) {
            element.textContent = value;
        }
        if (key === 'diseaseTypes' && diseaseStatNote && note) {
            const noteText = diseaseStatNote.querySelector('span');
            if (noteText) {
                noteText.textContent = note;
            }
        }
    };

    const renderRecentDetections = function (detections) {
        if (!recentDetections) {
            return;
        }
        recentDetections.replaceChildren();

        if (detections.length === 0) {
            const emptyState = document.createElement('p');
            emptyState.className = 'text-muted-green mb-0';
            emptyState.textContent = 'Belum ada hasil pemindaian yang tercatat.';
            recentDetections.append(emptyState);
            return;
        }

        const dateFormatter = new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'medium',
            timeStyle: 'short'
        });

        detections.forEach(function (detection) {
            const item = document.createElement('div');
            item.className = 'transaction-item';

            const icon = document.createElement('div');
            icon.className = 'transaction-icon bg-forest-light text-lime';
            const iconGlyph = document.createElement('i');
            iconGlyph.className = 'bi bi-camera';
            iconGlyph.setAttribute('aria-hidden', 'true');
            icon.append(iconGlyph);

            const info = document.createElement('div');
            info.className = 'transaction-info';
            const disease = document.createElement('div');
            disease.className = 'transaction-name';
            disease.textContent = detection.disease || 'Penyakit tidak diketahui';
            const metadata = document.createElement('p');
            metadata.className = 'transaction-date';
            const parsedDate = new Date(String(detection.date || '').replace(' ', 'T'));
            const formattedDate = Number.isNaN(parsedDate.getTime())
                ? 'Waktu tidak tersedia'
                : dateFormatter.format(parsedDate);
            metadata.textContent = `${detection.user || 'Pengguna'} · ${formattedDate}`;
            info.append(disease, metadata);

            const result = document.createElement('div');
            result.className = 'text-end ms-2';
            const severity = document.createElement('div');
            severity.className = 'transaction-amount text-success';
            severity.textContent = detection.severity || 'Belum dianalisis';
            result.append(severity);

            if (Number.isFinite(detection.confidence)) {
                const confidence = document.createElement('small');
                confidence.className = 'transaction-date';
                confidence.textContent = `Keyakinan ${numberFormatter.format(detection.confidence)}`;
                result.append(confidence);
            }

            item.append(icon, info, result);
            recentDetections.append(item);
        });
    };

    const renderSeveritySummary = function (distribution) {
        if (!severitySummary) {
            return;
        }
        severitySummary.replaceChildren();

        const total = distribution.reduce((sum, severity) => sum + severity.count, 0);
        distribution.forEach(function (severity) {
            const row = document.createElement('div');
            row.className = 'progress-container';

            const labelRow = document.createElement('div');
            labelRow.className = 'progress-label-row';
            const label = document.createElement('span');
            label.className = 'progress-label';
            label.textContent = severity.label;
            const count = document.createElement('span');
            count.className = 'progress-value';
            count.textContent = numberFormatter.format(severity.count);
            labelRow.append(label, count);

            const progress = document.createElement('div');
            progress.className = 'progress';
            progress.setAttribute('role', 'progressbar');
            progress.setAttribute('aria-label', `Hasil scan ${severity.label}`);
            progress.setAttribute('aria-valuemin', '0');
            progress.setAttribute('aria-valuemax', String(Math.max(total, 1)));
            progress.setAttribute('aria-valuenow', String(severity.count));

            const bar = document.createElement('div');
            bar.className = 'progress-bar';
            bar.style.backgroundColor = severityColors[severity.label.toLowerCase()] || '#2E7D32';
            bar.style.width = `${total > 0 ? (severity.count / total) * 100 : 0}%`;
            progress.append(bar);
            row.append(labelRow, progress);
            severitySummary.append(row);
        });
    };

    const renderSeverityLegend = function (distribution) {
        if (!severityChartLegend) {
            return;
        }
        severityChartLegend.replaceChildren();

        distribution.forEach(function (severity) {
            const item = document.createElement('div');
            item.className = 'chart-legend-item severity-legend-item';
            const dot = document.createElement('span');
            dot.className = 'legend-dot';
            dot.style.backgroundColor = severityColors[severity.label.toLowerCase()] || '#2E7D32';
            const label = document.createElement('span');
            label.className = 'text-muted-green severity-legend-label';
            label.textContent = severity.label;
            const count = document.createElement('span');
            count.className = 'text-muted-green severity-legend-count';
            count.textContent = numberFormatter.format(severity.count);
            item.append(dot, label, count);
            severityChartLegend.append(item);
        });
    };

    const refreshDashboardStats = async function () {
        try {
            const query = new URLSearchParams({ year: String(selectedDashboardYear) });
            const response = await fetch(`api/dashboard-stats.php?${query.toString()}`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            });
            if (!response.ok) {
                throw new Error(`Statistik gagal dimuat (HTTP ${response.status}).`);
            }

            const stats = await response.json();
            ['registeredUsers', 'activeUsers'].forEach(function (key) {
                if (Number.isSafeInteger(stats[key]) && stats[key] >= 0) {
                    setStatValue(key, numberFormatter.format(stats[key]));
                    const note = document.querySelector(`[data-stat-note="${key}"]`);
                    const noteText = note?.querySelector('span');
                    if (noteText) {
                        noteText.textContent = userStatsNoteDefaults.get(key) || '';
                    }
                } else {
                    setStatValue(key, 'Tidak tersedia');
                    const note = document.querySelector(`[data-stat-note="${key}"]`);
                    const noteText = note?.querySelector('span');
                    if (noteText) {
                        noteText.textContent = stats.errors?.users || 'Data Firebase Authentication gagal dimuat';
                    }
                }
            });

            if (Number.isSafeInteger(stats.diseaseTypes) && stats.diseaseTypes >= 0) {
                setStatValue(
                    'diseaseTypes',
                    numberFormatter.format(stats.diseaseTypes),
                    'Firebase · diperbarui tiap 60 detik'
                );
            } else {
                setStatValue(
                    'diseaseTypes',
                    'Tidak tersedia',
                    stats.errors?.diseaseTypes || 'Data Firebase gagal dimuat'
                );
            }

            if (scanTotal) {
                scanTotal.textContent = numberFormatter.format(stats.annualScanCount);
            }
            if (scanYearLabel) {
                scanYearLabel.textContent = `hasil scan pada ${stats.year}`;
            }
            if (scanChart && Array.isArray(stats.monthlyScans)) {
                scanChart.updateOptions({ xaxis: { categories: monthNames } });
                scanChart.updateSeries([{ name: 'Pemindaian', data: stats.monthlyScans }]);
            }

            renderRecentDetections(stats.recentDetections);
            renderSeveritySummary(stats.severityDistribution);
            renderSeverityLegend(stats.severityDistribution);
            if (severityChart) {
                severityChart.updateOptions({
                    labels: stats.severityDistribution.map(severity => severity.label),
                    colors: stats.severityDistribution.map(
                        severity => severityColors[severity.label.toLowerCase()] || '#2E7D32'
                    )
                });
                severityChart.updateSeries(
                    stats.severityDistribution.map(severity => severity.count)
                );
            }
        } catch (error) {
            console.error('Gagal memperbarui statistik dashboard:', error);
            statValues.forEach(function (element) {
                element.textContent = 'Tidak tersedia';
            });
            if (diseaseStatNote) {
                const noteText = diseaseStatNote.querySelector('span');
                if (noteText) {
                    noteText.textContent = 'Gagal memuat data statistik';
                }
            }
            if (recentDetections) {
                recentDetections.textContent = 'Riwayat pemindaian gagal dimuat.';
            }
            if (severitySummary) {
                severitySummary.textContent = 'Ringkasan tingkat keparahan gagal dimuat.';
            }
            if (severityChartLegend) {
                severityChartLegend.textContent = 'Distribusi gagal dimuat.';
            }
        } finally {
            window.setTimeout(refreshDashboardStats, 60000);
        }
    };

    refreshDashboardStats();

    const datePickerTrigger = document.querySelector('#date-picker-trigger');
    const selectedDateText = document.querySelector('#selected-date');
    
    if (datePickerTrigger && selectedDateText) {
        let followsCurrentDate = true;

        const datePicker = flatpickr(datePickerTrigger, {
            mode: 'single',
            dateFormat: 'Y-m-d',
            locale: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
                    longhand: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
                },
                months: {
                    shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                    longhand: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
                }
            },
            defaultDate: new Date(),
            onReady: function (selectedDates, dateStr, instance) {
                selectedDateText.textContent = instance.formatDate(new Date(), 'j F Y');
            },
            onChange: function (selectedDates, dateStr, instance) {
                const selectedDate = selectedDates[0];
                selectedDateText.textContent = instance.formatDate(selectedDate, 'j F Y');
                followsCurrentDate = selectedDate.toDateString() === new Date().toDateString();
                const selectedYear = selectedDate.getFullYear();
                if (selectedYear !== selectedDashboardYear) {
                    selectedDashboardYear = selectedYear;
                    refreshDashboardStats();
                }
            }
        });

        const updateCurrentDateAtMidnight = function () {
            const now = new Date();
            const nextMidnight = new Date(now);
            nextMidnight.setHours(24, 0, 0, 0);

            window.setTimeout(function () {
                if (followsCurrentDate) {
                    datePicker.setDate(new Date(), true);
                }
                updateCurrentDateAtMidnight();
            }, nextMidnight.getTime() - now.getTime());
        };

        updateCurrentDateAtMidnight();
    }

    const desktopToggleBtn = document.querySelector('#desktop-sidebar-toggle');
    if (desktopToggleBtn) {
        desktopToggleBtn.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-minimized');
            
            const icon = desktopToggleBtn.querySelector('i');
            if (icon) {
                if (document.body.classList.contains('sidebar-minimized')) {
                    icon.className = 'bi bi-chevron-bar-right';
                } else {
                    icon.className = 'bi bi-chevron-bar-left';
                }
            }
            
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 300);
        });
    }

    const fullscreenBtn = document.querySelector('#btn-fullscreen');
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', function () {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().then(() => {
                    updateFullscreenIcon(true);
                }).catch(err => {
                    console.error(`Gagal mengaktifkan mode layar penuh: ${err.message}`);
                });
            } else {
                document.exitFullscreen().then(() => {
                    updateFullscreenIcon(false);
                }).catch(err => {
                    console.error(`Gagal keluar dari mode layar penuh: ${err.message}`);
                });
            }
        });

        function updateFullscreenIcon(isFullscreen) {
            const icon = fullscreenBtn.querySelector('i');
            if (icon) {
                if (isFullscreen) {
                    icon.className = 'bi bi-fullscreen-exit';
                } else {
                    icon.className = 'bi bi-arrows-fullscreen';
                }
            }
        }

        document.addEventListener('fullscreenchange', () => {
            if (document.fullscreenElement) {
                updateFullscreenIcon(true);
            } else {
                updateFullscreenIcon(false);
            }
        });
    }
});
