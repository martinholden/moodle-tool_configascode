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
 * Functional tests for tool_configascode lib functions.
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
 * Functional tests for tool_configascode lib functions.
 *
 * Note: execution may take many minutes especially on slower servers.
 *
 * PHP UNIT GROUPS:
 *
 * @group tool
 * @group tool_configascode
 */
final class lib_test extends basic_testcase {
    /**
     * Backed-up CFG values for the policy test values.
     *
     * @var array<string, mixed>
     */
    private array $cfgbackup = [];

    /**
     * Config properties that the testing policy may set.
     *
     * @var string[]
     */
    private const CFG_PROPS = [
        'phpunit_tool_configascode_testvalue1',
        'phpunit_tool_configascode_testvalue2',
    ];

    protected function setUp(): void {
        parent::setUp();
        global $CFG;

        foreach (self::CFG_PROPS as $prop) {
            $this->cfgbackup[$prop] = property_exists($CFG, $prop) ? $CFG->$prop : null;
        }
    }

    protected function tearDown(): void {
        global $CFG;

        foreach (self::CFG_PROPS as $prop) {
            if ($this->cfgbackup[$prop] === null) {
                unset($CFG->$prop);
            } else {
                $CFG->$prop = $this->cfgbackup[$prop];
            }
        }

        parent::tearDown();
    }

    /**
     * Test the status checks hook returns the configascode check.
     *
     * @covers \tool_configascode_status_checks
     */
    public function test_status_checks_returns_configascode_check(): void {
        $checks = \tool_configascode_status_checks();

        $this->assertCount(1, $checks);
        $this->assertInstanceOf(\tool_configascode\check\configascode_check::class, $checks[0]);
    }

    /**
     * Test apply_policy loads the resolved policy when it exists.
     *
     * @covers \tool_configascode_apply_policy
     */
    public function test_apply_policy_loads_resolved_policy_when_present(): void {
        global $CFG;

        $resolvedpath = '';

        $loadedpath = \tool_configascode_apply_policy(function (string $path) use (&$resolvedpath): bool {
            $resolvedpath = $path;
            return true;
        }, true);

        $this->assertNotNull($resolvedpath);
        $this->assertStringContainsString('/tests/fixtures/testingpolicy.php', str_replace('\\', '/', $resolvedpath));
        $this->assertSame(str_replace('\\', '/', $resolvedpath), str_replace('\\', '/', $loadedpath));
        $this->assertTrue(isset($CFG->phpunit_tool_configascode_testvalue1));
        $this->assertTrue(isset($CFG->phpunit_tool_configascode_testvalue2));
    }

    /**
     * Test apply_policy loads the resolved policy via require_once when force include is disabled.
     *
     * @covers \tool_configascode_apply_policy
     */
    public function test_apply_policy_loads_resolved_policy_with_require_once_when_present(): void {
        $loadedpath = \tool_configascode_apply_policy(function (): bool {
            return true;
        });

        $this->assertStringContainsString(
            '/tests/fixtures/testingpolicy.php',
            str_replace('\\', '/', $loadedpath)
        );
    }

    /**
     * Test apply_policy falls back to the default policy when the resolved policy is missing.
     *
     * @covers \tool_configascode_apply_policy
     */
    public function test_apply_policy_falls_back_to_default_policy_when_missing(): void {
        $loadedpath = \tool_configascode_apply_policy(function (): bool {
            return false;
        });

        $this->assertStringEndsWith(
            'admin/tool/configascode/policy/default.php',
            str_replace('\\', '/', $loadedpath)
        );
    }

    /**
     * Test apply_policy falls back to default policy via include when force include is enabled.
     *
     * @covers \tool_configascode_apply_policy
     */
    public function test_apply_policy_falls_back_to_default_policy_with_include_when_missing(): void {
        $loadedpath = \tool_configascode_apply_policy(function (): bool {
            return false;
        }, true);

        $this->assertStringEndsWith(
            'admin/tool/configascode/policy/default.php',
            str_replace('\\', '/', $loadedpath)
        );
    }
}
