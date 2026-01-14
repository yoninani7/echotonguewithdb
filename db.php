<?php
// db_connect.php

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'echotongue';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for emoji support
$conn->set_charset("utf8mb4");
 
?>