<div class="form-container">
    <h2>Settings</h2>
    <form method="POST" action="/settings">
        <?= Csrf::getTokenField() ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   value="<?= htmlspecialchars($user['username']) ?>"
                   pattern="[a-zA-Z0-9_]{3,20}">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($user['email']) ?>">
        </div>
        <div class="form-group">
            <label for="new_password">New Password (leave blank to keep current)</label>
            <input type="password" id="new_password" name="new_password" minlength="8">
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password">
        </div>
        <div class="form-check">
            <input type="checkbox" id="notify_comments" name="notify_comments"
                   <?= $user['notify_comments'] ? 'checked' : '' ?>>
            <label for="notify_comments">Receive email when someone comments on my images</label>
        </div>
        <button type="submit" class="btn">Save Changes</button>
    </form>
</div>