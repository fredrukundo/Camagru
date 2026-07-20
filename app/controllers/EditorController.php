<?php

/**
 * EditorController.php
 *
 * Handles the image editor functionality, including capturing images from the webcam,
 * uploading images, applying overlays/stickers, and saving images to the database.
 */

class EditorController {

    public function index() {
        // Ensure the user is logged in
        if (!Session::isLoggedIn()) {
            Session::setFlash('error', 'Please log in to access the editor.');
            header('Location: /login');
            exit;
        }
        // Fetch user's previously created images
        $imageModel = new ImageModel();
        $userImages = $imageModel->getByUser(Session::getUserId());

        // Get overlays from public/overlays/
        $overlayDir = __DIR__ . '/../../public/overlays/';
        
        $overlays = [];
        if (is_dir($overlayDir)) {
            // Scan for PNG files in the overlays directory
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
            // Handle webcam capture via AJAX
            if (!Session::isLoggedIn()) {
                // User must be logged in to capture images
                http_response_code(401);
                echo json_encode(['error' => 'Not authenticated']);
                return;
            }

            if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
                // Invalid CSRF token
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                return;
            }
            // Validate and process the base64 image data
            $imageData = $_POST['image_data'] ?? '';
            $stickers = json_decode($_POST['stickers'] ?? '[]', true);

            if (!is_array($stickers)) {
                // If stickers is not an array, reset to empty
                $stickers = [];
            }

            if (empty($imageData)) {
                echo json_encode(['error' => 'No image data received']);
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

            // Remove the data URI prefix if present
            if (strpos($imageData, ',') !== false) {
                $parts = explode(',', $imageData, 2);
                $imageData = $parts[1];
            }
            // Replace spaces with plus signs for proper base64 decoding
            $imageData = str_replace(' ', '+', $imageData);
            $decodedImage = base64_decode($imageData, true);

            if ($decodedImage === false || strlen($decodedImage) < 100) {
                echo json_encode(['error' => 'Invalid base64 image data']);
                return;
            }

            // Create a GD image from the decoded data
            $webcamImage = @imagecreatefromstring($decodedImage);
            if ($webcamImage === false) {
                echo json_encode(['error' => 'Could not create image from data']);
                return;
            }
            // Merge every sticker
            foreach ($stickers as $sticker) {
            

                $overlayPath = __DIR__ . '/../../public/overlays/' .
                    basename($sticker['file']);

                if (!file_exists($overlayPath)) {
                    continue;
                }
                // Create a GD image from the overlay
                $overlayImage = @imagecreatefrompng($overlayPath);

                if (!$overlayImage) {
                    continue;
                }
                // Get sticker dimensions and position
                $width  = intval($sticker['width']);
                $height = intval($sticker['height']);

                $x = intval($sticker['x']);
                $y = intval($sticker['y']);
                // Create a true color image for the resized overlay
                $resizedOverlay = imagecreatetruecolor($width, $height);

                imagealphablending($resizedOverlay, false);
                imagesavealpha($resizedOverlay, true);
                
                $transparent = imagecolorallocatealpha(
                    $resizedOverlay,
                    0,
                    0,
                    0,
                    127
                );

                imagefilledrectangle(
                    $resizedOverlay,
                    0,
                    0,
                    $width,
                    $height,
                    $transparent
                );

                imagecopyresampled(
                    $resizedOverlay,
                    $overlayImage,
                    0,
                    0,
                    0,
                    0,
                    $width,
                    $height,
                    imagesx($overlayImage),
                    imagesy($overlayImage)
                );

                imagealphablending($webcamImage, true);

                imagecopy(
                    $webcamImage,
                    $resizedOverlay,
                    $x,
                    $y,
                    0,
                    0,
                    $width,
                    $height
                );

                imagedestroy($overlayImage);
                imagedestroy($resizedOverlay);
            }

            // Save final image
            $filename = 'img_' . uniqid() . '_' . time() . '.png';
            $savePath = $uploadsDir . $filename;

            $saved = imagepng($webcamImage, $savePath);

            // Cleanup GD
            imagedestroy($webcamImage);

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

        $stickers = json_decode($_POST['stickers'] ?? '[]', true);

        if (!is_array($stickers)) {
            $stickers = [];
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

        // Merge every sticker
        foreach ($stickers as $sticker) {

            $overlayPath = __DIR__ . '/../../public/overlays/' .
                basename($sticker['file']);

            if (!file_exists($overlayPath)) {
                continue;
            }

            $overlayImage = @imagecreatefrompng($overlayPath);

            if (!$overlayImage) {
                continue;
            }

            $width  = intval($sticker['width']);
            $height = intval($sticker['height']);

            $x = intval($sticker['x']);
            $y = intval($sticker['y']);

            $resizedOverlay = imagecreatetruecolor($width, $height);

            imagealphablending($resizedOverlay, false);
            imagesavealpha($resizedOverlay, true);

            $transparent = imagecolorallocatealpha(
                $resizedOverlay,
                0,
                0,
                0,
                127
            );

            imagefilledrectangle(
                $resizedOverlay,
                0,
                0,
                $width,
                $height,
                $transparent
            );

            imagecopyresampled(
                $resizedOverlay,
                $overlayImage,
                0,
                0,
                0,
                0,
                $width,
                $height,
                imagesx($overlayImage),
                imagesy($overlayImage)
            );

            imagealphablending($uploadedImage, true);

            imagecopy(
                $uploadedImage,
                $resizedOverlay,
                $x,
                $y,
                0,
                0,
                $width,
                $height
            );

            imagedestroy($overlayImage);
            imagedestroy($resizedOverlay);
        }
        // Save
        $uploadsDir = __DIR__ . '/../../public/uploads/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $filename = 'img_' . uniqid() . '_' . time() . '.png';
        $savePath = $uploadsDir . $filename;
        imagepng($uploadedImage, $savePath);

        imagedestroy($uploadedImage);
        // imagedestroy($overlayImage);
        // imagedestroy($resizedOverlay);

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