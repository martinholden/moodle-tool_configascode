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
 * The configascode policy, this policy file is used when BEHAT or PHPUNIT_TEST environment is detected,
 * it ensures that no lockdown settings are applied during testing to avoid breaking tests that rely on
 * default config settings.
 *
 * It does set some $CFG values to allow PHPUNIT and BEHAT tests to validate that the policy file is being loaded
 * and applied correctly.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Coding standards ignored due to no config inclusion or moodle internal check
// @codingStandardsIgnoreStart

// These test values need to be prefixed with phpunit_ otherwise they are removed.
$CFG->phpunit_tool_configascode_testvalue1 = true;
$CFG->phpunit_tool_configascode_testvalue2 = false;
// @codingStandardsIgnoreEnd
