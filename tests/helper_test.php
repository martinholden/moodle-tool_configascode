<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Functional tests for tool_configascode helper class.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We disable the warning for invalid tags in commments so we can add
// phpmd @SuppressWarnings tags to the docblocks without triggering a warning
// in the Moodle codebase which does not use those tags.
// phpcs:disable moodle.Commenting.ValidTags.Invalid

namespace tool_configascode;

use basic_testcase;

/**
 * Functional test for tool_configascode helper class.
 *
 * Note: execution may take many minutes especially on slower servers.
 *
 * PHP UNIT GROUPS:
 *
 * @group tool
 * @group tool_configascode
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class helper_test extends basic_testcase {
    /**
     * Stores the original environment variable value.
     *
     * @var string|null
     */
    private ?string $originalenv = null;

    /**
     * Set up the test case before each test method is executed.
     *
     * This method is called before each test to initialize the test environment,
     * set up any necessary fixtures, and prepare the test state.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $value = getenv('ENV');
        $this->originalenv = ($value === false) ? null : $value;

        // Set the ENV variable to a known value for testing purposes.
        putenv('ENV=testingenv');
    }

    protected function tearDown(): void {
        if ($this->originalenv === null) {
            putenv('ENV');
        } else {
            putenv('ENV=' . $this->originalenv);
        }
        parent::tearDown();
    }

    /**
     * Helper to temporarily replace the ENV process value within a test.
     *
     * @param ?string $value The value to set. Null unsets ENV.
     * @param callable $callback The test callback.
     * @return mixed
     */
    private function with_env_value(?string $value, callable $callback): mixed {
        $previous = getenv('ENV');

        if ($value === null) {
            putenv('ENV');
        } else {
            putenv('ENV=' . $value);
        }

        try {
            return $callback();
        } finally {
            if ($previous === false) {
                putenv('ENV');
            } else {
                putenv('ENV=' . $previous);
            }
        }
    }

    /**
     * Test normalized_environment work as expected.
     *
     * @covers \tool_configascode\helper::normalise_environment
     * @param ?string $env The environment string to test.
     * @param string $expected The expected normalized environment result.
     * @dataProvider normalise_environment_provider
     */
    public function test_normalise_environment(?string $env, string $expected): void {
        $result = \tool_configascode\helper::normalise_environment($env);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    /**
     * Provides test data for environment normalization tests.
     *
     * {@see self::test_normalise_environment()}.
     *
     * @return array List of data sets - (string) data set name => (array) data
     */
    public static function normalise_environment_provider(): array {
        return [
            'Empty string' => ['env' => '', 'expected' => 'default'],
            'Whitespace only' => ['env' => '   ', 'expected' => 'default'],
            'Null value' => ['env' => null, 'expected' => 'default'],
            'Lowercase - prod' => ['env' => 'prod', 'expected' => 'prod'],
            'Uppercase - PROD' => ['env' => 'PROD', 'expected' => 'prod'],
            'With suffix - prod001' => ['env' => 'prod001', 'expected' => 'prod'],
            'Lowercase - stage' => ['env' => 'stage', 'expected' => 'stage'],
            'Uppercase - STAGE' => ['env' => 'STAGE', 'expected' => 'stage'],
            'With suffix - stage-2' => ['env' => 'stage-2', 'expected' => 'stage'],
            'Lowercase - dev' => ['env' => 'dev', 'expected' => 'dev'],
            'Uppercase - DEV' => ['env' => 'DEV', 'expected' => 'dev'],
            'With suffix - dev.local' => ['env' => 'dev.local', 'expected' => 'dev'],
            'Lowercase - local' => ['env' => 'local', 'expected' => 'local'],
            'Uppercase - LOCAL' => ['env' => 'LOCAL', 'expected' => 'local'],
            'With suffix - local001' => ['env' => 'local001', 'expected' => 'local'],
            'Lowercase - default' => ['env' => 'default', 'expected' => 'default'],
            'Uppercase - DEFAULT' => ['env' => 'DEFAULT', 'expected' => 'default'],
            'With suffix - default001' => ['env' => 'default001', 'expected' => 'default'],
            'Lowercase - unknownenv' => ['env' => 'unknownenv', 'expected' => 'unknownenv'],
            'Uppercase - UNKNOWNENV' => ['env' => 'UNKNOWNENV', 'expected' => 'unknownenv'],
            'With suffix not removed - unknownenv001' => ['env' => 'unknownenv001', 'expected' => 'unknownenv001'],
            'Mixed case with suffix - ProD001' => ['env' => 'ProD001', 'expected' => 'prod'],
            'With padding - "  prod  "' => ['env' => '  prod  ', 'expected' => 'prod'],
        ];
    }

    /**
     * Test get_environment works as expected.
     *
     * @covers \tool_configascode\helper::get_environment
     * @param ?string $env The raw environment string to test.
     * @param string $expected The expected normalized environment result.
     * @dataProvider get_environment_provider
     */
    public function test_get_environment(?string $env, string $expected): void {
        $result = \tool_configascode\helper::get_environment($env);
        $this->assertIsString($result);
        $this->assertSame($expected, $result);
    }

    /**
     * Test get_environment falls back to the process ENV value when no argument is supplied.
     *
     * @covers \tool_configascode\helper::get_environment
     */
    public function test_get_environment_uses_process_env_when_missing_argument(): void {
        $result = $this->with_env_value('stage-2', function (): string {
            return \tool_configascode\helper::get_environment();
        });

        $this->assertSame('stage', $result);
    }

    /**
     * Test get_environment defaults to "default" when ENV is not set.
     *
     * @covers \tool_configascode\helper::get_environment
     */
    public function test_get_environment_defaults_when_process_env_unset(): void {
        $result = $this->with_env_value(null, function (): string {
            return \tool_configascode\helper::get_environment();
        });

        $this->assertSame('default', $result);
    }

    /**
     * Test get_policy_path falls back to the process ENV value when the environment argument is missing.
     *
     * @covers \tool_configascode\helper::get_policy_path
     */
    public function test_get_policy_path_uses_process_env_when_env_missing_and_not_testing(): void {
        $result = $this->with_env_value('stage-2', function (): string {
            return \tool_configascode\helper::get_policy_path(null, false, false);
        });

        $this->assertSame('admin/tool/configascode/policy/stage.php', $result);
    }

    /**
     * Provides test data for environment retrieval tests.
     *
     * @return array List of data sets - (string) data set name => (array) data
     */
    public static function get_environment_provider(): array {
        return [
            'Empty string - return default' => ['env' => '', 'expected' => 'default'],
            'Whitespace only - return default' => ['env' => '   ', 'expected' => 'default'],
            'Lowercase - prod' => ['env' => 'prod', 'expected' => 'prod'],
            'Lowercase - stage' => ['env' => 'stage', 'expected' => 'stage'],
            'Lowercase - dev' => ['env' => 'dev', 'expected' => 'dev'],
            'Lowercase - local' => ['env' => 'local', 'expected' => 'local'],
            'Lowercase - default' => ['env' => 'default', 'expected' => 'default'],
        ];
    }

    /**
     * Test is_testing works as expected.
     *
     * @covers \tool_configascode\helper::is_testing
     * @param ?bool $behat Whether Behat is considered active.
     * @param ?bool $phpunit Whether PHPUnit is considered active.
     * @param bool $expected The expected result.
     * @dataProvider is_testing_provider
     */
    public function test_is_testing(?bool $behat, ?bool $phpunit, bool $expected): void {
        $result = \tool_configascode\helper::is_testing($behat, $phpunit);
        $this->assertSame($expected, $result);
    }

    /**
     * Test is_testing uses the real PHPUnit constant when the argument is omitted.
     *
     * @covers \tool_configascode\helper::is_testing
     */
    public function test_is_testing_uses_real_phpunit_constant_when_missing_argument(): void {
        $this->assertTrue(\tool_configascode\helper::is_testing(false, null));
    }

    /**
     * Test is_testing uses the real Behat constant when the argument is omitted.
     *
     * @covers \tool_configascode\helper::is_testing
     */
    public function test_is_testing_uses_real_behat_constant_when_missing_argument(): void {
        $expected = defined('BEHAT_SITE_RUNNING');

        $this->assertSame($expected, \tool_configascode\helper::is_testing(null, false));
    }

    /**
     * Provides test data for testing-environment detection.
     *
     * @return array List of data sets - (string) data set name => (array) data
     */
    public static function is_testing_provider(): array {
        return [
            'Neither flag set' => ['behat' => false, 'phpunit' => false, 'expected' => false],
            'Behat only' => ['behat' => true, 'phpunit' => false, 'expected' => true],
            'PHPUnit only' => ['behat' => false, 'phpunit' => true, 'expected' => true],
            'Both flags set' => ['behat' => true, 'phpunit' => true, 'expected' => true],
        ];
    }

    /**
     * Test get_policy_path works as expected.
     *
     * @covers \tool_configascode\helper::get_policy_path
     * @param ?string $env The raw environment string to test.
     * @param ?bool $testing Whether testing mode is active.
     * @param bool $absolute Whether to return an absolute path.
     * @param string $expected The expected path or path suffix.
     * @dataProvider policy_path_provider
     */
    public function test_get_policy_path(?string $env, ?bool $testing, bool $absolute, string $expected): void {
        $result = \tool_configascode\helper::get_policy_path($env, $testing, $absolute);
        $result = str_replace('\\', '/', $result);

        if ($absolute) {
            $this->assertStringEndsWith($expected, $result);
        } else {
            $this->assertSame($expected, $result);
        }
    }

    /**
     * Test get_policy_path uses runtime testing detection when the flag is omitted.
     *
     * @covers \tool_configascode\helper::get_policy_path
     */
    public function test_get_policy_path_uses_runtime_testing_detection(): void {
        $result = \tool_configascode\helper::get_policy_path('prod001', null, false);

        $this->assertSame('admin/tool/configascode/tests/fixtures/testingpolicy.php', $result);
    }

    /**
     * Provides test data for policy path resolution tests.
     *
     * @return array List of data sets - (string) data set name => (array) data
     */
    public static function policy_path_provider(): array {
        return [
            'Default relative path' => [
                'env' => '',
                'testing' => false,
                'absolute' => false,
                'expected' => 'admin/tool/configascode/policy/default.php',
            ],
            'Production relative path' => [
                'env' => 'PROD001',
                'testing' => false,
                'absolute' => false,
                'expected' => 'admin/tool/configascode/policy/prod.php',
            ],
            'Testing relative path' => [
                'env' => 'PROD001',
                'testing' => true,
                'absolute' => false,
                'expected' => 'admin/tool/configascode/tests/fixtures/testingpolicy.php',
            ],
            'Production absolute suffix' => [
                'env' => 'stage-2',
                'testing' => false,
                'absolute' => true,
                'expected' => 'admin/tool/configascode/classes/../policy/stage.php',
            ],
            'Testing absolute suffix' => [
                'env' => 'dev',
                'testing' => true,
                'absolute' => true,
                'expected' => 'admin/tool/configascode/classes/../tests/fixtures/testingpolicy.php',
            ],
        ];
    }
}
