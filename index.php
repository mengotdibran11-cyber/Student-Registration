<?php
declare(strict_types=1);
session_start();

const DEPARTMENTS = [
    'Software Engineering',
    'Computer Science',
    'Information Systems',
    'Networks and Security',
];
const LEVELS  = ['100', '200', '300', '400'];
const GENDERS = ['Female', 'Male', 'Prefer not to say'];

/** Escape output for HTML. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Print an error message under a field, if there is one. */
function showError(array $errors, string $field): void
{
    if (isset($errors[$field])) {
        echo '<span class="error">' . e($errors[$field]) . '</span>';
    }
}

/** Mark an option as selected when it matches the previous value. */
function selected(array $old, string $field, string $value): string
{
    return (($old[$field] ?? '') === $value) ? ' selected' : '';
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors  = [];
$old     = [];
$success = '';

// One-time success message after redirect
if (!empty($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect and trim input (passwords are not trimmed)
    $old = [
        'full_name'     => trim((string)($_POST['full_name'] ?? '')),
        'email'         => trim((string)($_POST['email'] ?? '')),
        'phone'         => trim((string)($_POST['phone'] ?? '')),
        'matric_number' => strtoupper(trim((string)($_POST['matric_number'] ?? ''))),
        'date_of_birth' => trim((string)($_POST['date_of_birth'] ?? '')),
        'department'    => trim((string)($_POST['department'] ?? '')),
        'level'         => trim((string)($_POST['level'] ?? '')),
        'gender'        => trim((string)($_POST['gender'] ?? '')),
    ];
    $password        = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $accuracy        = ($_POST['confirm_accuracy'] ?? '') === 'yes';

    // CSRF check
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors['form'] = 'Your session expired. Please try again.';
    }

    // Full name
    if ($old['full_name'] === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (mb_strlen($old['full_name']) < 3 || mb_strlen($old['full_name']) > 100) {
        $errors['full_name'] = 'Full name must be between 3 and 100 characters.';
    } elseif (!preg_match("/^[\p{L}][\p{L}\s'.\-]*$/u", $old['full_name'])) {
        $errors['full_name'] = 'Full name may only contain letters, spaces, apostrophes, dots and hyphens.';
    }

    // Email
    if ($old['email'] === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (strlen($old['email']) > 150 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    // Phone (Cameroon): accepts 6XXXXXXXX, 2376XXXXXXXX or +2376XXXXXXXX
    $phone = preg_replace('/[\s\-]/', '', $old['phone']);
    if ($phone === '') {
        $errors['phone'] = 'Phone number is required.';
    } else {
        if (preg_match('/^6\d{8}$/', $phone)) {
            $phone = '+237' . $phone;
        } elseif (preg_match('/^237 ?6\d{8}$/', $phone)) {
            $phone = '+' . $phone;
        }
        if (!preg_match('/^\+2376\d{8}$/', $phone)) {
            $errors['phone'] = 'Enter a valid phone number, e.g. +2376XXXXXXXX.';
        } else {
            $old['phone'] = $phone;
        }
    }

    // Matriculation number: 2 letters + 7 digits (e.g. SE2026001)
    if ($old['matric_number'] === '') {
        $errors['matric_number'] = 'Matriculation number is required.';
    } elseif (!preg_match('/^[A-Z]{2}\d{7}$/', $old['matric_number'])) {
        $errors['matric_number'] = 'Use 2 letters followed by 7 digits, e.g. SE2026001.';
    }

    // Date of birth
    if ($old['date_of_birth'] === '') {
        $errors['date_of_birth'] = 'Date of birth is required.';
    } else {
        $dob = DateTime::createFromFormat('!Y-m-d', $old['date_of_birth']);
        if (!$dob || $dob->format('Y-m-d') !== $old['date_of_birth']) {
            $errors['date_of_birth'] = 'Enter a valid date.';
        } else {
            $age = $dob->diff(new DateTime('today'))->y;
            if ($dob > new DateTime('today')) {
                $errors['date_of_birth'] = 'Date of birth cannot be in the future.';
            } elseif ($age < 15 || $age > 70) {
                $errors['date_of_birth'] = 'Age must be between 15 and 70 years.';
            }
        }
    }

    // Department, level, gender
    if ($old['department'] === '') {
        $errors['department'] = 'Select a department.';
    } elseif (!in_array($old['department'], DEPARTMENTS, true)) {
        $errors['department'] = 'Invalid department selected.';
    }

    if ($old['level'] === '') {
        $errors['level'] = 'Select a level.';
    } elseif (!in_array($old['level'], LEVELS, true)) {
        $errors['level'] = 'Invalid level selected.';
    }

    if ($old['gender'] === '') {
        $errors['gender'] = 'Select a gender option.';
    } elseif (!in_array($old['gender'], GENDERS, true)) {
        $errors['gender'] = 'Invalid gender option.';
    }

    // Password
    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Password must not exceed 72 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must contain at least one letter and one number.';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Accuracy confirmation
    if (!$accuracy) {
        $errors['confirm_accuracy'] = 'You must confirm that the information is accurate.';
    }

    // Insert
    if (!$errors) {
        try {
            $config = require __DIR__ . '/config.php';
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );
            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            $stmt = $pdo->prepare(
                'INSERT INTO students
                    (full_name, email, phone, matric_number, date_of_birth,
                     department, level, gender, password_hash)
                 VALUES
                    (:full_name, :email, :phone, :matric_number, :date_of_birth,
                     :department, :level, :gender, :password_hash)'
            );
            $stmt->execute([
                ':full_name'     => $old['full_name'],
                ':email'         => strtolower($old['email']),
                ':phone'         => $old['phone'],
                ':matric_number' => $old['matric_number'],
                ':date_of_birth' => $old['date_of_birth'],
                ':department'    => $old['department'],
                ':level'         => (int)$old['level'],
                ':gender'        => $old['gender'],
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            // Post/Redirect/Get so a refresh doesn't resubmit the form
            $_SESSION['success'] = 'Registration successful. Welcome, ' . $old['full_name'] . '!';
            $_SESSION['csrf']    = bin2hex(random_bytes(32));
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;

        } catch (PDOException $ex) {
            if ((int)($ex->errorInfo[1] ?? 0) === 1062) {
                // Duplicate entry: find out which unique key was hit
                if (str_contains($ex->getMessage(), 'uk_students_email')) {
                    $errors['email'] = 'This email address is already registered.';
                } elseif (str_contains($ex->getMessage(), 'uk_students_matric')) {
                    $errors['matric_number'] = 'This matriculation number is already registered.';
                } else {
                    $errors['form'] = 'This student is already registered.';
                }
            } else {
                error_log('Registration error: ' . $ex->getMessage());
                $errors['form'] = 'Something went wrong while saving your registration. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Registration</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main>
    <h1>STUDENT REGISTRATION</h1>
    <p>Complete all required fields</p>

    <?php if ($success !== ''): ?>
      <div class="success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if (isset($errors['form'])): ?>
      <div><span class="error"><?= e($errors['form']) ?></span></div>
    <?php endif; ?>

    <form action="" method="POST" autocomplete="off" novalidate>
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

      <div>
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" placeholder="Ada Nfor" autocomplete="off"
               value="<?= e($old['full_name'] ?? '') ?>">
        <?php showError($errors, 'full_name'); ?>
      </div>

      <div>
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" placeholder="student@example.com"
               value="<?= e($old['email'] ?? '') ?>">
        <?php showError($errors, 'email'); ?>
      </div>

      <div>
        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone" placeholder="+2376XXXXXXXX"
               value="<?= e($old['phone'] ?? '') ?>">
        <?php showError($errors, 'phone'); ?>
      </div>

      <div>
        <label for="matric_number">Matriculation number</label>
        <input type="text" id="matric_number" name="matric_number" placeholder="SE2026001"
               value="<?= e($old['matric_number'] ?? '') ?>">
        <?php showError($errors, 'matric_number'); ?>
      </div>

      <div>
        <label for="date_of_birth">Date of birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth"
               value="<?= e($old['date_of_birth'] ?? '') ?>">
        <?php showError($errors, 'date_of_birth'); ?>
      </div>

      <div>
        <label for="department">Department</label>
        <select id="department" name="department">
          <option value="">Select department</option>
          <?php foreach (DEPARTMENTS as $dept): ?>
            <option value="<?= e($dept) ?>"<?= selected($old, 'department', $dept) ?>><?= e($dept) ?></option>
          <?php endforeach; ?>
        </select>
        <?php showError($errors, 'department'); ?>
      </div>

      <div>
        <label for="level">Level</label>
        <select id="level" name="level">
          <option value="">Select level</option>
          <?php foreach (LEVELS as $lvl): ?>
            <option value="<?= e($lvl) ?>"<?= selected($old, 'level', $lvl) ?>>Level <?= e($lvl) ?></option>
          <?php endforeach; ?>
        </select>
        <?php showError($errors, 'level'); ?>
      </div>

      <fieldset>
        <legend>Gender</legend>
        <?php foreach (GENDERS as $g): ?>
          <label>
            <input type="radio" name="gender" value="<?= e($g) ?>"<?= (($old['gender'] ?? '') === $g) ? ' checked' : '' ?>>
            <?= e($g) ?>
          </label>
        <?php endforeach; ?>
        <?php showError($errors, 'gender'); ?>
      </fieldset>

      <div>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="At least 8 characters" autocomplete="new-password">
        <?php showError($errors, 'password'); ?>
      </div>

      <div>
        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your password" autocomplete="new-password">
        <?php showError($errors, 'confirm_password'); ?>
      </div>

      <div>
        <label>
          <input type="checkbox" name="confirm_accuracy" value="yes"<?= !empty($_POST['confirm_accuracy']) ? ' checked' : '' ?>>
          I confirm that the information provided is accurate.
        </label>
        <?php showError($errors, 'confirm_accuracy'); ?>
      </div>

      <button type="submit">SUBMIT REGISTRATION</button>

    </form>
  </main>
</body>
</html>
