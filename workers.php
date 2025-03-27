<?php
session_start();
$host = "localhost";
$dbname = "users";
$username = "root";
$password = "";

// Create dual database connections
$conn_users = new mysqli($host, $username, $password, "users");
$conn_drugs = new mysqli($host, $username, $password, "drugs");

if ($conn_users->connect_error || $conn_drugs->connect_error) {
    die("Connection failed: " . $conn_users->connect_error . $conn_drugs->connect_error);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header("Location: index.php");
    exit();
}

$searchUserID = $_POST['patient_id'] ?? $_SESSION['searched_patient'] ?? "";
$patientData = null;
$patientName = null;
$successMessage = "";
$errorMessage = "";

if (isset($_POST['search_patient'])) {
    $_SESSION['searched_patient'] = $searchUserID;
    $tableName = "user_" . $searchUserID;
    $checkTableQuery = $conn_users->query("SHOW TABLES LIKE '$tableName'");
    if ($checkTableQuery->num_rows > 0) {
        $query = $conn_users->prepare("SELECT drugs, allergies, first_name, last_name FROM `$tableName` LIMIT 1");
        $query->execute();
        $result = $query->get_result();
        $patientData = $result->fetch_assoc();
        $patientName = $patientData['first_name'] . ' ' . $patientData['last_name'];
    } else {
        $errorMessage = "Patient ID not found.";
    }
}

if (isset($_POST['add_drug']) && !empty($searchUserID)) {
    $newDrug = trim($_POST['drug_name']);
    $tableName = "user_" . $searchUserID;

    if (!empty($newDrug)) {
        $query = $conn_users->prepare("SELECT drugs FROM `$tableName` LIMIT 1");
        $query->execute();
        $result = $query->get_result();
        $patientData = $result->fetch_assoc();
        $updatedDrugs = empty($patientData['drugs']) ? $newDrug : $patientData['drugs'] . ", " . $newDrug;
        $updateQuery = $conn_users->prepare("UPDATE `$tableName` SET drugs = ? LIMIT 1");
        $updateQuery->bind_param("s", $updatedDrugs);
        if ($updateQuery->execute()) {
            $successMessage = "Drug added successfully!";
            header("Location: workers.php");
            exit();
        } else {
            $errorMessage = "Error adding drug.";
        }
    }
}

if (isset($_POST['remove_drug']) && !empty($searchUserID)) {
    $removeDrug = trim($_POST['drug_name']);
    $tableName = "user_" . $searchUserID;
    $query = $conn_users->prepare("SELECT drugs FROM `$tableName` LIMIT 1");
    $query->execute();
    $result = $query->get_result();
    $patientData = $result->fetch_assoc();

    if (!empty($patientData['drugs'])) {
        $updatedDrugs = array_filter(explode(", ", $patientData['drugs']), function($drug) use ($removeDrug) {
            return trim($drug) !== trim($removeDrug);
        });
        $updatedDrugs = implode(", ", $updatedDrugs);
        $updateQuery = $conn_users->prepare("UPDATE `$tableName` SET drugs = ? LIMIT 1");
        $updateQuery->bind_param("s", $updatedDrugs);
        if ($updateQuery->execute()) {
            $successMessage = "Drug removed successfully!";
            header("Location: workers.php");
            exit();
        } else {
            $errorMessage = "Error removing drug.";
        }
    }
}

if (isset($_POST['select_prescription']) && !empty($_POST['prescription_to_fill'])) {
    $_SESSION['fill_prescription'] = $_POST['prescription_to_fill'];
    $_SESSION['fill_patient_id'] = $_POST['patient_id'];
}

