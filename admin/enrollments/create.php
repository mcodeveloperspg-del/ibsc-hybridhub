<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$user = current_user();
$flash = flash_message();
$formState = consume_form_state('enrollment_form_state', ['errors' => [], 'data' => []]);
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];
$transferFormState = consume_form_state('transfer_enrollment_form_state', ['errors' => [], 'data' => []]);
$transferErrors = $transferFormState['errors'] ?? [];
$transferOldData = $transferFormState['data'] ?? [];
$importPreview = $_SESSION['enrollment_import_preview'] ?? null;
$selectedStudentId = (int) ($oldData['student_id'] ?? ($_GET['student_id'] ?? 0));

$counts = $pdo->query("SELECT COUNT(*) AS total_enrollments, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_enrollments, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_enrollments, SUM(CASE WHEN status='withdrawn' THEN 1 ELSE 0 END) AS withdrawn_enrollments FROM enrollments")->fetch() ?: [];

$studentsSql = "SELECT users.id, users.student_number, users.first_name, users.last_name, users.email,
        COUNT(enrollments.id) AS enrollment_count
    FROM users
    INNER JOIN roles ON roles.id = users.role_id
    LEFT JOIN enrollments ON enrollments.student_id = users.id
    WHERE roles.name = 'student'";
$params = [];

$studentsSql .= '
    GROUP BY users.id, users.student_number, users.first_name, users.last_name, users.email
    ORDER BY users.first_name ASC, users.last_name ASC';
$studentsStatement = $pdo->prepare($studentsSql);
$studentsStatement->execute($params);
$students = $studentsStatement->fetchAll();

$courses = $pdo->query('SELECT id, title, course_code FROM courses ORDER BY title ASC')->fetchAll();
$currentEnrollments = $pdo->query(
    "SELECT enrollments.id, enrollments.student_id, enrollments.course_id, enrollments.batch_id,
            users.student_number, users.first_name, users.last_name, users.email,
            courses.title AS course_title, courses.course_code,
            batches.batch_name, batches.batch_year
     FROM enrollments
     INNER JOIN users ON users.id = enrollments.student_id
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN batches ON batches.id = enrollments.batch_id
     WHERE enrollments.status IN ('active', 'suspended')
     ORDER BY users.first_name ASC, users.last_name ASC, courses.title ASC"
)->fetchAll();
$batches = $pdo->query(
    "SELECT batches.id, batches.batch_name, batches.batch_number, batches.batch_year, batches.intake_code, batches.status,
            COUNT(batch_courses.course_id) AS course_count,
            GROUP_CONCAT(batch_courses.course_id ORDER BY batch_courses.course_id ASC SEPARATOR ',') AS course_ids,
            GROUP_CONCAT(CONCAT(courses.title, ' (', courses.course_code, ')') ORDER BY courses.title ASC SEPARATOR ', ') AS course_list
     FROM batches
     LEFT JOIN batch_courses ON batch_courses.batch_id = batches.id
     LEFT JOIN courses ON courses.id = batch_courses.course_id
     GROUP BY batches.id, batches.batch_name, batches.batch_number, batches.batch_year, batches.intake_code, batches.status
     ORDER BY batches.batch_year DESC, batches.batch_number ASC"
)->fetchAll();

