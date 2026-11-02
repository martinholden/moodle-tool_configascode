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
 * Privacy tests for tool_configascode.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode\privacy;

use basic_testcase;

/**
 * Privacy tests for tool_configascode.
 *
 * @covers \tool_configascode\privacy\provider
 *
 * Note: execution may take many minutes especially on slower servers.
 *
 * PHP UNIT GROUPS:
 *
 * @group tool
 * @group tool_configascode
 */
final class provider_test extends basic_testcase {
    /**
     * Test the privacy provider reason contract and its language string.
     */
    public function test_get_reason(): void {
        $reason = provider::get_reason();

        $this->assertSame('privacy:metadata', $reason);
        $this->assertSame(
            get_string('privacy:metadata', 'tool_configascode'),
            get_string($reason, 'tool_configascode')
        );
    }
}
