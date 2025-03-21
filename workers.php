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

// Ensure only workers can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header("Location: index.php");
    exit();
}

// Variables
$searchUserID = $_POST['patient_id'] ?? $_SESSION['searched_patient'] ?? "";
$patientData = null;
$successMessage = "";
$errorMessage = "";

// Handle patient search
if (isset($_POST['search_patient'])) {
    $_SESSION['searched_patient'] = $searchUserID; // Store patient ID in session
    $tableName = "user_" . $searchUserID;

    // Check if patient table exists
    $checkTableQuery = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($checkTableQuery->num_rows > 0) {
        // Fetch patient details (drugs & allergies)
        $query = $conn->prepare("SELECT drugs, allergies FROM `$tableName` LIMIT 1");
        $query->execute();
        $result = $query->get_result();
        $patientData = $result->fetch_assoc();
    } else {
        $errorMessage = "Patient ID not found.";
    }
}

// Handle adding drugs
if (isset($_POST['add_drug']) && !empty($searchUserID)) {
    $newDrug = trim($_POST['drug_name']);
    $tableName = "user_" . $searchUserID;

    if (!empty($newDrug)) {
        // Get current drug list
        $query = $conn->prepare("SELECT drugs FROM `$tableName` LIMIT 1");
        $query->execute();
        $result = $query->get_result();
        $patientData = $result->fetch_assoc();

        $updatedDrugs = empty($patientData['drugs']) ? $newDrug : $patientData['drugs'] . ", " . $newDrug;

        // Update drugs list
        $updateQuery = $conn->prepare("UPDATE `$tableName` SET drugs = ? LIMIT 1");
        $updateQuery->bind_param("s", $updatedDrugs);
        if ($updateQuery->execute()) {
            $successMessage = "Drug added successfully!";
            header("Location: workers.php"); // Refresh the page to show updated data
            exit();
        } else {
            $errorMessage = "Error adding drug.";
        }
    }
}

// Handle removing drugs
if (isset($_POST['remove_drug']) && !empty($searchUserID)) {
    $removeDrug = trim($_POST['drug_name']);
    $tableName = "user_" . $searchUserID;

    // Get current drug list
    $query = $conn->prepare("SELECT drugs FROM `$tableName` LIMIT 1");
    $query->execute();
    $result = $query->get_result();
    $patientData = $result->fetch_assoc();

    if (!empty($patientData['drugs'])) {
        $updatedDrugs = array_filter(explode(", ", $patientData['drugs']), function($drug) use ($removeDrug) {
            return trim($drug) !== trim($removeDrug);
        });

        $updatedDrugs = implode(", ", $updatedDrugs);

        // Update drugs list
        $updateQuery = $conn->prepare("UPDATE `$tableName` SET drugs = ? LIMIT 1");
        $updateQuery->bind_param("s", $updatedDrugs);
        if ($updateQuery->execute()) {
            $successMessage = "Drug removed successfully!";
            header("Location: workers.php"); // Refresh the page to show updated data
            exit();
        } else {
            $errorMessage = "Error removing drug.";
        }
    }
}

// Reload patient data after an action
if (!empty($searchUserID)) {
    $tableName = "user_" . $searchUserID;
    $query = $conn->prepare("SELECT drugs, allergies FROM `$tableName` LIMIT 1");
    $query->execute();
    $result = $query->get_result();
    $patientData = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Worker Dashboard</title>
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
        input, button {
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
    <h2>Pharmacist Dashboard</h2>

    <?php if ($successMessage) echo "<p class='success'>$successMessage</p>"; ?>
    <?php if ($errorMessage) echo "<p class='error'>$errorMessage</p>"; ?>

    <h3>Search for a Patient</h3>
    <form method="POST">
        <input type="number" name="patient_id" placeholder="Enter Patient ID" required value="<?php echo htmlspecialchars($searchUserID); ?>">
        <button type="submit" name="search_patient">Search</button>
    </form>

    <?php if ($patientData): ?>
        <h3>Allergies</h3>
        <p><?php echo !empty($patientData['allergies']) ? htmlspecialchars($patientData['allergies']) : "No known allergies."; ?></p>

        <h3>Prescriptions</h3>
        <p><?php echo !empty($patientData['drugs']) ? htmlspecialchars($patientData['drugs']) : "No prescriptions found."; ?></p>

        <h3>Modify Prescriptions</h3>
        <form method="POST">
            <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($searchUserID); ?>">
            <input type="text" name="drug_name" placeholder="Enter drug name" required>
            <button type="submit" name="add_drug">Add Drug</button>
            <button type="submit" name="remove_drug">Remove Drug</button>
        </form>
    <?php endif; ?>

    <p><a href="logout.php">Logout</a></p>
    <p><a href="inventory.php"><button type="button">Manage Inventory</button></a></p>
</div>
</body>
</html>