<?php 
session_start();
include 'functions.php';
?>

<body>
<?php
displayHead("Sentiment Insight");
displaySidebar();
displayTopNav()
?>

    <div class="content">
        <div class="search-container">
            <div class="search-box">
                <img src="images/icons/search.svg" alt="Search Icon">
                <input type="text" placeholder="Search organisation, industry, subreddit">
            </div>
            <button type="button">Search</button>
        </div>

        <div id="recommended-home">
            <h2>Recommended Metric Sets</h2>
        </div>
    </div>
</body>
</html>

<script>


</script>