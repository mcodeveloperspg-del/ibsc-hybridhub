CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE app_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    path VARCHAR(255) NOT NULL,
    icon VARCHAR(100) DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    available_actions VARCHAR(255) NOT NULL,
    is_visible_nav TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_app_modules_sort_order (sort_order),
    INDEX idx_app_modules_is_visible_nav (is_visible_nav),
    INDEX idx_app_modules_is_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE role_module_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    can_manage TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_role_module_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_module_permissions_module FOREIGN KEY (module_id) REFERENCES app_modules(id) ON DELETE CASCADE,
    CONSTRAINT uq_role_module_permissions UNIQUE (role_id, module_id),
    INDEX idx_role_module_permissions_role (role_id),
    INDEX idx_role_module_permissions_module (module_id)
) ENGINE=InnoDB;
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    student_number VARCHAR(100) DEFAULT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    password_must_reset TINYINT(1) NOT NULL DEFAULT 0,
    phone VARCHAR(30) DEFAULT NULL,
    gender ENUM('male', 'female', 'other') DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_users_role_id (role_id),
    INDEX idx_users_status (status),
    INDEX idx_users_name (last_name, first_name)
) ENGINE=InnoDB;

CREATE TABLE courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    duration_months TINYINT UNSIGNED NOT NULL DEFAULT 6,
    total_stages TINYINT UNSIGNED NOT NULL DEFAULT 4,
    weeks_per_stage TINYINT UNSIGNED NOT NULL DEFAULT 6,
    status ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_courses_status (status)
) ENGINE=InnoDB;

CREATE TABLE stages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    stage_number TINYINT UNSIGNED NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    week_start TINYINT UNSIGNED NOT NULL,
    week_end TINYINT UNSIGNED NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stages_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT uq_stages_course_stage UNIQUE (course_id, stage_number),
    INDEX idx_stages_course_id (course_id)
) ENGINE=InnoDB;

CREATE TABLE batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_number TINYINT UNSIGNED NOT NULL,
    batch_year SMALLINT UNSIGNED NOT NULL,
    batch_name VARCHAR(100) NOT NULL,
    intake_code VARCHAR(100) NOT NULL UNIQUE,
    total_stages TINYINT UNSIGNED NOT NULL DEFAULT 4,
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    status ENUM('planned', 'active', 'completed', 'archived') NOT NULL DEFAULT 'planned',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_batches_year_number UNIQUE (batch_year, batch_number),
    INDEX idx_batches_year (batch_year),
    INDEX idx_batches_status (status)
) ENGINE=InnoDB;

CREATE TABLE batch_stages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    stage_number TINYINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_batch_stages_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT uq_batch_stages_batch_stage UNIQUE (batch_id, stage_number),
    INDEX idx_batch_stages_batch_id (batch_id),
    INDEX idx_batch_stages_dates (start_date, end_date)
) ENGINE=InnoDB;

CREATE TABLE batch_courses (
    batch_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (batch_id, course_id),
    CONSTRAINT fk_batch_courses_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_batch_courses_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_batch_courses_course_id (course_id)
) ENGINE=InnoDB;

CREATE TABLE teacher_course_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED DEFAULT NULL,
    assigned_by INT UNSIGNED DEFAULT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    CONSTRAINT fk_teacher_course_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_teacher_course_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_teacher_course_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_teacher_course_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT uq_teacher_course_assignment UNIQUE (teacher_id, course_id, batch_id),
    INDEX idx_teacher_assignments_teacher_id (teacher_id),
    INDEX idx_teacher_assignments_course_id (course_id),
    INDEX idx_teacher_assignments_batch_id (batch_id)
) ENGINE=InnoDB;

CREATE TABLE units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    stage_id INT UNSIGNED NOT NULL,
    unit_title VARCHAR(150) NOT NULL,
    unit_code VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_units_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_units_stage FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE CASCADE,
    CONSTRAINT uq_units_course_title UNIQUE (course_id, unit_title),
    INDEX idx_units_course_id (course_id),
    INDEX idx_units_stage_id (stage_id),
    INDEX idx_units_sort_order (sort_order)
) ENGINE=InnoDB;

CREATE TABLE lecturer_unit_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lecturer_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL,
    unit_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lecturer_unit_assignment_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_lecturer_unit_assignment_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_lecturer_unit_assignment_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    CONSTRAINT fk_lecturer_unit_assignment_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT uq_lecturer_unit_batch UNIQUE (batch_id, unit_id),
    INDEX idx_lecturer_unit_assignment_lecturer_id (lecturer_id),
    INDEX idx_lecturer_unit_assignment_batch_id (batch_id),
    INDEX idx_lecturer_unit_assignment_unit_id (unit_id),
    INDEX idx_lecturer_unit_assignment_status (status)
) ENGINE=InnoDB;

