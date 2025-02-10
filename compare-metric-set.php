<?php
// compare-metric-set.php

session_start();
if (!isset($_SESSION['username'])) {
    header('Location: sign-in-page.php');
    exit;
}

require 'functions.php'; // your site-wide functions

$conn = connectDB();
$savedSets = fetchSavedSets($conn, $_SESSION['username']);
$conn->close();

// Output HTML
displayHead("Compare Metric Sets");
displaySidebar();
displayTopNavWithSearch();
?>
<link rel="stylesheet" href="css/compare-set-styles.css">

<!-- 1) Load Luxon (for date/time) -->
<script src="https://cdn.jsdelivr.net/npm/luxon@3"></script>

<!-- 2) Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- 3) Load the Chart.js adapter for Luxon, AFTER Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1"></script>

<!-- 4) Load your custom JS that references Chart + the adapter -->
<script src="js/charts-and-graphs/metric-comparison.js"></script>

<div id="compare-header">
    <div class="header-top">
        <h1>Compare Metric Sets</h1>
        <div class="date-picker">
            <label for="startDate">Start Date:</label>
            <input type="date" id="startDate" onchange="loadMetricData()">
            <label for="endDate">End Date:</label>
            <input type="date" id="endDate" onchange="loadMetricData()">
        </div>
    </div>
    <p>Compare your saved metric sets by selecting from the dropdown lists.</p>

    <div class="metrics-comparison">
        <div class="metric-select-container">
            <select id="metricSet1" onchange="loadMetricData()">
                <option value="">Select Metric Set</option>
                <?php foreach ($savedSets as $set): ?>
                    <option value="<?= htmlspecialchars($set['setID']) ?>">
                        <?= htmlspecialchars($set['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="metricSet2" onchange="loadMetricData()">
                <option value="">Select Metric Set</option>
                <?php foreach ($savedSets as $set): ?>
                    <option value="<?= htmlspecialchars($set['setID']) ?>">
                        <?= htmlspecialchars($set['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<div class="content">
    <!-- Net Severity Section -->
    <div class="metricsOutput">
        <div class="half">
            <h3>Net Severity</h3>
            <div id="netSeverity1" class="net-severity-value">--</div>
        </div>
        <div class="half">
            <h3>Net Severity</h3>
            <div id="netSeverity2" class="net-severity-value">--</div>
        </div>
    </div>

    <!-- Most Positive Aspects -->
    <div class="metricsOutput">
        <div class="half">
            <h3>Most Positive Aspects</h3>
            <table>
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Avg Severity</th>
                    </tr>
                </thead>
                <tbody id="mostPositive1"><!-- Filled by JS --></tbody>
            </table>
        </div>
        <div class="half">
            <h3>Most Positive Aspects</h3>
            <table>
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Avg Severity</th>
                    </tr>
                </thead>
                <tbody id="mostPositive2"><!-- Filled by JS --></tbody>
            </table>
        </div>
    </div>

    <!-- Most Negative Aspects -->
    <div class="metricsOutput">
        <div class="half">
            <h3>Most Negative Aspects</h3>
            <table>
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Avg Severity</th>
                    </tr>
                </thead>
                <tbody id="mostNegative1"><!-- Filled by JS --></tbody>
            </table>
        </div>
        <div class="half">
            <h3>Most Negative Aspects</h3>
            <table>
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Avg Severity</th>
                    </tr>
                </thead>
                <tbody id="mostNegative2"><!-- Filled by JS --></tbody>
            </table>
        </div>
    </div>

    <!-- Time Series Chart Section -->
    <div style="margin-top: 75px;">
        <h3>Average Sentiment Over Time</h3>
        <canvas id="timeSeriesChart" width="600" height="200"></canvas>
    </div>
</div>

<!-- Initialize default dates & auto-load data -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    let today = new Date();
    let endDateValue = today.toISOString().split('T')[0];
    document.getElementById('endDate').value = endDateValue;

    let lastWeek = new Date(today.getTime() - (7 * 24 * 60 * 60 * 1000));
    let startDateValue = lastWeek.toISOString().split('T')[0];
    document.getElementById('startDate').value = startDateValue;

    loadMetricData();
});
</script>

</body>
</html>
