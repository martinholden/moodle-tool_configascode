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
 * Plugin strings are defined here.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['checkconfigascode_check'] = 'Configuration as Code policy check';
$string['checkconfigascode_currenthash'] = 'Current policy hash';
$string['checkconfigascode_environment'] = 'Environment';
$string['checkconfigascode_hashmatches_no'] = 'Policy DB sync is pending.';
$string['checkconfigascode_hashmatches_yes'] = 'Policy DB sync is up to date.';
$string['checkconfigascode_hashnotset'] = '(not set)';
$string['checkconfigascode_normalisedenvironment'] = 'Normalised Environment';
$string['checkconfigascode_policybootstrap'] = 'Config.php bootstrap present';
$string['checkconfigascode_policyfileexists'] = 'Policy file exists for Environment';
$string['checkconfigascode_policysynchash'] = 'Policy DB sync status';
$string['checkconfigascode_policysynctime'] = 'Policy DB last sync timestamp';
$string['checkconfigascode_resolvedpolicyfile'] = 'Resolved policy file';
$string['checkconfigascode_storedhash'] = 'Policy DB stored hash';
$string['checkconfigascode_synclogsurl'] = 'Policy DB Sync Task Logs';
$string['checkconfigascode_synctaskenabled'] = 'Policy sync task enabled';
$string['checkconfigascode_testingenvironment'] = 'TESTING ENVIRONMENT';
$string['checkconfigascode_timenotset'] = '(not set)';
$string['emptypresetfile'] = 'The preset XML file is empty: {$a}';
$string['enforce_plugin_allowlist_task'] = 'Enforce plugin allowlist';
$string['enforce_plugin_allowlist_task_enabled'] = 'Enable plugin allowlist enforcement task';
$string['enforce_plugin_allowlist_task_enabled_desc'] = 'When disabled, the scheduled task will skip plugin allowlist and denylist enforcement.';
$string['invalidpresetxml'] = 'The preset XML file is invalid: {$a}';
$string['pluginallowlist'] = 'Plugin allowlist';
$string['pluginallowlist_desc'] = 'Enter one complete plugin component name per line, for example mod_quiz or filter_activitynames. Use type_* (for example mod_*) to allow every installed plugin of a known type. Adding any entry manages its entire plugin type: concrete entries disable other installed plugins of that type, while a wildcard allows them all. Types that do not support disabling and unavailable plugins are skipped.';
$string['plugindenylist'] = 'Plugin denylist';
$string['plugindenylist_desc'] = 'Enter one complete plugin component name per line, for example mod_scorm or filter_activitynames. Wildcards are not supported. Any plugin listed here is forced disabled even if it is enabled by the allowlist.';
$string['pluginname'] = 'Configuration as Code';
$string['policy:bootstrapmissing'] = 'The Configuration as Code bootstrap line is missing from config.php. Add the require_once block before lib/setup.php.';
$string['policy:default'] = 'The Configuration as Code DEFAULT Policy was applied.';
$string['policy:istesting'] = 'The Configuration as Code TESTING Policy was applied.';
$string['policy:notfound'] = 'The Configuration as Code \'{$a}\' Policy was not found. The DEFAULT Policy was applied.';
$string['policy:ok'] = 'The Configuration as Code \'{$a}\' Policy was applied.';
$string['policy:synctaskdisabled'] = 'The policy sync task is disabled, so database state may be out of date. {$a}';
$string['privacy:metadata'] = 'The tool_configascode plugin does not store any personal data.';
$string['sync_policy_task'] = 'Synchronise policy database state';
$string['sync_policy_task_enabled'] = 'Enable policy synchronisation task';
$string['sync_policy_task_enabled_desc'] = 'When disabled, the scheduled task will skip synchronising policy settings to the database.';
