-- ============================================================
--  School Tracking System — Test Data
--  Paste into phpMyAdmin > school_tracking_db > SQL tab
--  Passwords:  teacher1/teacher2 → teacher123
--              student1/2/3      → student123
--              parent1/parent2   → parent123
-- ============================================================

USE school_tracking_db;

\
INSERT INTO classes (class_name, academic_year, schedule, capacity) VALUES
('Class 10A', '2025-2026', 'Mon-Fri 08:00-14:00', 30),
('Class 10B', '2025-2026', 'Mon-Fri 08:00-14:00', 30);


INSERT INTO users (username, password, role, full_name, email, phone) VALUES
-- teachers
('teacher1', '$2y$10$CP4TBAuB.Y8rYaJnk1bwNeUIwgdcMed9.SRaEKwomTtwIoNoIqSq2', 'teacher', 'Mr. Ahmad Razali',   'teacher1@school.edu', '011-11111111'),
('teacher2', '$2y$10$CP4TBAuB.Y8rYaJnk1bwNeUIwgdcMed9.SRaEKwomTtwIoNoIqSq2', 'teacher', 'Ms. Siti Norzahra',  'teacher2@school.edu', '011-22222222'),
-- students
('student1', '$2y$10$hX2Gwc9ji7rmnTJsQMhZhuTIy3uJJLFC3rPDanwMbTucGi7ughpCq', 'student', 'Ali bin Hassan',     'student1@school.edu', NULL),
('student2', '$2y$10$hX2Gwc9ji7rmnTJsQMhZhuTIy3uJJLFC3rPDanwMbTucGi7ughpCq', 'student', 'Nurul Ain binti Yusof','student2@school.edu', NULL),
('student3', '$2y$10$hX2Gwc9ji7rmnTJsQMhZhuTIy3uJJLFC3rPDanwMbTucGi7ughpCq', 'student', 'Rajesh Kumar',       'student3@school.edu', NULL),
-- parents
('parent1',  '$2y$10$rHq2s8UHlxU/hNa.irTJT.xR74MUDDdC8avCPRSV2dmfm8LIo3abe', 'parent',  'Hassan bin Mahmud',  'parent1@school.edu',  '012-33333333'),
('parent2',  '$2y$10$rHq2s8UHlxU/hNa.irTJT.xR74MUDDdC8avCPRSV2dmfm8LIo3abe', 'parent',  'Yusof bin Ismail',   'parent2@school.edu',  '012-44444444');


INSERT INTO teachers (user_id, subject_specialty) VALUES
((SELECT user_id FROM users WHERE username='teacher1'), 'Mathematics & Science'),
((SELECT user_id FROM users WHERE username='teacher2'), 'English & Literature');


INSERT INTO students (user_id, class_id, date_of_birth, gender) VALUES
((SELECT user_id FROM users WHERE username='student1'), (SELECT class_id FROM classes WHERE class_name='Class 10A'), '2009-03-15', 'Male'),
((SELECT user_id FROM users WHERE username='student2'), (SELECT class_id FROM classes WHERE class_name='Class 10A'), '2009-07-22', 'Female'),
((SELECT user_id FROM users WHERE username='student3'), (SELECT class_id FROM classes WHERE class_name='Class 10B'), '2009-11-08', 'Male');


INSERT INTO parents (user_id, student_id, relationship) VALUES
(
    (SELECT user_id FROM users WHERE username='parent1'),
    (SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='student1')),
    'Father'
),
(
    (SELECT user_id FROM users WHERE username='parent2'),
    (SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='student2')),
    'Father'
);

INSERT INTO assignments (teacher_id, class_id, title, description, deadline) VALUES
(
    (SELECT teacher_id FROM teachers WHERE user_id=(SELECT user_id FROM users WHERE username='teacher1')),
    (SELECT class_id FROM classes WHERE class_name='Class 10A'),
    'Math Homework Chapter 3',
    'Solve exercises 3.1 to 3.5 from the textbook. Show all working.',
    DATE_ADD(CURDATE(), INTERVAL 7 DAY)
),
(
    (SELECT teacher_id FROM teachers WHERE user_id=(SELECT user_id FROM users WHERE username='teacher1')),
    (SELECT class_id FROM classes WHERE class_name='Class 10A'),
    'Science Lab Report',
    'Write a 2-page lab report on the photosynthesis experiment conducted in class.',
    DATE_ADD(CURDATE(), INTERVAL 14 DAY)
),
(
    (SELECT teacher_id FROM teachers WHERE user_id=(SELECT user_id FROM users WHERE username='teacher1')),
    (SELECT class_id FROM classes WHERE class_name='Class 10A'),
    'Mid-Term Math Revision',
    'Complete the revision worksheet distributed in class. All 30 questions required.',
    DATE_SUB(CURDATE(), INTERVAL 5 DAY)
);

\
SET @s1 = (SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='student1'));
SET @s2 = (SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='student2'));
SET @s3 = (SELECT student_id FROM students WHERE user_id=(SELECT user_id FROM users WHERE username='student3'));
SET @t1 = (SELECT teacher_id FROM teachers WHERE user_id=(SELECT user_id FROM users WHERE username='teacher1'));
SET @c10a = (SELECT class_id FROM classes WHERE class_name='Class 10A');
SET @c10b = (SELECT class_id FROM classes WHERE class_name='Class 10B');

