# Components and Reusable Logic

## UI Components
- **`includes/header.php`:**
  - Dynamic Navigation based on role.
  - **Collapsible Sidebar:** Optimized for both mobile (slide-in) and desktop (mini-mode).
  - **Toast System:** Global `showToast(message, type)` function for real-time feedback.
- **`includes/footer.php`:** Closing body/html and centralized JS loading.
- **`assets/css/style.css`:**
  - **Responsive Table Stack:** Utility classes to handle complex tables on small screens.
  - Modern card layouts and progress bar styling.

## Core Logic
- **`includes/functions.php`:**
  - Session management and role-based authorization.
  - Data distribution logic (Waterfall Finance).
  - Centralized Amali progress calculations.
- **`includes/mailer_helper.php`:**
  - **`send_email($to, $subject, $body)`:** PHPMailer SMTP wrapper.
  - **`get_email_template($title, $content, $userName)`:** Responsive HTML email wrapper.

## Automation & Batching
- **Broadcast Engine:** Handles batching logic (Limit 100/day) and prevents SMTP throttling with delays.
- **AJAX Handlers:** Decoupled frontend/backend logic for Quran, Book, and Finance entry.
