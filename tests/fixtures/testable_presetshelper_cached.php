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
 * Test double for verifying cached preset loading.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode;

use SimpleXMLElement;

/**
 * Test double that counts how many times the preset file is read.
 */
final class testable_presetshelper_cached extends presetshelper {
    /** @var int Number of times the file reader was invoked. */
    private int $readcount = 0;

    /**
     * Read the raw preset XML file contents.
     *
     * @return string|false
     */
    protected function read_preset_file(): string|false {
        $this->readcount++;
        return parent::read_preset_file();
    }

    /**
     * Returns the number of times the file reader was invoked.
     *
     * @return int
     */
    public function get_read_count(): int {
        return $this->readcount;
    }
}
