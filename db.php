<?php
// db.php – SQLite database connection

date_default_timezone_set('Asia/Bahrain'); // Consistent system time

$dsn = 'sqlite:' . __DIR__ . '/enginguard.db';

try {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Error handling
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Array results
    $pdo->exec("PRAGMA foreign_keys = ON"); // Enforce relations
    $pdo->exec("PRAGMA busy_timeout = 5000"); // Prevent lock issues
} catch (PDOException $e) {
    die("Database connection failed");
}
