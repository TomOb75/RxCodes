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

// Ensure only workers can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header("Location: index.php");
    exit();
}

$successMessage = "";
$errorMessage = "";
$inventoryData = [];

// Handle QR code input
if (isset($_POST['submit_qr'])) {
    $qrCode = trim($_POST['qr_code']);

    if (preg_match('/^\d{16}$/', $qrCode)) { // Validate 16-digit QR format
        $drugID = substr($qrCode, 0, 4);
        $lotNumber = substr($qrCode, 4, 4);
        $expirationDate = substr($qrCode, 8, 8); // MMDDYYYY format

        // Convert MMDDYYYY to MM/DD/YYYY format
        $expirationFormatted = substr($expirationDate, 0, 2) . "/" . substr($expirationDate, 2, 2) . "/" . substr($expirationDate, 4, 4);

        // Find drug name, amount, and strength using drugID
        $drugQuery = $conn->prepare("SELECT name, amount, strength FROM drugs WHERE id = ?");
        $drugQuery->bind_param("s", $drugID);
        $drugQuery->execute();
        $drugResult = $drugQuery->get_result();
        $drugData = $drugResult->fetch_assoc();

        if ($drugData) {
            $drugName = strtolower(str_replace(' ', '_', $drugData['name'])); // Convert to safe table name
            $amount = $drugData['amount'];
            $strength = $drugData['strength'];

            // Create table if it doesn't exist
            $createTableSQL = "CREATE TABLE IF NOT EXISTS `$drugName` (
                lot_number VARCHAR(10) PRIMARY KEY,
                expiration_date VARCHAR(10),
                amount DECIMAL(6,2),
                strength INT NOT NULL
            )";
            $conn->query($createTableSQL);

            // Insert or update the drug lot in inventory
            $insertSQL = "INSERT INTO `$drugName` (lot_number, expiration_date, amount, strength) 
                          VALUES (?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)";
            $insertStmt = $conn->prepare($insertSQL);
            $insertStmt->bind_param("ssii", $lotNumber, $expirationFormatted, $amount, $strength);

            if ($insertStmt->execute()) {
                $successMessage = "Drug added to inventory!";
            } else {
                $errorMessage = "Error adding drug.";
            }

        } else {
            $errorMessage = "Invalid drug ID.";
        }
    } else {
        $errorMessage = "Invalid QR format. Enter 16 digits.";
    }
}

// Fetch inventory for display
$inventoryQuery = $conn->query("SELECT id, name FROM drugs");
while ($row = $inventoryQuery->fetch_assoc()) {
    $tableName = strtolower(str_replace(' ', '_', $row['name']));
    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");

    if ($checkTable->num_rows > 0) {  // Only fetch if table exists
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
            width: 500px;
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
        table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #007bff;
            color: white;
        }
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