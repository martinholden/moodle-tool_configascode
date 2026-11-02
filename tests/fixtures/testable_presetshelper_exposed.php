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
 * Test double that exposes protected helper methods for direct unit testing.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode;

use SimpleXMLElement;

/**
 * Test double exposing protected helper methods for targeted unit tests.
 */
final class testable_presetshelper_exposed extends presetshelper {
    /**
     * Expose the protected formatter for value conversion.
     *
     * @param string $value Raw value.
     * @return string Formatted PHP literal.
     */
    public function expose_format_value(string $value): string {
        return $this->format_value($value);
    }

    /**
     * Expose the protected plugin denylist builder.
     *
     * @param SimpleXMLElement $xml Parsed preset XML.
     * @return array<string>
     */
    public function expose_build_plugin_denylist(SimpleXMLElement $xml): array {
        return $this->build_plugin_denylist($xml);
    }

    /**
     * Expose the protected string literal formatter.
     *
     * @param string $value Raw string value.
     * @return string Escaped PHP string literal.
     */
    public function expose_format_string_literal(string $value): string {
        return $this->format_string_literal($value);
    }

    /**
     * Expose the protected raw preset reader.
     *
     * @return string|false File contents or false if unreadable.
     */
    public function expose_read_preset_file(): string|false {
        return $this->read_preset_file();
    }

    /**
     * Expose the protected policy header builder.
     *
     * @return array Header lines.
     */
    public function expose_build_policy_header(): array {
        return $this->build_policy_header();
    }

    /**
     * Expose the protected denylist appender.
     *
     * @param SimpleXMLElement $xml Parsed preset XML.
     * @param array $lines Current lines.
     * @return array Updated lines.
     */
    public function expose_append_denylist_settings(SimpleXMLElement $xml, array $lines): array {
        return $this->append_denylist_settings($xml, $lines);
    }

    /**
     * Expose the protected admin settings appender.
     *
     * @param SimpleXMLElement $xml Parsed preset XML.
     * @param array $lines Current lines.
     * @return array Updated lines.
     */
    public function expose_append_admin_settings(SimpleXMLElement $xml, array $lines): array {
        return $this->append_admin_settings($xml, $lines);
    }

    /**
     * Expose the protected type-specific denylist collector.
     *
     * @param string $plugintype The raw plugin type name.
     * @param SimpleXMLElement $plugins The plugin entries for that type.
     * @param array $plugintypes Known plugin types.
     * @return array<string, string> Denylist entries.
     */
    public function expose_collect_denylist_for_type(string $plugintype, SimpleXMLElement $plugins, array $plugintypes): array {
        return $this->collect_denylist_for_type($plugintype, $plugins, $plugintypes);
    }

    /**
     * Expose the protected deny component builder.
     *
     * @param string $type The plugin type.
     * @param string $rawplugin The raw plugin name.
     * @param string $value The raw XML value.
     * @param array $plugintypes Known plugin types.
     * @return string|null Normalized component or null when ignored.
     */
    public function expose_build_deny_component(string $type, string $rawplugin, string $value, array $plugintypes): ?string {
        return $this->build_deny_component($type, $rawplugin, $value, $plugintypes);
    }
}
