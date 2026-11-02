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
 * Database-backed functional tests for tool_configascode check class.
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

use advanced_testcase;
use core\check\result;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../fixtures/testable_configascode_check.php');

/**
 * Database-backed functional tests for tool_configascode check class.
 *
 * @covers \tool_configascode\check\configascode_check
 *
 * PHP UNIT GROUPS:
 *
 * @group tool
 * @group tool_configascode
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class configascode_check_db_test extends advanced_testcase {
    /**
     * Test stored hash and time helpers normalise invalid and valid config values.
     *
     * @covers \tool_configascode\check\configascode_check::get_stored_policy_hash
     * @covers \tool_configascode\check\configascode_check::get_stored_policy_time
     */
    public function test_real_stored_hash_and_time_helpers(): void {
        $this->resetAfterTest(true);
        $this->assertTrue(defined('PHPUNIT_TEST') && PHPUNIT_TEST);

        $check = new testable_configascode_check();

        unset_config('confighash', 'tool_configascode');
        unset_config('configtime', 'tool_configascode');
        $this->assertNull($check->get_real_stored_policy_hash());
        $this->assertNull($check->get_real_stored_policy_time());

        set_config('confighash', '', 'tool_configascode');
        set_config('configtime', 'invalid', 'tool_configascode');
        $this->assertSame('', $check->get_real_stored_policy_hash());
        $this->assertNull($check->get_real_stored_policy_time());

        set_config('configtime', '0', 'tool_configascode');
        $this->assertNull($check->get_real_stored_policy_time());

        set_config('configtime', '1713350400', 'tool_configascode');
        $this->assertSame(1713350400, $check->get_real_stored_policy_time());
    }

    /**
     * Test get_result reports whether the policy sync task is enabled.
     *
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_get_result_reports_sync_task_enabled_state(): void {
        $this->resetAfterTest(true);
        $check = new testable_configascode_check();
        $check->set_test_state('prod001', false, true);

        set_config('sync_policy_task_enabled', 0, 'tool_configascode');
        $result = $check->get_result();
        $this->assertSame(result::WARNING, $result->get_status());
        $this->assertSame(
            get_string(
                'policy:synctaskdisabled',
                'tool_configascode',
                get_string('policy:ok', 'tool_configascode', 'prod')
            ),
            $result->get_summary()
        );
        $this->assertStringContainsString('data-sync-task-enabled="no"', $result->get_details());

        set_config('sync_policy_task_enabled', 1, 'tool_configascode');
        $result = $check->get_result();
        $this->assertStringContainsString('data-sync-task-enabled="yes"', $result->get_details());
    }

    /**
     * Test a disabled sync task does not lower a critical bootstrap status.
     *
     * @covers \tool_configascode\check\configascode_check::get_result
     */
    public function test_disabled_sync_task_does_not_mask_critical_bootstrap_failure(): void {
        $this->resetAfterTest(true);
        $check = new testable_configascode_check();
        $check->set_test_state('prod001', false, false);
        set_config('sync_policy_task_enabled', 0, 'tool_configascode');

        $result = $check->get_result();

        $this->assertSame(result::CRITICAL, $result->get_status());
        $this->assertSame(get_string('policy:bootstrapmissing', 'tool_configascode'), $result->get_summary());
        $this->assertStringContainsString('data-sync-task-enabled="no"', $result->get_details());
    }
}
