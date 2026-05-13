USE hybrid_learning_hub;

INSERT INTO roles (name, description) VALUES
('system_admin', 'Full system administrator'),
('technical_officer', 'Manages operational release of learning content'),
('teacher', 'Uploads lecture materials and manages teaching resources'),
('student', 'Views enrolled and unlocked learning materials');

INSERT INTO users (role_id, first_name, last_name, email, password_hash, phone, gender, status) VALUES
((SELECT id FROM roles WHERE name = 'system_admin'), 'Grace', 'Admin', 'admin@hybridhub.local', '$2y$10$Ei.dF26CIbyfnTbmP4kll.1RK0zPD.Cq71LbD9JxMSaHlVOKALBZG', '70000001', 'female', 'active'),
((SELECT id FROM roles WHERE name = 'technical_officer'), 'Daniel', 'Tech', 'tech@hybridhub.local', '$2y$10$Ei.dF26CIbyfnTbmP4kll.1RK0zPD.Cq71LbD9JxMSaHlVOKALBZG', '70000002', 'male', 'active'),
((SELECT id FROM roles WHERE name = 'teacher'), 'Miriam', 'Lecturer', 'teacher@hybridhub.local', '$2y$10$Ei.dF26CIbyfnTbmP4kll.1RK0zPD.Cq71LbD9JxMSaHlVOKALBZG', '70000003', 'female', 'active'),
((SELECT id FROM roles WHERE name = 'student'), 'John', 'Student', 'student1@hybridhub.local', '$2y$10$Ei.dF26CIbyfnTbmP4kll.1RK0zPD.Cq71LbD9JxMSaHlVOKALBZG', '70000004', 'male', 'active'),
((SELECT id FROM roles WHERE name = 'student'), 'Ruth', 'Student', 'student2@hybridhub.local', '$2y$10$Ei.dF26CIbyfnTbmP4kll.1RK0zPD.Cq71LbD9JxMSaHlVOKALBZG', '70000005', 'female', 'active');

INSERT INTO courses (course_code, title, description, duration_months, total_stages, weeks_per_stage, status, created_by) VALUES
('DIP-CS', 'Diploma in Computer Studies', 'A six-month controlled hybrid learning course for computing students.', 6, 4, 6, 'active', 1);

INSERT INTO stages (course_id, stage_number, title, description, week_start, week_end, status) VALUES
(1, 1, 'Stage 1', 'Weeks 1 to 6 foundation stage.', 1, 6, 'active'),
(1, 2, 'Stage 2', 'Weeks 7 to 12 development stage.', 7, 12, 'active'),
(1, 3, 'Stage 3', 'Weeks 13 to 18 applied practice stage.', 13, 18, 'active'),
(1, 4, 'Stage 4', 'Weeks 19 to 24 completion stage.', 19, 24, 'active');

INSERT INTO batches (batch_number, batch_year, batch_name, intake_code, start_date, end_date, status, notes) VALUES
(1, 2026, 'Batch 1', 'BATCH-1-2026', '2026-01-12', '2026-06-30', 'active', 'Primary intake for first semester'),
(2, 2026, 'Batch 2', 'BATCH-2-2026', '2026-07-06', '2026-12-18', 'planned', 'Second intake for the same year');

INSERT INTO batch_courses (batch_id, course_id) VALUES
(1, 1),
(2, 1);

INSERT INTO teacher_course_assignments (teacher_id, course_id, batch_id, assigned_by, status) VALUES
(3, 1, 1, 1, 'active');

INSERT INTO units (course_id, stage_id, unit_title, unit_code, description, sort_order, status) VALUES
(1, 1, 'Introduction to Computing', 'ICS101', 'Basic concepts of computer systems and digital learning.', 1, 'active'),
(1, 1, 'Learning Tools and Platforms', 'ICS102', 'Digital learning tools and institutional learning access practices.', 2, 'active'),
(1, 2, 'Programming Essentials', 'ICS201', 'Core programming concepts delivered during stage 2.', 1, 'active');

INSERT INTO lecturer_unit_assignments (lecturer_id, batch_id, unit_id, assigned_by, status) VALUES
(3, 1, 3, 1, 'active');

