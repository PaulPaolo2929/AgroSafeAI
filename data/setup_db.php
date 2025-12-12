<?php
// setup_db.php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'agrosafe_db';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");

    // 1. Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. History Table (NEW)
    // This table has a 'user_id' column to link data to specific users
    $pdo->exec("CREATE TABLE IF NOT EXISTS history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date DATETIME DEFAULT CURRENT_TIMESTAMP,
        symptom VARCHAR(100),
        diagnosis VARCHAR(100),
        roi DECIMAL(10, 2),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    echo "✅ Database tables updated! You are ready for multi-user support.";
    echo "<br><a href='index.php'>Go to Dashboard</a>";

} catch (PDOException $e) {
    die("❌ Error: " . $e->getMessage());
}
?>