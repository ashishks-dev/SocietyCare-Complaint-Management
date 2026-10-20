<?php
/*
 * SocietyCare - Example Database Configuration
 *
 * To run SocietyCare locally:
 * 1. Copy this file and name the copy database.php
 * 2. Update the database connection details if necessary.
 * 3. Import the provided SQL file into your local database.
 *
 * These settings are intended for local XAMPP development only.
 */

// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$database = "societycare_portfolio_demo";

// Connect to MySQL
try {
    $conn = mysqli_connect(
        $servername,
        $username,
        $password,
        $database
    );
} catch (mysqli_sql_exception $e) {
    error_log(
        "SocietyCare database connection failed: " . $e->getMessage()
    );

    die("Database connection failed. Check your local configuration.");
}