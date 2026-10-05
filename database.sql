CREATE DATABASE IF NOT EXISTS chebara_counselling;
USE chebara_counselling;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(150) NOT NULL UNIQUE,
 phone VARCHAR(30) DEFAULT NULL,
 password VARCHAR(255) NOT NULL,
 role ENUM('student','counsellor','admin') NOT NULL DEFAULT 'student',
 status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_users_role(role),
 INDEX idx_users_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE counsellor_profiles (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL UNIQUE,
 specialization VARCHAR(150) DEFAULT NULL,
 bio TEXT DEFAULT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE appointment_slots (
 id INT AUTO_INCREMENT PRIMARY KEY,
 counsellor_id INT NOT NULL,
 appointment_date DATE NOT NULL,
 start_time TIME NOT NULL,
 end_time TIME NOT NULL,
 status ENUM('available','booked','blocked') NOT NULL DEFAULT 'available',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(counsellor_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_slots_date(appointment_date),
 INDEX idx_slots_counsellor(counsellor_id),
 UNIQUE KEY uq_counsellor_slot(counsellor_id,appointment_date,start_time,end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE appointments (
 id INT AUTO_INCREMENT PRIMARY KEY,
 student_id INT NOT NULL,
 counsellor_id INT NOT NULL,
 slot_id INT NOT NULL,
 reason VARCHAR(255) DEFAULT NULL,
 status ENUM('pending','confirmed','cancelled','reschedule_requested','completed','rejected') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(counsellor_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(slot_id) REFERENCES appointment_slots(id) ON DELETE RESTRICT,
 INDEX idx_appointments_student(student_id),
 INDEX idx_appointments_counsellor(counsellor_id),
 INDEX idx_appointments_status(status),
 INDEX idx_appointments_slot(slot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 appointment_id INT DEFAULT NULL,
 title VARCHAR(150) NOT NULL,
 message TEXT NOT NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
 INDEX idx_notifications_user(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_logs (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT DEFAULT NULL,
 action VARCHAR(100) NOT NULL,
 description TEXT DEFAULT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create your first admin manually after importing:
-- INSERT INTO users(full_name,email,password,role,status)
-- VALUES ('System Administrator','admin@example.com','REPLACE_WITH_PASSWORD_HASH','admin','active');
-- Generate the password hash with PHP password_hash(), not plain text.
