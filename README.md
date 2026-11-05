# Student Registration

A simple PHP + MySQL student registration form with server-side validation and secure password storage.

## Features

- Server-side validation of every field, with an error message under each one
- Form values are kept after a failed submission (passwords are never repopulated)
- Passwords hashed with `password_hash()`
- PDO prepared statements (protection against SQL injection)
- Output escaped with `htmlspecialchars()` (protection against XSS)
- CSRF token on the form
- Duplicate email / matriculation number detection through unique database keys
- Post/Redirect/Get after success, so refreshing does not resubmit the form
- Responsive layout

## Project structure

```
student-registration/
├── index.php            Form, validation and database insert
├── style.css            Styling
├── config.php           Real DB credentials (never submitted)
├── config.example.php   Same file with fake values (submit this instead)
├── database.sql         Database structure export
└── README.md
```

## Requirements

- PHP 8.0 or newer (uses `str_contains`) with the `pdo_mysql` extension
- MySQL 5.7+ or MariaDB 10.3+
- A local server such as XAMPP, WAMP, Laragon, or PHP's built-in server

## Setup

1. **Create the database.** Import the structure:

   ```bash
   mysql -u root -p < database.sql
   ```

   Or import `database.sql` through phpMyAdmin.

2. **Configure credentials.** Copy the example file and edit it:

   ```bash
   cp config.example.php config.php
   ```

   Set `host`, `dbname`, `user` and `pass` to match your MySQL setup.

3. **Run the app.** Either place the folder in your server's web root (e.g. `htdocs`) and open
   `http://localhost/student-registration/`, or from the project folder run:

   ```bash
   php -S localhost:8000
   ```

   then open `http://localhost:8000`.

## Validation rules

| Field | Rule |
|---|---|
| Full name | Required, 3 to 100 characters, letters, spaces, `'`, `.` and `-` only |
| Email | Required, valid format, unique |
| Phone | Required, Cameroon mobile: `6XXXXXXXX`, `2376XXXXXXXX` or `+2376XXXXXXXX` (stored as `+2376XXXXXXXX`) |
| Matriculation number | Required, 2 letters + 7 digits (e.g. `SE2026001`), unique, stored uppercase |
| Date of birth | Required, valid date, not in the future, age 15 to 70 |
| Department | Required, must be one of the listed departments |
| Level | Required, 100, 200, 300 or 400 |
| Gender | Required, one of the listed options |
| Password | Required, 8 to 72 characters, at least one letter and one number |
| Confirm password | Must match the password |
| Accuracy checkbox | Must be ticked |

## Database

Single table, `students`:

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED | Primary key, auto-increment |
| `full_name` | VARCHAR(100) | |
| `email` | VARCHAR(150) | Unique |
| `phone` | VARCHAR(15) | |
| `matric_number` | VARCHAR(9) | Unique |
| `date_of_birth` | DATE | |
| `department` | ENUM | |
| `level` | SMALLINT UNSIGNED | |
| `gender` | ENUM | |
| `password_hash` | VARCHAR(255) | Output of `password_hash()` |
| `created_at` | TIMESTAMP | Defaults to the current time |

## Security notes

- `config.php` holds real credentials. Keep it out of version control and out of your submission
  (add `config.php` to `.gitignore`) and submit `config.example.php` instead.
- For production, use a dedicated MySQL user with only the privileges the app needs, and serve over HTTPS.
