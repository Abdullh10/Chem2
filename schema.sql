-- نظام تتبع خروج الطلاب - مخطط قاعدة البيانات
-- استورد هذا الملف في قاعدة بيانات MySQL/MariaDB فارغة قبل أول استخدام.

CREATE TABLE grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grade_id INT NOT NULL,
  name VARCHAR(50) NOT NULL,
  UNIQUE KEY uniq_grade_section (grade_id, name),
  FOREIGN KEY (grade_id) REFERENCES grades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','deputy','counselor','teacher') NOT NULL,
  grade_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (grade_id) REFERENCES grades(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE teacher_sections (
  teacher_id INT NOT NULL,
  section_id INT NOT NULL,
  PRIMARY KEY (teacher_id, section_id),
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE exit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  reason ENUM('حمام','ممرضة','إدارة','أخرى') NOT NULL,
  reason_note VARCHAR(255) NULL,
  recorded_by INT NOT NULL,
  returned_by INT NULL,
  out_time DATETIME NOT NULL,
  in_time DATETIME NULL,
  status ENUM('out','returned') NOT NULL DEFAULT 'out',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES users(id),
  FOREIGN KEY (returned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO grades (name, sort_order) VALUES
  ('أول ثانوي', 1),
  ('ثاني ثانوي', 2),
  ('ثالث ثانوي', 3);

-- حساب المدير الافتراضي: admin / admin123
-- مهم: غيّر كلمة المرور فوراً بعد أول تسجيل دخول من لوحة التحكم
INSERT INTO users (name, username, password_hash, role) VALUES
  ('مدير النظام', 'admin', '$2y$12$LBNgtjkfqxjJB7YRc4OggexnDr/qDrqcPqIif06unzA7zHwG5/cNm', 'admin');
