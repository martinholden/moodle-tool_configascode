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
 * The production configascode policy, this policy file is used for
 * environments with a prefix of 'prod' (configurable via env_prefixes setting).
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Coding standards ignored due to no config inclusion or moodle internal check
// @codingStandardsIgnoreStart
// Include common lockdown settings.
require_once(__DIR__ . '/common.php');

// You can add new or override the common lockdown settings by entering
// settings below here.

// Set all the relevant configuration values below.
// Use $CFG->{configsetting} for top level values.
// Example:
// $CFG->enableblogs = 0;

// Use $CFG->forced_plugin_settings['{pluginname}']['{setting}'] for plugin specific value.
// Example:
// $CFG->forced_plugin_settings['core_competency']['enabled'] = 0;

// If adding any define statements please ensure you always check if the
// define already exists before defining it again to avoid warnings, which
// become exceptions IN BEHAT environment.
// Example:
// if (!defined('CONSTANT_NAME')) {
//     define('CONSTANT_NAME', 'value');
// }

// @codingStandardsIgnoreEnd