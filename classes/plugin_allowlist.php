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
 * Plugin allowlist enforcement.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode;

use core_component;
use core_plugin_manager;

/**
 * Enforces the configured plugin allowlist.
 */
class plugin_allowlist {
    /** The plugin configuration setting name. */
    public const ALLOWLIST_CONFIG_NAME = 'pluginallowlist';

    /** The plugin denylist configuration setting name. */
    public const DENYLIST_CONFIG_NAME = 'plugindenylist';
    /** @var callable Logger callback, defaulting to a no-op. */
    private static $logger = [self::class, 'null_logger'];

    /**
     * Set the logger callback used by this service.
     *
     * Passing null restores the default no-op logger.
     *
     * @param ?callable $logger
     * @return void
     */
    public static function set_logger(?callable $logger): void {
        self::$logger = $logger ?? [self::class, 'null_logger'];
    }

    /**
     * Parse a newline-delimited list of complete plugin component names.
     *
     * @param string $value The raw configuration value.
     * @return array<string, array<string, bool>> Allowed plugin names indexed by plugin type.
     */
    public static function parse(string $value): array {
        [$allowlist] = self::parse_with_errors($value);
        return $allowlist;
    }

    /**
     * Parse a newline-delimited denylist of concrete plugin names.
     *
     * @param string $value The raw configuration value.
     * @return array<string, array<string, bool>> Denied plugin names indexed by plugin type.
     */
    public static function parse_denylist(string $value): array {
        [$denylist] = self::parse_denylist_with_errors($value);
        return $denylist;
    }

    /**
     * Enforce the configured plugin allowlist.
     *
     * @return array<string, int> Counts of changed, skipped and invalid entries.
     */
    public static function enforce(): array {
        $rawallowlist = get_config('tool_configascode', self::ALLOWLIST_CONFIG_NAME);
        [$allowlist, $invalidentries] = self::parse_with_errors((string)$rawallowlist);
        $rawdenylist = get_config('tool_configascode', self::DENYLIST_CONFIG_NAME);
        [$denylist, $denyinvalidentries] = self::parse_denylist_with_errors((string)$rawdenylist);
        $summary = self::build_summary($invalidentries, $denyinvalidentries);

        foreach ($invalidentries as $entry) {
            self::log("Ignoring invalid plugin allowlist entry '{$entry}'.");
        }

        foreach ($denyinvalidentries as $entry) {
            self::log("Ignoring invalid plugin denylist entry '{$entry}'.");
        }

        $plugintypes = core_component::get_plugin_types();
        $pluginmanager = core_plugin_manager::instance();

        foreach ($allowlist as $type => $allowedplugins) {
            $summary = self::enforce_allowlist_type(
                $type,
                $allowedplugins,
                $denylist,
                $plugintypes,
                $pluginmanager,
                $summary
            );
        }

        $summary = self::enforce_denylist_only_entries($denylist, $plugintypes, $pluginmanager, $summary);

        self::log('Allowlist enforcement complete. Results: ');
        self::log('Enabled: ' . $summary['enabled'] . ', Disabled: ' . $summary['disabled']
            . ', Invalid: ' . $summary['invalid'] . ', Invalid denylist: ' . $summary['invaliddeny']
            . ', Skipped types: ' . $summary['skippedtypes']
            . ', Unknown plugins: ' . $summary['unknownplugins'] . ', Errors: ' . $summary['errors']);
        return $summary;
    }

    /**
     * Build the initial summary for enforcement results.
     *
     * @param string[] $invalidentries Invalid allowlist entries.
     * @param string[] $denyinvalidentries Invalid denylist entries.
     * @return array<string, int>
     */
    private static function build_summary(array $invalidentries, array $denyinvalidentries): array {
        return [
            'enabled' => 0,
            'disabled' => 0,
            'invalid' => count($invalidentries),
            'invaliddeny' => count($denyinvalidentries),
            'skippedtypes' => 0,
            'unknownplugins' => 0,
            'errors' => 0,
        ];
    }

