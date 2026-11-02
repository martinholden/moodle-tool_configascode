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
 * Database-backed tests for plugin allowlist enforcement.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode;

use advanced_testcase;
use core_plugin_manager;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/testable_plugin_allowlist.php');

/**
 * Database-backed tests for plugin allowlist enforcement.
 *
 * @covers \tool_configascode\plugin_allowlist
 * @group tool
 * @group tool_configascode
 */
final class plugin_allowlist_db_test extends advanced_testcase {
    /**
     * Test enforcement enables allowlisted modules and disables other modules.
     *
     * @covers \tool_configascode\plugin_allowlist::enforce
     */
    public function test_enforce_changes_module_visibility_to_match_allowlist(): void {
        $this->resetAfterTest(true);

        $plugininfoclass = core_plugin_manager::resolve_plugininfo_class('mod');
        $plugininfoclass::enable_plugin('forum', 0);
        $plugininfoclass::enable_plugin('assign', 1);
        set_config(plugin_allowlist::ALLOWLIST_CONFIG_NAME, 'mod_forum', 'tool_configascode');
        set_config('enforce_plugin_allowlist_task_enabled', 1, 'tool_configascode');

        $messages = [];
        $logger = static function (string $message) use (&$messages): void {
            $messages[] = $message;
        };
        plugin_allowlist::set_logger($logger);
        try {
            $summary = plugin_allowlist::enforce();
        } finally {
            plugin_allowlist::set_logger(null);
        }

        $this->assertSame(1, $plugininfoclass::get_enabled_plugin('forum'));
        $this->assertSame(0, $plugininfoclass::get_enabled_plugin('assign'));
        $this->assertGreaterThanOrEqual(1, $summary['enabled']);
        $this->assertGreaterThanOrEqual(1, $summary['disabled']);
        $this->assertArrayNotHasKey('task_enabled', $summary);
        $this->assertContains("Enabled plugin 'mod_forum'.", $messages);
    }

    /**
     * Setting a logger routes enforcement messages to it and null restores the no-op logger.
     *
     * @covers \tool_configascode\plugin_allowlist::enforce
     * @covers \tool_configascode\plugin_allowlist::set_logger
     * @covers \tool_configascode\plugin_allowlist::log
     * @covers \tool_configascode\plugin_allowlist::null_logger
     */
    public function test_set_logger_routes_messages_and_null_restores_silence(): void {
        $this->resetAfterTest(true);

        $plugininfoclass = core_plugin_manager::resolve_plugininfo_class('mod');
        $plugininfoclass::enable_plugin('forum', 0);
        set_config(plugin_allowlist::ALLOWLIST_CONFIG_NAME, 'mod_forum', 'tool_configascode');

        $messages = [];
        $logger = static function (string $message) use (&$messages): void {
            $messages[] = $message;
        };

        plugin_allowlist::set_logger($logger);
        try {
            $summary = plugin_allowlist::enforce();
        } finally {
            plugin_allowlist::set_logger(null);
        }

        $this->assertSame(1, $plugininfoclass::get_enabled_plugin('forum'));
        $this->assertGreaterThanOrEqual(1, $summary['enabled']);
        $this->assertContains("Enabled plugin 'mod_forum'.", $messages);

        ob_start();
        plugin_allowlist::enforce();
        $output = ob_get_clean();
        $this->assertSame('', $output);
    }

    /**
     * Direct enforcement ignores the task toggle and reports enforcement results only.
     *
     * @covers \tool_configascode\plugin_allowlist::enforce
     */
    public function test_enforce_ignores_task_toggle_and_excludes_task_state(): void {
        $this->resetAfterTest(true);
        $plugininfoclass = core_plugin_manager::resolve_plugininfo_class('mod');
        $plugininfoclass::enable_plugin('forum', 0);
        set_config(plugin_allowlist::ALLOWLIST_CONFIG_NAME, 'mod_forum', 'tool_configascode');
        set_config('enforce_plugin_allowlist_task_enabled', 0, 'tool_configascode');

        ob_start();
        $summary = plugin_allowlist::enforce();
        $output = ob_get_clean();

        $this->assertSame(1, $plugininfoclass::get_enabled_plugin('forum'));
        $this->assertArrayNotHasKey('task_enabled', $summary);
        $this->assertSame('', $output);
    }

    /**
     * Test a module wildcard enables all installed modules without disabling any.
     *
     * @covers \tool_configascode\plugin_allowlist::enforce
     */
    public function test_enforce_allows_all_installed_modules_for_wildcard(): void {
        $this->resetAfterTest(true);

        $plugininfoclass = core_plugin_manager::resolve_plugininfo_class('mod');
        $plugininfoclass::enable_plugin('forum', 0);
        $plugininfoclass::enable_plugin('assign', 0);
        set_config(plugin_allowlist::ALLOWLIST_CONFIG_NAME, 'mod_*', 'tool_configascode');

        $summary = plugin_allowlist::enforce();

        $this->assertSame(1, $plugininfoclass::get_enabled_plugin('forum'));
        $this->assertSame(1, $plugininfoclass::get_enabled_plugin('assign'));
        $this->assertGreaterThanOrEqual(2, $summary['enabled']);
        $this->assertSame(0, $summary['disabled']);
        $this->assertSame(0, $summary['unknownplugins']);
    }

