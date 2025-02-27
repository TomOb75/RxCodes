<?php

$servername = "localhost"; // Change to your database server
$username = "root"; // Change to your database username
$password = ""; // Change to your database password
$database = "team"; // Change to your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query to fetch team members
$sql = "SELECT name FROM team";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h2>Team Members</h2><ul>";
    while($row = $result->fetch_assoc()) {
        echo "<li>" . htmlspecialchars($row["name"]) . "</li>";
    }
    echo "</ul>";
} else {
    echo "No team members found.";
}

// Close connection
$conn->close();

?>
