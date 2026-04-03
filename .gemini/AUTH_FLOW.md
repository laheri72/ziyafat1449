# Authentication Flow

## Mechanism
PHP Session-based authentication with role and admin-type validation.

## Super Admin Password Reset
Added a bypass for forgotten passwords:
1. Super Admin navigates to `edit_user.php`.
2. System detects `admin_type === 'super_admin'`.
3. Displays **"Reset Password to TR Number"** button.
4. On confirmation, the `password` field in the `users` table is overwritten with the user's `tr_number`.

## Login Logic
- Login via **ITS Number** and **Password**.
- Currently uses **Plain-text** comparison (High security risk - see Security Notes).
- Role-based redirection:
    - Admin -> `admin/index.php`
    - User -> `user/index.php`

## Authorization Helpers
- `require_login()`: Basic barrier.
- `require_admin()`: Restricts to role='admin'.
- `is_super_admin()`: Restricts to specific Broadcast and Reset features.
- `has_finance_access()` / `has_amali_access()`: Granular permission checks.
