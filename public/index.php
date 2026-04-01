<?php

// Load environment variables from .env (simple parser)
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Autoload core
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../core/Mailer.php';
require_once __DIR__ . '/../core/Router.php';

// Autoload models
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Image.php';
require_once __DIR__ . '/../app/models/Comment.php';
require_once __DIR__ . '/../app/models/Like.php';

Session::start();

$router = new Router();





// ---- ROUTES ----

// Gallery (public homepage)
$router->get('/', 'GalleryController@index');


// Auth
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register');
$router->get('/logout', 'AuthController@logout');
$router->get('/verify', 'AuthController@verify');
$router->get('/forgot-password', 'AuthController@showForgotPassword');
$router->post('/forgot-password', 'AuthController@forgotPassword');
$router->get('/reset-password', 'AuthController@showResetPassword');
$router->post('/reset-password', 'AuthController@resetPassword');

// User settings
$router->get('/settings', 'UserController@showSettings');
$router->post('/settings', 'UserController@updateSettings');

// Editor
$router->get('/editor', 'EditorController@index');
$router->post('/capture', 'EditorController@capture');
$router->post('/upload', 'EditorController@upload');
$router->post('/delete-image', 'EditorController@deleteImage');

// Gallery interactions
$router->post('/comment', 'GalleryController@addComment');
$router->post('/like', 'GalleryController@toggleLike');

$router->get('/api/updates', 'GalleryController@getUpdates');

$router->get('/api/new-posts', 'GalleryController@getNewPosts');

// Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);