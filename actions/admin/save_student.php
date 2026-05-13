<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/uploads.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

function save_student_avatar_upload(array $file): ?string
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $upload = upload_store_file($file, UPLOAD_STUDENT_PHOTOS_PATH, 'uploads/student_photos', upload_allowed_image_mimes(), 'student');
    return $upload['path'];
}

if (!is_post_request()) {
    redirect(base_url('admin/students/index.php'));
}

$studentId = (int) ($_POST['id'] ?? 0);
$isEditing = $studentId > 0;
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$gender = trim((string) ($_POST['gender'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$status = trim((string) ($_POST['status'] ?? 'active'));

$redirectPath = $isEditing
    ? base_url('admin/students/form.php?edit=' . (string) $studentId)
    : base_url('admin/students/create.php');

$errors = [];
$avatarPath = null;

if ($isEditing) {
    $studentCheckStatement = $pdo->prepare(
        "SELECT users.id
         FROM users
         INNER JOIN roles ON roles.id = users.role_id
         WHERE users.id = :id AND roles.name = 'student'
         LIMIT 1"
    );
    $studentCheckStatement->execute(['id' => $studentId]);

    if (!$studentCheckStatement->fetch()) {
        flash_message('The selected student account could not be found.', 'warning');
        redirect(base_url('admin/students/index.php'));
    }
}

if (isset($_FILES['avatar']) && is_array($_FILES['avatar']) && (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
        $avatarPath = save_student_avatar_upload($_FILES['avatar']);
    } catch (Throwable $throwable) {
        $errors[] = $throwable->getMessage();
    }
}

if ($firstName === '') {
    $errors[] = 'First name is required.';
}

if ($lastName === '') {
    $errors[] = 'Last name is required.';
}

if ($email === '') {
    $errors[] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email address is invalid.';
}

if ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) {
    $errors[] = 'Gender selection is invalid.';
}

if ($isEditing) {
    if ($password !== '' || $confirmPassword !== '') {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }
    }
} else {
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }
}

if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
    $errors[] = 'Invalid student status.';
}

$duplicateStatement = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
$duplicateStatement->execute(['email' => $email, 'id' => $studentId]);

if ($duplicateStatement->fetch()) {
    $errors[] = 'That email address is already in use.';
}

if ($errors !== []) {
    if ($avatarPath !== null) {
        $uploadedPath = dirname(__DIR__, 2) . '/' . $avatarPath;
        if (is_file($uploadedPath)) {
            unlink($uploadedPath);
        }
    }

    $_SESSION['student_enrollment_form_state'] = [
        'errors' => $errors,
        'data' => [
            'id' => $studentId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'gender' => $gender,
            'status' => $status,
        ],
    ];

    redirect($redirectPath);
}

$roleStatement = $pdo->prepare("SELECT id FROM roles WHERE name = 'student' LIMIT 1");
$roleStatement->execute();
$studentRoleId = (int) $roleStatement->fetchColumn();

if ($studentRoleId < 1) {
    flash_message('Student role is not configured in the system.', 'danger');
    redirect(base_url('admin/students/index.php'));
}

if ($isEditing) {
    if ($password !== '') {
        $statement = $pdo->prepare(
            'UPDATE users
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = :email,
                 phone = :phone,
                 gender = :gender,
                 status = :status,
                 avatar = COALESCE(:avatar, avatar),
                 password_hash = :password_hash,
                 password_must_reset = :password_must_reset
             WHERE id = :id AND role_id = :role_id'
        );
        $statement->execute([
            'id' => $studentId,
            'role_id' => $studentRoleId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'gender' => $gender !== '' ? $gender : null,
            'status' => $status,
            'avatar' => $avatarPath,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'password_must_reset' => $password === 'Password' ? 1 : 0,
        ]);
    } else {
        $statement = $pdo->prepare(
            'UPDATE users
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = :email,
                 phone = :phone,
                 gender = :gender,
                 status = :status,
                 avatar = COALESCE(:avatar, avatar)
             WHERE id = :id AND role_id = :role_id'
        );
        $statement->execute([
            'id' => $studentId,
            'role_id' => $studentRoleId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'gender' => $gender !== '' ? $gender : null,
            'status' => $status,
            'avatar' => $avatarPath,
        ]);
    }

    flash_message('Student account updated successfully.', 'success');
    redirect(base_url('admin/students/form.php?edit=' . (string) $studentId));
}

$statement = $pdo->prepare(
    'INSERT INTO users (role_id, first_name, last_name, email, password_hash, password_must_reset, phone, gender, avatar, status)
     VALUES (:role_id, :first_name, :last_name, :email, :password_hash, :password_must_reset, :phone, :gender, :avatar, :status)'
);
$statement->execute([
    'role_id' => $studentRoleId,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'password_must_reset' => $password === 'Password' ? 1 : 0,
    'phone' => $phone !== '' ? $phone : null,
    'gender' => $gender !== '' ? $gender : null,
    'avatar' => $avatarPath,
    'status' => $status,
]);

flash_message('Student account created successfully. You can now assign academic enrollments.', 'success');
redirect(base_url('admin/students/index.php'));