    /**
     * Test denylist entries override wildcard allowlist entries.
     *
     * @covers \tool_configascode\plugin_allowlist::enforce
     */
    public function test_enforce_denylist_overrides_wildcard_allowlist(): void {
        $this->resetAfterTest(true);

        $plugininfoclass = core_plugin_manager::resolve_plugininfo_class('mod');
        $plugininfoclass::enable_plugin('forum', 1);
        $plugininfoclass::enable_plugin('assign', 0);
        set_config(plugin_allowlist::ALLOWLIST_CONFIG_NAME, 'mod_*', 'tool_configascode');
        set_config(plugin_allowlist::DENYLIST_CONFIG_NAME, 'mod_forum', 'tool_configascode');

        $summary = plugin_allowlist::enforce();

        $this->assertSame(0, $plugininfoclass::get_enabled_plugin('forum'));
        $this->assertSame(1, $plugininfoclass::get_enabled_plugin('assign'));
        $this->assertGreaterThanOrEqual(1, $summary['disabled']);
    }

    /**
     * Test enforcement handles every defensive allowlist path.
     *
     * @covers \tool_configascode\plugin_allowlist::enforce
     */
    public function test_enforce_handles_invalid_unsupported_missing_unavailable_and_failed_plugins(): void {
        $this->resetAfterTest(true);
        testable_plugin_allowlist::reset();
        testable_plugin_allowlist::$support = [
            'mod' => true,
            'tool' => false,
        ];
        testable_plugin_allowlist::$plugins = [
            'allowed' => new testable_plugin_allowlist_plugin('/plugin/allowed'),
            'disabled' => new testable_plugin_allowlist_plugin('/plugin/disabled'),
            'unavailable' => new testable_plugin_allowlist_plugin(false, false),
            'throw' => new testable_plugin_allowlist_plugin('/plugin/throw'),
        ];
        testable_plugin_allowlist::$states = [
            'allowed' => 0,
            'disabled' => 1,
            'throw' => 0,
        ];
        testable_plugin_allowlist::$throwonenable = 'throw';
        set_config(plugin_allowlist::ALLOWLIST_CONFIG_NAME, implode("\n", [
            'invalid',
            'tool_configascode',
            'mod_allowed',
            'mod_throw',
            'mod_missing',
            'mod_unavailable',
        ]), 'tool_configascode');

        $summary = testable_plugin_allowlist::enforce();

        $this->assertSame(1, testable_plugin_allowlist::$states['allowed']);
        $this->assertSame(0, testable_plugin_allowlist::$states['disabled']);
        $this->assertSame(1, $summary['invalid']);
        $this->assertSame(1, $summary['skippedtypes']);
        $this->assertSame(2, $summary['unknownplugins']);
        $this->assertSame(1, $summary['errors']);
    }

    /**
     * Test denylist enforcement handles wildcard rejection, unsupported types and deny override branches.
     *
     * @covers \tool_configascode\plugin_allowlist::enforce
     */
    public function test_enforce_handles_denylist_invalid_unsupported_missing_unavailable_and_failed_plugins(): void {
        $this->resetAfterTest(true);
        testable_plugin_allowlist::reset();
        testable_plugin_allowlist::$support = [
            'mod' => true,
            'tool' => false,
        ];
        testable_plugin_allowlist::$plugins = [
            'allowed' => new testable_plugin_allowlist_plugin('/plugin/allowed'),
            'disabled' => new testable_plugin_allowlist_plugin('/plugin/disabled'),
            'rootless' => new testable_plugin_allowlist_plugin(false),
            'unavailable' => new testable_plugin_allowlist_plugin(false, false),
            'throw' => new testable_plugin_allowlist_plugin('/plugin/throw'),
        ];
        testable_plugin_allowlist::$states = [
            'allowed' => 1,
            'disabled' => 0,
            'rootless' => 1,
            'throw' => 1,
        ];
        testable_plugin_allowlist::$throwonenable = 'throw';
        set_config(plugin_allowlist::ALLOWLIST_CONFIG_NAME, '', 'tool_configascode');
        set_config(plugin_allowlist::DENYLIST_CONFIG_NAME, implode("\n", [
            'mod_*',
            'tool_configascode',
            'mod_allowed',
            'mod_throw',
            'mod_missing',
            'mod_unavailable',
            'mod_disabled',
            'mod_rootless',
        ]), 'tool_configascode');

        $summary = testable_plugin_allowlist::enforce();

        $this->assertSame(1, $summary['invaliddeny']);
        $this->assertSame(0, testable_plugin_allowlist::$states['allowed']);
        $this->assertSame(1, $summary['errors']);
        $this->assertGreaterThanOrEqual(1, $summary['disabled']);
        $this->assertSame(2, $summary['unknownplugins']);
    }
}
