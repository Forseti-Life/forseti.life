# Running Tests

## Setup

1. **Copy the PHPUnit configuration template:**
   ```bash
   cp phpunit.xml.dist phpunit.xml
   ```

2. **Edit `phpunit.xml` and set your local values:**
   - `SIMPLETEST_DB`: Your test database connection string (e.g., `mysql://drupal_user:password@localhost/drupal_test`)
   - `SIMPLETEST_BASE_URL`: Your local Drupal site URL (e.g., `http://localhost:8080`)

3. **Ensure the test database exists:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS drupal_test;"
   ```

4. **Ensure the simpletest directory is writable by the test runner:**
   
   PHPUnit tests create temporary test sites in `web/sites/simpletest/`. The directory must be writable by the user running the tests.
   
   ```bash
   # For command-line test execution by current user
   chmod 755 web/sites/simpletest
   
   # If running tests as web server user (e.g., via CI/CD)
   # sudo chown -R www-data:www-data web/sites/simpletest
   # chmod 775 web/sites/simpletest
   ```
   
   **Note:** Tests should only be run in development environments, never on production servers.

## Running Tests

### Run all project-level functional tests:
```bash
./vendor/bin/phpunit -c phpunit.xml
```

## Running Module Tests

For module-specific tests, use the module's phpunit.xml:
```bash
./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Notes

- The `phpunit.xml` file is ignored by git to prevent committing local credentials
- Always use `phpunit.xml.dist` as the template for creating your local `phpunit.xml`
- Test databases are temporary and will be cleaned up automatically after tests run