    /**
     * Apply allowlist logic for a specific plugin type.
     *
     * @param string $type The plugin type.
     * @param array $allowedplugins The allowed plugins for the type.
     * @param array $denylist The denylist indexed by type.
     * @param array $plugintypes The known plugin types.
     * @param core_plugin_manager $pluginmanager The plugin manager instance.
     * @param array $summary The current summary.
     * @return array
     */
    private static function enforce_allowlist_type(
        string $type,
        array $allowedplugins,
        array $denylist,
        array $plugintypes,
        core_plugin_manager $pluginmanager,
        array $summary
    ): array {
        $plugininfoclass = static::get_plugininfo_class($type);
        if (!static::plugin_type_supports_disabling($plugininfoclass)) {
            self::log("Skipping plugin type '{$type}' because it does not support disabling.");
            $summary['skippedtypes']++;
            return $summary;
        }

        $plugins = static::get_plugins($type, $plugintypes[$type], $plugininfoclass, $pluginmanager);
        $summary = self::record_unknown_allowlist_plugins($type, $allowedplugins, $plugins, $summary);

        foreach ($plugins as $pluginname => $plugin) {
            if (!self::is_plugin_change_candidate($plugin)) {
                continue;
            }

            $summary = self::apply_plugin_state_change(
                $type,
                $pluginname,
                $plugininfoclass,
                $allowedplugins,
                $denylist,
                $summary
            );
        }

        return $summary;
    }

    /**
     * Record unknown or unavailable allowlist entries for the given type.
     *
     * @param string $type The plugin type.
     * @param array $allowedplugins The allowed plugins for the type.
     * @param array $plugins The available plugins for the type.
     * @param array $summary The current summary.
     * @return array The updated summary.
     */
    private static function record_unknown_allowlist_plugins(
        string $type,
        array $allowedplugins,
        array $plugins,
        array $summary
    ): array {
        foreach (array_keys($allowedplugins) as $pluginname) {
            if ($pluginname === '*') {
                continue;
            }

            if (!isset($plugins[$pluginname]) || !$plugins[$pluginname]->is_installed_and_upgraded()) {
                self::log("Ignoring unknown or unavailable plugin '{$type}_{$pluginname}'.");
                $summary['unknownplugins']++;
            }
        }

        return $summary;
    }

    /**
     * Check whether a plugin can be considered for state changes.
     *
     * @param object $plugin The plugin instance.
     * @return bool Whether the plugin is eligible for enforcement.
     */
    private static function is_plugin_change_candidate(object $plugin): bool {
        return $plugin->rootdir && $plugin->is_installed_and_upgraded();
    }

    /**
     * Apply the desired state change for one plugin.
     *
     * @param string $type The plugin type.
     * @param string $pluginname The plugin name.
     * @param string $plugininfoclass The plugin info class.
     * @param array $allowedplugins The allowed plugins for the type.
     * @param array $denylist The denylist grouped by type.
     * @param array $summary The current summary.
     * @return array The updated summary.
     */
    protected static function apply_plugin_state_change(
        string $type,
        string $pluginname,
        string $plugininfoclass,
        array $allowedplugins,
        array $denylist,
        array $summary
    ): array {
        $desiredstate = self::resolve_desired_plugin_state($type, $pluginname, $allowedplugins, $denylist);
        $currentstate = static::get_enabled_plugin($plugininfoclass, $pluginname);
        if ($currentstate === $desiredstate) {
            return $summary;
        }

        try {
            if (static::enable_plugin($plugininfoclass, $pluginname, $desiredstate)) {
                $summary[$desiredstate ? 'enabled' : 'disabled']++;
                self::log(($desiredstate ? 'Enabled' : 'Disabled') . " plugin '{$type}_{$pluginname}'.");
            }
        } catch (\Throwable $exception) {
            $summary['errors']++;
            self::log("Unable to change plugin '{$type}_{$pluginname}': {$exception->getMessage()}");
        }

        return $summary;
    }

    /**
     * Resolve the desired state for a plugin based on the allowlist and denylist.
     *
     * @param string $type The plugin type.
     * @param string $pluginname The plugin name.
     * @param array $allowedplugins The allowed plugins for the type.
     * @param array $denylist The denylist grouped by type.
     * @return int The desired enabled state.
     */
    protected static function resolve_desired_plugin_state(
        string $type,
        string $pluginname,
        array $allowedplugins,
        array $denylist
    ): int {
        if (isset($denylist[$type][$pluginname])) {
            return 0;
        }

        return (isset($allowedplugins['*']) || isset($allowedplugins[$pluginname])) ? 1 : 0;
    }

