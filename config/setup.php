<?php

/**
 * Database Setup Script
 * 
 * Run this script to initialize the database:
 * php config/setup.php
 */

/**
 * Purpose:
 * Standalone setup script — can be run outside Docker
 * Creates database if it doesn't exist
 * Executes init.sql to create tables
 * Verifies all tables were created
 * Provides clear feedback and error messages
 */

/**
 * How to Use:
 * From local machine :
 *   php config/setup.php
 * From Docker container:
 *  docker-compose exec php php /var/www/html/config/setup.php
 */

// Load database configuration
$dbConfig = require __DIR__ . '/database.php';

echo "===========================================\n";
echo "  Camagru Database Setup\n";
echo "===========================================\n\n";

try {
    // Connect to MySQL (without selecting database first)
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );

    echo "✓ Connected to MySQL server\n";

    // Create database if it doesn't exist
    $dbName = $dbConfig['database'];
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '{$dbName}' ready\n";

    // Select the database
    $pdo->exec("USE `{$dbName}`");

    // Read SQL schema file
    $sqlFile = __DIR__ . '/init.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL schema file not found: {$sqlFile}");
    }

    $sql = file_get_contents($sqlFile);
    
    echo "✓ SQL schema loaded\n";

    // Execute schema (create tables)
    $pdo->exec($sql);
    
    echo "✓ Database tables created\n";

    // Check if tables exist
    $tables = ['users', 'images', 'comments', 'likes'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "  → Table '{$table}' exists\n";
        } else {
            echo "  ✗ Table '{$table}' missing\n";
        }
    }

    echo "\n===========================================\n";
    echo "  Database Setup Complete!\n";
    echo "===========================================\n";
    echo "\nYou can now start the application:\n";
    echo "  docker-compose up -d\n\n";

} catch (PDOException $e) {
    echo "\n✗ Database Error: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "  - Database credentials in .env\n";
    echo "  - Database server is running\n";
    echo "  - User has CREATE DATABASE privileges\n\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}