<?php

class Comment {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($imageId, $userId, $content) {
        $stmt = $this->db->prepare("INSERT INTO comments (image_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$imageId, $userId, $content]);
        return $this->db->lastInsertId();
    }

    public function getByImage($imageId) {
        $stmt = $this->db->prepare("
            SELECT comments.*, users.username 
            FROM comments 
            JOIN users ON comments.user_id = users.id 
            WHERE comments.image_id = ? 
            ORDER BY comments.created_at ASC
        ");
        $stmt->execute([$imageId]);
        return $stmt->fetchAll();
    }
}