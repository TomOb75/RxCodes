<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "worker") {
    header("Location: login.php");
    exit();
}
echo "<h1>Welcome, Pharmacy Worker!</h1>";
?>
