<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "patient") {
    header("Location: login.php");
    exit();
}
echo "<h1>Welcome, Patient!</h1>";
?>
