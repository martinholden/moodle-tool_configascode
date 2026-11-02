# `tool_configascode` PHPUnit tests

The plugin's PHPUnit tests are split by whether they need Moodle's database-backed test environment:

- `basic_testcase` is used for tests that can run without changing the database. These tests avoid database setup and reset overhead, so they run faster. They cover pure/helper behaviour, policy handling, preset conversion, privacy, and the non-database parts of the plugin allowlist and policy checks.
- `advanced_testcase` is used for tests that read or change Moodle configuration, use the database, or exercise scheduled tasks and database-backed checks. These test classes are named with the `_db_test.php` suffix.

The current database-backed test classes are:

- `check/configascode_check_db_test.php`
- `plugin_allowlist_db_test.php`
- `policy_sync_db_test.php`
- `task/enforce_plugin_allowlist_task_db_test.php`
- `task/sync_policy_task_db_test.php`

The corresponding basic tests use the normal `_test.php` suffix, for example `helper_test.php`, `plugin_allowlist_test.php`, and `check/configascode_check_test.php`. Fixtures under `tests/fixtures` are support classes or input files; they are not test classes.

Tests are grouped with `tool_configascode` and use `@covers` annotations to identify the production code they exercise. Data providers are used where the same behaviour needs to be checked against several inputs.

## Running the tests

Run the plugin test suite from the Moodle root. The suite name is `tool_configascode_testsuite`:

```bash
vendor/bin/phpunit -c public/admin/tool/configascode/phpunit-11.xml \
    --testsuite tool_configascode_testsuite
```

On Moodle installations where the Moodle root is the web root rather than `public`, use the equivalent path without `public`:

```bash
vendor/bin/phpunit -c admin/tool/configascode/phpunit-9.xml \
    --testsuite tool_configascode_testsuite
```

Use `phpunit-9.xml` with the PHPUnit 9.6 setup used by Moodle versions before 5.1, and `phpunit-11.xml` with the PHPUnit 11 setup used by Moodle 5.1 and later. The two configuration files define the same plugin suite but use the coverage configuration format supported by their respective PHPUnit versions.

To run a single test file or class, pass it after the configuration option, for example:

```bash
vendor/bin/phpunit -c public/admin/tool/configascode/phpunit-11.xml \
    public/admin/tool/configascode/tests/helper_test.php
```

## Code coverage

The plugin configurations include the plugin source directory and exclude code that is not suitable for PHPUnit coverage, including tests, fixtures, policy files, language files, database/install files, and selected entry-point files. `tests/coverage.php` contains the plugin's Moodle coverage metadata and keeps the same production-code exclusions (while allowing the test runner to handle test-file exclusion).

To generate a coverage report for this plugin, explicitly use the plugin configuration and enable a coverage driver supported by the local setup. The following uses Xdebug as an example:

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit \
    -c public/admin/tool/configascode/phpunit-11.xml \
    --testsuite tool_configascode_testsuite \
    --testdox --coverage-text
```

For PHPUnit 9.6, replace `phpunit-11.xml` with `phpunit-9.xml`. If the plugin configuration is omitted, PHPUnit runs against the wider Moodle configuration and the coverage report is not limited to this plugin.
