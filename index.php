<?php 
session_start();
include 'functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sentiment Insight - Search</title>
  <link rel="stylesheet" href="styles.css"> <!-- your external stylesheet -->
</head>
<body>
<?php
displayHead("Sentiment Insight");
displaySidebar();
displayTopNav();
?>

<div class="content-index">
<div class="search-container">
    <form id="searchForm" action="search-results.php" method="GET" autocomplete="off">
      <div class="search-box" style="position: relative;">
        <img src="images/icons/search.svg" alt="Search Icon">
        <input type="text" id="searchInput" name="q" placeholder="Search organisation, industry, subreddit" oninput="toggleSubmitButton()">
        <div id="suggestions" class="suggestions-box"></div>
      </div>
      <button type="submit" id="searchButton" disabled>Search</button>
    </form>
  </div>
  

  <div id="recommended-home">
    <h2>Recommended Metric Sets</h2>
    <!-- Additional content here -->
  </div>
</div>

<script>
  function toggleSubmitButton() {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    searchButton.disabled = searchInput.value.trim() === '';
  }
</script>
</body>
</html>
