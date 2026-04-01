<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camagru<?= isset($pageTitle) ? ' - ' . htmlspecialchars($pageTitle) : '' ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <a href="/" class="logo">📷 Camagru</a>
            <div class="nav-links">
                <a href="/">Gallery</a>
                <?php if (Session::isLoggedIn()): ?>
                    <a href="/editor">Editor</a>
                    <a href="/settings">Settings</a>
                    <a href="/logout">Logout</a>
                <?php else: ?>
                    <a href="/login">Login</a>
                    <a href="/register">Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    <main class="container">
        <?php
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');
        if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>