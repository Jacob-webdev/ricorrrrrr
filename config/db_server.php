<?php
// Database connection configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'tonowpfr_user');
define('DB_PASS', "cnui3F2q9uc-:m2co2'cn71lcDn16Acl1nC1jl2V4d-2.C_:mG12jcP9.kn1vl");
define('DB_NAME', 'tonowpfr_ricordella');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to UTF-8
$conn->set_charset("utf8mb4");

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>