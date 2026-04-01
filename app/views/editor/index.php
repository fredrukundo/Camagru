<div class="editor-layout">
    <div class="editor-main">
        <h2>Photo Editor</h2>

        <!-- Webcam Section -->
        <div class="webcam-container" id="webcam-container">
            <video id="webcam" autoplay playsinline></video>
            <canvas id="canvas" style="display:none;"></canvas>
            <img id="overlay-preview" class="overlay-preview"
                 src="" alt="" style="display:none;">
            <p id="webcam-error" style="display:none; color:#fff;
               text-align:center; padding:40px;">
                Webcam not available. Use the upload option below.
            </p>
        </div>

        <!-- Upload preview (shown when user picks a file) -->
        <div class="webcam-container" id="upload-preview-container"
             style="display:none;">
            <img id="upload-preview-img" src="" alt="Preview"
                 style="width:100%; display:block;">
            <img id="upload-overlay-preview" class="overlay-preview"
                 src="" alt="" style="display:none;">
        </div>

        <!-- Overlays -->
        <h3>Select an Overlay</h3>
        <div class="overlays-list" id="overlays-list">
            <?php if (empty($overlays)): ?>
                <p>No overlays found.</p>
            <?php else: ?>
                <?php foreach ($overlays as $overlay): ?>
                    <img src="/overlays/<?= htmlspecialchars($overlay) ?>"
                         class="overlay-thumb"
                         data-overlay="<?= htmlspecialchars($overlay) ?>"
                         alt="Overlay">
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Capture Button -->
        <button id="capture-btn" class="btn" disabled>
            📸 Take Photo
        </button>

        <hr style="margin: 20px 0;">

        <!-- Upload fallback -->
        <h3>Or Upload an Image</h3>
        <form method="POST" action="/upload"
              enctype="multipart/form-data" id="upload-form">
            <?= Csrf::getTokenField() ?>
            <input type="hidden" name="overlay"
                   id="upload-overlay" value="">
            <div class="form-group">
                <input type="file" name="user_image" id="user-image-input"
                       accept="image/jpeg,image/png,image/gif">
            </div>
            <button type="submit" class="btn" id="upload-btn" disabled>
                Upload &amp; Merge
            </button>
            <p id="upload-hint" style="margin-top:8px; font-size:0.85rem;
               color:#888;">
                Select an overlay above and choose a file to enable upload.
            </p>
        </form>
    </div>

    <div class="editor-sidebar">
        <h3>My Photos</h3>
        <?php if (empty($userImages)): ?>
            <p style="color:#888; font-size:0.9rem;">
                No photos yet. Take one!
            </p>
        <?php else: ?>
            <div class="thumbnails-grid">
                <?php foreach ($userImages as $img): ?>
                    <div class="thumbnail-item">
                        <img src="/uploads/<?= htmlspecialchars($img['filename']) ?>"
                             alt="Photo"
                             onerror="this.style.display='none';">
                        <form method="POST" action="/delete-image"
                              style="display:inline;">
                            <?= Csrf::getTokenField() ?>
                            <input type="hidden" name="image_id"
                                   value="<?= intval($img['id']) ?>">
                            <button type="submit" class="delete-btn"
                                    onclick="return confirm('Delete this image?')">
                                ✕
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden CSRF token for JS -->
<input type="hidden" id="csrf-token"
       value="<?= htmlspecialchars(Csrf::generateToken()) ?>">