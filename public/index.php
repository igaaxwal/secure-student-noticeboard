<?php
$pageTitle = 'Student Noticeboard';
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
        <h1><?= $pageTitle ?></h1>

        <form method="post">
            <h2>Sign in</h2>

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

            <button type="submit" disabled>Sign in</button>
        </form>
    </main>
</body>
</html>