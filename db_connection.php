<?php
require_once 'config_secrets.php'; // Add this line

$db_host = DB_HOST; // Use the constant from config_secrets.php
$db_user = DB_USER; // Use the constant from config_secrets.php
$db_pass = DB_PASS; // Use the constant from config_secrets.php
$db_name = DB_NAME; // Use the constant from config_secrets.php

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

if (!$conn->set_charset("utf8mb4")) {

}

?>