<?php

/**
 * Database Connection Class
 */

/**
 * Purpose:
    * Provides a single point of access to the database connection
    * Loads configuration from config/database.php
    * Handles connection errors gracefully
    * Prevents multiple connections to the database
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Load database configuration
        $config = require __DIR__ . '/../config/database.php';

        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                $config['host'],
                $config['database'],
                $config['charset']
            );

            $this->pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );

        } catch (PDOException $e) {
            // Log error (don't expose to user)
            error_log('Database connection failed: ' . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }

    /**
     * Get singleton instance
     * 
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     * 
     * @return PDO
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}