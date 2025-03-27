<?php
session_start();
$host = "localhost";
$dbname = "drugs";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$successMessage = "";
$errorMessage = "";
$inventoryData = [];

// Handle QR code add to inventory
if (isset($_POST['submit_qr'])) {
    $qrCode = trim($_POST['qr_code']);
    if (preg_match('/^\d{16}$/', $qrCode)) {
        $drugID = substr($qrCode, 0, 4);
        $lotNumber = substr($qrCode, 4, 4);
        $expirationDate = substr($qrCode, 8, 8);
        $expirationFormatted = substr($expirationDate, 0, 2) . "/" . substr($expirationDate, 2, 2) . "/" . substr($expirationDate, 4, 4);

        $drugQuery = $conn->prepare("SELECT name, amount, strength FROM drugs WHERE id = ?");
        $drugQuery->bind_param("s", $drugID);
        $drugQuery->execute();
        $drugResult = $drugQuery->get_result();
        $drugData = $drugResult->fetch_assoc();

        if ($drugData) {
            $drugName = strtolower(str_replace(' ', '_', $drugData['name']));
            $amount = $drugData['amount'];
            $strength = $drugData['strength'];

            $conn->query("CREATE TABLE IF NOT EXISTS `$drugName` (
                lot_number VARCHAR(10) PRIMARY KEY,
                expiration_date VARCHAR(10),
                amount DECIMAL(6,2),
                strength INT NOT NULL
            )");

            $insertSQL = "INSERT INTO `$drugName` (lot_number, expiration_date, amount, strength)
                          VALUES (?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)";
            $insertStmt = $conn->prepare($insertSQL);
            $insertStmt->bind_param("ssdi", $lotNumber, $expirationFormatted, $amount, $strength);

            if ($insertStmt->execute()) {
                $successMessage = "Drug added to inventory.";
            } else {
                $errorMessage = "Error adding drug.";
            }
        } else {
            $errorMessage = "Invalid drug ID.";
        }
    } else {
        $errorMessage = "Invalid QR format. Must be 16 digits.";
    }
}

// Handle QR code recall
if (isset($_POST['recall_qr'])) {
    $qrCode = trim($_POST['recall_code']);
    if (preg_match('/^\d{16}$/', $qrCode)) {
        $drugID = substr($qrCode, 0, 4);
        $lotNumber = substr($qrCode, 4, 4);

        $getDrug = $conn->prepare("SELECT name FROM drugs WHERE id = ?");
        $getDrug->bind_param("s", $drugID);
        $getDrug->execute();
        $result = $getDrug->get_result();
        $drug = $result->fetch_assoc();

        if ($drug) {
            $tableName = strtolower(str_replace(' ', '_', $drug['name']));
            $check = $conn->prepare("SELECT * FROM `$tableName` WHERE lot_number = ?");
            $check->bind_param("s", $lotNumber);
            $check->execute();
            $checkResult = $check->get_result();
            if ($checkResult->num_rows > 0) {
                $delete = $conn->prepare("DELETE FROM `$tableName` WHERE lot_number = ?");
                $delete->bind_param("s", $lotNumber);
                $delete->execute();
                $successMessage = "Lot $lotNumber of {$drug['name']} has been recalled and removed.";
            } else {
                $successMessage = "Nothing to be recalled. Lot not found.";
            }
        } else {
            $errorMessage = "Invalid drug ID.";
        }
    } else {
        $errorMessage = "Invalid QR format. Must be 16 digits.";
    }
}

// Fetch inventory for display
$inventoryQuery = $conn->query("SELECT id, name FROM drugs");
while ($row = $inventoryQuery->fetch_assoc()) {
    $tableName = strtolower(str_replace(' ', '_', $row['name']));
    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($checkTable->num_rows > 0) {
        $result = $conn->query("SELECT * FROM `$tableName`");
        while ($data = $result->fetch_assoc()) {
            $inventoryData[] = [
                'drug_name' => $row['name'],
                'lot_number' => $data['lot_number'],
                'expiration_date' => $data['expiration_date'],
                'amount' => $data['amount'],
                'strength' => $data['strength']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; background: #f4f4f4; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 8px; width: 100%; max-width: 600px; }
        input, button { width: 100%; padding: 10px; margin: 8px 0; border-radius: 4px; border: 1px solid #ccc; }
        button { background: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #007bff; color: white; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
<div class="container">
    <h2>Inventory Management</h2>

    <?php if ($successMessage) echo "<p class='success'>$successMessage</p>"; ?>
    <?php if ($errorMessage) echo "<p class='error'>$errorMessage</p>"; ?>

    <form method="POST">
        <input type="text" name="qr_code" placeholder="Enter 16-digit QR Code" required>
        <button type="submit" name="submit_qr">Add to Inventory</button>
    </form>

    <form method="POST">
        <input type="text" name="recall_code" placeholder="Enter QR Code to Recall" required>
        <button type="submit" name="recall_qr">Recall Lot</button>
    </form>

    <h3>Current Inventory</h3>
    <table>
        <tr>
            <th>Drug</th>
            <th>Lot #</th>
            <th>Expiration</th>
            <th>Amount</th>
            <th>Strength (mg)</th>
        </tr>
        <?php foreach ($inventoryData as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['drug_name']); ?></td>
                <td><?php echo htmlspecialchars($item['lot_number']); ?></td>
                <td><?php echo htmlspecialchars($item['expiration_date']); ?></td>
                <td><?php echo htmlspecialchars($item['amount']); ?></td>
                <td><?php echo htmlspecialchars($item['strength']); ?> mg</td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p><a href="workers.php">Back to Dashboard</a></p>
</div>
</body>
</html>