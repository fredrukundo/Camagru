<div class="form-container">
    <h2>Forgot Password</h2>
    <form method="POST" action="/forgot-password">
        <?= Csrf::getTokenField() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <button type="submit" class="btn">Send Reset Link</button>
    </form>
    <p class="form-link"><a href="/login">Back to Login</a></p>
</div>