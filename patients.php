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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];
$tableName = "user_" . $userID;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_allergy'])) {
    $newAllergy = trim($_POST['new_allergy']);
    if (!empty($newAllergy)) {
        $getAllergy = $conn->prepare("SELECT allergies FROM `$tableName` LIMIT 1");
        $getAllergy->execute();
        $result = $getAllergy->get_result();
        $data = $result->fetch_assoc();
        $existing = $data['allergies'] ?? '';
        $updated = $existing ? $existing . ', ' . $newAllergy : $newAllergy;
        $update = $conn->prepare("UPDATE `$tableName` SET allergies = ?");
        $update->bind_param("s", $updated);
        $update->execute();
    }
}

$query = $conn->query("SELECT first_name, drugs, allergies FROM `$tableName` LIMIT 1");
$userData = $query->fetch_assoc();
$firstName = $userData['first_name'] ?? 'Patient';
$drugs = $userData['drugs'] ?? 'None';
$allergies = $userData['allergies'] ?? 'None';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: auto; }
        h2 { margin-bottom: 0; }
        p, label { margin: 8px 0; }
        input[type="text"] { width: 100%; padding: 8px; margin-top: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { margin-top: 10px; padding: 10px 20px; border: none; background-color: #007bff; color: white; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($firstName); ?></h2>
    <p>User ID: <?php echo htmlspecialchars($userID); ?></p>

    <h3>Prescriptions</h3>
    <p><?php echo htmlspecialchars($drugs); ?></p>

    <h3>Allergies</h3>
    <p><?php echo htmlspecialchars($allergies); ?></p>

    <form method="POST">
        <label for="new_allergy">Add New Allergy:</label>
        <input type="text" name="new_allergy" id="new_allergy" placeholder="Enter allergy..." required>
        <button type="submit">Add Allergy</button>
    </form>

    <p><a href="logout.php">Logout</a></p>
</div>
</body>
</html>
