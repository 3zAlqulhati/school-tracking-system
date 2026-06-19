# Smart School Tracking System

A web-based school management system built with PHP and MySQL. It lets a school manage students, teachers, parents, classes, attendance, grades, assignments, and notifications — all from one platform with role-based dashboards.

This is my final year project for the BSc (Hons) Computing — Software Technology programme.

## Features

**Admin**
- Manage users (students, teachers, parents) and classes
- Assign teachers and enroll students into classes
- View school-wide reports (attendance, grades, submissions)
- Send notifications to any role, class, or individual
- Dashboard with live stats and charts

**Teacher**
- Record daily attendance per class
- Enter and manage student grades
- Create assignments with deadlines and file attachments
- Review and grade student submissions
- Send notifications to students/parents in their classes

**Student**
- View personal attendance history
- View grades
- Submit assignments before the deadline
- Receive notifications

**Parent**
- View their child's attendance and grades
- Receive notifications about their child

## Tech Stack

- **Backend:** PHP 8.1
- **Database:** MySQL (PDO with prepared statements)
- **Frontend:** HTML5, custom CSS, vanilla JavaScript
- **Auth:** Session-based login, bcrypt password hashing
- **Icons/Fonts:** Font Awesome, Google Fonts (CDN)
- **Charts:** Chart.js (CDN)

## Project Structure

```
school_tracking/
├── admin/          # Admin pages (users, classes, reports, notifications)
├── teacher/        # Teacher pages (attendance, grades, assignments)
├── student/        # Student pages (assignments, attendance, grades)
├── parent/         # Parent pages (view child's progress)
├── includes/       # Shared auth, header, footer, helper functions
├── config/         # Database connection config
├── assets/         # CSS, JS, uploaded files
├── school_tracking_db.sql   # Database schema
└── test_data.sql            # Sample data for testing
```

## Setup

1. Clone the repo into your local server's web root (e.g. `htdocs` for XAMPP):
   ```
   git clone https://github.com/3zAlqulhati/school-tracking-system.git
   ```

2. Start Apache and MySQL (XAMPP, WAMP, or similar).

3. Create the database by importing the schema:
   - Open phpMyAdmin
   - Import `school_tracking_db.sql`
   - (Optional) Import `test_data.sql` for sample students, teachers, and classes

4. Check `config/db.php` matches your local MySQL credentials:
   ```php
   $host = '127.0.0.1';
   $db   = 'school_tracking_db';
   $user = 'root';
   $pass = '';
   ```

5. Visit `http://localhost/school_tracking` in your browser.

## Default Login

| Role  | Username | Password   |
|-------|----------|------------|
| Admin | admin    | admin123   |

Create teacher, student, and parent accounts from the Admin → Manage Users page.

## Notes

- Each role only has access to its own dashboard and pages; direct URL access to another role's pages is blocked.
- Passwords are hashed with bcrypt — never stored in plain text.
- All database queries use PDO prepared statements to prevent SQL injection.

## Author

**Almoataz Adnan (3z)**
GitHub: [@3zAlqulhati](https://github.com/3zAlqulhati)
Supervisor: Dr. Jitendra Pandey
Middle East College — BSc (Hons) Computing, Software Technology
