<?php

function displaySidebar() {
    echo <<<HTML
    <div class="sidebar">
        <a href='index.php'>
            <div id="logo">
                <img src='images/logo.png'>
            </div>
        </a>

        <div id="sidebar-buttons">
            <!-- Buttons with icon and text -->
            <a href="index.php" class="sidebar-button">
                <img src="images/icons/home.svg" alt="Home">
                <span>Home</span>
            </a>
            
           
            <a href="saved-metric-sets.php" class="sidebar-button">    
                <img src="images/icons/folder.svg" alt="Saved">
                <span>Saved Metric Sets</span>
            </a>
            

            <div class="sidebar-button">
                <img src="images/icons/browse.svg" alt="Browse">
                <span>Browse</span>
            </div>

            <a href="compare-metric-set.php" class="sidebar-button">    
                <img src="images/icons/compare.svg" alt="Saved">
                <span>Compare</span>
            </a>

            <div id="sidebar-separator"></div>
        </div>
    </div>
HTML;
}

function displayTopNav() {
    // Check if the user is logged in by checking a session variable
    if (isset($_SESSION['username'])) {
        $username = $_SESSION['username']; // Assume you store username in session
        echo <<<HTML
        <div class="top-nav">
            <div class="user-icon-container" onclick="toggleDropdownMenu()">
                <img src="images/icons/user.svg" alt="User">
                <img src="images/icons/chevron-right.svg" alt="More Options" id="chevron-icon">
            </div>
            <div class="dropdown-menu" id="dropdown-menu">
                <span style="display: block; text-align: center; color: #333; font-weight: bold;">@$username</span>
                <form action="logout.php" method="POST" style="display: inline;">
                    <button type="submit" name="logout" class="link-button">Sign Out</button>
                </form>
            </div>
        </div>
HTML;
    } else {
        echo <<<HTML
        <div class="top-nav">
            <div class="user-icon-container" onclick="toggleDropdownMenu()">
                <img src="images/icons/user.svg" alt="User">
                <img src="images/icons/chevron-right.svg" alt="More Options" id="chevron-icon">
            </div>
            <div class="dropdown-menu" id="dropdown-menu">
                <a href="sign-in-page.php">Sign In</a>
                <a href="registration-page.php">Create Account</a>
            </div>
        </div>
HTML;
    }
}

function displayTopNavWithSearch() {
    // Check if the user is logged in by checking a session variable
    if (isset($_SESSION['username'])) {
        $username = $_SESSION['username']; // Assume you store username in session
        // Check if the search query is set and sanitize it
        $searchQuery = isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '';

        echo <<<HTML
        <div class="top-nav">
            <div class="search-box-container">
                <form action="search-results.php" method="GET" autocomplete="off">
                    <div class="search-box">
                        <img src="images/icons/search.svg" alt="Search Icon">
                        <input type="text" id="searchInput" name="q" placeholder="Search organisation, industry, subreddit" value="{$searchQuery}">
                        <!-- Suggestions box -->
                        <div id="suggestions" class="suggestions-box"></div>
                    </div>
                </form>
            </div>
            <div class="user-icon-container" onclick="toggleDropdownMenu()">
                <img src="images/icons/user.svg" alt="User">
                <img src="images/icons/chevron-right.svg" alt="More Options" id="chevron-icon">
            </div>
            <div class="dropdown-menu" id="dropdown-menu">
                <span style="display: block; text-align: center; color: #333; font-weight: bold;">@$username</span>
                <form action="logout.php" method="POST" style="display: inline;">
                    <button type="submit" name="logout" class="link-button">Sign Out</button>
                </form>
            </div>
        </div>
HTML;
    } else {
        // Check if the search query is set and sanitize it
        $searchQuery = isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '';

        echo <<<HTML
        <div class="top-nav">
            <div class="search-box-container">
                <form action="search-results.php" method="GET" autocomplete="off">
                    <div class="search-box">
                        <img src="images/icons/search.svg" alt="Search Icon">
                        <input type="text" id="searchInput" name="q" placeholder="Search organisation, industry, subreddit" value="{$searchQuery}">
                        <!-- Suggestions box -->
                        <div id="suggestions" class="suggestions-box"></div>
                    </div>
                </form>
            </div>
            <div class="user-icon-container" onclick="toggleDropdownMenu()">
                <img src="images/icons/user.svg" alt="User">
                <img src="images/icons/chevron-right.svg" alt="More Options" id="chevron-icon">
            </div>
            <div class="dropdown-menu" id="dropdown-menu">
                <a href="sign-in-page.php">Sign In</a>
                <a href="registration-page.php">Create Account</a>
            </div>
        </div>
HTML;
    }
}