    /**
     * Apply denylist-only overrides for explicit plugins not handled by the allowlist.
     *
     * @param array $denylist The denylist grouped by type.
     * @param array $plugintypes The known plugin types.
     * @param core_plugin_manager $pluginmanager The plugin manager instance.
     * @param array $summary The current summary.
     * @return array
     */
    private static function enforce_denylist_only_entries(
        array $denylist,
        array $plugintypes,
        core_plugin_manager $pluginmanager,
        array $summary
    ): array {
        foreach ($denylist as $type => $deniedplugins) {
            $summary = self::enforce_denylist_for_type(
                $type,
                $deniedplugins,
                $plugintypes,
                $pluginmanager,
                $summary
            );
        }

        return $summary;
    }

    /**
     * Apply the explicit denylist for a specific plugin type.
     *
     * @param string $type The plugin type.
     * @param array $deniedplugins The denied plugin names for the type.
     * @param array $plugintypes The known plugin types.
     * @param core_plugin_manager $pluginmanager The plugin manager instance.
     * @param array $summary The current summary.
     * @return array The updated summary.
     */
    private static function enforce_denylist_for_type(
        string $type,
        array $deniedplugins,
        array $plugintypes,
        core_plugin_manager $pluginmanager,
        array $summary
    ): array {
        $plugininfoclass = static::get_plugininfo_class($type);
        if (!static::plugin_type_supports_disabling($plugininfoclass)) {
            return $summary;
        }

        $plugins = static::get_plugins($type, $plugintypes[$type], $plugininfoclass, $pluginmanager);
        foreach (array_keys($deniedplugins) as $pluginname) {
            $summary = self::disable_denylisted_plugin(
                $type,
                $pluginname,
                $plugininfoclass,
                $plugins,
                $summary
            );
        }

        return $summary;
    }

    /**
     * Disable a single plugin that was explicitly denied.
     *
     * @param string $type The plugin type.
     * @param string $pluginname The plugin name.
     * @param string $plugininfoclass The plugin info class.
     * @param array $plugins The available plugins for the type.
     * @param array $summary The current summary.
     * @return array The updated summary.
     */
    private static function disable_denylisted_plugin(
        string $type,
        string $pluginname,
        string $plugininfoclass,
        array $plugins,
        array $summary
    ): array {
        if (!isset($plugins[$pluginname]) || !$plugins[$pluginname]->is_installed_and_upgraded()) {
            self::log("Ignoring unknown or unavailable plugin '{$type}_{$pluginname}'.");
            $summary['unknownplugins']++;
            return $summary;
        }

        if (!$plugins[$pluginname]->rootdir || !$plugins[$pluginname]->is_installed_and_upgraded()) {
            return $summary;
        }

        $currentstate = static::get_enabled_plugin($plugininfoclass, $pluginname);
        if ($currentstate === 0) {
            return $summary;
        }

        try {
            if (static::enable_plugin($plugininfoclass, $pluginname, 0)) {
                $summary['disabled']++;
                self::log("Disabled plugin '{$type}_{$pluginname}' due to denylist override.");
            }
        } catch (\Throwable $exception) {
            $summary['errors']++;
            self::log("Unable to change plugin '{$type}_{$pluginname}': {$exception->getMessage()}");
        }

        return $summary;
    }

    /**
     * Resolve the plugin-info class for a plugin type.
     *
     * @param string $type The plugin type.
     * @return string The plugin-info class name.
     */
    protected static function get_plugininfo_class(string $type): string {
        return core_plugin_manager::resolve_plugininfo_class($type);
    }

    /**
     * Check whether a plugin-info class supports disabling plugins.
     *
     * @param string $plugininfoclass The plugin-info class name.
     * @return bool Whether the type supports disabling.
     */
    protected static function plugin_type_supports_disabling(string $plugininfoclass): bool {
        return $plugininfoclass::plugintype_supports_disabling();
    }

