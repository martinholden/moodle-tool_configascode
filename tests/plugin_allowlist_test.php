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
 * Unit tests for plugin allowlist parsing.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode;

use basic_testcase;

/**
 * Unit tests for plugin allowlist parsing.
 *
 * @covers \tool_configascode\plugin_allowlist
 * @group tool
 * @group tool_configascode
 */
final class plugin_allowlist_test extends basic_testcase {
    /**
     * Test allowlist entries are grouped by plugin type and deduplicated.
     *
     * @covers \tool_configascode\plugin_allowlist::parse
     */
    public function test_parse_groups_and_deduplicates_components(): void {
        $allowlist = plugin_allowlist::parse(" mod_scorm\nmod_quiz\nfilter_activitynames\nmod_scorm\n");

        $this->assertSame([
            'mod' => [
                'scorm' => true,
                'quiz' => true,
            ],
            'filter' => [
                'activitynames' => true,
            ],
        ], $allowlist);
    }

    /**
     * Test invalid entries are not included in the parsed allowlist.
     *
     * @covers \tool_configascode\plugin_allowlist::parse
     */
    public function test_parse_ignores_invalid_entries(): void {
        $allowlist = plugin_allowlist::parse("quiz\nnotatype_example\nmod_invalid_name\nmod_forum");

        $this->assertSame([
            'mod' => [
                'forum' => true,
            ],
        ], $allowlist);
    }

    /**
     * Test plugin type wildcards are grouped and deduplicated.
     *
     * @covers \tool_configascode\plugin_allowlist::parse
     */
    public function test_parse_accepts_plugin_type_wildcards(): void {
        $allowlist = plugin_allowlist::parse(" mod_*\nmod_forum\nmod_*\n\nfilter_*");

        $this->assertSame([
            'mod' => [
                '*' => true,
                'forum' => true,
            ],
            'filter' => [
                '*' => true,
            ],
        ], $allowlist);
    }

    /**
     * Test malformed and unknown plugin type wildcards are ignored.
     *
     * @covers \tool_configascode\plugin_allowlist::parse
     */
    public function test_parse_ignores_invalid_plugin_type_wildcards(): void {
        $allowlist = plugin_allowlist::parse("*_forum\nmod_*_extra\n*\nunknown_*");

        $this->assertSame([], $allowlist);
    }

    /**
     * Test the helper that decides the final state prefers denylist entries over allowlist entries.
     *
     * @covers \tool_configascode\plugin_allowlist::resolve_desired_plugin_state
     */
    public function test_resolve_desired_plugin_state_prefers_denylist_over_allowlist(): void {
        $this->assertSame(0, testable_plugin_allowlist::expose_resolve_desired_plugin_state(
            'mod',
            'forum',
            ['*' => true],
            ['mod' => ['forum' => true]]
        ));
        $this->assertSame(1, testable_plugin_allowlist::expose_resolve_desired_plugin_state(
            'mod',
            'assign',
            ['assign' => true],
            []
        ));
        $this->assertSame(0, testable_plugin_allowlist::expose_resolve_desired_plugin_state(
            'mod',
            'quiz',
            [],
            []
        ));
    }

    /**
     * Test denylist entries reject wildcards while allowing exact plugin names.
     *
     * @covers \tool_configascode\plugin_allowlist::parse_denylist
     */
    public function test_parse_denylist_rejects_wildcards(): void {
        $denylist = plugin_allowlist::parse_denylist(" mod_scorm\nmod_*\nmod_quiz\nfilter_activitynames\nmod_scorm\n");

        $this->assertSame([
            'mod' => [
                'scorm' => true,
                'quiz' => true,
            ],
            'filter' => [
                'activitynames' => true,
            ],
        ], $denylist);
    }
}
