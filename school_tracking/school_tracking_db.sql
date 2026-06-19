SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS school_tracking_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE school_tracking_db;


CREATE TABLE IF NOT EXISTS users (
    user_id         INT          NOT NULL AUTO_INCREMENT,
    username        VARCHAR(100) NOT NULL,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('student','parent','teacher','admin') NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    email           VARCHAR(150)          DEFAULT NULL,
    phone           VARCHAR(20)           DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    login_timestamp TIMESTAMP NULL        DEFAULT NULL,
    last_login      TIMESTAMP NULL        DEFAULT NULL,
    failed_attempts INT          NOT NULL DEFAULT 0,

    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email    (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS classes (
    class_id      INT          NOT NULL AUTO_INCREMENT,
    class_name    VARCHAR(100) NOT NULL,
    academic_year VARCHAR(20)  NOT NULL,
    schedule      TEXT                  DEFAULT NULL,
    capacity      INT                   DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
    student_id    INT         NOT NULL AUTO_INCREMENT,
    user_id       INT         NOT NULL,
    class_id      INT                  DEFAULT NULL,
    date_of_birth DATE                 DEFAULT NULL,
    gender        VARCHAR(10)          DEFAULT NULL,
    created_at    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (student_id),
    CONSTRAINT fk_students_user
        FOREIGN KEY (user_id)  REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_students_class
        FOREIGN KEY (class_id) REFERENCES classes(class_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teachers (
    teacher_id        INT          NOT NULL AUTO_INCREMENT,
    user_id           INT          NOT NULL,
    subject_specialty VARCHAR(100)          DEFAULT NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (teacher_id),
    CONSTRAINT fk_teachers_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Junction table: which student belongs to which class (many-to-many)
CREATE TABLE IF NOT EXISTS student_classes (
    student_id INT NOT NULL,
    class_id   INT NOT NULL,

    PRIMARY KEY (student_id, class_id),
    CONSTRAINT fk_studentclasses_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_studentclasses_class
        FOREIGN KEY (class_id) REFERENCES classes(class_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Junction table: which teacher is assigned to which class (many-to-many)
CREATE TABLE IF NOT EXISTS class_teachers (
    class_id   INT NOT NULL,
    teacher_id INT NOT NULL,

    PRIMARY KEY (class_id, teacher_id),
    CONSTRAINT fk_classteachers_class
        FOREIGN KEY (class_id) REFERENCES classes(class_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_classteachers_teacher
        FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS parents (
    parent_id    INT         NOT NULL AUTO_INCREMENT,
    user_id      INT         NOT NULL,
    student_id   INT         NOT NULL,
    relationship VARCHAR(50)          DEFAULT NULL,
    created_at   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (parent_id),
    CONSTRAINT fk_parents_user
        FOREIGN KEY (user_id)    REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_parents_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS assignments (
    assignment_id INT          NOT NULL AUTO_INCREMENT,
    teacher_id    INT          NOT NULL,
    class_id      INT          NOT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT                  DEFAULT NULL,
    file_path     VARCHAR(255)          DEFAULT NULL,
    deadline      DATETIME              DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (assignment_id),
    CONSTRAINT fk_assignments_teacher
        FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_assignments_class
        FOREIGN KEY (class_id)   REFERENCES classes(class_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT      NOT NULL AUTO_INCREMENT,
    student_id    INT      NOT NULL,
    teacher_id    INT      NOT NULL,
    class_id      INT      NOT NULL,
    date          DATE     NOT NULL,
    status        ENUM('present','absent','late') NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (attendance_id),
    UNIQUE KEY uq_attendance (student_id, class_id, date),
    CONSTRAINT fk_attendance_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_teacher
        FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_class
        FOREIGN KEY (class_id)   REFERENCES classes(class_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS grades (
    grade_id      INT          NOT NULL AUTO_INCREMENT,
    student_id    INT          NOT NULL,
    teacher_id    INT          NOT NULL,
    class_id      INT          NOT NULL,
    assignment_id INT                   DEFAULT NULL,
    subject       VARCHAR(100) NOT NULL,
    grade_value   DECIMAL(5,2) NOT NULL,
    exam_type     VARCHAR(50)           DEFAULT NULL,
    remarks       TEXT                  DEFAULT NULL,
    graded_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (grade_id),
    CONSTRAINT fk_grades_student
        FOREIGN KEY (student_id)    REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_grades_teacher
        FOREIGN KEY (teacher_id)    REFERENCES teachers(teacher_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_grades_class
        FOREIGN KEY (class_id)      REFERENCES classes(class_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_grades_assignment
        FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT          NOT NULL AUTO_INCREMENT,
    sender_id       INT          NOT NULL,
    recipient_id    INT          NULL     DEFAULT NULL,
    title           VARCHAR(200) NOT NULL,
    message         TEXT                  DEFAULT NULL,
    is_read         TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (notification_id),
    CONSTRAINT fk_notifications_sender
        FOREIGN KEY (sender_id)    REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_recipient
        FOREIGN KEY (recipient_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS assignment_submissions (
    submission_id INT          NOT NULL AUTO_INCREMENT,
    assignment_id INT          NOT NULL,
    student_id    INT          NOT NULL,
    file_path     VARCHAR(255)          DEFAULT NULL,
    submitted_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status        ENUM('submitted','graded','late') NOT NULL DEFAULT 'submitted',

    PRIMARY KEY (submission_id),
    UNIQUE KEY uq_submission (assignment_id, student_id),
    CONSTRAINT fk_submissions_assignment
        FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_submissions_student
        FOREIGN KEY (student_id)    REFERENCES students(student_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;


-- SEED DATA : Admin user
-- Password  : admin123  (bcrypt cost 10)
-- Regenerate: php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"

INSERT INTO users
    (username, password, role, full_name, email, phone)
VALUES
    (
        'admin',
        '$2y$10$c3dWnpXlmSyCYqrgX5dnhe9W4uExsIUAglvf/xP1G.z0oUXgmNrqe',
        'admin',
        'System Administrator',
        'admin@school.edu',
        NULL
    );
