<?php
session_start([
    'use_strict_mode' => 1,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

header('Cache-Control: no-store');

$pageTitle = 'Student Noticeboard';
$error = '';
$notices = [];
$search = '';
$noticeTitle = '';
$noticeMessage = '';
$previewError = '';
$showPreview = false;

$_SESSION['token'] ??= bin2hex(random_bytes(32));

function database(): PDO
{
    $path = __DIR__ . '/../data/noticeboard.sqlite';

    if (!is_file($path)) {
        throw new RuntimeException('Missing noticeboard database');
    }

    return new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

function html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['token'] ?? '';

        if (!is_string($token) || !hash_equals($_SESSION['token'], $token)) {
            http_response_code(403);
            $error = 'Please refresh the page and try again.';
        } elseif (in_array($_POST['action'] ?? '', ['preview_notice', 'publish_notice'], true)
            && isset($_SESSION['user_id'])
            && ($_SESSION['role'] ?? '') === 'student') {
            $titleInput = $_POST['title'] ?? '';
            $messageInput = $_POST['message'] ?? '';

            $noticeTitle = is_string($titleInput) ? trim($titleInput) : '';
            $noticeMessage = is_string($messageInput) ? trim($messageInput) : '';

            $draft = [
                'title' => $noticeTitle,
                'message' => $noticeMessage,
            ];

            if ($noticeTitle === '' || $noticeMessage === '') {
                unset($_SESSION['notice_preview']);
                $previewError = 'Enter both a title and a message to preview your notice.';
            } elseif ($_POST['action'] === 'preview_notice') {
                $_SESSION['notice_preview'] = $draft;
                $showPreview = true;
            } elseif (($_SESSION['notice_preview'] ?? null) !== $draft) {
                $previewError = 'Please preview the current title and message before publishing.';
            } else {
                $query = database()->prepare(
                    'INSERT INTO notices (author_id, title, message) VALUES (?, ?, ?)'
                );
                $query->execute([
                    $_SESSION['user_id'],
                    $draft['title'],
                    $draft['message'],
                ]);

                unset($_SESSION['notice_preview']);
                header('Location: /', true, 303);
                exit;
            }
        } elseif (($_POST['action'] ?? '') === 'sign_out') {
            $_SESSION = [];
            session_destroy();

            header('Location: /', true, 303);
            exit;
        } elseif (($_POST['action'] ?? '') === 'sign_in' && !isset($_SESSION['user_id'])) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $query = database()->prepare(
                'SELECT user_id, username, password_hash, role
                 FROM users WHERE username = ?'
            );
            $query->execute([is_string($username) ? trim($username) : '']);
            $user = $query->fetch();

            if ($user && is_string($password) && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['token'] = bin2hex(random_bytes(32));

                header('Location: /', true, 303);
                exit;
            }

            $error = 'Incorrect username or password.';
            error_log('Noticeboard: failed sign-in');
        }
    }

    if (isset($_SESSION['user_id'])) {
        $searchInput = $_GET['search'] ?? '';
        $search = is_string($searchInput) ? trim($searchInput) : '';

        $query = database()->prepare(
            'SELECT title, message
            FROM notices
            WHERE instr(lower(title), lower(?)) > 0
            ORDER BY notice_id DESC'
        );
        $query->execute([$search]);
        $notices = $query->fetchAll();
    }
} catch (RuntimeException $exception) {
    http_response_code(503);
    $error = 'The noticeboard is temporarily unavailable.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $pageTitle ?></title>
</head>
<body>
    <main>
        <h1><?= html($pageTitle) ?></h1>

        <?php if ($error !== ''): ?>
            <p role="alert"><?= html($error) ?></p>
        <?php endif; ?>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <form method="post">
                <h2>Sign in</h2>

                <input type="hidden" name="action" value="sign_in">
                <input type="hidden" name="token" value="<?= html($_SESSION['token']) ?>">

                <p>
                    <label for="username">Username</label><br>
                    <input type="text" id="username" name="username"
                        autocomplete="username" required>
                </p>

                <p>
                    <label for="password">Password</label><br>
                    <input type="password" id="password" name="password"
                        autocomplete="current-password" required>
                </p>

                <button type="submit">Sign in</button>
            </form>
        <?php else: ?>
            <p>
                Signed in as <?= html($_SESSION['username']) ?>
                (<?= html($_SESSION['role']) ?>)
            </p>

            <form method="post">
                <input type="hidden" name="action" value="sign_out">
                <input type="hidden" name="token" value="<?= html($_SESSION['token']) ?>">
                <button type="submit">Sign out</button>
            </form>

            <?php if (($_SESSION['role'] ?? '') === 'student'): ?>
                <h2>Make a notice</h2>

                <form id="notice-form" method="post" action="/">
                    <input type="hidden" name="token" value="<?= html($_SESSION['token']) ?>">

                    <p>
                        <label for="notice-title">Title</label><br>
                        <input type="text" id="notice-title" name="title"
                            value="<?= html($noticeTitle) ?>" required>
                    </p>

                    <p>
                        <label for="notice-message">Message</label><br>
                        <textarea id="notice-message" name="message" rows="4"
                                required><?= html($noticeMessage) ?></textarea>
                    </p>

                    <button type="submit" name="action" value="preview_notice">Preview notice</button>
                </form>

                <?php if ($previewError !== ''): ?>
                    <p role="alert"><?= html($previewError) ?></p>
                <?php endif; ?>

                <?php if ($showPreview): ?>
                    <h2>Notice preview</h2>
                    <h3><?= html($noticeTitle) ?></h3>
                    <p><?= nl2br(html($noticeMessage)) ?></p>
                    <button type="submit" form="notice-form" name="action" value="publish_notice">
                        Publish this notice
                    </button>
                <?php endif; ?>
            <?php endif; ?>

            <h2>Published notices</h2>
            <form method="get" action="/">
                <label for="search">Search by title</label>
                <input type="search" id="search" name="search" value="<?= html($search) ?>">
                <button type="submit">Search</button>
                <a href="/">Show all notices</a>
            </form>

            <?php if (!$notices && $error === ''): ?>
                <p><?= $search === '' ? 'No notices have been published yet.' : 'No notices match your search.' ?></p>
            <?php endif; ?>

            <?php foreach ($notices as $notice): ?>
                <article>
                    <h3><?= html($notice['title']) ?></h3>
                    <p><?= html($notice['message']) ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>