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
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $email = $_POST['email'];
    $isPharmacist = isset($_POST['is_pharmacist']) ? 1 : 0;
    $npiNumber = isset($_POST['npi']) ? $_POST['npi'] : null;

    // Determine role
    $role = ($isPharmacist && !empty($npiNumber)) ? 'worker' : 'patient';

    // Count total users to determine next user ID
    $result = $conn->query("SELECT COUNT(*) AS total FROM Users");
    $row = $result->fetch_assoc();
    $userID = $row['total'] + 1;  // New user ID based on table length

    // Hash the password
    $hashedPassword = password_hash($pass, PASSWORD_BCRYPT);

    // Insert into Users table with manual user ID
    $stmt = $conn->prepare("INSERT INTO Users (id, username, password, role, npi) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userID, $user, $hashedPassword, $role, $npiNumber);

    if ($stmt->execute()) {
        if ($role == 'patient') {
            // Create a new table for patient data
            $tableName = "user_" . $userID;
            $createTableSQL = "CREATE TABLE `$tableName` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                drugs TEXT DEFAULT NULL,
                allergies TEXT DEFAULT NULL
            )";

            if ($conn->query($createTableSQL) === TRUE) {
                // Insert patient details
                $insertDetailsSQL = $conn->prepare("INSERT INTO `$tableName` (first_name, last_name, email) VALUES (?, ?, ?)");
                $insertDetailsSQL->bind_param("sss", $firstName, $lastName, $email);
                $insertDetailsSQL->execute();
            }
        }

        echo "<p class='success'>Registration successful! <a href='login.php'>Login here</a></p>";
    } else {
        echo "<p class='error'>Error registering user: " . $conn->error . "</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px 0px rgba(0, 0, 0, 0.1);
            width: 350px;
            text-align: center;
        }
        .container h2 {
            margin-bottom: 20px;
        }
        input, button {
            width: 100%;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        button {
            background: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #218838;
        }
        .toggle-container {
            text-align: left;
            margin-top: 10px;
        }
        .npi-field {
            display: none;
        }
        .error {
            color: red;
        }
        .success {
            color: green;
        }
    </style>
    <script>
        function toggleNpiField() {
            let checkbox = document.getElementById("is_pharmacist");
            let npiField = document.getElementById("npi_field");
            npiField.style.display = checkbox.checked ? "block" : "none";
        }
    </script>
</head>
<body>
<div class="container">
    <h2>Register</h2>
    <form action="" method="POST">
        <input type="text" name="username" placeholder="Username" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <input type="text" name="first_name" placeholder="First Name" required><br>
        <input type="text" name="last_name" placeholder="Last Name" required><br>
        <input type="email" name="email" placeholder="Email" required><br>

        <div class="toggle-container">
            <label>
                <input type="checkbox" id="is_pharmacist" name="is_pharmacist" onclick="toggleNpiField()"> Register as Pharmacist
            </label>
        </div>

        <div id="npi_field" class="npi-field">
            <input type="text" name="npi" placeholder="Enter NPI Number"><br>
        </div>

        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="index.php">Login here</a></p>
</div>
</body>
</html>