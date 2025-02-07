<?php
// metric-set.php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

// Include your main functions and chart-related functions
include 'functions.php'; 
include 'chart-functions/linechart-functions.php';
include 'chart-functions/sankey-functions.php';
include 'chart-functions/severity-table-queries.php';
include 'chart-functions/force-field-diagram-functions.php'; 
include 'chart-functions/topic-frequency-functions.php';

$conn = connectDB();

// 1) Check if setID is provided
$setID = isset($_GET['setID']) ? (int)$_GET['setID'] : null;
if (!$setID) {
    echo "<p>No setID provided.</p>";
    exit;
}

// 2) Fetch entity info
$entityArr  = getEntityInfo($conn, $setID);
$entityType = $entityArr['entityType'];
$entityName = $entityArr['entityName'];

// 3) Determine icon path
$iconPath = getIconPath($entityType);

// 4) Fetch line chart data
try {
    $data = getLineChartData($conn, $setID);
} catch (Exception $e) {
    echo "<p>Error fetching line chart data: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// 5) Check login / saved state
$isLoggedIn = isset($_SESSION['username']);
$savedState = isSetSavedByUser($conn, $setID);

// 6) Earliest & latest date
try {
    list($earliestDate, $latestDate) = getEarliestAndLatestDate($conn, $setID);
} catch (Exception $e) {
    echo "<p>Error fetching earliest/latest dates: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Use earliest/today if not provided
$startDate = !empty($_GET['startDate']) ? $_GET['startDate'] : $earliestDate;
$endDate   = !empty($_GET['endDate'])   ? $_GET['endDate']   : date('Y-m-d');

// 7) Build Sankey data
try {
    $sankeyPlotData = getSankeyPlotData($conn, $setID, $startDate, $endDate);
} catch (Exception $e) {
    echo "<p>Error building Sankey data: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// 8) top 10 topics by max severity
try {
    $topTenTopics = getTopTenTopics($conn, $setID, $startDate, $endDate);
} catch (Exception $e) {
    echo "<p>Error retrieving top 10 topics: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// 9) all grouped topics
try {
    $allTopics = getAllGroupedTopics($conn, $setID, $startDate, $endDate);
} catch (Exception $e) {
    echo "<p>Error retrieving full table data: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// 10) Force Field Data (full)
try {
    $forceFieldDataAll = getForceFieldData($conn, $setID, $startDate, $endDate); 
} catch (Exception $e) {
    echo "<p>Error retrieving force field data: " . htmlspecialchars($e->getMessage()) . "</p>";
    $forceFieldDataAll = ['positive' => [], 'negative' => []];
}

// Slice Force Field => top 5 negative, top 5 positive
$negCount = count($forceFieldDataAll['negative']);
$posCount = count($forceFieldDataAll['positive']);
$negativeSlice = array_slice($forceFieldDataAll['negative'], 0, ($negCount>5 ? 5 : $negCount));
$posLimit = ($posCount>5 ? 5 : $posCount);
$positiveSlice = array_slice($forceFieldDataAll['positive'], $posCount-$posLimit, $posLimit);
$forceFieldDataLimited = [
    'negative' => $negativeSlice,
    'positive' => $positiveSlice
];

// NEW: Topic Frequencies (full)
try {
    $topicFreqAll = getTopicFrequencies($conn, $setID, $startDate, $endDate);
} catch (Exception $e) {
    echo "<p>Error retrieving topic frequencies: " . htmlspecialchars($e->getMessage()) . "</p>";
    $topicFreqAll = [];
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sentiment Insight - Metric Set</title>
  <link rel="stylesheet" href="css/styles.css"> 
  <link rel="stylesheet" href="css/metric-set-styles.css"> 

  <!-- Chart.js + Plotly + date-fns -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/date-fns@2.28.0/date-fns.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.2.0/dist/chartjs-plugin-annotation.min.js"></script>
  <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
  <!-- Ensure plugin versions match Chart.js v3: -->
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
</head>
<body>
<?php
displayHead("Sentiment Insight - Metric Set");
displaySidebar();
displayTopNavWithSearch();
?>
<div class="content">
    <!-- Header -->
    <div class="header-bar">
        <h1 class="entity-header">
            <img src="<?php echo $iconPath; ?>" alt="<?php echo htmlspecialchars($entityType); ?>" class="entity-icon">
            <?php echo htmlspecialchars($entityName); ?>
        </h1>
        <?php echo generateSaveButtonHtml($savedState); ?>
    </div>

    <h1>Sentiment Over Time</h1>
    
    <!-- Graph Controls & Line Chart Container -->
    <div style="width: 28%;" class="graph-controls">
        <div class="graph-options">
            <div class="graph-option">
                <button id="breakdownOption" class="graph-option-button active" style="background-color: #D3D3D3;">
                    <img id="breakdownIcon" src="images/icons/breakdown-black.svg" alt="Breakdown">
                </button>
                <div class="graph-option-label">Breakdown</div>
            </div>
            <div class="graph-option">
                <button id="averageOption" class="graph-option-button">
                    <img id="averageIcon" src="images/icons/average-severity-grey.svg" alt="Average Severity">
                </button>
                <div class="graph-option-label">Average Severity</div>
            </div>
        </div>
        <div class="time-range-controls">
            <button id="timeRange1W" class="time-range-button selected">1W</button>
            <button id="timeRange1M" class="time-range-button">1M</button>
            <button id="timeRangeYTD" class="time-range-button">YTD</button>
        </div>
    </div>
    <div class="chart-container">
        <canvas id="sentimentTimeSeriesChart"></canvas>
        <div class="scroll-controls">
            <button id="scrollLeft" class="scroll-button">&lt; Prev</button>
            <button id="todayButton" class="scroll-button">Today</button>
            <button id="scrollRight" class="scroll-button">Next &gt;</button>
        </div>
    </div>

    <!-- Sankey Diagram Section -->
    <div id="sankey-diagram">
        <h1>Key Discussions and Debated Topics</h1>
        
        <!-- Date Range Form -->
        <div class="sankey-date-filter">
            <label for="startDate">Start:</label>
            <input type="date" 
                   name="startDate"
                   id="startDate"
                   min="<?php echo htmlspecialchars($earliestDate); ?>"
                   max="<?php echo htmlspecialchars($latestDate); ?>"
                   value="<?php echo htmlspecialchars($startDate); ?>">

            <label for="endDate">End:</label>
            <input type="date" 
                   name="endDate"
                   id="endDate"
                   min="<?php echo htmlspecialchars($earliestDate); ?>"
                   max="<?php echo htmlspecialchars($latestDate); ?>"
                   value="<?php echo htmlspecialchars($endDate); ?>">

            <form method="GET" action="" style="display: inline;">
                <input type="hidden" name="setID" value="<?php echo htmlspecialchars($setID); ?>">
                <input type="hidden" name="startDate" id="hiddenStart" value="">
                <input type="hidden" name="endDate" id="hiddenEnd" value="">
                <button type="submit" class="filter-button">Filter</button>
            </form>
        </div>
        
        <div id="sankey-plot"></div>
    </div>

    <!-- Analysis sections: left=1/3, right=2/3 -->
    <div class="analysis-sections">
        <!-- LEFT 1/3 COLUMN: (e.g., Top 10 Topics) -->
        <div class="analysis-section-33">
            <h3>Net Metric Scores</h3>
            <table class="metric-table">
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Severity</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($topTenTopics)): ?>
                    <?php foreach($topTenTopics as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['topic']); ?></td>
                        <td><?php echo htmlspecialchars($row['maxSeverity']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2">No topics found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            
            <!-- If big table has more topics than 10, show link -->
            <?php if(count($allTopics) > 10): ?>
              <a href="#" id="viewFullTableLink" style="color: #fa9f42; text-decoration: underline; cursor: pointer;">
                View Full Table &gt;
              </a>
            <?php endif; ?>
        </div>

        <!-- RIGHT 2/3 COLUMN: Force Field Analysis -->
        <div class="analysis-section-force-field">
            <h3>Force Field Analysis</h3>
            <p>(Showing up to 5 most negative &amp; 5 most positive)</p>
            <a href="#" id="viewFullForceFieldLink" style="color: #fa9f42; text-decoration: underline; cursor: pointer;">
              View Full Diagram &gt;
            </a>
        </div>
    </div>

    <h1>Most Talked About</h1>
    <!-- Filter Buttons for Negative/Neutral/Positive -->
    <div class="graph-controls">
        <div class="time-range-controls">
            <button id="topicNegBtn" class="time-range-button selected">Negative</button>
            <button id="topicNeuBtn" class="time-range-button">Neutral</button>
            <button id="topicPosBtn" class="time-range-button">Positive</button>
        </div>
    </div>

    <div class="analysis-sections">

        <!-- LEFT 2/3: Frequency Distribution Graph (top 5 topics) -->
        <div class="analysis-section-67" style="background:#101214; border-radius:8px;">
            <canvas id="topicFrequencyChart" width="100%" height="450"></canvas>
        </div>
        
        <!-- RIGHT 1/3: top 5 table, link -> modal for full table -->
        <div class="analysis-section-33" style="margin-right: 5%;">
            <h3>Topic Frequency (Top 5)</h3>
            <table class="metric-table" id="topicFreqTable">
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Impressions</th>
                    </tr>
                </thead>
                <tbody id="topicFreqTableBody">
                    <!-- We'll populate this from JS -->
                </tbody>
            </table>
            <?php if(count($topicFreqAll) > 5): ?>
              <a href="#" id="viewFullFreqLink" style="color: #fa9f42; text-decoration: underline; cursor: pointer;">
                View Full Table &gt;
              </a>
            <?php endif; ?>


            
        </div>
    </div>


    <h3>Word Cloud</h3>
    <div style="float: center; margin-left: 20px;" id="wordCloudContainer"></div>
</div>

<!-- MODAL: All Grouped Topics by Max Severity, Descending -->
<div class="modal-overlay" id="fullTableModal">
    <div class="modal-content">
        <button class="close-modal" id="closeModalBtn">╳</button>

        <div class="modal-body">
            <!-- LEFT half: Table container -->
            <div class="table-container">
                <h2>All Topics Ranked By Severity</h2>
                <table class="metric-table">
                    <thead>
                        <tr>
                            <th>Topic</th>
                            <th>Severity</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($allTopics)): ?>
                        <?php foreach($allTopics as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['topic']); ?></td>
                            <td><?php echo htmlspecialchars($row['maxSeverity']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2">No topics found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- RIGHT half: severity scale explanation -->
            <div class="severity-scale-container">
                <h3 style='display: inline'>Severity Scale</h3>
                <br>

                <p><strong>Severity Ratings:</strong> The severity ratings range from 1 to 10, with 1 indicating very positive or impactful events (e.g., breakthrough achievements, global peace, or environmental success) and 10 indicating catastrophic events (e.g., natural disasters, wars, or pandemics). The lower the severity, the more positive or neutral the sentiment; the higher the severity, the more negative or destructive the sentiment.</p>

                <p><strong>Moderate Scores (5):</strong> A score of 5 typically indicates neutral, everyday discussions or news, where the sentiment is unclear, mixed, or arbitrary. For instance, general conversations or updates about routine events like city council meetings or neutral news reports may fall into this category. It might also indicate that the sentiment is not strongly expressed, or it could reflect topics with little to no emotional weight.</p>

                <p><strong>High Scores (8-10):</strong> Scores above 7 reflect increasingly negative impacts. These numbers are assigned to topics with a severe or concerning tone, including controversies, crises, and issues causing distress or harm. A 10, in particular, represents events of extreme consequence, such as global wars, natural disasters, or massive economic collapses.</p>

                <p><strong>Possible Errors:</strong> It's important to note that sentiment analysis can sometimes misinterpret or oversimplify the tone of a topic. While the scale aims to accurately reflect the intensity of a topic's sentiment, some topics may be rated inaccurately due to ambiguous language or lack of clear context. In some cases, topics with neutral or unclear sentiment may receive a score of 5 due to insufficient clarity in the data.</p>

                <div class="gradient-bar">
                    <!-- the gradient from #0B6E4F -> #fa9f42 -> #721817 -->
                </div>
                <div class="gradient-labels">
                    <span>Low Severity</span>
                    <span>High Severity</span>
                </div>

                <div class="severity-colors">
                    <div class="severity-color" style="background-color: #0B6E4F;">Positive</div>
                    <div class="severity-color" style="background-color: #fa9f42;">Neutral</div>
                    <div class="severity-color" style="background-color: #721817;">Negative</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Full Force Field Diagram -->
<div class="modal-overlay" id="fullForceFieldModal">
    <div class="modal-content">
        <button class="close-modal" id="closeForceFieldModalBtn">╳</button>
        <div class="modal-body">
            <div id="fullForceFieldDiagram">
                <h2>Full Force Field Diagram</h2>
                <div id="svgContainer"></div>
            </div>
        </div>
    </div>
</div>

<!-- NEW: Full Topic Frequency Table Modal -->
<div class="modal-overlay" id="fullFreqModal">
    <div class="modal-content">
        <button class="close-modal" id="closeFreqModalBtn">╳</button>
        <div class="modal-body">
            <h2>All Topics by Frequency</h2>
            <table class="metric-table">
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Impressions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(!empty($topicFreqAll)): ?>
                    <?php foreach($topicFreqAll as $fr): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($fr['topic']); ?></td>
                        <td><?php echo (int)$fr['totalImpressions']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2">No topics data found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Provide Force Field Data & Topic Frequency Data to JS -->
<script>
window.forceFieldData     = <?php echo json_encode($forceFieldDataLimited); ?>;
window.forceFieldDataAll  = <?php echo json_encode($forceFieldDataAll); ?>;
window.topicFreqData      = <?php echo json_encode($topicFreqAll); ?>; // We'll store ALL here, but filter in JS
</script>

<!-- External JS -->
<script src="js/charts-and-graphs/sentiment-over-time.js"></script>
<script src="js/charts-and-graphs/sankey-diagram.js"></script>
<script src="js/charts-and-graphs/force-field-diagram.js"></script>
<script src="js/charts-and-graphs/topic-frequency.js"></script>
<script src="js/charts-and-graphs/word-cloud.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function(){
    // 1) Setup line chart
    var chartData  = <?php echo json_encode($data); ?>;
    var setID      = <?php echo json_encode($setID); ?>;
    var isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;
    setupSentimentChart(chartData, setID, isLoggedIn);

    // 2) Setup Sankey
    var sankeyData = <?php echo json_encode($sankeyPlotData); ?>;
    setupSankeyDiagram(sankeyData);

    // Date pickers
    const startInput  = document.getElementById('startDate');
    const endInput    = document.getElementById('endDate');
    const hiddenStart = document.getElementById('hiddenStart');
    const hiddenEnd   = document.getElementById('hiddenEnd');
    function updateHiddenFields(){
        hiddenStart.value = startInput.value;
        hiddenEnd.value   = endInput.value;
    }
    if(startInput && endInput){
        startInput.addEventListener('change', updateHiddenFields);
        endInput.addEventListener('change', updateHiddenFields);
        updateHiddenFields();
    }

    // Full Table Modal (Severity)
    const viewFullTableLink = document.getElementById('viewFullTableLink');
    const fullTableModal    = document.getElementById('fullTableModal');
    const closeModalBtn     = document.getElementById('closeModalBtn');
    if(viewFullTableLink && fullTableModal){
        viewFullTableLink.addEventListener('click', function(e){
            e.preventDefault();
            fullTableModal.style.display = 'flex';
        });
    }
    if(closeModalBtn){
        closeModalBtn.addEventListener('click', function(){
            fullTableModal.style.display = 'none';
        });
    }

    // Force Field Diagram Modal
    const viewFullFFLink     = document.getElementById('viewFullForceFieldLink');
    const fullFFModalOverlay = document.getElementById('fullForceFieldModal');
    const closeFFModalBtn    = document.getElementById('closeForceFieldModalBtn');
    if(viewFullFFLink) {
        viewFullFFLink.addEventListener('click', function(e){
            e.preventDefault();
            fullFFModalOverlay.style.display = 'flex';

            if (typeof drawFullForceFieldDiagram === 'function') {
                drawFullForceFieldDiagram(window.forceFieldDataAll);
            }
        });
    }
    if(closeFFModalBtn){
        closeFFModalBtn.addEventListener('click', function(){
            fullFFModalOverlay.style.display = 'none';
        });
    }

    // Force Field limited
    if(window.forceFieldData && typeof drawLimitedForceFieldDiagram==='function'){
        drawLimitedForceFieldDiagram(window.forceFieldData);
    }

    // 3) Setup Topic Frequency Chart 
    if(window.topicFreqData && typeof setupTopicFrequencyChart==='function'){
        setupTopicFrequencyChart(window.topicFreqData, 'negative'); // default negative
    }

    // 4) Full Frequency Table Modal
    const viewFullFreqLink = document.getElementById('viewFullFreqLink');
    const fullFreqModal    = document.getElementById('fullFreqModal');
    const closeFreqModalBtn= document.getElementById('closeFreqModalBtn');
    if(viewFullFreqLink && fullFreqModal){
        viewFullFreqLink.addEventListener('click', function(e){
            e.preventDefault();
            fullFreqModal.style.display = 'flex';
        });
    }
    if(closeFreqModalBtn){
        closeFreqModalBtn.addEventListener('click', function(){
            fullFreqModal.style.display = 'none';
        });
    }
});
</script>
</body>
</html>
