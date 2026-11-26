<?php

$dataDirectory = __DIR__ . '/../data';

if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0775, true)) {
    exit("Could not create the data folder.\n");
}

$db = new PDO('sqlite:' . $dataDirectory . '/noticeboard.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON');

$db->beginTransaction();

try {
    $version = (int) $db->query('PRAGMA user_version')->fetchColumn();

    if ($version === 1) {
        $db->rollBack();
        echo "The noticeboard database is already set up.\n";
        exit;
    }

    $db->exec("
        CREATE TABLE users (
            user_id INTEGER PRIMARY KEY,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN ('student', 'moderator'))
        )
    ");

    $db->exec("
        CREATE TABLE notices (
            notice_id INTEGER PRIMARY KEY,
            author_id INTEGER NOT NULL REFERENCES users(user_id),
            title TEXT NOT NULL,
            message TEXT NOT NULL
        )
    ");

    $addUser = $db->prepare(
        'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)'
    );

    $addUser->execute([
        'student1',
        password_hash('CampusStudentDemo1!', PASSWORD_DEFAULT),
        'student'
    ]);
    $studentId = (int) $db->lastInsertId();

    $addUser->execute([
        'moderator1',
        password_hash('CampusModeratorDemo1!', PASSWORD_DEFAULT),
        'moderator'
    ]);

    $addNotice = $db->prepare(
        'INSERT INTO notices (author_id, title, message) VALUES (?, ?, ?)'
    );

    $addNotice->execute([
        $studentId,
        'Library study group',
        'Meet at the library entrance on Thursday at 2 pm.'
    ]);

    $addNotice->execute([
        $studentId,
        'Chess club',
        'The chess club is meeting on Friday. Beginners are welcome.'
    ]);

    $db->exec('PRAGMA user_version = 1');
    $db->commit();

    echo "Setup complete: two accounts and two notices created.\n";
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}