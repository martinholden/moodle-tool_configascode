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
 * Test double forcing an in-transaction policy hash match.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode;

/**
 * Test double forcing an in-transaction policy hash match.
 */
class testable_policy_sync_tx_match extends policy_sync {
    /** @var int Number of hash reads performed. */
    private static int $hashreadcount = 0;

    /**
     * Reset helper state for deterministic tests.
     *
     * @return void
     */
    public static function reset_state(): void {
        self::$hashreadcount = 0;
    }

    /**
     * Get the stored sync hash.
     *
     * @return mixed
     */
    protected static function get_stored_sync_hash(): mixed {
        self::$hashreadcount++;
        if (self::$hashreadcount === 1) {
            // Force entry into transaction path on the first read.
            return 'non-matching-hash';
        }
        // Match on second read to exercise the in-transaction early-exit branch.
        return parent::get_policy_config_hash();
    }
}
