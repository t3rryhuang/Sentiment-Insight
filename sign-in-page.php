<?php 
session_start();
include 'functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<?php
displayHead("Sign In - Sentiment Insight");
?>
</head>
<body>
<?php
displaySidebar();
displayTopNav();
?>

<div class="content">
    <div class="sign-in-wrapper">
        <div class="sign-in-box">
            <h2>Sign In</h2>
            <?php
            if (isset($_SESSION['error'])) {
                echo '<div class="error" style="color: #721817; background-color: #FFD6D6; padding: 10px; border-radius: 5px;">' . $_SESSION['error'] . '</div>';
                unset($_SESSION['error']); // Clear the error message after displaying it
            }
            ?>
            <form action="sign-in.php" method="POST">
                <label for="username_email">Username or Email</label>
                <input type="text" id="username_email" name="username_email" placeholder="Enter your username or email" required>
                
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                
                <button type="submit">Sign In</button>
            </form>
            <p>Don't have an account? <a href="registration-page.php">Create one</a></p>
        </div>
    </div>
</div>

</body>
</html>
