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
 * Test double for plugin allowlist enforcement.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode;

use core_plugin_manager;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/testable_plugin_allowlist_plugin.php');

/**
 * Plugin allowlist test double with controllable plugin state.
 */
class testable_plugin_allowlist extends plugin_allowlist {
    /** @var array<string, bool> Supported plugin types. */
    public static array $support = [];

    /** @var array<string, testable_plugin_allowlist_plugin> Plugins indexed by name. */
    public static array $plugins = [];

    /** @var array<string, int> Plugin states indexed by name. */
    public static array $states = [];

    /** @var string|null Plugin name whose state change throws an exception. */
    public static ?string $throwonenable = null;

    /**
     * Reset the test double state.
     *
     * @return void
     */
    public static function reset(): void {
        self::$support = [];
        self::$plugins = [];
        self::$states = [];
        self::$throwonenable = null;
    }

    /**
     * Resolve the test plugin-info class.
     *
     * @param string $type The plugin type.
     * @return string The test plugin-info class name.
     */
    protected static function get_plugininfo_class(string $type): string {
        return $type;
    }

    /**
     * Check whether the test plugin type supports disabling.
     *
     * @param string $plugininfoclass The plugin-info class name.
     * @return bool Whether the type supports disabling.
     */
    protected static function plugin_type_supports_disabling(string $plugininfoclass): bool {
        return self::$support[$plugininfoclass] ?? false;
    }

    /**
     * Return the configured test plugin records.
     *
     * @param string $type The plugin type.
     * @param string $typerootdir The plugin type root directory.
     * @param string $plugininfoclass The plugin-info class name.
     * @param core_plugin_manager $pluginmanager The plugin manager instance.
     * @return array<string, testable_plugin_allowlist_plugin> Plugins indexed by name.
     */
    protected static function get_plugins(
        string $type,
        string $typerootdir,
        string $plugininfoclass,
        core_plugin_manager $pluginmanager
    ): array {
        return self::$plugins;
    }

    /**
     * Return a configured test plugin state.
     *
     * @param string $plugininfoclass The plugin-info class name.
     * @param string $pluginname The plugin name.
     * @return int The plugin state.
     */
    protected static function get_enabled_plugin(string $plugininfoclass, string $pluginname): int {
        return self::$states[$pluginname];
    }

    /**
     * Change a test plugin state.
     *
     * @param string $plugininfoclass The plugin-info class name.
     * @param string $pluginname The plugin name.
     * @param int $enabled The desired plugin state.
     * @return bool Whether the state was changed.
     */
    protected static function enable_plugin(string $plugininfoclass, string $pluginname, int $enabled): bool {
        if ($pluginname === self::$throwonenable) {
            throw new \RuntimeException('Test exception');
        }

        self::$states[$pluginname] = $enabled;
        return true;
    }

    /**
     * Expose the helper that decides the desired plugin state.
     *
     * @param string $type The plugin type.
     * @param string $pluginname The plugin name.
     * @param array $allowedplugins Allowed plugins for the type.
     * @param array $denylist Denied plugins for the type.
     * @return int The desired enabled state.
     */
    public static function expose_resolve_desired_plugin_state(
        string $type,
        string $pluginname,
        array $allowedplugins,
        array $denylist
    ): int {
        return self::resolve_desired_plugin_state($type, $pluginname, $allowedplugins, $denylist);
    }
}
