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

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];
$tableName = "user_" . $userID; // Patient's personal table

// Fetch patient details (prescriptions, allergies)
$query = $conn->prepare("SELECT drugs, allergies FROM `$tableName` LIMIT 1");
$query->execute();
$result = $query->get_result();
$patientData = $result->fetch_assoc();

// Handle allergy updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_allergies'])) {
    $newAllergies = $_POST['allergies'];
    $updateQuery = $conn->prepare("UPDATE `$tableName` SET allergies = ? LIMIT 1");
    $updateQuery->bind_param("s", $newAllergies);
    if ($updateQuery->execute()) {
        $patientData['allergies'] = $newAllergies;
        $successMessage = "Allergies updated successfully!";
    } else {
        $errorMessage = "Error updating allergies.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Dashboard</title>
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
            width: 400px;
            text-align: center;
        }
        .container h2 {
            margin-bottom: 20px;
        }
        input, button, textarea {
            width: 100%;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Welcome, Patient</h2>

    <?php if (isset($successMessage)) echo "<p class='success'>$successMessage</p>"; ?>
    <?php if (isset($errorMessage)) echo "<p class='error'>$errorMessage</p>"; ?>

    <h3>Your Prescriptions</h3>
    <p><?php echo isset($patientData['drugs']) ? $patientData['drugs'] : "No prescriptions found."; ?></p>

    <h3>Allergies</h3>
    <form method="POST">
        <textarea name="allergies" placeholder="Enter allergies..."><?php echo isset($patientData['allergies']) ? $patientData['allergies'] : ""; ?></textarea>
        <button type="submit" name="update_allergies">Update Allergies</button>
    </form>

    <p><a href="logout.php">Logout</a></p>
</div>
</body>
</html>