<?php

$servername = "localhost";
$username = "root";
$password = "";
$database = "team";

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
