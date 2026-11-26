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
        $notices = database()->query(
            'SELECT title, message FROM notices ORDER BY notice_id DESC'
        )->fetchAll();
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

            <h2>Published notices</h2>

            <?php if (!$notices && $error === ''): ?>
                <p>There are no published notices yet.</p>
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