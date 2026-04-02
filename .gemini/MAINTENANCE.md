# Maintenance and Deployment

## How to Safely Modify the Project

### 1. Local Development
- Use XAMPP/WAMP/MAMP to host the project locally.
- Import `database/127_0_0_1.sql` into your local MariaDB instance.
- Update `config/database.php` to point to `localhost` and your local credentials.

### 2. Making Changes
- **Backend:** Add new functions to `includes/functions.php` to keep logic centralized.
- **Frontend:** Follow the pattern in `user/` or `admin/` using the common header/footer.
- **Database:** If you modify the schema, update `database/127_0_0_1.sql` to keep it in sync.
- **Surgical Updates:** Use the `replace` tool for specific lines rather than rewriting whole files.

### 3. Validation
- Test with different user roles (`user`, `admin`, `super_admin`).
- Verify financial calculations when adding new contributions (Waterfall logic check).
- Check for SQL injection risks (ensure `bind_param` is used for all user inputs).

## Deployment Flow (GitHub -> Hostinger)

### Current Setup (Inferred)
1. **Development:** Changes made locally and pushed to GitHub.
2. **Transfer:** Files are likely uploaded to Hostinger via FTP/SFTP or a "Git Deploy" feature in the Hostinger panel.
3. **Database:** Migrations are manually applied via phpMyAdmin on Hostinger.

### Suggested CI/CD Flow
1. **GitHub Actions:** Set up a workflow to:
   - Run PHP linting.
   - (Optional) Run tests if implemented.
   - Automatically deploy to Hostinger via SSH/SFTP on merge to `main` branch.
2. **Secrets:** Store database credentials and SSH keys in GitHub Repository Secrets.

## Deployment Checklist
- [ ] Update `config/database.php` with production credentials.
- [ ] Clear PHP opcache if necessary.
- [ ] Verify file permissions on Hostinger (usually 755 for dirs, 644 for files).
- [ ] Ensure `.git` folder is not web-accessible.