INSERT INTO topics (unit_id, topic_title, description, sort_order, is_unlocked, unlocked_at, unlocked_by, status) VALUES
(1, 'Computer Fundamentals', 'Introduction to computer concepts and system components.', 1, 1, NOW(), 2, 'active'),
(1, 'Data and Information', 'Understanding data representation and information flow.', 2, 0, NULL, NULL, 'active'),
(2, 'Using the Learning Hub', 'How students interact with controlled learning content.', 1, 0, NULL, NULL, 'active'),
(3, 'Introduction to Programming Logic', 'Covers variables, decisions, and flow control.', 1, 0, NULL, NULL, 'active');

INSERT INTO sessions (
    topic_id,
    session_title,
    session_summary,
    session_date,
    video_provider,
    video_url,
    video_embed_url,
    duration_minutes,
    session_type,
    sort_order,
    is_unlocked,
    unlocked_at,
    unlocked_by,
    status,
    created_by
) VALUES
(1, 'Introduction to Computer Hardware', 'Covers the main hardware components and their roles.', '2026-01-14', 'youtube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 45, 'regular', 1, 1, NOW(), 2, 'published', 3),
(1, 'Computer Software Basics', 'Explains system software and application software.', '2026-01-16', 'youtube', 'https://www.youtube.com/watch?v=ysz5S6PUM-U', 'https://www.youtube.com/embed/ysz5S6PUM-U', 40, 'regular', 2, 1, NOW(), 2, 'published', 3),
(2, 'Data vs Information', 'Introduces the difference between raw data and processed information.', '2026-01-20', 'youtube', 'https://www.youtube.com/watch?v=ScMzIvxBSi4', 'https://www.youtube.com/embed/ScMzIvxBSi4', 35, 'replacement', 1, 0, NULL, NULL, 'published', 3),
(3, 'Student Orientation to the Platform', 'Shows how controlled access works inside the learning hub.', '2026-01-22', 'external_link', 'https://example.com/hybrid-learning-orientation', NULL, 30, 'regular', 1, 0, NULL, NULL, 'draft', 3),
(4, 'Programming Logic Walkthrough', 'Introduces the foundations of writing simple program logic.', '2026-03-24', 'youtube', 'https://www.youtube.com/watch?v=ysz5S6PUM-U', 'https://www.youtube.com/embed/ysz5S6PUM-U', 50, 'regular', 1, 0, NULL, NULL, 'published', 3);

INSERT INTO session_resources (
    session_id,
    uploaded_by,
    resource_title,
    resource_type,
    file_name,
    file_path,
    external_url,
    file_size,
    mime_type,
    status
) VALUES
(1, 3, 'Hardware Lecture Slides', 'slide', 'hardware-introduction.pdf', 'uploads/slides/hardware-introduction.pdf', NULL, 1250000, 'application/pdf', 'active'),
(1, 3, 'Hardware Worksheet', 'worksheet', 'hardware-worksheet.pdf', 'uploads/resources/hardware-worksheet.pdf', NULL, 850000, 'application/pdf', 'active'),
(2, 3, 'Software Basics Slides', 'slide', 'software-basics.pdf', 'uploads/slides/software-basics.pdf', NULL, 1100000, 'application/pdf', 'active');

INSERT INTO enrollments (student_id, course_id, batch_id, enrollment_date, status, created_by) VALUES
(4, 1, 1, '2026-01-13', 'active', 1),
(5, 1, 1, '2026-01-13', 'active', 1);

INSERT INTO watched_sessions (student_id, session_id, watched_at, progress_percent) VALUES
(4, 1, NOW(), 100),
(4, 2, NOW(), 70);

INSERT INTO unlock_logs (unlock_type, topic_id, session_id, unlocked_by, notes, unlocked_at) VALUES
('topic', 1, NULL, 2, 'Topic released after in-person delivery.', NOW()),
('session', NULL, 1, 2, 'Session released after topic coverage.', NOW()),
('session', NULL, 2, 2, 'Session released after topic coverage.', NOW());

INSERT INTO audit_logs (user_id, action_type, target_table, target_id, action_description, ip_address) VALUES
(1, 'create', 'courses', 1, 'Created Diploma in Computer Studies course.', '127.0.0.1'),
(2, 'unlock', 'topics', 1, 'Unlocked Computer Fundamentals topic.', '127.0.0.1'),
(2, 'unlock', 'sessions', 1, 'Unlocked Introduction to Computer Hardware session.', '127.0.0.1');