$pageTitle = 'Enroll Students - ' . APP_NAME;
$pageHeading = 'Enroll Students';
$pageDescription = 'Create new enrollments, transfer students between batches, or import batch enrollments in bulk from a CSV file.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Total Enrollments</div><div class="metric-value"><?php echo e((string) ($counts['total_enrollments'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Active</div><div class="metric-value"><?php echo e((string) ($counts['active_enrollments'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Completed</div><div class="metric-value"><?php echo e((string) ($counts['completed_enrollments'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Withdrawn</div><div class="metric-value"><?php echo e((string) ($counts['withdrawn_enrollments'] ?? 0)); ?></div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-5 d-flex flex-column gap-4">
        <div class="surface-card p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-2">Bulk Import From CSV</h2>
                    <p class="text-muted mb-0">Upload a CSV exported from your other system. The importer will polish the data, match courses and batches, then pause for confirmation before saving.</p>
                </div>
                <a href="<?php echo e(base_url('admin/enrollments/index.php')); ?>" class="btn btn-outline-secondary">Back to Enrollments</a>
            </div>
            <form action="<?php echo e(base_url('actions/admin/preview_enrollment_import.php')); ?>" method="POST" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Course</label>
                    <select class="form-select" id="import_course_id" name="course_id" required>
                        <option value="">Select course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo e((string) $course['id']); ?>"><?php echo e($course['title'] . ' (' . $course['course_code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Batch</label>
                    <select class="form-select" id="import_batch_id" name="batch_id" required>
                        <option value="">Select batch</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo e((string) $batch['id']); ?>" data-course-ids="<?php echo e((string) ($batch['course_ids'] ?? '')); ?>" data-course-list="<?php echo e((string) ($batch['course_list'] ?? '')); ?>"><?php echo e($batch['batch_name'] . ' (' . $batch['batch_year'] . ') - ' . (string) ($batch['course_count'] ?? 0) . ' courses'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="importBatchHelp" class="form-text">Choose a course first to see matching batches.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">CSV File</label>
                    <input type="file" class="form-control" name="enrollment_csv" accept=".csv,text/csv" required>
                </div>
                <div class="col-12">
                    <div class="form-text">CSV columns only: Student ID, Student Name, Email ID, Mobile, Date of Birth.</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Preview Import</button>
                </div>
            </form>
        </div>

        <div class="surface-card p-4">
            <h2 class="h5 mb-3">Manual Enrollment</h2>
            <?php if ($errors !== []): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?php echo e(base_url('actions/admin/save_enrollment.php')); ?>" method="POST" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Student</label>
                    <select class="form-select" name="student_id" required>
                        <option value="">Select student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo e((string) $student['id']); ?>" <?php echo (string) ($oldData['student_id'] ?? '') === (string) $student['id'] ? 'selected' : ''; ?>><?php echo e(trim($student['first_name'] . ' ' . $student['last_name']) . ' - ' . (string) ($student['student_number'] ?: $student['email']) . ((int) ($student['enrollment_count'] ?? 0) > 0 ? ' - currently enrolled' : '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Use this form only for students who do not already have an enrollment. Use Transfer Student below to move an enrolled student between batches.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course</label>
                    <select class="form-select" id="course_id" name="course_id" required>
                        <option value="">Select course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo e((string) $course['id']); ?>" <?php echo (string) ($oldData['course_id'] ?? '') === (string) $course['id'] ? 'selected' : ''; ?>><?php echo e($course['title'] . ' (' . $course['course_code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Batch</label>
                    <select class="form-select" id="batch_id" name="batch_id" required>
                        <option value="">Select batch</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo e((string) $batch['id']); ?>" data-course-ids="<?php echo e((string) ($batch['course_ids'] ?? '')); ?>" data-course-list="<?php echo e((string) ($batch['course_list'] ?? '')); ?>" <?php echo (string) ($oldData['batch_id'] ?? '') === (string) $batch['id'] ? 'selected' : ''; ?>><?php echo e($batch['batch_name'] . ' (' . $batch['batch_year'] . ') - ' . (string) ($batch['course_count'] ?? 0) . ' courses'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="batchHelp" class="form-text">Choose a batch that already contains the selected course.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Enrollment Date</label>
                    <input type="date" class="form-control" name="enrollment_date" value="<?php echo e((string) ($oldData['enrollment_date'] ?? date('Y-m-d'))); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" required>
                        <?php foreach (['active', 'completed', 'suspended', 'withdrawn'] as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo (string) ($oldData['status'] ?? 'active') === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Save Enrollment</button>
                </div>
            </form>
        </div>

        <div class="surface-card p-4 h-100">
            <h2 class="h5 mb-3">Transfer Student</h2>
            <?php if ($transferErrors !== []): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($transferErrors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?php echo e(base_url('actions/admin/transfer_enrollment.php')); ?>" method="POST" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Current Enrollment</label>
                    <select class="form-select" id="transfer_enrollment_id" name="enrollment_id" required>
                        <option value="">Select current enrollment</option>
                        <?php foreach ($currentEnrollments as $currentEnrollment): ?>
                            <?php
                                $studentLabel = trim($currentEnrollment['first_name'] . ' ' . $currentEnrollment['last_name']);
                                $studentIdentifier = (string) ($currentEnrollment['student_number'] ?: $currentEnrollment['email']);
                                $courseLabel = $currentEnrollment['course_title'] . ' (' . $currentEnrollment['course_code'] . ')';
                                $batchLabel = $currentEnrollment['batch_name'] . ' (' . $currentEnrollment['batch_year'] . ')';
                            ?>
                            <option
                                value="<?php echo e((string) $currentEnrollment['id']); ?>"
                                data-course-id="<?php echo e((string) $currentEnrollment['course_id']); ?>"
                                data-current-batch-id="<?php echo e((string) $currentEnrollment['batch_id']); ?>"
                                <?php echo (string) ($transferOldData['enrollment_id'] ?? ($_GET['transfer_enrollment_id'] ?? '')) === (string) $currentEnrollment['id'] ? 'selected' : ''; ?>
                            >
                                <?php echo e($studentLabel . ' - ' . $studentIdentifier . ' - ' . $courseLabel . ' / ' . $batchLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Transfer updates the existing enrollment record instead of creating a second batch enrollment.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Destination Batch</label>
                    <select class="form-select" id="transfer_batch_id" name="batch_id" required>
                        <option value="">Select destination batch</option>
                        <?php foreach ($batches as $batch): ?>
                            <option
                                value="<?php echo e((string) $batch['id']); ?>"
                                data-course-ids="<?php echo e((string) ($batch['course_ids'] ?? '')); ?>"
                                data-course-list="<?php echo e((string) ($batch['course_list'] ?? '')); ?>"
                                <?php echo (string) ($transferOldData['batch_id'] ?? '') === (string) $batch['id'] ? 'selected' : ''; ?>
                            >
                                <?php echo e($batch['batch_name'] . ' (' . $batch['batch_year'] . ') - ' . (string) ($batch['course_count'] ?? 0) . ' courses'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="transferBatchHelp" class="form-text">Choose an enrollment first to see valid destination batches.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Transfer Date</label>
                    <input type="date" class="form-control" name="transfer_date" value="<?php echo e((string) ($transferOldData['transfer_date'] ?? date('Y-m-d'))); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status After Transfer</label>
                    <select class="form-select" name="status" required>
                        <?php foreach (['active', 'completed', 'suspended', 'withdrawn'] as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo (string) ($transferOldData['status'] ?? 'active') === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Transfer Student</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-7">
        <?php if (is_array($importPreview) && isset($importPreview['rows']) && is_array($importPreview['rows'])): ?>
            <?php $previewSummary = $importPreview['summary'] ?? []; ?>
            <div class="surface-card p-4 h-100">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Import Preview</h2>
                        <p class="text-muted mb-0">File: <?php echo e((string) ($importPreview['file_name'] ?? 'CSV upload')); ?>. Target: <?php echo e((string) ($importPreview['course_label'] ?? 'Selected course')); ?> / <?php echo e((string) ($importPreview['batch_label'] ?? 'Selected batch')); ?>.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <form action="<?php echo e(base_url('actions/admin/confirm_enrollment_import.php')); ?>" method="POST">
                            <button type="submit" class="btn btn-success" <?php echo (int) ($previewSummary['blocking_rows'] ?? 0) > 0 ? 'disabled' : ''; ?>>Confirm Import</button>
                        </form>
                        <form action="<?php echo e(base_url('actions/admin/clear_enrollment_import_preview.php')); ?>" method="POST">
                            <button type="submit" class="btn btn-outline-secondary">Clear Preview</button>
                        </form>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6 col-xl-2"><div class="surface-card border p-3 h-100"><div class="small text-muted">Rows</div><div class="h4 mb-0"><?php echo e((string) ($previewSummary['total_rows'] ?? 0)); ?></div></div></div>
                    <div class="col-sm-6 col-xl-2"><div class="surface-card border p-3 h-100"><div class="small text-muted">Ready</div><div class="h4 mb-0"><?php echo e((string) ($previewSummary['valid_rows'] ?? 0)); ?></div></div></div>
                    <div class="col-sm-6 col-xl-2"><div class="surface-card border p-3 h-100"><div class="small text-muted">Blocked</div><div class="h4 mb-0"><?php echo e((string) ($previewSummary['blocking_rows'] ?? 0)); ?></div></div></div>
                    <div class="col-sm-6 col-xl-2"><div class="surface-card border p-3 h-100"><div class="small text-muted">New Students</div><div class="h4 mb-0"><?php echo e((string) ($previewSummary['new_students'] ?? 0)); ?></div></div></div>
                    <div class="col-sm-6 col-xl-2"><div class="surface-card border p-3 h-100"><div class="small text-muted">Existing Students</div><div class="h4 mb-0"><?php echo e((string) ($previewSummary['existing_students'] ?? 0)); ?></div></div></div>
                    <div class="col-sm-6 col-xl-2"><div class="surface-card border p-3 h-100"><div class="small text-muted">Transfers</div><div class="h4 mb-0"><?php echo e((string) ($previewSummary['transfers'] ?? 0)); ?></div></div></div>
                </div>

                <?php if ((int) ($previewSummary['blocking_rows'] ?? 0) > 0): ?>
                    <div class="alert alert-warning">Some rows are blocked. Clear the preview, correct the CSV, and upload it again before confirming.</div>
                <?php else: ?>
                    <div class="alert alert-success">Everything looks valid. Confirm the import to create new student accounts, reuse existing accounts without changing them, and enroll or transfer students automatically.</div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Source Student</th>
                                <th>Polished Account</th>
                                <th>Course and Batch</th>
                                <th>Import Action</th>
                                <th>Issues</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($importPreview['rows'] as $previewRow): ?>
                                <tr>
                                    <td><?php echo e((string) ($previewRow['row_number'] ?? '')); ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e((string) ($previewRow['student_name'] ?? '')); ?></div>
                                        <div class="small text-muted">Student ID: <?php echo e((string) ($previewRow['student_id'] ?? '')); ?></div>
                                        <div class="small text-muted"><?php echo e((string) ($previewRow['email'] ?? '')); ?></div>
                                        <div class="small text-muted">Mobile: <?php echo e((string) ($previewRow['mobile'] ?? '')); ?></div>
                                        <div class="small text-muted">DOB: <?php echo e((string) ($previewRow['date_of_birth'] ?? '')); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e(trim((string) ($previewRow['first_name'] ?? '') . ' ' . (string) ($previewRow['last_name'] ?? ''))); ?></div>
                                        <div class="small text-muted">Action: <?php echo e((string) ($previewRow['student_action'] ?? '')); ?></div>
                                        <?php if ((string) ($previewRow['temp_password'] ?? '') !== ''): ?>
                                            <div class="small text-muted">Temporary password: <?php echo e((string) $previewRow['temp_password']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e((string) ($previewRow['matched_course_label'] ?? 'Unmatched')); ?></div>
                                        <div class="small text-muted"><?php echo e((string) ($previewRow['matched_batch_label'] ?? 'No batch selected')); ?></div>
                                        <div class="small text-muted">Enrollment: <?php echo e((string) ($previewRow['enrollment_action'] ?? '')); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-<?php echo !empty($previewRow['is_blocking']) ? 'warning' : 'success'; ?>">
                                            <?php echo !empty($previewRow['is_blocking']) ? 'Needs fix' : 'Ready'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($previewRow['issues']) && is_array($previewRow['issues'])): ?>
                                            <ul class="mb-0 ps-3">
                                                <?php foreach ($previewRow['issues'] as $issue): ?>
                                                    <li><?php echo e((string) $issue); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php elseif (!empty($previewRow['notices']) && is_array($previewRow['notices'])): ?>
                                            <ul class="mb-0 ps-3 text-muted small">
                                                <?php foreach ($previewRow['notices'] as $notice): ?>
                                                    <li><?php echo e((string) $notice); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <span class="text-muted small">No issues</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="surface-card p-4 h-100 d-flex align-items-center justify-content-center text-center">
                <div>
                    <h2 class="h5 mb-2">No Import Preview Yet</h2>
                    <p class="text-muted mb-0">Upload a CSV on this page to preview cleaned student records before they are inserted into the LMS.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
(() => {
    const courseSelect = document.getElementById('course_id');
    const batchSelect = document.getElementById('batch_id');
    const batchHelp = document.getElementById('batchHelp');

    if (!courseSelect || !batchSelect || !batchHelp) {
        return;
    }

    const updateBatchOptions = () => {
        const selectedCourseId = courseSelect.value;
        const options = Array.from(batchSelect.options).slice(1);
        let firstAvailable = '';

        options.forEach((option) => {
            const courseIds = (option.dataset.courseIds || '').split(',').filter(Boolean);
            const matchesCourse = selectedCourseId === '' || courseIds.includes(selectedCourseId);
            option.hidden = !matchesCourse;
            option.disabled = !matchesCourse;

            if (matchesCourse && firstAvailable === '') {
                firstAvailable = option.value;
            }
        });

        if (batchSelect.value !== '' && batchSelect.selectedOptions.length > 0 && batchSelect.selectedOptions[0].disabled) {
            batchSelect.value = '';
        }

        if (selectedCourseId === '') {
            batchHelp.textContent = 'Choose a course first to see the matching batches.';
            return;
        }

        if (batchSelect.value === '' && firstAvailable !== '') {
            batchSelect.value = firstAvailable;
        }

        const selectedOption = batchSelect.selectedOptions[0];
        if (selectedOption && selectedOption.value !== '') {
            batchHelp.textContent = selectedOption.dataset.courseList ? 'Courses in this batch: ' + selectedOption.dataset.courseList : 'This batch is ready for the selected course.';
        } else {
            batchHelp.textContent = 'No available batch currently includes the selected course.';
        }
    };

    courseSelect.addEventListener('change', updateBatchOptions);
    batchSelect.addEventListener('change', updateBatchOptions);
    updateBatchOptions();
})();

(() => {
    const courseSelect = document.getElementById('import_course_id');
    const batchSelect = document.getElementById('import_batch_id');
    const batchHelp = document.getElementById('importBatchHelp');

    if (!courseSelect || !batchSelect || !batchHelp) {
        return;
    }

    const updateBatchOptions = () => {
        const selectedCourseId = courseSelect.value;
        const options = Array.from(batchSelect.options).slice(1);
        let firstAvailable = '';

        options.forEach((option) => {
            const courseIds = (option.dataset.courseIds || '').split(',').filter(Boolean);
            const matchesCourse = selectedCourseId === '' || courseIds.includes(selectedCourseId);
            option.hidden = !matchesCourse;
            option.disabled = !matchesCourse;

            if (matchesCourse && firstAvailable === '') {
                firstAvailable = option.value;
            }
        });

        if (batchSelect.value !== '' && batchSelect.selectedOptions.length > 0 && batchSelect.selectedOptions[0].disabled) {
            batchSelect.value = '';
        }

        if (selectedCourseId === '') {
            batchHelp.textContent = 'Choose a course first to see matching batches.';
            return;
        }

        if (batchSelect.value === '' && firstAvailable !== '') {
            batchSelect.value = firstAvailable;
        }

        const selectedOption = batchSelect.selectedOptions[0];
        if (selectedOption && selectedOption.value !== '') {
            batchHelp.textContent = selectedOption.dataset.courseList ? 'Courses in this batch: ' + selectedOption.dataset.courseList : 'This batch is ready for the selected course.';
        } else {
            batchHelp.textContent = 'No available batch currently includes the selected course.';
        }
    };

    courseSelect.addEventListener('change', updateBatchOptions);
    batchSelect.addEventListener('change', updateBatchOptions);
    updateBatchOptions();
})();

(() => {
    const enrollmentSelect = document.getElementById('transfer_enrollment_id');
    const batchSelect = document.getElementById('transfer_batch_id');
    const batchHelp = document.getElementById('transferBatchHelp');

    if (!enrollmentSelect || !batchSelect || !batchHelp) {
        return;
    }

    const updateTransferBatchOptions = () => {
        const selectedEnrollment = enrollmentSelect.selectedOptions[0];
        const selectedCourseId = selectedEnrollment ? (selectedEnrollment.dataset.courseId || '') : '';
        const currentBatchId = selectedEnrollment ? (selectedEnrollment.dataset.currentBatchId || '') : '';
        const options = Array.from(batchSelect.options).slice(1);
        let firstAvailable = '';

        options.forEach((option) => {
            const courseIds = (option.dataset.courseIds || '').split(',').filter(Boolean);
            const matchesCourse = selectedCourseId !== '' && courseIds.includes(selectedCourseId);
            const isCurrentBatch = currentBatchId !== '' && option.value === currentBatchId;
            const isAvailable = matchesCourse && !isCurrentBatch;
            option.hidden = !isAvailable;
            option.disabled = !isAvailable;

            if (isAvailable && firstAvailable === '') {
                firstAvailable = option.value;
            }
        });

        if (batchSelect.value !== '' && batchSelect.selectedOptions.length > 0 && batchSelect.selectedOptions[0].disabled) {
            batchSelect.value = '';
        }

        if (selectedCourseId === '') {
            batchHelp.textContent = 'Choose an enrollment first to see valid destination batches.';
            return;
        }

        if (batchSelect.value === '' && firstAvailable !== '') {
            batchSelect.value = firstAvailable;
        }

        const selectedOption = batchSelect.selectedOptions[0];
        if (selectedOption && selectedOption.value !== '') {
            batchHelp.textContent = selectedOption.dataset.courseList ? 'Courses in this batch: ' + selectedOption.dataset.courseList : 'This batch can receive the selected enrollment.';
        } else {
            batchHelp.textContent = 'No other batch currently includes this enrollment course.';
        }
    };

    enrollmentSelect.addEventListener('change', updateTransferBatchOptions);
    batchSelect.addEventListener('change', updateTransferBatchOptions);
    updateTransferBatchOptions();
})();
</script>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>

