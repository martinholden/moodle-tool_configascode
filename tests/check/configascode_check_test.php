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
 * Functional tests for tool_configascode check class.
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

namespace tool_configascode\check;

use basic_testcase;
use core\check\result;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../fixtures/testable_configascode_check.php');

/**
 * Functional test for tool_configascode check class.
 *
 * @covers \tool_configascode\check\configascode_check
 *
 * PHP UNIT GROUPS:
 *
 * @group tool
 * @group tool_configascode
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class configascode_check_test extends basic_testcase {
    /**
     * Builds a check test double with the desired state.
     *
     * @param ?string $env
     * @param bool $testing
     * @param bool $bootstrapincluded
     * @return testable_configascode_check
     */
    private function build_check(?string $env, bool $testing, bool $bootstrapincluded): testable_configascode_check {
        $check = new testable_configascode_check();
        $check->set_test_state($env, $testing, $bootstrapincluded);
        return $check;
    }

    /**
     * Test get_result in testing mode.
     *
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_get_result_testing_mode(): void {
        $result = $this->build_check('prod001', true, true)->get_result();

        $this->assertSame(result::WARNING, $result->get_status());
        $this->assertSame(get_string('policy:istesting', 'tool_configascode'), $result->get_summary());
        $details = $result->get_details();

        // Array of strings we expect to find in the details output,
        // validating the environment detection and testing mode.
        $expecteddetails = [
            'class="tool-lockdown-config-check"',
            'data-env="prod001"',
            'data-normalisedenv="prod"',
            'data-testing="yes"',
            'data-config-bootstrap="yes"',
            'data-policy-file-exists="yes"',
            'data-policy-path="admin/tool/configascode/tests/fixtures/testingpolicy.php"',
            'data-synclogsurl="/admin/tasklogs.php?filter=%5Ctool_configascode%5Ctask%5Csync_policy_task"',
            'TESTING ENVIRONMENT',
        ];

        foreach ($expecteddetails as $expected) {
             $this->assertStringContainsString($expected, $details);
        }
    }

    /**
     * Test get_result when the default policy is active.
     *
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_get_result_default_policy(): void {
        $result = $this->build_check('', false, true)->get_result();

        $this->assertSame(result::WARNING, $result->get_status());
        $this->assertSame(get_string('policy:default', 'tool_configascode'), $result->get_summary());
        $details = $result->get_details();

        // Array of strings we expect to find in the details output,
        // validating the environment detection and testing mode.
        $expecteddetails = [
            'class="tool-lockdown-config-check"',
            'data-env=""',
            'data-normalisedenv="default"',
            'data-testing="no"',
            'data-config-bootstrap="yes"',
            'data-policy-file-exists="yes"',
            'data-policy-path="admin/tool/configascode/policy/default.php"',
            'data-synclogsurl="/admin/tasklogs.php?filter=%5Ctool_configascode%5Ctask%5Csync_policy_task"',
        ];

        foreach ($expecteddetails as $expected) {
             $this->assertStringContainsString($expected, $details);
        }
    }

    /**
     * Test get_result when an explicit default-style environment is supplied.
     *
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_get_result_default_style_policy(): void {
        $result = $this->build_check('default001', false, true)->get_result();

        $this->assertSame(result::WARNING, $result->get_status());
        $this->assertSame(get_string('policy:default', 'tool_configascode'), $result->get_summary());
        $details = $result->get_details();

        // Array of strings we expect to find in the details output,
        // validating the environment detection and testing mode.
        $expecteddetails = [
            'class="tool-lockdown-config-check"',
            'data-env="default001"',
            'data-normalisedenv="default"',
            'data-testing="no"',
            'data-config-bootstrap="yes"',
            'data-policy-file-exists="yes"',
            'data-policy-path="admin/tool/configascode/policy/default.php"',
            'data-synclogsurl="/admin/tasklogs.php?filter=%5Ctool_configascode%5Ctask%5Csync_policy_task"',
        ];

        foreach ($expecteddetails as $expected) {
             $this->assertStringContainsString($expected, $details);
        }
    }

    /**
     * Test get_result when a production policy exists.
     *
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_get_result_existing_policy(): void {
        $result = $this->build_check('prod001', false, true)->get_result();

        $this->assertSame(result::OK, $result->get_status());
        $this->assertSame(get_string('policy:ok', 'tool_configascode', 'prod'), $result->get_summary());
        $details = $result->get_details();

        // Array of strings we expect to find in the details output,
        // validating the environment detection and testing mode.
        $expecteddetails = [
            'class="tool-lockdown-config-check"',
            'data-env="prod001"',
            'data-normalisedenv="prod"',
            'data-testing="no"',
            'data-config-bootstrap="yes"',
            'data-policy-file-exists="yes"',
            'data-policy-path="admin/tool/configascode/policy/prod.php"',
            'data-synclogsurl="/admin/tasklogs.php?filter=%5Ctool_configascode%5Ctask%5Csync_policy_task"',
        ];

        foreach ($expecteddetails as $expected) {
             $this->assertStringContainsString($expected, $details);
        }
    }

    /**
     * Test get_result for the other known environment prefixes that map to real policies.
     *
     * @param string $env The raw environment value.
     * @param string $expectedenv The normalised environment value.
     * @dataProvider existing_policy_provider
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_get_result_existing_policy_prefixes(string $env, string $expectedenv): void {
        $result = $this->build_check($env, false, true)->get_result();

        $this->assertSame(result::OK, $result->get_status());
        $this->assertSame(get_string('policy:ok', 'tool_configascode', $expectedenv), $result->get_summary());
        $details = $result->get_details();

        // Array of strings we expect to find in the details output,
        // validating the environment detection and testing mode.
        $expecteddetails = [
            'class="tool-lockdown-config-check"',
            'data-env="' . $env . '"',
            'data-normalisedenv="' . $expectedenv . '"',
            'data-testing="no"',
            'data-config-bootstrap="yes"',
            'data-policy-file-exists="yes"',
            'data-policy-path="admin/tool/configascode/policy/' . $expectedenv . '.php"',
            'data-synclogsurl="/admin/tasklogs.php?filter=%5Ctool_configascode%5Ctask%5Csync_policy_task"',
        ];

        foreach ($expecteddetails as $expected) {
             $this->assertStringContainsString($expected, $details);
        }
    }

    /**
     * Test get_result when the requested policy does not exist.
     *
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_get_result_missing_policy(): void {
        $result = $this->build_check('unknownenv', false, true)->get_result();

        $this->assertSame(result::WARNING, $result->get_status());
        $this->assertSame(get_string('policy:notfound', 'tool_configascode', 'unknownenv'), $result->get_summary());
        $details = $result->get_details();

        // Array of strings we expect to find in the details output,
        // validating the environment detection and testing mode.
        $expecteddetails = [
            'class="tool-lockdown-config-check"',
            'data-env="unknownenv"',
            'data-normalisedenv="unknownenv"',
            'data-testing="no"',
            'data-config-bootstrap="yes"',
            'data-policy-file-exists="no"',
            'data-policy-path="admin/tool/configascode/policy/default.php"',
            'data-synclogsurl="/admin/tasklogs.php?filter=%5Ctool_configascode%5Ctask%5Csync_policy_task"',
        ];

        foreach ($expecteddetails as $expected) {
             $this->assertStringContainsString($expected, $details);
        }
    }

    /**
     * Test get_result when config.php is missing the bootstrap line.
     *
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_get_result_missing_config_bootstrap(): void {
        $result = $this->build_check('prod001', false, false)->get_result();

        $this->assertSame(result::CRITICAL, $result->get_status());
        $this->assertSame(get_string('policy:bootstrapmissing', 'tool_configascode'), $result->get_summary());
        $details = $result->get_details();

        // Array of strings we expect to find in the details output,
        // validating the environment detection and testing mode.
        $expecteddetails = [
            'class="tool-lockdown-config-check"',
            'data-env="prod001"',
            'data-normalisedenv="prod"',
            'data-testing="no"',
            'data-config-bootstrap="no"',
            'data-policy-file-exists="yes"',
            'data-policy-path="admin/tool/configascode/policy/prod.php"',
            'data-synclogsurl="/admin/tasklogs.php?filter=%5Ctool_configascode%5Ctask%5Csync_policy_task"',
        ];

        foreach ($expecteddetails as $expected) {
             $this->assertStringContainsString($expected, $details);
        }
    }

    /**
     * Test get_result includes the sync hash status when the policy DB is out of date.
     *
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_get_result_hash_sync_pending(): void {
        $check = new testable_configascode_check();
        $check->set_test_state('prod001', false, true, 'stored-hash', 'current-hash', 1713350400);

        $result = $check->get_result();

        $this->assertSame(result::OK, $result->get_status());
        $details = $result->get_details();
        $this->assertStringContainsString('data-current-hash="current-hash"', $details);
        $this->assertStringContainsString('data-stored-hash="stored-hash"', $details);
        $this->assertStringContainsString('data-policy-sync-time="1713350400"', $details);
        $this->assertStringContainsString('data-hash-matches="no"', $details);
    }

    /**
     * Test get_result includes the sync hash status when the policy DB is up to date.
     *
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_get_result_hash_sync_in_sync(): void {
        $check = new testable_configascode_check();
        $check->set_test_state('prod001', false, true, 'matched-hash', 'matched-hash', 1713350400);

        $result = $check->get_result();

        $this->assertSame(result::OK, $result->get_status());
        $details = $result->get_details();
        $this->assertStringContainsString('data-current-hash="matched-hash"', $details);
        $this->assertStringContainsString('data-stored-hash="matched-hash"', $details);
        $this->assertStringContainsString('data-policy-sync-time="1713350400"', $details);
        $this->assertStringContainsString('data-hash-matches="yes"', $details);
    }

    /**
     * Test get_result shows the timestamp fallback when no policy sync time is stored.
     *
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_get_result_hash_sync_time_not_set(): void {
        $check = new testable_configascode_check();
        $check->set_test_state('prod001', false, true, 'matched-hash', 'matched-hash', null);

        $result = $check->get_result();

        $this->assertSame(result::OK, $result->get_status());
        $details = $result->get_details();
        $this->assertStringContainsString('data-policy-sync-time=""', $details);
        $this->assertStringContainsString(get_string('checkconfigascode_timenotset', 'tool_configascode'), $details);
        $this->assertStringContainsString('data-hash-matches="yes"', $details);
    }

    /**
     * Test the real protected environment helpers on the check class.
     *
     * @covers \tool_configascode\check\configascode_check::get_original_environment
     * @covers \tool_configascode\check\configascode_check::get_policy_path_for_environment
     * @covers \tool_configascode\check\configascode_check::is_testing_environment
     */
    public function test_real_environment_helpers(): void {
        $check = new testable_configascode_check();
        $originalenv = getenv('ENV');

        try {
            putenv('ENV=stage-2');
            $this->assertSame('stage-2', $check->get_real_original_environment());
            $this->assertSame(
                'admin/tool/configascode/policy/stage.php',
                $check->get_real_policy_path_for_environment('stage-2', false)
            );
            $this->assertTrue($check->is_real_testing_environment());
        } finally {
            if ($originalenv === false) {
                putenv('ENV');
            } else {
                putenv('ENV=' . $originalenv);
            }
        }
    }

    /**
     * Provides environments that should resolve to existing policy files.
     *
     * @return array
     */
    public static function existing_policy_provider(): array {
        return [
            'production policy' => ['env' => 'prod001', 'expectedenv' => 'prod'],
            'stage policy' => ['env' => 'stage-2', 'expectedenv' => 'stage'],
            'dev policy' => ['env' => 'dev.local', 'expectedenv' => 'dev'],
            'local policy' => ['env' => 'local-dev', 'expectedenv' => 'local'],
        ];
    }

    /**
     * Test the real bootstrap helper returns true as we are including the test
     * bootstrap in this test, validating that the check is correctly detecting
     * the presence of the configascode config bootstrap line in the config.php
     * file used for this test suite.
     *
     * @covers \tool_configascode\check\configascode_check::has_config_bootstrap
     */
    public function test_has_config_bootstrap(): void {
        $this->assertTrue(defined('PHPUNIT_TEST') && PHPUNIT_TEST);

        $check = new testable_configascode_check();

        $this->assertTrue($check->has_real_config_bootstrap());
    }
}
