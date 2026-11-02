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
 * Functional tests for tool_configascode scheduled task.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode\task;

use advanced_testcase;
use core_plugin_manager;

/**
 * Database-backed functional tests for tool_configascode scheduled task.
 *
 * @covers \tool_configascode\task\enforce_plugin_allowlist_task
 *
 * PHP UNIT GROUPS:
 *
 * @group tool
 * @group tool_configascode
 */
final class enforce_plugin_allowlist_task_db_test extends advanced_testcase {
    /**
     * Test get_name returns the expected language string.
     *
     * @covers \tool_configascode\task\enforce_plugin_allowlist_task::get_name
     */
    public function test_get_name(): void {
        $task = new enforce_plugin_allowlist_task();

        $this->assertSame(
            get_string('enforce_plugin_allowlist_task', 'tool_configascode'),
            $task->get_name()
        );
    }

    /**
     * Test execute enforces the configured plugin allowlist.
     *
     * @covers \tool_configascode\task\enforce_plugin_allowlist_task::execute
     */
    public function test_execute_enforces_plugin_allowlist(): void {
        $this->resetAfterTest(true);

        $plugininfoclass = core_plugin_manager::resolve_plugininfo_class('mod');
        $plugininfoclass::enable_plugin('forum', 0);
        set_config('pluginallowlist', 'mod_forum', 'tool_configascode');
        set_config('enforce_plugin_allowlist_task_enabled', 1, 'tool_configascode');

        ob_start();
        $task = new enforce_plugin_allowlist_task();
        $task->execute();
        $output = ob_get_clean();

        $this->assertSame(1, $plugininfoclass::get_enabled_plugin('forum'));
        $this->assertStringContainsString("Enabled plugin 'mod_forum'.", $output);

        ob_start();
        \tool_configascode\plugin_allowlist::enforce();
        $directoutput = ob_get_clean();
        $this->assertSame('', $directoutput);
    }

    /**
     * An unset task-enabled setting does not prevent enforcement.
     *
     * @covers \tool_configascode\task\enforce_plugin_allowlist_task::execute
     */
    public function test_execute_enforces_when_task_enabled_setting_is_unset(): void {
        $this->resetAfterTest(true);

        $plugininfoclass = core_plugin_manager::resolve_plugininfo_class('mod');
        $plugininfoclass::enable_plugin('forum', 0);
        set_config('pluginallowlist', 'mod_forum', 'tool_configascode');
        unset_config('enforce_plugin_allowlist_task_enabled', 'tool_configascode');

        ob_start();
        $task = new enforce_plugin_allowlist_task();
        $task->execute();
        $output = ob_get_clean();

        $this->assertSame(1, $plugininfoclass::get_enabled_plugin('forum'));
        $this->assertStringContainsString("Enabled plugin 'mod_forum'.", $output);
    }

    /**
     * The scheduled task leaves plugin state unchanged when enforcement is disabled.
     *
     * @covers \tool_configascode\task\enforce_plugin_allowlist_task::execute
     */
    public function test_execute_does_not_enforce_when_task_disabled(): void {
        $this->resetAfterTest(true);

        $plugininfoclass = core_plugin_manager::resolve_plugininfo_class('mod');
        $plugininfoclass::enable_plugin('forum', 0);
        set_config('pluginallowlist', 'mod_forum', 'tool_configascode');
        set_config('enforce_plugin_allowlist_task_enabled', 0, 'tool_configascode');

        ob_start();
        $task = new enforce_plugin_allowlist_task();
        $task->execute();
        $output = ob_get_clean();

        $this->assertSame(0, $plugininfoclass::get_enabled_plugin('forum'));
        $this->assertStringContainsString('Allowlist enforcement skipped:', $output);
    }
}
