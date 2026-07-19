<?php

/**
 * Database Configuration
 * 
 * This file centralizes database connection parameters
 * and provides a configured PDO instance.
 * 
 * Centralizes DB configuration
 * Returns an array that can be used by Database class
 * Provides fallback values
 * Makes it easy to change DB settings in one place
 */

return [
    'host'     => getenv('DB_HOST') ?: 'db',
    'database' => getenv('DB_NAME') ?: 'camagru',
    'username' => getenv('DB_USER') ?: 'camagru_user',
    'password' => getenv('DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
    'options'  => [ // tell PDO how i want it to behave
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];