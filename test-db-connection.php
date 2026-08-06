<?php
/**
 * Database Connection Test Script
 * Run this to verify your database configuration
 */

echo "========================================\n";
echo "Database Connection Test\n";
echo "========================================\n\n";

// Load Laravel environment
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get database config
$host = env('DB_HOST', '127.0.0.1');
$port = env('DB_PORT', '3306');
$database = env('DB_DATABASE', '');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');

echo "Configuration:\n";
echo "  Host: $host\n";
echo "  Port: $port\n";
echo "  Database: $database\n";
echo "  Username: $username\n";
echo "  Password: " . (empty($password) ? '(empty)' : '***') . "\n\n";

// Test connection
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✓ SUCCESS: Database connection successful!\n\n";
    
    // Test query
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch();
    echo "MySQL Version: " . $version['version'] . "\n";
    
    // Check if tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . count($tables) . "\n";
    
    if (count($tables) > 0) {
        echo "\nFirst 10 tables:\n";
        foreach (array_slice($tables, 0, 10) as $table) {
            echo "  - $table\n";
        }
    }
    
} catch (PDOException $e) {
    echo "✗ ERROR: Database connection failed!\n\n";
    echo "Error Message: " . $e->getMessage() . "\n\n";
    
    echo "Troubleshooting:\n";
    echo "1. Make sure MySQL is running in XAMPP Control Panel\n";
    echo "2. Verify the database '$database' exists\n";
    echo "3. Check if username '$username' has access to the database\n";
    echo "4. If MySQL has a password, update DB_PASSWORD in .env file\n";
    echo "5. Try connecting with phpMyAdmin to verify credentials\n";
}

echo "\n========================================\n";



