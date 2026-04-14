<?php

/*
        User system => Registration , verification , login
*/

class AuthController {

    public function showLogin() {
        $pageTitle = 'Login';
        require __DIR__ . '/../views/layout/header.php';
        require __DIR__ . '/../views/auth/login.php';
        require __DIR__ . '/../views/layout/footer.php';
    }

    public function login() {
        if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'Invalid CSRF token.');
            header('Location: /login');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            Session::setFlash('error', 'All fields are required.');
            header('Location: /login');
            exit;
        }

        $userModel = new User();
        $user = $userModel->findByUsername($username);

        if (!$user || !password_verify($password, $user['password'])) {
            Session::setFlash('error', 'Invalid username or password.');
            header('Location: /login');
            exit;
        }

        if (!$user['is_verified']) {
            Session::setFlash('error', 'Please verify your email before logging in.');
            header('Location: /login');
            exit;
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);

        Session::setFlash('success', 'Welcome back, ' . $user['username'] . '!');
        header('Location: /');
        exit;
    }

    public function showRegister() {
        $pageTitle = 'Register';
        require __DIR__ . '/../views/layout/header.php';
        require __DIR__ . '/../views/auth/register.php';
        require __DIR__ . '/../views/layout/footer.php';
    }

    public function register() {
        if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'Invalid CSRF token.');
            header('Location: /register');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        // Validations
        $errors = [];

        if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
            $errors[] = 'All fields are required.';
        }
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $errors[] = 'Username must be 3-20 characters, alphanumeric and underscores only.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must be at least 8 characters with uppercase, lowercase, and a number.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        $userModel = new User();
        if ($userModel->usernameExists($username)) {
            $errors[] = 'Username is already taken.';
        }
        if ($userModel->emailExists($email)) {
            $errors[] = 'Email is already registered.';
        }

        if (!empty($errors)) {
            Session::setFlash('error', implode('<br>', $errors));
            header('Location: /register');
            exit;
        }

        $token = $userModel->create($username, $email, $password);

        // Send verification email
        $verifyUrl = getenv('APP_URL') . '/verify?token=' . $token;
        $body = "<h2>Welcome to Camagru!</h2><p>Click the link below to verify your account:</p><a href=\"$verifyUrl\">Verify Email</a>";
        Mailer::send($email, 'Verify your Camagru account', $body);

        Session::setFlash('success', 'Registration successful! Check your email to verify your account.');
        header('Location: /login');
        exit;
    }

    public function verify() {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            Session::setFlash('error', 'Invalid verification link.');
            header('Location: /login');
            exit;
        }

        $userModel = new User();
        if ($userModel->verify($token)) {
            Session::setFlash('success', 'Email verified! You can now log in.');
        } else {
            Session::setFlash('error', 'Invalid or expired verification link.');
        }

        header('Location: /login');
        exit;
    }

    public function logout() {
        Session::destroy();
        session_start(); // Restart for flash messages
        Session::setFlash('success', 'You have been logged out.');
        header('Location: /login');
        exit;
    }

    public function showForgotPassword() {
        $pageTitle = 'Forgot Password';
        require __DIR__ . '/../views/layout/header.php';
        require __DIR__ . '/../views/auth/forgot_password.php';
        require __DIR__ . '/../views/layout/footer.php';
    }

    public function forgotPassword() {
        if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'Invalid CSRF token.');
            header('Location: /forgot-password');
            exit;
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::setFlash('error', 'Please enter a valid email.');
            header('Location: /forgot-password');
            exit;
        }

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        // Always show success (don't reveal if email exists)
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $userModel->setResetToken($email, $token, $expiry);

            $resetUrl = getenv('APP_URL') . '/reset-password?token=' . $token;
            $body = "<h2>Reset your password</h2><p>Click the link below to reset your password:</p><a href=\"$resetUrl\">Reset Password</a><p>This link expires in 1 hour.</p>";
            Mailer::send($email, 'Reset your Camagru password', $body);
        }

        Session::setFlash('success', 'If an account exists with this email, a reset link has been sent.');
        header('Location: /login');
        exit;
    }

    public function showResetPassword() {
        $token = $_GET['token'] ?? '';
        if (empty($token)) {
            header('Location: /login');
            exit;
        }

        $userModel = new User();
        $user = $userModel->findByResetToken($token);
        if (!$user) {
            Session::setFlash('error', 'Invalid or expired reset link.');
            header('Location: /login');
            exit;
        }

        $pageTitle = 'Reset Password';
        require __DIR__ . '/../views/layout/header.php';
        require __DIR__ . '/../views/auth/reset_password.php';
        require __DIR__ . '/../views/layout/footer.php';
    }

    public function resetPassword() {
        if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'Invalid CSRF token.');
            header('Location: /login');
            exit;
        }

        $token    = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if (empty($password) || empty($confirm)) {
            Session::setFlash('error', 'All fields are required.');
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            Session::setFlash('error', 'Password must be at least 8 characters with uppercase, lowercase, and a number.');
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        if ($password !== $confirm) {
            Session::setFlash('error', 'Passwords do not match.');
            header('Location: /reset-password?token=' . urlencode($token));
            exit;
        }

        $userModel = new User();
        $user = $userModel->findByResetToken($token);

        if (!$user) {
            Session::setFlash('error', 'Invalid or expired reset link.');
            header('Location: /login');
            exit;
        }

        $userModel->updatePassword($user['id'], $password);
        Session::setFlash('success', 'Password updated! You can now log in.');
        header('Location: /login');
        exit;
    }
}