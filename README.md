# Camagru

Instagram-like photo booth built for the 42 School "Camagru" subject: capture a photo from your webcam (or upload one), composite it with server-side sticker overlays, and share it in a public gallery with likes and comments. Custom PHP MVC, no framework, fully dockerized.

## Getting started

### Requirements

- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/)
- That's it — PHP, nginx, MariaDB, and a mail server all run inside containers.

### 1. Clone the repo

```bash
git clone <this-repo-url>
cd Camagru
```

### 2. Create your `.env` file

Copy the template below into a `.env` file at the project root (see [env file](#env-file) for the required variables), then adjust the passwords/secrets as you like.

### 3. Build and start the containers

```bash
docker compose up -d --build
```

This starts four services:

| Service | URL | Purpose |
|---|---|---|
| `nginx` | http://localhost:8080 | web app entry point |
| `php` | — | PHP-FPM, runs the app |
| `db` | localhost:3306 | MariaDB, schema auto-created from `config/init.sql` on first boot |
| `mailhog` | http://localhost:8025 | fake SMTP inbox — verification/reset emails land here in dev |

### 4. Open the app

Visit **http://localhost:8080**, register an account, then check **http://localhost:8025** for the verification email (no real email is sent in dev — msmtp routes everything to mailhog).

### 5. Stop the app

```bash
docker compose down          # stop containers, keep DB data
docker compose down -v       # stop containers and wipe DB data
```

### Troubleshooting

- **DB schema missing/out of date**: the schema is only loaded on the *first* creation of the `db_data` volume. If you change `config/init.sql` after that, run `docker compose down -v` to reset, or apply changes manually via `docker compose exec db mariadb -u root -p`.
- **Port already in use**: something else on your machine is using 8080, 3306, 8025, or 1025 — stop it or change the port mapping in `docker-compose.yml`.
- **Webcam capture doesn't work**: browsers only allow `getUserMedia` on `localhost` or HTTPS, so use `http://localhost:8080`, not your machine's LAN IP.

## env file

```bash

DB_HOST=db  
DB_NAME=camagru  
DB_USER=camagru_user  
DB_PASS=your_secure_password_here  
DB_ROOT_PASS=your_root_password_here  

MAIL_HOST=mailhog  
MAIL_PORT=1025  
MAIL_FROM=noreply@camagru.local  

APP_URL=http://localhost:8080  
APP_SECRET=a_random_long_secret_key_here
```

## Useful commands

Access the MariaDB database directly inside the `db` container:

```bash
docker compose exec db bash
mariadb -u root -p

SHOW DATABASES;
USE camagru;
SHOW TABLES;
DESCRIBE users;
SELECT * FROM users;
DELETE FROM users;
```

## Architecture

Custom MVC, no framework: `public/index.php` bootstraps everything → `core/Router.php` dispatches → `app/controllers/*` handle logic → `app/models/*` talk to MySQL via PDO → `app/views/*` render HTML. Deployed via Docker (nginx + php-fpm + mariadb + mailhog).

### Entry & routing

- `public/index.php` — front controller. Loads `.env`, requires all core classes and models, registers every route, dispatches the request. This is the map of the whole app — read it first.
- `core/Router.php` — tiny router: stores `GET`/`POST` routes in an array, matches on exact path, instantiates `Controller@method` strings.

### Core services (`core/`)

- `Database.php` — singleton PDO wrapper, reads `config/database.php`.
- `Session.php` — wraps `$_SESSION`: login state, flash messages.
- `Csrf.php` — generates/validates CSRF tokens (`hash_equals`), used on every POST.
- `Mailer.php` — thin wrapper over PHP's `mail()`, routed through msmtp → mailhog in dev.

### Models (`app/models/` — one per table, plain PDO, no ORM)

- `User.php` — CRUD, password hashing (`password_hash`/`password_verify`), email verification tokens, password-reset tokens.
- `Image.php` (class `ImageModel`) — create/find/delete images, paginated gallery query with like counts.
- `Comment.php` — create/list comments per image.
- `Like.php` — toggle like/unlike, count, `hasLiked`.

### Controllers (`app/controllers/`)

- `AuthController.php` — register (validates + emails verification link), login (checks `is_verified`, regenerates session id), logout, email verify, forgot/reset password flow.
- `UserController.php` — settings page: update username/email/password/notification prefs.
- `EditorController.php` — the project's centerpiece. `capture()` takes a base64 webcam frame from JS, decodes it with GD, composites sticker overlays onto it server-side, saves as PNG, inserts DB row. `upload()` does the same for a user-uploaded file (with MIME/size validation) instead of a webcam frame. `deleteImage()` removes file + DB row (ownership-checked).
- `GalleryController.php` — public gallery listing (paginated), AJAX comment/like endpoints, and two polling endpoints (`/api/updates`, `/api/new-posts`) that the frontend polls every 5s for "live" comments/likes/new posts.

### Views (`app/views/`)

Plain PHP templates, split by feature (`auth/`, `editor/`, `gallery/`, `user/`), with shared `layout/header.php` + `footer.php` (nav, flash messages, `<script src="/js/app.js">`).

### Frontend

- `public/js/app.js` — vanilla JS, no framework. Key pieces: `initEditor()` (getUserMedia webcam access, drag-and-drop sticker placement, canvas capture → base64 → POST `/capture`), `initLikes()`/`initComments()` (AJAX), `initPolling()`/`initPostPolling()` (setInterval-based pseudo-realtime updates).
- `public/css/style.css` — styling.
- `public/overlays/*.png` — the predefined sticker images (glasses, mustache, cat ears, frames) offered in the editor.
- `public/uploads/` — where final composited photos are saved.

### Config / infra

- `config/database.php`, `config/init.sql` (schema: `users`, `images`, `comments`, `likes`), `config/setup.php` (standalone DB bootstrap script).
- `Dockerfile` — PHP 8.2-fpm + GD + msmtp.
- `docker-compose.yml` — nginx + php + mariadb + mailhog services.
- `nginx/default.conf` — routes PHP to php-fpm, serves `/uploads/` and `/overlays/` directly.
- `msmtp/msmtprc` — SMTP relay config pointing at mailhog.

## Top files to focus on for evaluation

1. `EditorController.php` — this is the subject's core requirement (webcam capture, overlay compositing with GD, upload fallback). Evaluators will poke hardest here: sticker `x`/`y`/`width`/`height` come straight from client JSON with only `intval()` — worth understanding if asked about validation, and note `basename()` is used on the overlay filename to block path traversal.
2. `public/index.php` + `core/Router.php` — shows you understand the request lifecycle end-to-end.
3. `public/js/app.js` `initEditor()` — webcam/canvas/drag-drop logic; be ready to explain how a capture becomes a base64 PNG POST.
4. `AuthController.php` — full auth flow (register/verify/login/reset) is a big graded chunk in the 42 Camagru subject; know the validation rules and why session ID is regenerated on login.
5. `GalleryController.php` + polling in `app.js` — the "real-time-ish" like/comment updates without websockets.
6. `config/init.sql` — know the schema (FKs, `UNIQUE` constraint on likes preventing double-likes) cold.

One thing worth being ready to explain/defend in a defense: CSRF tokens are validated on every state-changing POST, passwords are hashed with bcrypt, uploads are MIME-checked via `finfo`, and image filenames are server-generated (`uniqid()+time()`) rather than trusting client input — all standard 42-subject security expectations.
