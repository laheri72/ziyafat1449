<?php
// Database Configuration

$host = '127.0.0.1';
$username = 'u719177696_ziyafatushukr';
$password = 'Mufaddal25739';
$database = 'u719177696_ZS1449';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");
?>