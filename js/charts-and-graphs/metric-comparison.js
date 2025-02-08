// js/charts-and-graphs/metric-comparison.js

function loadMetricData() {
    const set1 = document.getElementById("metricSet1").value;
    const set2 = document.getElementById("metricSet2").value;
    const startDate = document.getElementById("startDate").value;
    const endDate = document.getElementById("endDate").value;

    // If neither set is selected, clear displays
    if (!set1 && !set2) {
        document.getElementById("netSeverity1").innerText = "--";
        document.getElementById("netSeverity2").innerText = "--";
        clearTable("mostPositive1");
        clearTable("mostPositive2");
        clearTable("mostNegative1");
        clearTable("mostNegative2");
        return;
    }

    // Build query parameters
    const params = new URLSearchParams({
        set1,
        set2,
        startDate,
        endDate
    });

    // Adjust the path if needed.
    // e.g. if compare-metric-set.php and chart-functions in same folder:
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
                mostNegative2
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
        // Optional: show a row indicating no data
        const row = document.createElement("tr");
        row.innerHTML = `<td colspan="2">No data</td>`;
        tbody.appendChild(row);
        return;
    }

    items.forEach(item => {
        const row = document.createElement("tr");
        // item.topic (string) and item.avgSeverity (number)
        // We'll color the severity text, if you like, using colorForScore
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

/** A simple color gradient: 1 => green, 5 => orange, 10 => red */
function colorForScore(score) {
    // clamp to [1..10]
    score = Math.max(1, Math.min(10, score));

    // piecewise linear from #0B6E4F (green) to #FA9F42 (orange) to #721817 (red)
    if (score <= 5) {
        const fraction = (score - 1) / 4; // 1..5 => 0..1
        return interpolateHexColor("#0B6E4F", "#FA9F42", fraction);
    } else {
        const fraction = (score - 5) / 5; // 5..10 => 0..1
        return interpolateHexColor("#FA9F42", "#721817", fraction);
    }
}

/** Interpolate between two hex colors (e.g. #RRGGBB) by fraction [0..1]. */
function interpolateHexColor(hexA, hexB, fraction) {
    const rgbA = hexToRgb(hexA);
    const rgbB = hexToRgb(hexB);

    const r = Math.round(rgbA.r + (rgbB.r - rgbA.r) * fraction);
    const g = Math.round(rgbA.g + (rgbB.g - rgbA.g) * fraction);
    const b = Math.round(rgbA.b + (rgbB.b - rgbA.b) * fraction);

    return rgbToHex({ r, g, b });
}

/** Convert #RRGGBB => {r,g,b}. */
function hexToRgb(hex) {
    hex = hex.replace(/^#/, '');
    if (hex.length === 3) {
        hex = hex[0]+hex[0] + hex[1]+hex[1] + hex[2]+hex[2];
    }
    const num = parseInt(hex, 16);
    return {
        r: (num >> 16) & 255,
        g: (num >> 8) & 255,
        b: num & 255
    };
}

/** Convert {r,g,b} => #RRGGBB. */
function rgbToHex({ r, g, b }) {
    const toHex = c => {
        const h = c.toString(16);
        return h.length < 2 ? '0' + h : h;
    };
    return '#' + toHex(r) + toHex(g) + toHex(b);
}
