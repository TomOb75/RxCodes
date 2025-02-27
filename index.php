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
    $input_username = $_POST['username'];
    $input_password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, role FROM Users WHERE username = ?");
    $stmt->bind_param("s", $input_username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password, $role);
        $stmt->fetch();

        if (password_verify($input_password, $hashed_password)) {
            $_SESSION["user_id"] = $id;
            $_SESSION["role"] = $role;

            if ($role === "patient") {
                header("Location: patients.php");
            } else if ($role === "worker") {
                header("Location: workers.php");
            }
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    } else {
        $error = "Invalid username or password!";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>RxCodes Login</title>
    <style>
        body { text-align: center; font-family: Arial, sans-serif; }
        .container { margin-top: 50px; }
        .box { border: 2px solid black; padding: 20px; display: inline-block; border-radius: 10px; }
        input { display: block; margin: 10px auto; padding: 8px; width: 200px; }
        button { padding: 8px 20px; border: none; background-color: black; color: white; cursor: pointer; }
        .links { margin-top: 15px; }
        .links a { display: block; color: blue; text-decoration: none; margin: 5px 0;}
    </style>
</head>
<body>

<div class="container">
    <div class="box">
        <h1>RxCodes</h1>
        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <button type="submit">Login</button>
        </form>
        <?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
    </div>
</div>
<div class="links">
    <a href="register.php">Don't have an account? Register here</a>
    <a href="hello_db.php">Team Members</a>
    <a href="UseCase.pdf">Use Case</a>
</div>
</body>
</html>
