<?php
// master_setup.php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'agrosafe_db';

echo "<h1>🛠️ AgroSafeAI Database Fixer</h1>";

try {
    // 1. Connect to MySQL Server (Root)
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    echo "✅ Database `$dbname` is ready.<br>";
    
    // 3. Connect to that Database
    $pdo->exec("USE `$dbname`");

    // 4. Create USERS Table (If missing)
    $sql_users = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql_users);
    echo "✅ Table `users` is ready.<br>";

    // 5. Create HISTORY Table (With ALL new columns)
    // We define the FULL structure here so new installs get everything immediately.
    $sql_history = "CREATE TABLE IF NOT EXISTS history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date DATETIME DEFAULT CURRENT_TIMESTAMP,
        symptom TEXT,
        diagnosis VARCHAR(100),
        roi DECIMAL(10, 2),
        status VARCHAR(20) DEFAULT 'Pending',
        notes TEXT DEFAULT NULL,
        severity INT DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql_history);
    echo "✅ Table `history` is ready.<br>";

    // 6. FORCE UPDATE (For existing tables)
    // If the table already existed from an old version, it might miss columns.
    // We try to add them. If they exist, it fails silently (catch block).
    
    $updates = [
        "ALTER TABLE history ADD status VARCHAR(20) DEFAULT 'Pending'",
        "ALTER TABLE history ADD notes TEXT DEFAULT NULL",
        "ALTER TABLE history ADD severity INT DEFAULT 1",
        // Fix for the symptom column if it was too short (VARCHAR) -> change to TEXT
        "ALTER TABLE history MODIFY symptom TEXT" 
    ];

    foreach ($updates as $sql) {
        try {
            $pdo->exec($sql);
            echo "🔹 Applied update: " . htmlspecialchars($sql) . "<br>";
        } catch (Exception $e) {
            // Ignore error if column already exists
        }
    }

    echo "<hr><h2 style='color:green'>🎉 Repair Complete!</h2>";
    echo "<p>Your database is now fully compatible with the new features.</p>";
    echo "<a href='index.php?page=history' style='font-size:1.2rem; font-weight:bold;'>Go to History Page</a>";

} catch (PDOException $e) {
    die("<h2 style='color:red'>❌ Error: " . $e->getMessage() . "</h2>");
}
?>