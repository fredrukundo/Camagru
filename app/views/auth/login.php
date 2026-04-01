<div class="form-container">
    <h2>Login</h2>
    <form method="POST" action="/login">
        <?= Csrf::getTokenField() ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn">Login</button>
    </form>
    <p class="form-link"><a href="/forgot-password">Forgot your password?</a></p>
    <p class="form-link">Don't have an account? <a href="/register">Register</a></p>
</div>