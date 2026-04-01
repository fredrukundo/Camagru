<h2>Gallery</h2>

<?php if (empty($images)): ?>
    <p>No images yet.
        <?php if (Session::isLoggedIn()): ?>
            <a href="/editor">Create one!</a>
        <?php endif; ?>
    </p>
<?php endif; ?>

<div class="gallery" id="gallery-container">
    <?php foreach ($images as $image): ?>
        <div class="gallery-card" id="image-<?= $image['id'] ?>">
            <div class="gallery-card-header">
                <?= htmlspecialchars($image['username']) ?>
            </div>
            <img src="/uploads/<?= htmlspecialchars($image['filename']) ?>"
                 alt="User image">

            <div class="gallery-card-actions">
                <?php if (Session::isLoggedIn()): ?>
                    <button class="like-btn"
                            data-image-id="<?= $image['id'] ?>">
                        <?= $image['user_liked'] ? '❤️' : '🤍' ?>
                    </button>
                <?php else: ?>
                    <span>❤️</span>
                <?php endif; ?>
                <span class="like-count"
                      id="like-count-<?= $image['id'] ?>">
                    <?= $image['like_count'] ?> like<?= $image['like_count'] != 1 ? 's' : '' ?>
                </span>

                <!-- Share buttons -->
                <div class="share-buttons" style="margin-left:auto; display:flex; gap:8px; align-items:center;">
                    <?php
                    $shareUrl = urlencode(getenv('APP_URL') . '/uploads/' . $image['filename']);
                    $shareText = urlencode('Check out this photo on Camagru!');
                    ?>
                    <a href="https://twitter.com/intent/tweet?url=<?= $shareUrl ?>&text=<?= $shareText ?>"
                       target="_blank" rel="noopener" title="Share on X">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                             viewBox="0 0 512 462.799" fill="#000">
                            <path fill-rule="nonzero" d="M403.229 0h78.506L310.219 196.04 512 462.799H354.002L230.261 301.007 88.669 462.799h-78.56l183.455-209.683L0 0h161.999l111.856 147.88L403.229 0zm-27.556 415.805h43.505L138.363 44.527h-46.68l283.99 371.278z"/>
                        </svg>
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>"
                       target="_blank" rel="noopener" title="Share on Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                             viewBox="0 0 512 512" fill="#1877F2">
                            <path d="M512 256C512 114.6 397.4 0 256 0S0 114.6 0 256c0 120.2 82.7 220.8 194.2 248.5V334.2h-56.6V256h56.6v-33.7c0-105.1 46.4-152.3 142.2-152.3 18.5 0 50.4 3.6 63.5 7.2v70.2c-6.9-.7-18.9-1.1-33.8-1.1-48 0-66.5 18.2-66.5 65.4V256h94.9l-16.3 78.2H299.6v175.5C425.1 488.1 512 381.7 512 256z"/>
                        </svg>
                    </a>
                    <a href="mailto:?subject=<?= $shareText ?>&body=<?= $shareUrl ?>"
                       title="Share via Email">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                             viewBox="0 0 512 512" fill="#666">
                            <path d="M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4L236.8 313.6c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4 0-26.5-21.5-48-48-48H48zM0 176v208c0 35.3 28.7 64 64 64h384c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Comments -->
            <div class="gallery-card-comments"
                 id="comments-<?= $image['id'] ?>">
                <?php foreach ($image['comments'] as $comment): ?>
                    <div class="comment">
                        <strong><?= htmlspecialchars($comment['username']) ?></strong>
                        <?= htmlspecialchars($comment['content']) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- AJAX Comment (NO form tag) -->
            <?php if (Session::isLoggedIn()): ?>
                <div class="comment-box" data-image-id="<?= $image['id'] ?>">
                    <input type="text" class="comment-input"
                        placeholder="Add a comment..."
                        maxlength="500"
                        autocomplete="off">
                    <button type="button" class="comment-post-btn">Post</button>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="/?page=<?= $page - 1 ?>">&laquo; Prev</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="/?page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="/?page=<?= $page + 1 ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- CSRF token for AJAX -->
<?php if (Session::isLoggedIn()): ?>
    <input type="hidden" id="csrf-token"
           value="<?= htmlspecialchars(Csrf::generateToken()) ?>">
<?php endif; ?>