if (isset($_POST['confirm_fill']) && isset($_SESSION['fill_prescription']) && isset($_POST['qr_code_fill']) && isset($_POST['fill_quantity'])) {
    $prescriptionDrug = trim($_SESSION['fill_prescription']);
    $qr = $_POST['qr_code_fill'];
    $qtyToFill = (int)$_POST['fill_quantity'];

    if (preg_match('/^\d{16}$/', $qr)) {
        $drugID = substr($qr, 0, 4);
        $lotNumber = substr($qr, 4, 4);
        $exp = substr($qr, 8, 8);
        $formattedExp = substr($exp, 0, 2) . "/" . substr($exp, 2, 2) . "/" . substr($exp, 4, 4);

        $getDrug = $conn_drugs->prepare("SELECT name, amount FROM drugs WHERE id = ?");
        $getDrug->bind_param("s", $drugID);
        $getDrug->execute();
        $res = $getDrug->get_result();
        $drug = $res->fetch_assoc();

        if ($drug && strtolower(str_replace(' ', '_', $drug['name'])) === strtolower(str_replace(' ', '_', $prescriptionDrug))) {
            $tableName = strtolower(str_replace(' ', '_', $drug['name']));

            $conn_drugs->query("CREATE TABLE IF NOT EXISTS `$tableName` (
                lot_number VARCHAR(10) PRIMARY KEY,
                expiration_date VARCHAR(10),
                amount DECIMAL(6,2),
                strength INT NOT NULL
            )");

            $fetchAmt = $conn_drugs->prepare("SELECT amount FROM `$tableName` WHERE lot_number = ?");
            $fetchAmt->bind_param("s", $lotNumber);
            $fetchAmt->execute();
            $result = $fetchAmt->get_result();
            $lotData = $result->fetch_assoc();

            if ($lotData) {
                if ($lotData['amount'] >= $qtyToFill) {
                    $newAmount = $lotData['amount'] - $qtyToFill;
                    $update = $conn_drugs->prepare("UPDATE `$tableName` SET amount = ? WHERE lot_number = ?");
                    $update->bind_param("ds", $newAmount, $lotNumber);
                    $update->execute();
                    $successMessage = "Filled $qtyToFill pills of $prescriptionDrug from lot $lotNumber.";
                } else {
                    $errorMessage = "Not enough inventory for this lot.";
                }
            } else {
                $initialAmount = $drug['amount'];
                $newAmount = $initialAmount - $qtyToFill;
                if ($newAmount >= 0) {
                    $strength = 0;
                    $insert = $conn_drugs->prepare("INSERT INTO `$tableName` (lot_number, expiration_date, amount, strength) VALUES (?, ?, ?, ?)");
                    $insert->bind_param("ssdi", $lotNumber, $formattedExp, $newAmount, $strength);
                    $insert->execute();
                    $successMessage = "Created new lot and filled $qtyToFill pills of $prescriptionDrug.";
                } else {
                    $errorMessage = "Cannot fill more than available default amount.";
                }
            }
            unset($_SESSION['fill_prescription']);
        } else {
            $errorMessage = "QR drug does not match selected prescription.";
        }
    } else {
        $errorMessage = "Invalid QR code.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pharmacist Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: auto; }
        input, select, button { width: 100%; padding: 10px; margin-top: 10px; border-radius: 4px; border: 1px solid #ccc; }
        button { background: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
        .success { color: green; }
        .error { color: red; }
        h2, h3 { margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Pharmacist Dashboard</h2>

    <?php if ($successMessage) echo "<p class='success'>$successMessage</p>"; ?>
    <?php if ($errorMessage) echo "<p class='error'>$errorMessage</p>"; ?>

    <!-- Search Patient -->
    <form method="POST">
        <input type="number" name="patient_id" placeholder="Enter Patient ID" required value="<?php echo htmlspecialchars($searchUserID); ?>">
        <button type="submit" name="search_patient">Search</button>
    </form>

    <!-- If Patient Found -->
    <?php if ($patientData): ?>
        <h3>Patient Profile: <?php echo htmlspecialchars($patientName); ?></h3>
        <p><strong>Allergies:</strong> <?php echo htmlspecialchars($patientData['allergies'] ?? 'None'); ?></p>
        <p><strong>Prescriptions:</strong> <?php echo htmlspecialchars($patientData['drugs'] ?? 'None'); ?></p>

        <!-- Add/Remove Drugs -->
        <h3>Modify Prescriptions</h3>
        <form method="POST">
            <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($searchUserID); ?>">
            <input type="text" name="drug_name" placeholder="Enter drug name" required>
            <button type="submit" name="add_drug">Add Drug</button>
            <button type="submit" name="remove_drug">Remove Drug</button>
        </form>

        <!-- Select Prescription -->
        <h3>Fill a Prescription</h3>
        <form method="POST">
            <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($searchUserID); ?>">
            <select name="prescription_to_fill" required>
                <option value="">Select a prescription</option>
                <?php
                if (!empty($patientData['drugs'])) {
                    $prescriptions = explode(',', $patientData['drugs']);
                    foreach ($prescriptions as $prescription) {
                        $drugName = trim($prescription);
                        echo "<option value=\"$drugName\">$drugName</option>";
                    }
                } else {
                    echo "<option disabled>No prescriptions found.</option>";
                }
                ?>
            </select>
            <button type="submit" name="select_prescription">Next</button>
        </form>
    <?php endif; ?>

    <!-- If prescription selected, show QR + quantity -->
    <?php if (isset($_SESSION['fill_prescription'])): ?>
        <h3>Scan QR Code and Fill: <?php echo htmlspecialchars($_SESSION['fill_prescription']); ?></h3>
        <form method="POST">
            <input type="text" name="qr_code_fill" placeholder="Scan 16-digit QR Code" required>
            <input type="number" name="fill_quantity" placeholder="Enter quantity to fill" required min="1">
            <button type="submit" name="confirm_fill">Fill Prescription</button>
        </form>
    <?php endif; ?>

    <p><a href="inventory.php">Go to Inventory</a> | <a href="verify.php">Go to Queue Verification</a> | <a href="logout.php">Logout</a></p>
</div>
</body>
</html>