    /**
     * Get the plugins for a type through its plugin-info class.
     *
     * @param string $type The plugin type.
     * @param string $typerootdir The plugin type root directory.
     * @param string $plugininfoclass The plugin-info class name.
     * @param core_plugin_manager $pluginmanager The plugin manager instance.
     * @return array<string, \core\plugininfo\base> Plugins indexed by name.
     */
    protected static function get_plugins(
        string $type,
        string $typerootdir,
        string $plugininfoclass,
        core_plugin_manager $pluginmanager
    ): array {
        return $plugininfoclass::get_plugins($type, $typerootdir, $plugininfoclass, $pluginmanager);
    }

    /**
     * Get the current state of a plugin.
     *
     * @param string $plugininfoclass The plugin-info class name.
     * @param string $pluginname The plugin name.
     * @return int The plugin state.
     */
    protected static function get_enabled_plugin(string $plugininfoclass, string $pluginname): int {
        return $plugininfoclass::get_enabled_plugin($pluginname);
    }

    /**
     * Change the enabled state of a plugin.
     *
     * @param string $plugininfoclass The plugin-info class name.
     * @param string $pluginname The plugin name.
     * @param int $enabled The desired plugin state.
     * @return bool Whether the state was changed.
     */
    protected static function enable_plugin(string $plugininfoclass, string $pluginname, int $enabled): bool {
        return $plugininfoclass::enable_plugin($pluginname, $enabled);
    }

    /**
     * Parse a raw allowlist and retain the invalid entries for task logging.
     *
     * @param string $value The raw configuration value.
     */
    private static function parse_with_errors(string $value): array {
        return self::parse_config_entries($value, true);
    }

    /**
     * Parse a raw denylist and retain the invalid entries for task logging.
     *
     * @param string $value The raw configuration value.
     */
    private static function parse_denylist_with_errors(string $value): array {
        return self::parse_config_entries($value, false);
    }

    /**
     * Parse a raw config list with support for wildcard entries only on allowlists.
     *
     * @param string $value The raw configuration value.
     * @param bool $allowwildcards Whether wildcard type entries are supported.
     * @return array{0: array<string, array<string,bool>>, 1: string[]} Parsed entries and invalid entries.
     */
    private static function parse_config_entries(string $value, bool $allowwildcards): array {
        $result = [];
        $invalidentries = [];
        $plugintypes = core_component::get_plugin_types();
        $lines = preg_split('/\R/u', $value) ?: [];

        foreach ($lines as $line) {
            $component = trim($line);
            if ($component === '') {
                continue;
            }

            [$type, $pluginname, $isvalid] = self::parse_component_entry($component, $allowwildcards, $plugintypes);
            if (!$isvalid) {
                $invalidentries[] = $component;
                continue;
            }

            $result[$type][$pluginname] = true;
        }

        return [$result, $invalidentries];
    }

    /**
     * Parse a single component entry and validate whether it is allowed.
     *
     * @param string $component The raw component entry.
     * @param bool $allowwildcards Whether wildcard entries are allowed.
     * @param array $plugintypes The known plugin types.
     * @return array
     */
    private static function parse_component_entry(string $component, bool $allowwildcards, array $plugintypes): array {
        if (substr($component, -2) === '_*') {
            if (!$allowwildcards) {
                return [null, '', false];
            }

            $type = substr($component, 0, -2);
            if (!isset($plugintypes[$type])) {
                return [null, '', false];
            }

            return [$type, '*', true];
        }

        if (strpos($component, '_') === false) {
            return [null, '', false];
        }

        [$type, $pluginname] = core_component::normalize_component($component);
        if (!isset($plugintypes[$type]) || !core_component::is_valid_plugin_name($type, $pluginname)) {
            return [null, '', false];
        }

        return [$type, $pluginname, true];
    }

    /**
     * Send an enforcement message to the configured logger.
     *
     * @param string $message The message to write.
     * @return void
     */
    private static function log(string $message): void {
        (self::$logger)($message);
    }

    /**
     * Default no-op logger.
     *
     * @param string $message
     * @return void
     */
    private static function null_logger(string $message): void {
    }
}
