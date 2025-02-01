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
            <div class="sidebar-button">
                <img src="images/icons/folder.svg" alt="Saved">
                <span>Saved Metric Sets</span>
            </div>
            <div class="sidebar-button">
                <img src="images/icons/browse.svg" alt="Browse">
                <span>Browse</span>
            </div>
            <div class="sidebar-button">
                <img src="images/icons/compare.svg" alt="Compare">
                <span>Compare</span>
            </div>
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

?>