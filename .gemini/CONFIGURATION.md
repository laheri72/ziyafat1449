# Configuration

## Database Connection
Configured in `config/database.php`.
- **Host:** `auth-db2145.hstgr.io`
- **Database:** `u719177696_ZS1449`

## Mailing (SMTP)
Configured in `config/mail.php`.
- **Server:** `smtp.hostinger.com`
- **Port:** `465` (SSL)
- **Account:** `reminders@ziyafatshukr1449.com`
- **Limit:** Manual batch control required to stay within ~100/day limit.

## Financial Constants
- **INR to USD:** `84.67` (Hardcoded in `get_user_contributions`).
- **Targets:**
    - Tasea: 66,000 INR
    - Ashera: 97,000 INR
    - Hadi Ashara: 127,000 INR
- **Quran Total:** 120 Juz (cumulative).
