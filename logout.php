<?php
session_start();
if (isset($_POST['logout'])) {
    // Destroy the session and redirect to the index
    session_destroy();
    header("Location: index.php");
    exit;
}
?>