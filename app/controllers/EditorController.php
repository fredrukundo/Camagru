<?php

class EditorController {

    public function index() {
        if (!Session::isLoggedIn()) {
            Session::setFlash('error', 'Please log in to access the editor.');
            header('Location: /login');
            exit;
        }

        $imageModel = new ImageModel();
        $userImages = $imageModel->getByUser(Session::getUserId());

        // Get overlays from public/overlays/
        $overlayDir = __DIR__ . '/../../public/overlays/';
        $overlays = [];
        if (is_dir($overlayDir)) {
            $files = scandir($overlayDir);
            foreach ($files as $file) {
                if (preg_match('/\.png$/i', $file)) {
                    $overlays[] = $file;
                }
            }
        }

        $pageTitle = 'Editor';
        require __DIR__ . '/../views/layout/header.php';
        require __DIR__ . '/../views/editor/index.php';
        require __DIR__ . '/../views/layout/footer.php';
    }

    public function capture() {
        // Always return JSON
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

            $imageData = $_POST['image_data'] ?? '';
            $overlay   = $_POST['overlay'] ?? '';

            if (empty($imageData)) {
                echo json_encode(['error' => 'No image data received']);
                return;
            }

            if (empty($overlay)) {
                echo json_encode(['error' => 'No overlay selected']);
                return;
            }

            // Validate overlay exists
            $overlayPath = __DIR__ . '/../../public/overlays/' . basename($overlay);
            if (!file_exists($overlayPath)) {
                echo json_encode(['error' => 'Overlay not found: ' . basename($overlay)]);
                return;
            }

            // Check uploads directory
            $uploadsDir = __DIR__ . '/../../public/uploads/';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            if (!is_writable($uploadsDir)) {
                echo json_encode(['error' => 'Uploads directory not writable']);
                return;
            }

            // Decode base64 image
            // Handle both "data:image/png;base64,xxxx" and raw base64
            if (strpos($imageData, ',') !== false) {
                $parts = explode(',', $imageData, 2);
                $imageData = $parts[1];
            }
            $imageData = str_replace(' ', '+', $imageData);
            $decodedImage = base64_decode($imageData, true);

            if ($decodedImage === false || strlen($decodedImage) < 100) {
                echo json_encode(['error' => 'Invalid base64 image data']);
                return;
            }

            // Create GD image from webcam data
            $webcamImage = @imagecreatefromstring($decodedImage);
            if ($webcamImage === false) {
                echo json_encode(['error' => 'Could not create image from data']);
                return;
            }

            // Load overlay PNG
            $overlayImage = @imagecreatefrompng($overlayPath);
            if ($overlayImage === false) {
                imagedestroy($webcamImage);
                echo json_encode(['error' => 'Could not load overlay PNG']);
                return;
            }

            // Get dimensions
            $ww = imagesx($webcamImage);
            $wh = imagesy($webcamImage);
            $ow = imagesx($overlayImage);
            $oh = imagesy($overlayImage);

            // Create a properly sized overlay with alpha
            $resizedOverlay = imagecreatetruecolor($ww, $wh);
            // Enable alpha blending
            imagealphablending($resizedOverlay, false);
            imagesavealpha($resizedOverlay, true);
            // Fill with transparent
            $transparent = imagecolorallocatealpha($resizedOverlay, 0, 0, 0, 127);
            imagefilledrectangle($resizedOverlay, 0, 0, $ww, $wh, $transparent);
            // Copy overlay resized
            imagecopyresampled(
                $resizedOverlay, $overlayImage,
                0, 0, 0, 0,
                $ww, $wh, $ow, $oh
            );

            // Merge onto webcam image
            imagealphablending($webcamImage, true);
            imagesavealpha($webcamImage, true);
            imagecopy($webcamImage, $resizedOverlay, 0, 0, 0, 0, $ww, $wh);

            // Save final image
            $filename = 'img_' . uniqid() . '_' . time() . '.png';
            $savePath = $uploadsDir . $filename;

            $saved = imagepng($webcamImage, $savePath);

            // Cleanup GD
            imagedestroy($webcamImage);
            imagedestroy($overlayImage);
            imagedestroy($resizedOverlay);

            if (!$saved) {
                echo json_encode(['error' => 'Failed to save image file']);
                return;
            }

            // Verify file was actually created
            if (!file_exists($savePath) || filesize($savePath) < 100) {
                echo json_encode(['error' => 'Image file was not created properly']);
                return;
            }

            // Save to database
            $imageModel = new ImageModel();
            $imageId = $imageModel->create(Session::getUserId(), $filename);

            echo json_encode([
                'success' => true,
                'image' => [
                    'id' => $imageId,
                    'filename' => $filename,
                ]
            ]);

        } catch (Exception $e) {
            error_log('Capture error: ' . $e->getMessage());
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
    }

