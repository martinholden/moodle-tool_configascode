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
 * Plugin settings.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_configascode', get_string('pluginname', 'tool_configascode'));
    $ADMIN->add('tools', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'tool_configascode/sync_policy_task_enabled',
        get_string('sync_policy_task_enabled', 'tool_configascode'),
        get_string('sync_policy_task_enabled_desc', 'tool_configascode'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_configascode/enforce_plugin_allowlist_task_enabled',
        get_string('enforce_plugin_allowlist_task_enabled', 'tool_configascode'),
        get_string('enforce_plugin_allowlist_task_enabled_desc', 'tool_configascode'),
        1
    ));

    $settings->add(new admin_setting_configtextarea(
        'tool_configascode/pluginallowlist',
        get_string('pluginallowlist', 'tool_configascode'),
        get_string('pluginallowlist_desc', 'tool_configascode'),
        ''
    ));

    $settings->add(new admin_setting_configtextarea(
        'tool_configascode/plugindenylist',
        get_string('plugindenylist', 'tool_configascode'),
        get_string('plugindenylist_desc', 'tool_configascode'),
        ''
    ));
}
