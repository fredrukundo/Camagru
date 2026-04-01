<div class="form-container">
    <h2>Reset Password</h2>
    <form method="POST" action="/reset-password">
        <?= Csrf::getTokenField() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" id="password" name="password" required minlength="8">
        </div>
        <div class="form-group">
            <label for="password_confirm">Confirm Password</label>
            <input type="password" id="password_confirm" name="password_confirm" required>
        </div>
        <button type="submit" class="btn">Reset Password</button>
    </form>
</div>