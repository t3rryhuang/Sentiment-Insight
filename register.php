<?php
session_start();  // Start session to use session variables
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'functions.php'; // Ensure that this includes the connectDB() function

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = connectDB(); // Establish the database connection
    
    // Sanitize and prepare input values
    $username = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];

    // Check if passwords match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: registration-page.php");
        exit;
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Check if username or email already exists
    $sql = $conn->prepare("SELECT * FROM Account WHERE username=? OR email=?");
    $sql->bind_param("ss", $username, $email);
    $sql->execute();
    $result = $sql->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Username or email already exists. Please choose a different one.";
        header("Location: registration-page.php");
        exit;
    } else {
        // Prepare and execute the insert statement
        $query = $conn->prepare("INSERT INTO Account (username, email, password) VALUES (?, ?, ?)");
        $query->bind_param("sss", $username, $email, $hashed_password);
        if ($query->execute()) {
            $_SESSION['success'] = "Registration successful. You can now <a style='color: #0B6E4F; text-decoration: underline;' href='sign-in-page.php'>sign in</a>.";
            header("Location: registration-page.php");
            exit;
        } else {
            $_SESSION['error'] = "Error registering user: " . $conn->error;
            header("Location: registration-page.php");
            exit;
        }
    }

    $conn->close();
}
?>