    public function upload() {
        if (!Session::isLoggedIn()) {
            Session::setFlash('error', 'Not authenticated.');
            header('Location: /login');
            exit;
        }

        if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'Invalid CSRF token.');
            header('Location: /editor');
            exit;
        }

        $overlay = $_POST['overlay'] ?? '';

        if (empty($overlay)) {
            Session::setFlash('error', 'Please select an overlay.');
            header('Location: /editor');
            exit;
        }

        $overlayPath = __DIR__ . '/../../public/overlays/' . basename($overlay);
        if (!file_exists($overlayPath)) {
            Session::setFlash('error', 'Invalid overlay.');
            header('Location: /editor');
            exit;
        }

        // Check file upload
        if (!isset($_FILES['user_image']) ||
            $_FILES['user_image']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'File too large (server limit).',
                UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit).',
                UPLOAD_ERR_PARTIAL    => 'File only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk.',
            ];
            $errCode = $_FILES['user_image']['error'] ?? UPLOAD_ERR_NO_FILE;
            $errMsg = $uploadErrors[$errCode] ?? 'Unknown upload error.';
            Session::setFlash('error', $errMsg);
            header('Location: /editor');
            exit;
        }

        $file = $_FILES['user_image'];

        // Validate MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowedTypes = [
            'image/jpeg' => 'jpeg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
        ];

        if (!isset($allowedTypes[$mimeType])) {
            Session::setFlash('error', 'Only JPEG, PNG, and GIF images are allowed. Got: ' . $mimeType);
            header('Location: /editor');
            exit;
        }

        // Max 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            Session::setFlash('error', 'Image must be less than 5MB.');
            header('Location: /editor');
            exit;
        }

        // Create GD image
        switch ($mimeType) {
            case 'image/jpeg':
                $uploadedImage = @imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $uploadedImage = @imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/gif':
                $uploadedImage = @imagecreatefromgif($file['tmp_name']);
                break;
            default:
                $uploadedImage = false;
        }

        if (!$uploadedImage) {
            Session::setFlash('error', 'Could not process image. File may be corrupt.');
            header('Location: /editor');
            exit;
        }

        // Load overlay
        $overlayImage = @imagecreatefrompng($overlayPath);
        if (!$overlayImage) {
            imagedestroy($uploadedImage);
            Session::setFlash('error', 'Could not load overlay.');
            header('Location: /editor');
            exit;
        }

        $iw = imagesx($uploadedImage);
        $ih = imagesy($uploadedImage);
        $ow = imagesx($overlayImage);
        $oh = imagesy($overlayImage);

        // Create resized overlay
        $resizedOverlay = imagecreatetruecolor($iw, $ih);
        imagealphablending($resizedOverlay, false);
        imagesavealpha($resizedOverlay, true);
        $transparent = imagecolorallocatealpha($resizedOverlay, 0, 0, 0, 127);
        imagefilledrectangle($resizedOverlay, 0, 0, $iw, $ih, $transparent);
        imagecopyresampled(
            $resizedOverlay, $overlayImage,
            0, 0, 0, 0,
            $iw, $ih, $ow, $oh
        );

        // Merge
        imagealphablending($uploadedImage, true);
        imagesavealpha($uploadedImage, true);
        imagecopy($uploadedImage, $resizedOverlay, 0, 0, 0, 0, $iw, $ih);

        // Save
        $uploadsDir = __DIR__ . '/../../public/uploads/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $filename = 'img_' . uniqid() . '_' . time() . '.png';
        $savePath = $uploadsDir . $filename;
        imagepng($uploadedImage, $savePath);

        imagedestroy($uploadedImage);
        imagedestroy($overlayImage);
        imagedestroy($resizedOverlay);

        if (!file_exists($savePath)) {
            Session::setFlash('error', 'Failed to save image.');
            header('Location: /editor');
            exit;
        }

        $imageModel = new ImageModel();
        $imageModel->create(Session::getUserId(), $filename);

        Session::setFlash('success', 'Image created successfully!');
        header('Location: /editor');
        exit;
    }

    public function deleteImage() {
        if (!Session::isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'Invalid CSRF token.');
            header('Location: /editor');
            exit;
        }

        $imageId = intval($_POST['image_id'] ?? 0);

        $imageModel = new ImageModel();
        if ($imageModel->delete($imageId, Session::getUserId())) {
            Session::setFlash('success', 'Image deleted.');
        } else {
            Session::setFlash('error', 'Could not delete image.');
        }

        header('Location: /editor');
        exit;
    }
}