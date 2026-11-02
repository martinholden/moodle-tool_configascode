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
 * Database-backed tests for the policy synchronisation scheduled task.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode\task;

use advanced_testcase;

/**
 * Database-backed functional tests the policy synchronisation scheduled task.
 *
 * @covers \tool_configascode\task\sync_policy_task
 *
 * PHP UNIT GROUPS:
 *
 * @group tool
 * @group tool_configascode
 */
final class sync_policy_task_db_test extends advanced_testcase {
    /**
     * Test get_name returns the expected language string.
     *
     * @covers \tool_configascode\task\sync_policy_task::get_name
     */
    public function test_get_name(): void {
        $task = new sync_policy_task();

        $this->assertSame(
            get_string('sync_policy_task', 'tool_configascode'),
            $task->get_name()
        );
    }

    /**
     * The scheduled task reconciles policy values.
     *
     * @covers \tool_configascode\task\sync_policy_task::execute
     */
    public function test_execute_syncs_policy_values(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        set_config('phpunit_tool_configascode_taskvalue', 'before');
        $CFG->config_php_settings = [
            'phpunit_tool_configascode_taskvalue' => 'after',
        ];
        $CFG->forced_plugin_settings = [];
        set_config('sync_policy_task_enabled', 1, 'tool_configascode');
        unset_config('confighash', 'tool_configascode');

        ob_start();
        $task = new sync_policy_task();
        $task->execute();
        $output = ob_get_clean();

        $this->assertSame(
            'after',
            $DB->get_field('config', 'value', ['name' => 'phpunit_tool_configascode_taskvalue'])
        );
        $this->assertStringContainsString('Starting policy sync check...', $output);

        ob_start();
        \tool_configascode\policy_sync::sync_if_needed();
        $directoutput = ob_get_clean();
        $this->assertSame('', $directoutput);
    }

    /**
     * The scheduled task syncs policy values when the enable setting is unset.
     *
     * @covers \tool_configascode\task\sync_policy_task::execute
     */
    public function test_execute_syncs_when_task_enabled_setting_is_unset(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        set_config('phpunit_tool_configascode_taskvalue', 'before');
        $CFG->config_php_settings = [
            'phpunit_tool_configascode_taskvalue' => 'after',
        ];
        $CFG->forced_plugin_settings = [];
        // Unset the task enabled setting to simulate an unset state.
        unset_config('sync_policy_task_enabled', 'tool_configascode');
        unset_config('confighash', 'tool_configascode');

        ob_start();
        $task = new sync_policy_task();
        $task->execute();
        $output = ob_get_clean();

        $this->assertSame(
            'after',
            $DB->get_field('config', 'value', ['name' => 'phpunit_tool_configascode_taskvalue'])
        );
        $this->assertStringContainsString('Starting policy sync check...', $output);
    }

    /**
     * The scheduled task leaves policy state and sync metadata unchanged when disabled.
     *
     * @covers \tool_configascode\task\sync_policy_task::execute
     */
    public function test_execute_does_not_sync_when_task_disabled(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $configkey = 'phpunit_tool_configascode_disabled_taskvalue';
        set_config($configkey, 'before');
        $CFG->config_php_settings = [
            $configkey => 'after',
        ];
        $CFG->forced_plugin_settings = [];
        set_config('sync_policy_task_enabled', 0, 'tool_configascode');
        set_config('confighash', 'existing-hash', 'tool_configascode');
        set_config('configtime', 1713350400, 'tool_configascode');

        ob_start();
        $task = new sync_policy_task();
        $task->execute();
        $output = ob_get_clean();

        $this->assertSame('before', $DB->get_field('config', 'value', ['name' => $configkey]));
        $this->assertSame('existing-hash', get_config('tool_configascode', 'confighash'));
        $this->assertSame('1713350400', get_config('tool_configascode', 'configtime'));
        $this->assertStringContainsString('Policy sync skipped:', $output);
    }

    /**
     * The task restores the default logger when policy synchronization throws.
     *
     * @covers \tool_configascode\task\sync_policy_task::execute
     */
    public function test_execute_resets_logger_after_sync_exception(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $configkey = 'phpunit_tool_configascode_exception_taskvalue';
        set_config($configkey, 'before');
        $CFG->config_php_settings = [$configkey => 'after'];
        $CFG->forced_plugin_settings = [];
        set_config('sync_policy_task_enabled', 1, 'tool_configascode');
        unset_config('confighash', 'tool_configascode');

        \tool_configascode\policy_sync::set_sync_write_hook(static function (): void {
            throw new \Exception('forced task sync failure');
        });

        ob_start();
        try {
            try {
                (new sync_policy_task())->execute();
                $this->fail('Expected forced task sync failure was not thrown.');
            } catch (\Exception $e) {
                $this->assertSame('forced task sync failure', $e->getMessage());
            }
        } finally {
            $output = ob_get_clean();
            \tool_configascode\policy_sync::set_sync_write_hook(null);
        }
        $this->assertStringContainsString('Starting policy sync check...', $output);

        ob_start();
        \tool_configascode\policy_sync::sync_if_needed();
        $directoutput = ob_get_clean();
        $this->assertSame('', $directoutput);
    }
}
