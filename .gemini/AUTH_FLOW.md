# Authentication Flow

## Mechanism
The application uses PHP Sessions for authentication and authorization.

## Steps
1. **Login (`auth/login.php`):**
   - User enters ITS Number and Password.
   - System queries `users` table for matching `its_number`.
   - **Password Verification:** Plain-text comparison `$password === $user['password']`.
   - **Session Initialization:** If match, sets:
     - `$_SESSION['user_id']`
     - `$_SESSION['name']`
     - `$_SESSION['its_number']`
     - `$_SESSION['role']`
     - `$_SESSION['admin_type']` (for admins)

2. **Authorization:**
   - Pages call helper functions from `includes/functions.php`:
     - `require_login()`: Redirects to login if `user_id` is not in session.
     - `require_admin()`: Redirects to user dashboard if `role` is not 'admin'.
     - Specialized checks: `is_super_admin()`, `is_finance_admin()`, `is_amali_coordinator()`.

3. **Logout (`auth/logout.php`):**
   - Destroys the session and clears all `$_SESSION` variables.
   - Redirects to `auth/login.php`.

## Roles and Permissions
| Role | Admin Type | Permissions |
| :--- | :--- | :--- |
| `user` | N/A | Log personal spiritual activities, view own financial reports. |
| `admin` | `super_admin` | Full system access, all reports, all user management. |
| `admin` | `finance_admin` | Manage contributions and view financial reports. |
| `admin` | `amali_coordinator` | View spiritual reports for all users. |
| `admin` | `*_amali_coordinator` | View spiritual reports for users in a specific category (e.g., Surat). |

## Security Note
Passwords are currently stored in **plain text**. This is a critical security risk and should be addressed by implementing `password_hash()` and `password_verify()`.