CREATE TABLE topics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unit_id INT UNSIGNED NOT NULL,
    topic_title VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    is_unlocked TINYINT(1) NOT NULL DEFAULT 0,
    unlocked_at DATETIME DEFAULT NULL,
    unlocked_by INT UNSIGNED DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_topics_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    CONSTRAINT fk_topics_unlocked_by FOREIGN KEY (unlocked_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT uq_topics_unit_title UNIQUE (unit_id, topic_title),
    INDEX idx_topics_unit_id (unit_id),
    INDEX idx_topics_unlock (is_unlocked, unlocked_at),
    INDEX idx_topics_sort_order (sort_order)
) ENGINE=InnoDB;

CREATE TABLE sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED DEFAULT NULL,
    fallback_source_session_id INT UNSIGNED DEFAULT NULL,
    recording_source ENUM('current_batch','previous_session') NOT NULL DEFAULT 'current_batch',
    session_title VARCHAR(150) NOT NULL,
    session_summary TEXT DEFAULT NULL,
    session_date DATE DEFAULT NULL,
    video_provider ENUM('youtube', 'vimeo', 'external_link', 'internal_embed') NOT NULL DEFAULT 'youtube',
    video_url VARCHAR(255) DEFAULT NULL,
    video_embed_url VARCHAR(255) DEFAULT NULL,
    duration_minutes SMALLINT UNSIGNED DEFAULT NULL,
    session_type ENUM('regular', 'replacement', 'revision') NOT NULL DEFAULT 'regular',
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    is_unlocked TINYINT(1) NOT NULL DEFAULT 0,
    unlocked_at DATETIME DEFAULT NULL,
    unlocked_by INT UNSIGNED DEFAULT NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessions_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    CONSTRAINT fk_sessions_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_sessions_fallback_source FOREIGN KEY (fallback_source_session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    CONSTRAINT fk_sessions_unlocked_by FOREIGN KEY (unlocked_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_sessions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT uq_sessions_batch_topic_title UNIQUE (batch_id, topic_id, session_title),
    INDEX idx_sessions_topic_id (topic_id),
    INDEX idx_sessions_batch_id (batch_id),
    INDEX idx_sessions_fallback_source_id (fallback_source_session_id),
    INDEX idx_sessions_unlock (is_unlocked, unlocked_at),
    INDEX idx_sessions_status (status),
    INDEX idx_sessions_sort_order (sort_order)
) ENGINE=InnoDB;

CREATE TABLE session_resources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    resource_title VARCHAR(150) NOT NULL,
    resource_type ENUM('slide', 'document', 'worksheet', 'link', 'other') NOT NULL DEFAULT 'document',
    file_name VARCHAR(255) DEFAULT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    external_url VARCHAR(255) DEFAULT NULL,
    file_size INT UNSIGNED DEFAULT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_resources_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_resources_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_resources_session_id (session_id),
    INDEX idx_resources_uploaded_by (uploaded_by),
    INDEX idx_resources_type (resource_type)
) ENGINE=InnoDB;

CREATE TABLE enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL,
    enrollment_date DATE NOT NULL,
    status ENUM('active', 'completed', 'suspended', 'withdrawn') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT uq_enrollments_student UNIQUE (student_id),
    INDEX idx_enrollments_student_id (student_id),
    INDEX idx_enrollments_course_id (course_id),
    INDEX idx_enrollments_batch_id (batch_id),
    INDEX idx_enrollments_status (status)
) ENGINE=InnoDB;

CREATE TABLE watched_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NOT NULL,
    watched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 100,
    CONSTRAINT fk_watched_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_watched_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    CONSTRAINT uq_watched_student_session UNIQUE (student_id, session_id),
    INDEX idx_watched_student_id (student_id),
    INDEX idx_watched_session_id (session_id)
) ENGINE=InnoDB;

CREATE TABLE unlock_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unlock_type ENUM('topic', 'session') NOT NULL,
    topic_id INT UNSIGNED DEFAULT NULL,
    session_id INT UNSIGNED DEFAULT NULL,
    unlocked_by INT UNSIGNED NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_unlock_logs_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL,
    CONSTRAINT fk_unlock_logs_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    CONSTRAINT fk_unlock_logs_user FOREIGN KEY (unlocked_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_unlock_logs_type (unlock_type),
    INDEX idx_unlock_logs_topic_id (topic_id),
    INDEX idx_unlock_logs_session_id (session_id),
    INDEX idx_unlock_logs_unlocked_by (unlocked_by)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action_type VARCHAR(100) NOT NULL,
    target_table VARCHAR(100) DEFAULT NULL,
    target_id INT UNSIGNED DEFAULT NULL,
    action_description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_logs_user_id (user_id),
    INDEX idx_audit_logs_action_type (action_type),
    INDEX idx_audit_logs_created_at (created_at)
) ENGINE=InnoDB;





