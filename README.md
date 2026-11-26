## Run locally

This project uses PHP 8.4 with the PDO SQLite extension enabled.

From the project folder, create the database and fictional sample data:

powershell```
php scripts/setup.php
```

Start the local PHP server:

```powershell
php -S 127.0.0.1:8000 -t public
```

Open http://127.0.0.1:8000 in a browser. Press Ctrl+C in the terminal to stop the server.

## Local demo accounts

| Role | Username | Password |
|---|---|---|
| Student | `student1` | `CampusStudentDemo1!` |
| Moderator | `moderator1` | `CampusModeratorDemo1!` |

Both accounts are fictional and are created by `php scripts/setup.php`.