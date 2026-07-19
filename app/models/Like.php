<?php

/*
    Like Model
    Handles like toggling and retrieval for images.
*/

class Like {
    private $db;

    public function __construct() {
        // Initialize database connection
        $this->db = Database::getInstance()->getConnection();
    }

    public function toggle($imageId, $userId) {
        // Check if like exists
        $stmt = $this->db->prepare("SELECT id FROM likes WHERE image_id = ? AND user_id = ?");
        $stmt->execute([$imageId, $userId]);

        if ($stmt->fetch()) {
            // Unlike
            $stmt = $this->db->prepare("DELETE FROM likes WHERE image_id = ? AND user_id = ?");
            $stmt->execute([$imageId, $userId]);
            return false; // unliked
        } else {
            // Like
            $stmt = $this->db->prepare("INSERT INTO likes (image_id, user_id) VALUES (?, ?)");
            $stmt->execute([$imageId, $userId]);
            return true; // liked
        }
    }

    public function getCount($imageId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM likes WHERE image_id = ?");
        $stmt->execute([$imageId]);
        return $stmt->fetchColumn();
    }

    public function hasLiked($imageId, $userId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM likes WHERE image_id = ? AND user_id = ?");
        $stmt->execute([$imageId, $userId]);
        return $stmt->fetchColumn() > 0;
    }
}