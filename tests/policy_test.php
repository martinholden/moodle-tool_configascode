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
 * Functional tests for tool_configascode
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode;

use basic_testcase;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');

/**
 * Functional test for tool_configascode
 *
 * Note: execution may take many minutes especially on slower servers.
 *
 * PHP UNIT GROUPS:
 *
 * @group tool
 * @group tool_configascode
 */
final class policy_test extends basic_testcase {
    /**
     * Test policy is applied when PHPUNIT environment is detected.
     *
     * @covers \tool_configascode_apply_policy
     */
    public function test_policy_applied(): void {
        global $CFG;

        $backup = [];
        foreach (['phpunit_tool_configascode_testvalue1', 'phpunit_tool_configascode_testvalue2'] as $prop) {
            $backup[$prop] = property_exists($CFG, $prop) ? $CFG->$prop : null;
        }

        try {
            $loadedpath = \tool_configascode_apply_policy(function (): bool {
                return true;
            }, true);

            $this->assertStringContainsString(
                '/tests/fixtures/testingpolicy.php',
                str_replace('\\', '/', $loadedpath)
            );

            // Check the config values as set in the testing policy file exist.
            $this->assertTrue(isset($CFG->phpunit_tool_configascode_testvalue1));
            $this->assertTrue(isset($CFG->phpunit_tool_configascode_testvalue2));

            // Check the values are correct as set in the testing policy file.
            // Moodle converts true/false to "1"/"0" when stored in config, so we
            // need to cast to bool to validate the value is correct.
            $this->assertTrue((bool)$CFG->phpunit_tool_configascode_testvalue1);
            $this->assertFalse((bool)$CFG->phpunit_tool_configascode_testvalue2);
        } finally {
            foreach ($backup as $prop => $value) {
                if ($value === null) {
                    unset($CFG->$prop);
                } else {
                    $CFG->$prop = $value;
                }
            }
        }
    }

    /**
     * Test environment policy settings override common settings and config.php values.
     *
     * @coversNothing
     */
    public function test_environment_policy_settings_override_common_and_config(): void {
        global $CFG;

        $valueprop = 'phpunit_tool_configascode_precedence_value';
        $commonvalueprop = 'phpunit_tool_configascode_precedence_common_value';
        $backup = [];
        foreach ([$valueprop, $commonvalueprop] as $prop) {
            $exists = property_exists($CFG, $prop);
            $backup[$prop] = [
                'exists' => $exists,
                'value' => $exists ? $CFG->$prop : null,
            ];
        }

        try {
            // Simulate the value initially set in config.php.
            $CFG->$valueprop = 'config';

            include(__DIR__ . '/fixtures/policy_precedence/dev.php');

            // The environment policy records the value it received from common.php.
            $this->assertSame('common', $CFG->$commonvalueprop);
            $this->assertSame('environment', $CFG->$valueprop);
        } finally {
            foreach ($backup as $prop => $state) {
                if ($state['exists']) {
                    $CFG->$prop = $state['value'];
                } else {
                    unset($CFG->$prop);
                }
            }
        }
    }
}
