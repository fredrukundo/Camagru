<?php

class UserController {

    public function showSettings() {
        if (!Session::isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        $userModel = new User();
        $user = $userModel->findById(Session::getUserId());

        $pageTitle = 'Settings';
        require __DIR__ . '/../views/layout/header.php';
        require __DIR__ . '/../views/user/settings.php';
        require __DIR__ . '/../views/layout/footer.php';
    }

    public function updateSettings() {
        if (!Session::isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        if (!Csrf::validateToken($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'Invalid CSRF token.');
            header('Location: /settings');
            exit;
        }

        $userId = Session::getUserId();
        $userModel = new User();
        $user = $userModel->findById($userId);

        $newUsername = trim($_POST['username'] ?? '');
        $newEmail   = trim($_POST['email'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $notifyComments = isset($_POST['notify_comments']) ? 1 : 0;

        $errors = [];

        // Username update
        if (!empty($newUsername) && $newUsername !== $user['username']) {
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $newUsername)) {
                $errors[] = 'Invalid username format.';
            } elseif ($userModel->usernameExists($newUsername, $userId)) {
                $errors[] = 'Username already taken.';
            } else {
                $userModel->updateUsername($userId, $newUsername);
                Session::set('username', $newUsername);
            }
        }

        // Email update
        if (!empty($newEmail) && $newEmail !== $user['email']) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format.';
            } elseif ($userModel->emailExists($newEmail, $userId)) {
                $errors[] = 'Email already in use.';
            } else {
                $userModel->updateEmail($userId, $newEmail);
            }
        }

        // Password update
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                $errors[] = 'Password must be at least 8 characters with uppercase, lowercase, and a number.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            } else {
                $userModel->updatePassword($userId, $newPassword);
            }
        }

        // Notification preference
        $userModel->updateNotifyComments($userId, $notifyComments);

        if (!empty($errors)) {
            Session::setFlash('error', implode('<br>', $errors));
        } else {
            Session::setFlash('success', 'Settings updated successfully.');
        }

        header('Location: /settings');
        exit;
    }
}