function displayContent($title, $message) {
    echo <<<HTML
    <div class="content">
        <h2>$title</h2>
        <p>$message</p>
    </div>
HTML;
}

function displayHead($pageTitle) {
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$pageTitle</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/script.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>
HTML;
}

function connectDB() {
    // Database connection details
    $host = 'localhost';
    $db_user = 'sentimentInsight';
    $db_password = 'josram-bebQap-wefjy0';
    $db_name = 'sentiment_insight';

    // Establish database connection
    $conn = new mysqli($host, $db_user, $db_password, $db_name);

    // Check connection
    if ($conn->connect_error) {
        $_SESSION['error'] = "Database connection failed: " . $conn->connect_error;
        header("Location: registration-page.php");
        exit;
    }

    return $conn;  // Return the connection object
}

function getIconPath($entityType) {
    $iconMap = [
        'Organisation' => "images/icons/organisation.svg",
        'Subreddit'    => "images/icons/subreddit.svg",
        'Industry'     => "images/icons/industry.svg",
    ];

    // Return the mapped icon path, or default if not found
    return $iconMap[$entityType] ?? "images/icons/default.svg";
}

/**
 * Check if a user has saved a specific set.
 *
 * @param mysqli $conn      The database connection.
 * @param int    $setID     The set ID to check.
 * @return bool             True if the set is saved by the logged-in user, otherwise false.
 */
function isSetSavedByUser($conn, $setID) {
    if (!isset($_SESSION['username'])) {
        return false; // User is not logged in
    }

    $username = $_SESSION['username'];

    // Get the account ID from the username
    $queryAcc = "SELECT accountID FROM Account WHERE username = ?";
    $stmtAcc = $conn->prepare($queryAcc);
    if (!$stmtAcc) {
        return false; // Prevent errors if statement preparation fails
    }

    $stmtAcc->bind_param("s", $username);
    $stmtAcc->execute();
    $resultAcc = $stmtAcc->get_result();
    $account_id = $resultAcc->fetch_assoc()['accountID'] ?? null;
    $stmtAcc->close();

    if (!$account_id) {
        return false; // No valid account ID found
    }

    // Check if the set is saved
    $checkQuery = "SELECT 1 FROM SavedSet WHERE accountID = ? AND setID = ?";
    $checkStmt = $conn->prepare($checkQuery);
    if (!$checkStmt) {
        return false;
    }

    $checkStmt->bind_param("ii", $account_id, $setID);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $isSaved = $checkResult->num_rows > 0;
    $checkStmt->close();

    return $isSaved;
}

/**
 * Generate the HTML for the save button based on whether the set is saved or not.
 *
 * @param bool $savedState  Whether the set is saved by the logged-in user.
 * @return string           The HTML string for the save button.
 */
function generateSaveButtonHtml($savedState) {
    if ($savedState) {
        return '
            <button id="saveButton" class="save-button saved">
                <img src="images/icons/saved.svg" alt="Saved Icon"> Saved
            </button>
        ';
    } else {
        return '
            <button id="saveButton" class="save-button">
                <img src="images/icons/save-grey.svg" alt="Save Icon"> Save
            </button>
        ';
    }
}

/**
 * Fetch the entity info (type + name) for a given setID.
 *
 * @param mysqli $conn  Database connection
 * @param int    $setID The set ID
 * @return array        ['entityType' => string, 'entityName' => string]
 *                     or ['entityType' => 'Unknown', 'entityName' => 'Unknown'] if not found.
 */
function getEntityInfo(mysqli $conn, int $setID): array {
    $sql = "SELECT entityType, name FROM TrackedEntity WHERE setID = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['entityType' => 'Unknown', 'entityName' => 'Unknown'];
    }

    $stmt->bind_param("i", $setID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $entityType = $row['entityType'];
        $entityName = $row['name'] ?? 'Unknown';
    } else {
        $entityType = 'Unknown';
        $entityName = 'Unknown';
    }
    $stmt->close();
    return [
        'entityType' => $entityType,
        'entityName' => $entityName
    ];
}

?>