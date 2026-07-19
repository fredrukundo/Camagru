<?php

/*
    Image Model
    Handles image uploads, retrieval, deletion, and pagination.
*/

class ImageModel {
    private $db;

    public function __construct() {
        // Initialize database connection
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($userId, $filename) {

    // create a new image record in the database
        $stmt = $this->db->prepare("INSERT INTO images (user_id, filename) VALUES (?, ?)");
        $stmt->execute([$userId, $filename]);
        return $this->db->lastInsertId();
    }

    public function findById($id) {

    // find an image by its ID, including the username of the uploader
        $stmt = $this->db->prepare("SELECT images.*, users.username FROM images JOIN users ON images.user_id = users.id WHERE images.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function delete($id, $userId) {
        // Only allow deletion by the owner
        $image = $this->findById($id);
        if ($image && $image['user_id'] == $userId) {
            // Delete file
            $filepath = __DIR__ . '/../../public/uploads/' . $image['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            $stmt = $this->db->prepare("DELETE FROM images WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            return true;
        }
        return false;
    }

    public function getByUser($userId) {

    // get all images uploaded by a specific user, ordered by creation date descending
        $stmt = $this->db->prepare("SELECT * FROM images WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getAllPaginated($page = 1, $perPage = 5) {
    
    // get all images with pagination, including the username of the uploader and like count
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare("
            SELECT images.*, users.username,
                   (SELECT COUNT(*) FROM likes WHERE likes.image_id = images.id) as like_count
            FROM images
            JOIN users ON images.user_id = users.id
            ORDER BY images.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        return $stmt->fetchAll();
    }

    public function getTotalCount() {

    // get the total count of images in the database
        $stmt = $this->db->query("SELECT COUNT(*) FROM images");
        return $stmt->fetchColumn();
    }
}