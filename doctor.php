<?php
session_start();
$host = "localhost";
$username = "root";
$password = "";
$dbname = "prescriptions";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$successMessage = "";
$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_name = trim($_POST['patient_name']);
    $drug_name = trim($_POST['drug_name']);
    $quantity = intval($_POST['quantity']);
    $dosage = intval($_POST['dosage']);
    $npi = intval($_POST['npi']);

    if ($patient_name && $drug_name && $quantity && $dosage && $npi) {
        $stmt = $conn->prepare("INSERT INTO verify (patient_name, drug_name, quantity, dosage, npi) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiii", $patient_name, $drug_name, $quantity, $dosage, $npi);
        if ($stmt->execute()) {
            $successMessage = "Prescription recorded successfully.";
        } else {
            $errorMessage = "Error inserting prescription.";
        }
    } else {
        $errorMessage = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Prescription Form</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: auto; }
        input, button { width: 100%; padding: 10px; margin: 8px 0; border-radius: 4px; border: 1px solid #ccc; }
        button { background: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
<div class="container">
    <h2>Doctor Prescription Form</h2>

    <?php if ($successMessage) echo "<p class='success'>$successMessage</p>"; ?>
    <?php if ($errorMessage) echo "<p class='error'>$errorMessage</p>"; ?>

    <form method="POST">
        <input type="text" name="patient_name" placeholder="Patient Name" required>
        <input type="text" name="drug_name" placeholder="Drug Name" required>
        <input type="number" name="quantity" placeholder="Quantity" required>
        <input type="number" name="dosage" placeholder="Dosage (mg)" required>
        <input type="number" name="npi" placeholder="NPI Number" required>
        <button type="submit">Submit Prescription</button>
    </form>

    <p><a href="index.php">Back to Dashboard</a></p>
</div>
</body>
</html>
