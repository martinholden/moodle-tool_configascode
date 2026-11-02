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
 * The main entry point for applying the policy based on the current environment.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We ignore the MoodleInternal check here as this file is
// intended to be included from other Moodle code.
// phpcs:ignoreFile moodle.Files.MoodleInternal.MoodleInternalGlobalState

if (!defined('TOOL_LOCKDOWN_CONFIG_APPLYPOLICY_INCLUDED')) {
    define('TOOL_LOCKDOWN_CONFIG_APPLYPOLICY_INCLUDED', true);
}

require_once(__DIR__ . '/lib.php');
\tool_configascode_apply_policy();
