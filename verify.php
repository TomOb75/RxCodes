<?php
session_start();
$host = "localhost";
$username = "root";
$password = "";

$conn_prescriptions = new mysqli($host, $username, $password, "prescriptions");
$conn_users = new mysqli($host, $username, $password, "users");

if ($conn_prescriptions->connect_error || $conn_users->connect_error) {
    die("Connection failed: " . $conn_prescriptions->connect_error . $conn_users->connect_error);
}

// Ensure 'id' column exists in 'verify' table
$checkIdColumn = $conn_prescriptions->query("SHOW COLUMNS FROM verify LIKE 'id'");
if ($checkIdColumn->num_rows === 0) {
    $conn_prescriptions->query("ALTER TABLE verify ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST");
}

$successMessage = "";
$errorMessage = "";

// Handle verification
if (isset($_POST['verify']) && isset($_POST['selected'])) {
    foreach ($_POST['selected'] as $id) {
        $query = $conn_prescriptions->prepare("SELECT * FROM verify WHERE id = ?");
        $query->bind_param("i", $id);
        $query->execute();
        $result = $query->get_result();
        if ($row = $result->fetch_assoc()) {
            $patient_name = $row['patient_name'];
            $drug_name = $row['drug_name'];

            $userQuery = $conn_users->query("SHOW TABLES LIKE 'user_%'");
            while ($table = $userQuery->fetch_array()) {
                $tableName = $table[0];
                $checkName = $conn_users->prepare("SELECT * FROM `$tableName` WHERE CONCAT(first_name, ' ', last_name) = ? LIMIT 1");
                $checkName->bind_param("s", $patient_name);
                $checkName->execute();
                $userResult = $checkName->get_result();
                if ($userData = $userResult->fetch_assoc()) {
                    $currentDrugs = $userData['drugs'] ?? '';
                    $updatedDrugs = trim($currentDrugs . ', ' . $drug_name, ', ');
                    $updateDrugs = $conn_users->prepare("UPDATE `$tableName` SET drugs = ? WHERE CONCAT(first_name, ' ', last_name) = ?");
                    $updateDrugs->bind_param("ss", $updatedDrugs, $patient_name);
                    $updateDrugs->execute();
                    break;
                }
            }
            $delete = $conn_prescriptions->prepare("DELETE FROM verify WHERE id = ?");
            $delete->bind_param("i", $id);
            $delete->execute();
        }
    }
    $successMessage = "Selected prescriptions have been verified and assigned.";
}

// Handle rejection
if (isset($_POST['reject']) && isset($_POST['selected'])) {
    foreach ($_POST['selected'] as $id) {
        $delete = $conn_prescriptions->prepare("DELETE FROM verify WHERE id = ?");
        $delete->bind_param("i", $id);
        $delete->execute();
    }
    $successMessage = "Selected prescriptions have been rejected.";
}

$prescriptions = $conn_prescriptions->query("SELECT * FROM verify");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Prescriptions</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 800px; margin: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #007bff; color: white; }
        input[type="submit"] { padding: 10px; margin-top: 10px; border-radius: 4px; border: none; background: #007bff; color: white; cursor: pointer; }
        input[type="submit"]:hover { background: #0056b3; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
<div class="container">
    <h2>Verify Prescriptions</h2>

    <?php if ($successMessage) echo "<p class='success'>$successMessage</p>"; ?>
    <?php if ($errorMessage) echo "<p class='error'>$errorMessage</p>"; ?>

    <form method="POST">
        <table>
            <tr>
                <th>Select</th>
                <th>Patient Name</th>
                <th>Drug Name</th>
                <th>Quantity</th>
                <th>Dosage</th>
                <th>NPI</th>
            </tr>
            <?php while ($row = $prescriptions->fetch_assoc()): ?>
                <tr>
                    <td><input type="checkbox" name="selected[]" value="<?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?>" /></td>
                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['drug_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($row['dosage']); ?></td>
                    <td><?php echo htmlspecialchars($row['npi']); ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
        <input type="submit" name="verify" value="Verify Selected">
        <input type="submit" name="reject" value="Reject Selected">
    </form>

    <p><a href="workers.php">Back to Dashboard</a></p>
</div>
</body>
</html>