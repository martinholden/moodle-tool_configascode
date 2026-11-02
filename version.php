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
 * Plugin version and other meta-data are defined here.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The current plugin version (Date: YYYYMMDDXX).
$plugin->version = 2026110200;
// The current plugin release, this is human readable version name.
$plugin->release  = '1.0.0';
// Requires Moodle 4.5 or later.
$plugin->requires = 2024100700;
// Full name of the plugin (used for diagnostics).
$plugin->component = 'tool_configascode';
// The maturity level of the plugin.
// MATURITY_ALPHA - internals can be tested using white box techniques.
// MATURITY_BETA - feature complete, ready for preview and testing.
// MATURITY_RC - tested, will be released unless there are fatal bugs.
// MATURITY_STABLE - ready for production deployment.
$plugin->maturity = MATURITY_STABLE;
// Dependency add comments about why module has this dependency.
$plugin->dependencies = [];
