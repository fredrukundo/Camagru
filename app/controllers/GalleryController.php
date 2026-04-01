<?php

class GalleryController {

    public function index() {
        $page    = max(1, intval($_GET['page'] ?? 1));
        $perPage = 5;

        $imageModel   = new ImageModel();
        $commentModel = new Comment();
        $likeModel    = new Like();

        $images      = $imageModel->getAllPaginated($page, $perPage);
        $totalImages = $imageModel->getTotalCount();
        $totalPages  = ceil($totalImages / $perPage);

        foreach ($images as &$image) {
            $image['comments']   = $commentModel->getByImage($image['id']);
            $image['like_count'] = $likeModel->getCount($image['id']);
            $image['user_liked'] = Session::isLoggedIn()
                ? $likeModel->hasLiked($image['id'], Session::getUserId())
                : false;
        }
        unset($image);

        $pageTitle = 'Gallery';
        require __DIR__ . '/../views/layout/header.php';
        require __DIR__ . '/../views/gallery/index.php';
        require __DIR__ . '/../views/layout/footer.php';
    }

    public function addComment() {
        // ALWAYS return JSON
        header('Content-Type: application/json');

        try {
            if (!Session::isLoggedIn()) {
                http_response_code(401);
                echo json_encode(['error' => 'Not authenticated']);
                return;
            }

            if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                return;
            }

            $imageId = intval($_POST['image_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');

            if ($imageId <= 0) {
                echo json_encode(['error' => 'Invalid image']);
                return;
            }

            if (empty($content)) {
                echo json_encode(['error' => 'Comment cannot be empty']);
                return;
            }

            // Sanitize
            $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            if (strlen($content) > 500) {
                $content = substr($content, 0, 500);
            }

            // Save comment
            $commentModel = new Comment();
            $commentId = $commentModel->create(
                $imageId,
                Session::getUserId(),
                $content
            );

            // Email notification to image author
            $imageModel = new ImageModel();
            $image = $imageModel->findById($imageId);

            if ($image) {
                $userModel = new User();
                $author = $userModel->findById($image['user_id']);

                if ($author
                    && $author['notify_comments']
                    && $author['id'] != Session::getUserId()) {
                    $commenterName = htmlspecialchars(
                        Session::get('username')
                    );
                    $body = "<p><strong>" . $commenterName
                          . "</strong> commented on your image:</p>"
                          . "<blockquote>" . $content . "</blockquote>"
                          . "<p><a href=\"" . getenv('APP_URL')
                          . "/#image-" . $imageId
                          . "\">View image</a></p>";
                    Mailer::send(
                        $author['email'],
                        'New comment on your Camagru image',
                        $body
                    );
                }
            }

            // Return JSON success
            echo json_encode([
                'success'    => true,
                'comment_id' => $commentId,
                'username'   => Session::get('username'),
                'content'    => $content,
                'image_id'   => $imageId,
            ]);

        } catch (Exception $e) {
            error_log('Comment error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Server error']);
        }
    }

    public function toggleLike() {
        header('Content-Type: application/json');

        try {
            if (!Session::isLoggedIn()) {
                http_response_code(401);
                echo json_encode(['error' => 'Not authenticated']);
                return;
            }

            if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                return;
            }

            $imageId = intval($_POST['image_id'] ?? 0);

            if ($imageId <= 0) {
                echo json_encode(['error' => 'Invalid image']);
                return;
            }

            $likeModel = new Like();
            $liked = $likeModel->toggle($imageId, Session::getUserId());
            $count = $likeModel->getCount($imageId);

            echo json_encode([
                'success' => true,
                'liked'   => $liked,
                'count'   => $count,
            ]);

        } catch (Exception $e) {
            error_log('Like error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Server error']);
        }
    }

    public function getUpdates() {
        header('Content-Type: application/json');

        try {
            // Get image IDs from query string
            $imageIds = $_GET['ids'] ?? '';
            if (empty($imageIds)) {
                echo json_encode(['images' => []]);
                return;
            }

            // Parse and sanitize IDs
            $ids = array_map('intval', explode(',', $imageIds));
            $ids = array_filter($ids, function ($id) { return $id > 0; });

            if (empty($ids)) {
                echo json_encode(['images' => []]);
                return;
            }

            $commentModel = new Comment();
            $likeModel    = new Like();
            $result       = [];

            foreach ($ids as $id) {
                $comments = $commentModel->getByImage($id);

                // Format comments for JSON
                $formattedComments = [];
                foreach ($comments as $c) {
                    $formattedComments[] = [
                        'id'       => $c['id'],
                        'username' => $c['username'],
                        'content'  => $c['content'],
                    ];
                }

                $result[] = [
                    'id'          => $id,
                    'like_count'  => $likeModel->getCount($id),
                    'user_liked'  => Session::isLoggedIn()
                        ? $likeModel->hasLiked($id, Session::getUserId())
                        : false,
                    'comments'    => $formattedComments,
                    'comment_count' => count($formattedComments),
                ];
            }

            echo json_encode([
                'success'   => true,
                'images'    => $result,
                'timestamp' => time(),
            ]);

        } catch (Exception $e) {
            error_log('Updates error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Server error']);
        }
    }

    public function getNewPosts() {
        header('Content-Type: application/json');

        try {
            $afterId = max(0, intval($_GET['after'] ?? 0));

            $db = Database::getInstance()->getConnection();

            $stmt = $db->prepare("
                SELECT images.*, users.username
                FROM images
                JOIN users ON images.user_id = users.id
                WHERE images.id > ?
                ORDER BY images.id DESC
                LIMIT 10
            ");
            $stmt->execute([$afterId]);
            $images = $stmt->fetchAll();

            $result = [];
            foreach ($images as $img) {
                $result[] = [
                    'id'       => $img['id'],
                    'username' => $img['username'],
                    'filename' => $img['filename'],
                ];
            }

            echo json_encode([
                'success'   => true,
                'images'    => $result,
                'logged_in' => Session::isLoggedIn(),
            ]);

        } catch (Exception $e) {
            error_log('New posts error: ' . $e->getMessage());
            echo json_encode(['success' => false]);
        }
    }
}