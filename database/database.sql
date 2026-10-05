-- =========================================================
-- CHEBARA TVC ONLINE COUNSELLING BOOKING SYSTEM
-- Database: chebara_counselling
-- =========================================================

CREATE DATABASE IF NOT EXISTS chebara_counselling
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE chebara_counselling;

-- =========================================================
-- 1. USERS
-- =========================================================

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'counsellor', 'admin') NOT NULL DEFAULT 'student',
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- =========================================================
-- 2. COUNSELLOR PROFILES
-- =========================================================

CREATE TABLE counsellor_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    specialization VARCHAR(150) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_counsellor_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =========================================================
-- 3. APPOINTMENT SLOTS
-- =========================================================

CREATE TABLE appointment_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    counsellor_id INT UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('available', 'booked', 'blocked')
        NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_slot_counsellor
        FOREIGN KEY (counsellor_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY unique_counsellor_slot (
        counsellor_id,
        appointment_date,
        start_time,
        end_time
    )
);

-- =========================================================
-- 4. APPOINTMENTS
-- =========================================================

CREATE TABLE appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    counsellor_id INT UNSIGNED NOT NULL,
    slot_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    status ENUM(
        'pending',
        'confirmed',
        'cancelled',
        'reschedule_requested',
        'completed',
        'rejected'
    ) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_appointment_student
        FOREIGN KEY (student_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_appointment_counsellor
        FOREIGN KEY (counsellor_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_appointment_slot
        FOREIGN KEY (slot_id)
        REFERENCES appointment_slots(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- =========================================================
-- 5. NOTIFICATIONS
-- =========================================================

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notification_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_notification_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- =========================================================
-- 6. ACTIVITY LOGS
-- =========================================================

CREATE TABLE activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_activity_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- =========================================================
-- 7. INDEXES
-- =========================================================

CREATE INDEX idx_users_role
ON users(role);

CREATE INDEX idx_users_status
ON users(status);

CREATE INDEX idx_slots_date
ON appointment_slots(appointment_date);

CREATE INDEX idx_slots_status
ON appointment_slots(status);

CREATE INDEX idx_appointments_student
ON appointments(student_id);

CREATE INDEX idx_appointments_counsellor
ON appointments(counsellor_id);

CREATE INDEX idx_appointments_status
ON appointments(status);

CREATE INDEX idx_notifications_user
ON notifications(user_id);

-- =========================================================
-- END OF DATABASE
-- =========================================================
