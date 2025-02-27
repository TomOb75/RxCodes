<?php
session_start();
$host = "localhost";
$dbname = "users";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);
    $confirmPass = trim($_POST['confirm_password']);

    if ($pass !== $confirmPass) {
        $error = "Passwords do not match!";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM Users WHERE username = ?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Username already exists. Please choose another.";
        } else {
            // Hash the password
            $hashedPassword = password_hash($pass, PASSWORD_BCRYPT);
            $role = "patient"; // All users are stored as patients

            // Insert new user into the database
            $stmt = $conn->prepare("INSERT INTO Users (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $user, $hashedPassword, $role);

            if ($stmt->execute()) {
                header("Location: login.php"); // Redirect to login after successful registration
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - RxCodes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 50px;
        }
        .container {
            width: 300px;
            margin: auto;
            padding: 20px;
            border: 1px solid black;
            border-radius: 10px;
        }
        input {
            width: 90%;
            padding: 8px;
            margin: 10px 0;
        }
        button {
            padding: 10px;
            width: 100%;
            background-color: green;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .links {
            margin-top: 15px;
        }
        .links a {
            color: blue;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Register as a Patient</h2>

    <?php if (isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>

    <form action="" method="POST">
        <input type="text" name="username" placeholder="Username" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required><br>
        <button type="submit">Register</button>
    </form>

    <div class="links">
        <a href="index.php">Already have an account? Login here</a>
    </div>
</div>

</body>
</html>
