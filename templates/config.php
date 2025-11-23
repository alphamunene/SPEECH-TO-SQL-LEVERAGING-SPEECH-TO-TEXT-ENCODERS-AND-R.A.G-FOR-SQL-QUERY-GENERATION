<?php
// --- MySQL connection ---
$mysql = new mysqli("localhost", "root", "", "speech2sql");

// Check connection
if ($mysql->connect_error) {
    die("MySQL Connection failed: " . $mysql->connect_error);
}

// Optional: set charset
$mysql->set_charset("utf8mb4");

// --- SQLite connection (for logs, optional) ---
$sqlite = new SQLite3('logs.db');

// Create logs table if not exists
$sqlite->exec("CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT,
    email TEXT,
    timestamp TEXT
)");

// Logging function for SQLite
function logAction($sqlite, $action, $email) {
    if (!$sqlite) return;
    $stmt = $sqlite->prepare("
        INSERT INTO logs (action, email, timestamp)
        VALUES (:action, :email, datetime('now', 'localtime'))
    ");
    $stmt->bindValue(':action', $action, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->execute();
}

// Optional: combined logging with details
function log_action($action, $email, $details = null) {
    global $sqlite;

    $stmt = $sqlite->prepare("INSERT INTO logs (action, email, timestamp, details) 
                              VALUES (:action, :email, datetime('now'), :details)");
    $stmt->bindValue(':action', $action, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':details', $details, SQLITE3_TEXT);
    $stmt->execute();
}
?>
