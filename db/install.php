<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The post-installation hook, which is called immediately after
 * the initial creation of your database schema.
 *
 * @return bool result
 */
function xmldb_tool_configascode_install() {

    // Ensure we create the default config settings for the plugin, if they do not already exist.
    $config = get_config('tool_configascode');

    if (!isset($config->sync_policy_task_enabled)) {
        set_config('sync_policy_task_enabled', 1, 'tool_configascode');
    }

    if (!isset($config->enforce_plugin_allowlist_task_enabled)) {
        set_config('enforce_plugin_allowlist_task_enabled', 1, 'tool_configascode');
    }

    if (!isset($config->pluginallowlist)) {
        set_config('pluginallowlist', '', 'tool_configascode');
    }
    if (!isset($config->plugindenylist)) {
        set_config('plugindenylist', '', 'tool_configascode');
    }

    return true;
}
