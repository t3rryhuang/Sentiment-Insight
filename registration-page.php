<?php 
session_start();
include 'functions.php';
?>

<body>

<?php
displayHead("Register - Sentiment Insight");
displaySidebar();
displayTopNav();
?>

<div class="content">
    <div class="sign-in-wrapper">
        <div class="sign-in-box">
            <h2>Register</h2>
            <?php
            if (isset($_SESSION['error'])) {
                echo '<div class="error" style="color: #721817; background-color: #FFD6D6; padding: 10px; border-radius: 5px;">' . $_SESSION['error'] . '</div>';
                unset($_SESSION['error']); // Clear the error message after displaying it
            }
            if (isset($_SESSION['success'])) {
                echo '<div class="success" style="color: #0B6E4F; background-color: #D4EDDA; padding: 10px; border-radius: 5px;">' . $_SESSION['success'] . '</div>';
                unset($_SESSION['success']); // Clear the success message after displaying it
            }
            ?>
            <form action="register.php" method="POST">
                <label for="name">Username</label>
                <input type="text" id="name" name="name" placeholder="Enter a new username" required>
                
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
                
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
                
                <label for="confirm-password">Confirm Password</label>
                <input type="password" id="confirm-password" name="confirm-password" placeholder="Confirm your password" required>
                
                <button type="submit">Register</button>
            </form>
            <p>Already have an account? <a href="/sign-in-page.php">Sign in</a></p>
        </div>
    </div>
</div>


</body>
</html>
