<div class="form-container">
    <h2>Register</h2>
    <form method="POST" action="/register">
        <?= Csrf::getTokenField() ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required
                   pattern="[a-zA-Z0-9_]{3,20}" title="3-20 characters, alphanumeric and underscores">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   minlength="8" title="Min 8 chars, with uppercase, lowercase, and number">
        </div>
        <div class="form-group">
            <label for="password_confirm">Confirm Password</label>
            <input type="password" id="password_confirm" name="password_confirm" required>
        </div>
        <button type="submit" class="btn">Register</button>
    </form>
    <p class="form-link">Already have an account? <a href="/login">Login</a></p>
</div>