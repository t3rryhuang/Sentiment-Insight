<?php
session_start();
include 'functions.php';  // Make sure this file contains connectDB()

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = connectDB();  // Get the database connection

    $input = $conn->real_escape_string($_POST['username_email']);
    $password = $_POST['password'];

    // Prepare the SQL statement to prevent SQL injection
    if ($sql = $conn->prepare("SELECT username, email, password FROM Account WHERE username=? OR email=?")) {
        $sql->bind_param("ss", $input, $input);
        $sql->execute();
        $result = $sql->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Password is correct, start the session
                $_SESSION['username'] = $user['username'];
                header("Location: index.php");
                exit;
            } else {
                $_SESSION['error'] = "Invalid password.";
                header("Location: sign-in-page.php");
                exit;
            }
        } else {
            $_SESSION['error'] = "User not found.";
            header("Location: sign-in-page.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Error preparing database query: " . $conn->error;
        header("Location: sign-in-page.php");
        exit;
    }

    $conn->close();
}
?>