INSERT INTO attendance (student_id, teacher_id, class_id, date, status) VALUES
-- student1
(@s1, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'present'),
(@s1, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 9  DAY), 'present'),
(@s1, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 8  DAY), 'absent'),
(@s1, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 7  DAY), 'present'),
(@s1, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 6  DAY), 'late'),
(@s1, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 5  DAY), 'present'),
(@s1, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 4  DAY), 'present'),
(@s1, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 3  DAY), 'absent'),
(@s1, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 2  DAY), 'present'),
(@s1, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 1  DAY), 'present'),
-- student2
(@s2, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'present'),
(@s2, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 9  DAY), 'late'),
(@s2, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 8  DAY), 'present'),
(@s2, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 7  DAY), 'present'),
(@s2, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 6  DAY), 'present'),
(@s2, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 5  DAY), 'absent'),
(@s2, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 4  DAY), 'present'),
(@s2, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 3  DAY), 'present'),
(@s2, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 2  DAY), 'late'),
(@s2, @t1, @c10a, DATE_SUB(CURDATE(), INTERVAL 1  DAY), 'present'),
-- student3 (Class 10B)
(@s3, @t1, @c10b, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'present'),
(@s3, @t1, @c10b, DATE_SUB(CURDATE(), INTERVAL 9  DAY), 'present'),
(@s3, @t1, @c10b, DATE_SUB(CURDATE(), INTERVAL 8  DAY), 'present'),
(@s3, @t1, @c10b, DATE_SUB(CURDATE(), INTERVAL 7  DAY), 'absent'),
(@s3, @t1, @c10b, DATE_SUB(CURDATE(), INTERVAL 6  DAY), 'absent'),
(@s3, @t1, @c10b, DATE_SUB(CURDATE(), INTERVAL 5  DAY), 'present'),
(@s3, @t1, @c10b, DATE_SUB(CURDATE(), INTERVAL 4  DAY), 'late'),
(@s3, @t1, @c10b, DATE_SUB(CURDATE(), INTERVAL 3  DAY), 'present'),
(@s3, @t1, @c10b, DATE_SUB(CURDATE(), INTERVAL 2  DAY), 'present'),
(@s3, @t1, @c10b, DATE_SUB(CURDATE(), INTERVAL 1  DAY), 'present');

-- ============================================================
-- GRADES — 5 records × 3 students × 3 subjects
-- ============================================================
SET @t1id = (SELECT teacher_id FROM teachers WHERE user_id=(SELECT user_id FROM users WHERE username='teacher1'));

INSERT INTO grades (student_id, teacher_id, class_id, subject, grade_value, exam_type, remarks) VALUES
-- student1
(@s1, @t1id, @c10a, 'Mathematics', 88.0, 'Quiz',      'Good effort'),
(@s1, @t1id, @c10a, 'Mathematics', 92.5, 'Mid-Term',  'Excellent work'),
(@s1, @t1id, @c10a, 'English',     75.0, 'Quiz',      'Needs improvement in grammar'),
(@s1, @t1id, @c10a, 'English',     80.0, 'Mid-Term',  'Good progress'),
(@s1, @t1id, @c10a, 'Science',     95.0, 'Lab Report','Outstanding lab report'),
-- student2
(@s2, @t1id, @c10a, 'Mathematics', 70.0, 'Quiz',      'Keep practising'),
(@s2, @t1id, @c10a, 'Mathematics', 68.5, 'Mid-Term',  'Below average, revise chapter 3'),
(@s2, @t1id, @c10a, 'English',     90.0, 'Quiz',      'Excellent vocabulary'),
(@s2, @t1id, @c10a, 'English',     94.0, 'Mid-Term',  'Top of class'),
(@s2, @t1id, @c10a, 'Science',     77.5, 'Lab Report','Good observations'),
-- student3 (10B — teacher can grade cross-class via admin setup; adjust class_id if needed)
(@s3, @t1id, @c10b, 'Mathematics', 55.0, 'Quiz',      'Needs significant improvement'),
(@s3, @t1id, @c10b, 'Mathematics', 60.0, 'Mid-Term',  'Borderline pass'),
(@s3, @t1id, @c10b, 'English',     45.0, 'Quiz',      'Failing — extra tutoring recommended'),
(@s3, @t1id, @c10b, 'English',     50.0, 'Mid-Term',  'Bare pass'),
(@s3, @t1id, @c10b, 'Science',     72.0, 'Lab Report','Satisfactory');


SET @sender = (SELECT user_id FROM users WHERE username='teacher1');

INSERT INTO notifications (sender_id, recipient_id, title, message) VALUES
-- to student1
(@sender,
 (SELECT user_id FROM users WHERE username='student1'),
 'Assignment Reminder',
 'Please submit your Math Homework Chapter 3 before the deadline. Contact me if you need help.'),
-- to student2
(@sender,
 (SELECT user_id FROM users WHERE username='student2'),
 'Great Work This Week!',
 'Your English Mid-Term result was outstanding. Keep up the excellent performance!'),
-- broadcast to all
(@sender, NULL,
 'School Announcement',
 'Reminder: Parent-Teacher Day is scheduled for next Friday. All students must bring their report cards.');
