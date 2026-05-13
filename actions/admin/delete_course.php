<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/courses/index.php'));
}

$courseId = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;

if ($courseId < 1) {
    flash_message('The selected course is invalid.', 'warning');
    redirect(base_url('admin/courses/index.php'));
}

$courseStatement = $pdo->prepare(
    'SELECT id, title,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = courses.id) AS enrollment_count
     FROM courses
     WHERE id = :id
     LIMIT 1'
);
$courseStatement->execute(['id' => $courseId]);
$course = $courseStatement->fetch();

if (!$course) {
    flash_message('The selected course could not be found.', 'warning');
    redirect(base_url('admin/courses/index.php'));
}

if ((int) $course['enrollment_count'] > 0) {
    $enrollmentStatement = $pdo->prepare(
        'SELECT enrollments.id,
                enrollments.status,
                CONCAT(users.first_name, " ", users.last_name) AS student_name,
                users.email AS student_email,
                batches.batch_name
         FROM enrollments
         INNER JOIN users ON users.id = enrollments.student_id
         INNER JOIN batches ON batches.id = enrollments.batch_id
         WHERE enrollments.course_id = :course_id
         ORDER BY users.first_name ASC, users.last_name ASC, enrollments.id ASC'
    );
    $enrollmentStatement->execute(['course_id' => $courseId]);

    $_SESSION['course_delete_blocked'] = [
        'course_title' => $course['title'],
        'enrollments' => $enrollmentStatement->fetchAll(),
    ];

    flash_message('This course has enrollments that must be deleted first.', 'warning');
    redirect(base_url('admin/courses/index.php'));
}

try {
    $deleteStatement = $pdo->prepare('DELETE FROM courses WHERE id = :id');
    $deleteStatement->execute(['id' => $courseId]);

    if ($deleteStatement->rowCount() < 1) {
        flash_message('The selected course could not be deleted.', 'warning');
        redirect(base_url('admin/courses/index.php'));
    }

    flash_message('Course deleted successfully.', 'success');
} catch (Throwable $throwable) {
    flash_message('The course could not be deleted. Please try again.', 'danger');
}

redirect(base_url('admin/courses/index.php'));
