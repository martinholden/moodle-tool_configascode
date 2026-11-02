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
 * Scheduled plugin allowlist enforcement task.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode\task;

/**
 * Enforces the configured plugin allowlist.
 */
class enforce_plugin_allowlist_task extends \core\task\scheduled_task {
    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('enforce_plugin_allowlist_task', 'tool_configascode');
    }

    /**
     * Enforce the plugin allowlist.
     *
     * @return void
     */
    public function execute(): void {
        if ((string)get_config('tool_configascode', 'enforce_plugin_allowlist_task_enabled') === '0') {
            self::log('Allowlist enforcement skipped: the scheduled task is disabled in plugin settings.');
            return;
        }

        \tool_configascode\plugin_allowlist::set_logger([self::class, 'log']);
        try {
            \tool_configascode\plugin_allowlist::enforce();
        } finally {
            \tool_configascode\plugin_allowlist::set_logger(null);
        }
    }

    /**
     * Write a timestamped task log message.
     *
     * @param string $message The message to write.
     * @return void
     */
    public static function log(string $message): void {
        mtrace(date("H:i:s") . ' ' . $message);
    }
}
