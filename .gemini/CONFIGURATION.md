# Configuration

## Database Connection
Configured in `config/database.php`.

### Parameters:
- **Host:** `auth-db2145.hstgr.io` (Remote Hostinger DB)
- **Username:** `u719177696_ziyafatushukr`
- **Database:** `u719177696_ZS1449`
- **Charset:** `utf8`

## Application Constants
(Mostly hardcoded in `includes/functions.php`)
- **INR to USD Rate:** `84.67` (Hardcoded in `get_user_contributions`).
- **Finance Targets:**
  - Tasea: 66,000 INR
  - Ashera: 97,000 INR
  - Hadi Ashara: 127,000 INR
- **Quran Total:** 120 Juz (cumulative across 4 Qurans).

## Environment Variables
The application does not currently use a `.env` file. All configuration is hardcoded in PHP files.

## Modern Frontend Config
The presence of `src/` and `.tsx` files suggests a Vite or Webpack setup might exist for certain parts of the app, though the core remains traditional PHP.
