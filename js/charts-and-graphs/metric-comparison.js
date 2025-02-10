// js/charts-and-graphs/metric-comparison.js

let timeSeriesChart = null;

function loadMetricData() {
    const set1 = document.getElementById("metricSet1").value;
    const set2 = document.getElementById("metricSet2").value;
    const startDate = document.getElementById("startDate").value;
    const endDate = document.getElementById("endDate").value;

    // Determine labels for the legend, using the text of the selected option.
    // If no set is selected, default to "Set 1" or "Set 2".
    const set1Label = set1 
        ? document.querySelector(`#metricSet1 option[value="${set1}"]`).textContent 
        : "Set 1";
    const set2Label = set2 
        ? document.querySelector(`#metricSet2 option[value="${set2}"]`).textContent 
        : "Set 2";

    // If neither set is selected, clear displays and show an empty chart
    if (!set1 && !set2) {
        document.getElementById("netSeverity1").innerText = "--";
        document.getElementById("netSeverity2").innerText = "--";
        clearTable("mostPositive1");
        clearTable("mostPositive2");
        clearTable("mostNegative1");
        clearTable("mostNegative2");

        updateTimeSeriesChart([], [], { 
            startDate, 
            endDate, 
            label1: set1Label, 
            label2: set2Label 
        });
        return;
    }

    // Build query parameters
    const params = new URLSearchParams({ set1, set2, startDate, endDate });
    const url = "chart-functions/get-compare-data.php?" + params.toString();

    fetch(url)
        .then(response => response.json())
        .then(data => {
            const { 
                netSeverity1, 
                netSeverity2,
                mostPositive1,
                mostPositive2,
                mostNegative1,
                mostNegative2,
                timeSeries1,
                timeSeries2
            } = data;

            // --- Net Severity ---
            updateSeverityDisplay("netSeverity1", netSeverity1);
            updateSeverityDisplay("netSeverity2", netSeverity2);

            // --- Most Positive Aspects ---
            fillTable("mostPositive1", mostPositive1);
            fillTable("mostPositive2", mostPositive2);

            // --- Most Negative Aspects ---
            fillTable("mostNegative1", mostNegative1);
            fillTable("mostNegative2", mostNegative2);

            // --- Time Series Data ---
            // Pass the chosen labels to the chart
            updateTimeSeriesChart(timeSeries1, timeSeries2, {
                startDate,
                endDate,
                label1: set1Label,
                label2: set2Label
            });
        })
        .catch(err => {
            console.error("Error fetching data", err);
        });
}

/** Clear any existing rows in a <tbody> by ID. */
function clearTable(tbodyId) {
    const tbody = document.getElementById(tbodyId);
    if (tbody) {
        tbody.innerHTML = "";
    }
}

/** Fill a table <tbody> with rows [ {topic, avgSeverity}, ... ]. */
function fillTable(tbodyId, items) {
    clearTable(tbodyId);
    const tbody = document.getElementById(tbodyId);
    if (!tbody || !items) return;

    if (items.length === 0) {
        const row = document.createElement("tr");
        row.innerHTML = `<td colspan="2">No data</td>`;
        tbody.appendChild(row);
        return;
    }

    items.forEach(item => {
        const row = document.createElement("tr");
        const colorStyle = `color:${colorForScore(item.avgSeverity)}`;
        row.innerHTML = `
            <td>${item.topic}</td>
            <td style="${colorStyle}">${item.avgSeverity.toFixed(2)}</td>
        `;
        tbody.appendChild(row);
    });
}

/** Update the net severity display in the DOM. */
function updateSeverityDisplay(elementId, severityVal) {
    const el = document.getElementById(elementId);
    if (!el) return;

    if (severityVal === null || isNaN(severityVal)) {
        el.innerText = "--";
        el.style.color = "#000";
    } else {
        el.innerText = severityVal.toFixed(2);
        el.style.color = colorForScore(severityVal);
    }
}

/** A simple color gradient for severity score (1..10). */
function colorForScore(score) {
    // clamp to [1..10]
    score = Math.max(1, Math.min(10, score));

    if (score <= 5) {
        const fraction = (score - 1) / 4;
        return interpolateHexColor("#0B6E4F", "#FA9F42", fraction);
    } else {
        const fraction = (score - 5) / 5;
        return interpolateHexColor("#FA9F42", "#721817", fraction);
    }
}

/** Interpolate between two hex colors (#RRGGBB). */
function interpolateHexColor(hexA, hexB, fraction) {
    const rgbA = hexToRgb(hexA);
    const rgbB = hexToRgb(hexB);

    const r = Math.round(rgbA.r + (rgbB.r - rgbA.r) * fraction);
    const g = Math.round(rgbA.g + (rgbB.g - rgbA.g) * fraction);
    const b = Math.round(rgbA.b + (rgbB.b - rgbA.b) * fraction);

    return rgbToHex({ r, g, b });
}

function hexToRgb(hex) {
    hex = hex.replace(/^#/, '');
    if (hex.length === 3) {
        hex = hex[0]+hex[0] + hex[1]+hex[1] + hex[2]+hex[2];
    }
    const num = parseInt(hex, 16);
    return {
        r: (num >> 16) & 255,
        g: (num >> 8) & 255,
        b: (num) & 255
    };
}

function rgbToHex({ r, g, b }) {
    const toHex = c => c.toString(16).padStart(2, '0');
    return '#' + toHex(r) + toHex(g) + toHex(b);
}

/**
 * Build or update the Chart.js line chart for two sets.
 * timeSeries1, timeSeries2 => arrays of { date: "YYYY-MM-DD", avgSeverity: number }
 */
function updateTimeSeriesChart(
    timeSeries1, 
    timeSeries2, 
    { startDate, endDate, label1 = "Set 1", label2 = "Set 2" }
) {
    // Prepare data for each set
    const dataset1 = timeSeries1.map(item => ({ x: item.date, y: item.avgSeverity }));
    const dataset2 = timeSeries2.map(item => ({ x: item.date, y: item.avgSeverity }));

    const ctx = document.getElementById("timeSeriesChart").getContext("2d");

    // If chart instance exists, destroy it first to prevent "Canvas is already in use"
    if (timeSeriesChart) {
        timeSeriesChart.destroy();
    }

    timeSeriesChart = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [
                {
                    label: label1,
                    data: dataset1,
                    borderColor: "#A8A8A8",
                    backgroundColor: "#A8A8A8",
                    borderWidth: 3, 
                    pointRadius: 5, 
                    pointHoverRadius: 7, 
                    fill: false,
                    tension: 0.1
                },
                {
                    label: label2,
                    data: dataset2,
                    borderColor: "#6A7FDB",
                    backgroundColor: "#6A7FDB",
                    borderWidth: 3, 
                    pointRadius: 5, 
                    pointHoverRadius: 7, 
                    fill: false,
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'day',
                        tooltipFormat: 'd MMM yyyy', // e.g., 2 Feb 2025
                        displayFormats: {
                            day: 'd MMM yyyy'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Date',
                        font: {
                            size: 16, // Bigger font for label
                            weight: 'bold'
                        }
                    },
                    min: startDate,
                    max: endDate,
                    grid: {
                        color: "#5B5B5B",  // Dashed vertical grid color
                        borderDash: [5, 5]
                    },
                    ticks: {
                        font: {
                            size: 12 // Tick font size
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Avg Severity',
                        font: {
                            size: 16, // Bigger font for label
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: "#353638", // Dashed horizontal grid color
                        borderDash: [5, 5]
                    },
                    ticks: {
                        font: {
                            size: 12 // Tick font size
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true
                }
            }
        }
    });
}
