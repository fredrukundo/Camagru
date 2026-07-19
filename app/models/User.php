<?php


/*
    User Model
    Handles user authentication, account management,
    email verification, password reset, and profile updates.
*/


class User {
    private $db;

    public function __construct() {

        // Initialize database connection
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($username, $email, $password) {

        // create a new user with hashed password and verification token
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $token = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare("INSERT INTO users (username, email, password, verification_token) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hash, $token]);

        return $token;
    }

    public function findByUsername($username) {

    // find a user by username
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    public function findByEmail($email) {

    // find a user by email
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function findById($id) {

    // find a user by id
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function verify($token) {

    // verify a user's email using the verification token
        $stmt = $this->db->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE verification_token = ?");
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }

    public function setResetToken($email, $token, $expiry) {

    // set/save a password reset token and its expiry for a user
        $stmt = $this->db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
        $stmt->execute([$token, $expiry, $email]);
        return $stmt->rowCount() > 0;
    }

    public function findByResetToken($token) {

    // find a user by reset token and ensure the token is still valid (not expired)
        $stmt = $this->db->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    public function updatePassword($userId, $password) {

    // update a user's password and clear the reset token and its expiry
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        $stmt->execute([$hash, $userId]);
    }

    public function updateUsername($userId, $username) {

    // update a user's username
        $stmt = $this->db->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$username, $userId]);
    }

    public function updateEmail($userId, $email) {

    // update a user's email
        $stmt = $this->db->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$email, $userId]);
    }

    public function updateNotifyComments($userId, $value) {

    // update a user's notification preference for comments
        $stmt = $this->db->prepare("UPDATE users SET notify_comments = ? WHERE id = ?");
        $stmt->execute([$value, $userId]);
    }

    public function usernameExists($username, $excludeId = null) {

    // check if a username already exists in the database, optionally excluding a specific user ID 
        if ($excludeId) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $excludeId]);
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
        }
        return $stmt->fetchColumn() > 0;
    }

    public function emailExists($email, $excludeId = null) {
    // check if an email already exists in the database, optionally excluding a specific user ID
        if ($excludeId) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
        }
        return $stmt->fetchColumn() > 0;
    }
}