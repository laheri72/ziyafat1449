# Database Schema

## Tables Overview

### `users`
Core user and admin profiles.
- `id` (INT, PK): Unique identifier.
- `its_number` (VARCHAR): Community ID (used for login).
- `tr_number` (VARCHAR): Tracking reference number.
- `password` (VARCHAR): Plain-text password (Security Concern).
- `name` (VARCHAR): Full name.
- `role` (ENUM: 'user', 'admin'): User permissions level.
- `admin_type` (VARCHAR): specific role ('super_admin', 'finance_admin', etc.).
- `category` (VARCHAR): User grouping (e.g., 'Surat', 'Marol').

### `contributions`
Financial payment records.
- `id` (INT, PK): Unique identifier.
- `user_id` (INT, FK): Link to `users.id`.
- `amount_usd` (DECIMAL): Amount in US Dollars.
- `amount_inr` (DECIMAL): Amount in Indian Rupees.
- `payment_year` (VARCHAR): Reference year.
- `payment_date` (DATE): When the payment was made.
- `payment_method` (VARCHAR): e.g., 'NEFT', 'ECO'.
- `transaction_reference` (VARCHAR): External ID.
- `notes` (TEXT): Optional comments.

### `mail_campaigns`
Stores manual broadcast events created by super admins.
- `id` (INT, PK): Unique identifier.
- `event_name` (VARCHAR): Internal reference name.
- `subject` (VARCHAR): Email subject line.
- `custom_message` (TEXT): Personalized note prepended to the stats.
- `status` (ENUM: 'active', 'completed').
- `created_at` (TIMESTAMP).

### `mail_sent_logs`
Tracks individual email delivery within a campaign.
- `id` (INT, PK).
- `campaign_id` (INT, FK): Link to `mail_campaigns.id`.
- `user_id` (INT, FK): Link to `users.id`.
- `sent_at` (TIMESTAMP).
- `status` (ENUM: 'success', 'failed').
- `error_message` (TEXT): Reason for failure if any.

### `books_master`
Master list of books available for transcription.
- `id` (INT, PK): Unique identifier.
- `book_name` (VARCHAR): English name.
- `book_name_arabic` (VARCHAR): Arabic name.
- `author` (VARCHAR): Book author.
- `total_pages` (INT): Length of the book.
- `is_active` (BOOLEAN): Status.

### `book_transcription`
Tracks user progress on specific books.
- `id` (INT, PK): Unique identifier.
- `user_id` (INT, FK): Link to `users.id`.
- `book_id` (INT, FK): Link to `books_master.id`.
- `pages_completed` (INT): Progress counter.
- `status` (ENUM: 'selected', 'completed'): Current state.
- `started_date`, `completed_date` (DATE).

### `duas_master`
Master list of duas to be recited.
- `id` (INT, PK): Unique identifier.
- `dua_name` (VARCHAR): English name.
- `dua_name_arabic` (VARCHAR): Arabic name.
- `category` (VARCHAR): grouping for display.
- `target_count` (INT): Required count.

### `dua_entries`
Daily/periodic logs of dua recitation.
- `id` (INT, PK): Unique identifier.
- `user_id` (INT, FK): Link to `users.id`.
- `dua_id` (INT, FK): Link to `duas_master.id`.
- `count_added` (INT): Number recited in this entry.
- `entry_date` (DATE): When it was recited.

### `quran_progress`
Logs completion of Quran Juz.
- `id` (INT, PK): Unique identifier.
- `user_id` (INT, FK): Link to `users.id`.
- `quran_number` (INT): Index of the current Quran (e.g., 1st, 2nd).
- `juz_number` (INT): 1-30.
- `is_completed` (BOOLEAN).
- `completion_date` (DATE).

### `system_settings`
Global application configuration.
- `id` (INT, PK).
- `exchange_rate` (DECIMAL).
- `current_fiscal_year` (VARCHAR).

## Key Relationships
- `users` (1) <-> (N) `contributions`
- `users` (1) <-> (N) `mail_sent_logs`
- `users` (1) <-> (N) `book_transcription`
- `users` (1) <-> (N) `dua_entries`
- `users` (1) <-> (N) `quran_progress`
- `mail_campaigns` (1) <-> (N) `mail_sent_logs`
- `books_master` (1) <-> (N) `book_transcription`
- `duas_master` (1) <-> (N) `dua_entries`
