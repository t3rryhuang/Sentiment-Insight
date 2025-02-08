<?php
// compare-metric-set.php

session_start();

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header('Location: sign-in-page.php');
    exit;
}

require 'functions.php'; // your site-wide functions

$conn = connectDB();
$savedSets = fetchSavedSets($conn, $_SESSION['username']);
$conn->close();

// HTML output
displayHead("Compare Metric Sets");
displaySidebar();
displayTopNavWithSearch();
?>
<link rel="stylesheet" href="css/compare-set-styles.css">

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
    <!-- We'll show 3 lowest-severity topics for each set in a table -->
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
                <tbody id="mostPositive1">
                    <!-- Filled by JS -->
                </tbody>
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
                <tbody id="mostPositive2">
                    <!-- Filled by JS -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Most Negative Aspects -->
    <!-- We'll show 3 highest-severity topics for each set in a table -->
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
                <tbody id="mostNegative1">
                    <!-- Filled by JS -->
                </tbody>
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
                <tbody id="mostNegative2">
                    <!-- Filled by JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Link to the metric comparison JS file -->
<script src="js/charts-and-graphs/metric-comparison.js"></script>

<!-- 
     Inline script to set default dates to last 7 days 
     and immediately load data on page load 
-->
<script>
document.addEventListener("DOMContentLoaded", function() {
    let today = new Date();
    let endDateValue = today.toISOString().split('T')[0];
    document.getElementById('endDate').value = endDateValue;

    let lastWeek = new Date(today.getTime() - (7 * 24 * 60 * 60 * 1000));
    let startDateValue = lastWeek.toISOString().split('T')[0];
    document.getElementById('startDate').value = startDateValue;

    // Kick off the initial data load
    loadMetricData();
});
</script>

</body>
</html>
