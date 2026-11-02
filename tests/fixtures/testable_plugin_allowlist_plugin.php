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
 * Testable plugin record for allowlist tests.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We disable the warning for invalid tags in commments so we can add
// phpmd @SuppressWarnings tags to the docblocks without triggering a warning
// in the Moodle codebase which does not use those tags.
// phpcs:disable moodle.Commenting.ValidTags.Invalid

namespace tool_configascode;

/**
 * Testable plugin record.
 */
class testable_plugin_allowlist_plugin {
    /** @var string|false The plugin root directory. */
    public $rootdir;

    /** @var bool Whether the plugin is installed and upgraded. */
    private bool $installed;

    /**
     * Create a testable plugin record.
     *
     * @param string|false $rootdir The plugin root directory.
     * @param bool $installed Whether the plugin is installed and upgraded.
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function __construct($rootdir, bool $installed = true) {
        $this->rootdir = $rootdir;
        $this->installed = $installed;
    }

    /**
     * Check whether this plugin record is actionable.
     *
     * @return bool Whether the plugin is installed and upgraded.
     */
    public function is_installed_and_upgraded(): bool {
        return $this->installed;
    }
}
