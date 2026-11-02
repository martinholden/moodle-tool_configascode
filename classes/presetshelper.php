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

namespace tool_configascode;

use moodle_exception;
use SimpleXMLElement;

/**
 * Helper to convert a Moodle admin preset XML file into a configascode policy PHP file.
 *
 * The helper accepts the path to a preset XML file, loads it through the admin presets manager,
 * and exposes the generated policy content for the ADMIN_SETTINGS section.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presetshelper {
    /** @var string The source preset XML file path. */
    protected string $presetfilepath;

    /** @var ?SimpleXMLElement The parsed XML payload. */
    protected ?SimpleXMLElement $xml = null;

    /**
     * Create a helper for the supplied preset XML file.
     *
     * @param string $presetfilepath The preset XML file path.
     */
    public function __construct(string $presetfilepath) {
        $this->presetfilepath = $presetfilepath;
    }

    /**
     * Load and parse the preset XML file.
     *
     * @return SimpleXMLElement Parsed XML.
     * @throws moodle_exception If the file does not exist, cannot be read, is empty, or contains invalid XML.
     */
    public function load_preset(): SimpleXMLElement {
        if ($this->xml !== null) {
            return $this->xml;
        }

        if (!is_file($this->presetfilepath)) {
            throw new moodle_exception('filenotfound', 'error', '', $this->presetfilepath);
        }

        $xmlcontent = $this->read_preset_file();
        if ($xmlcontent === false) {
            throw new moodle_exception('filenotreadable', 'error', '', $this->presetfilepath);
        }
        if (trim($xmlcontent) === '') {
            throw new moodle_exception(
                'emptypresetfile',
                'tool_configascode',
                '',
                $this->presetfilepath
            );
        }

        // Suppress parser diagnostics and block network access without changing
        // libxml's process-wide error mode or leaking parser state to other code.
        $xml = simplexml_load_string(
            $xmlcontent,
            \SimpleXMLElement::class,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        if ($xml === false || $xml === null) {
            throw new moodle_exception(
                'invalidpresetxml',
                'tool_configascode',
                '',
                $this->presetfilepath
            );
        }

        $this->xml = $xml;
        return $xml;
    }

    /**
     * Read the raw preset XML file contents.
     *
     * Separated for testability so unreadable-file handling can be covered
     * without relying on platform-specific filesystem permissions.
     *
     * @return string|false File contents, or false when the file cannot be read.
     */
    protected function read_preset_file(): string|false {
        return file_get_contents($this->presetfilepath);
    }

    /**
     * Generate the policy PHP content for the ADMIN_SETTINGS section.
     *
     * @return string Policy PHP content.
     */
    public function get_policy_content(): string {
        $xml = $this->load_preset();

        $lines = $this->build_policy_header();
        $lines = $this->append_admin_settings($xml, $lines);
        $lines = $this->append_denylist_settings($xml, $lines);
        $lines[] = '';
        $lines[] = '// @codingStandardsIgnoreEnd';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Build the generated policy header lines.
     *
     * @return array Header lines.
     */
    protected function build_policy_header(): array {
        return [
            '<?php',
            '// This file is part of Moodle - https://moodle.org/',
            '//',
            '// Moodle is free software: you can redistribute it and/or modify',
            '// it under the terms of the GNU General Public License as published by',
            '// the Free Software Foundation, either version 3 of the License, or',
            '// (at your option) any later version.',
            '//',
            '// Moodle is distributed in the hope that it will be useful,',
            '// but WITHOUT ANY WARRANTY; without even the implied warranty of',
            '// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the',
            '// GNU General Public License for more details.',
            '//',
            '// You should have received a copy of the GNU General Public License',
            '// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.',
            '',
            '/**',
            ' * A configascode policy created using the configascode CLI tool.',
            ' * Generated from preset XML: ' . $this->presetfilepath,
            ' * Generated at: ' . date('Y-m-d H:i:s'),
            ' *',
            ' * @package     tool_configascode',
            ' * @copyright   2026 Martin Holden',
            ' * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>',
            ' * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later',
            ' */',
            '',
            '// Coding standards ignored due to no config inclusion or moodle internal check',
            '// @codingStandardsIgnoreStart',
            '',
            'require_once(__DIR__ . \'/common.php\');',
            '',
        ];
    }

    /**
     * Append all admin settings from the preset XML into policy lines.
     *
     * @param SimpleXMLElement $xml Parsed preset XML.
     * @param array $lines Current lines to append to.
     * @return array Updated policy lines.
     */
    protected function append_admin_settings(SimpleXMLElement $xml, array $lines): array {
        $adminsettings = $xml->ADMIN_SETTINGS;
        if (!isset($adminsettings[0])) {
            return $lines;
        }

        foreach ($adminsettings[0] as $pluginname => $pluginsettings) {
            $plugin = strtolower((string) $pluginname);
            if (str_contains($plugin, '__')) {
                $plugin = str_replace('__', '/', $plugin);
            }

            $settings = $pluginsettings->SETTINGS;
            if (!isset($settings[0])) {
                continue;
            }

            foreach ($settings[0]->children() as $settingname => $settingvalue) {
                $setting = strtolower((string) $settingname);
                $value = trim((string) $settingvalue);
                if ($plugin === 'none') {
                    $lines[] = '$CFG->' . $setting . ' = ' . $this->format_value($value) . ';';
                    continue;
                }

                $lines[] = '$CFG->forced_plugin_settings[\'' . $plugin . '\'][\'' . $setting . '\'] = '
                    . $this->format_value($value) . ';';
            }
        }

        return $lines;
    }

    /**
     * Append the generated denylist block when the preset contains disabled plugins.
     *
     * @param SimpleXMLElement $xml Parsed preset XML.
     * @param array $lines Current lines to append to.
     * @return array Updated policy lines.
     */
    protected function append_denylist_settings(SimpleXMLElement $xml, array $lines): array {
        $plugindenylist = $this->build_plugin_denylist($xml);
        if (empty($plugindenylist)) {
            return $lines;
        }

        $lines[] = '$denylist = [];';
        foreach ($plugindenylist as $component) {
            $lines[] = '$denylist[] = \'' . $component . '\';';
        }
        $lines[] = '$CFG->forced_plugin_settings[\'tool_configascode\'][\'plugindenylist\'] = implode("\n", $denylist);';

        return $lines;
    }

    /**
     * Format a value for PHP policy output.
     *
     * @param string $value Raw value from the preset XML.
     * @return string The string representation of the value.
     */
    protected function format_value(string $value): string {
        if (is_numeric($value)) {
            return $value;
        }

        if (strtolower($value) === 'true' || strtolower($value) === 'false') {
            return strtolower($value);
        }

        return var_export($value, true);
    }

    /**
     * Build the denylist array from disabled entries in the preset PLUGINS section.
     *
     * @param SimpleXMLElement $xml Parsed preset XML.
     * @return array Array of component names.
     */
    protected function build_plugin_denylist(SimpleXMLElement $xml): array {
        if (!isset($xml->PLUGINS[0])) {
            return [];
        }

        $plugintypes = \core_component::get_plugin_types();
        $denylist = [];

        foreach ($xml->PLUGINS[0]->children() as $plugintype => $plugins) {
            $denylist += $this->collect_denylist_for_type((string) $plugintype, $plugins, $plugintypes);
        }

        return array_values($denylist);
    }

    /**
     * Collect denylist entries for a single plugin type.
     *
     * @param string $plugintype The raw plugin type name.
     * @param SimpleXMLElement $plugins The plugin entries for that type.
     * @param array $plugintypes Known plugin types.
     * @return array<string, string> Denylist components keyed by component name.
     */
    protected function collect_denylist_for_type(string $plugintype, SimpleXMLElement $plugins, array $plugintypes): array {
        $type = strtolower($plugintype);
        if ($type === '' || !isset($plugintypes[$type])) {
            return [];
        }

        $plugininfo = \core_plugin_manager::resolve_plugininfo_class($type);
        if (!class_exists($plugininfo) || !$plugininfo::plugintype_supports_disabling()) {
            return [];
        }

        $denylist = [];
        foreach ($plugins->children() as $pluginname => $pluginvalue) {
            $component = $this->build_deny_component($type, (string) $pluginname, (string) $pluginvalue, $plugintypes);
            if ($component === null) {
                continue;
            }

            $denylist[$component] = $component;
        }

        return $denylist;
    }

    /**
     * Build a denylist component if the XML entry is a valid disabled plugin.
     *
     * @param string $type The plugin type.
     * @param string $rawplugin The raw plugin name.
     * @param string $value The raw XML value.
     * @param array $plugintypes Known plugin types.
     * @return string|null Normalized plugin component or null when ignored.
     */
    protected function build_deny_component(string $type, string $rawplugin, string $value, array $plugintypes): ?string {
        if (trim($value) !== '0') {
            return null;
        }

        $component = strtolower($type) . '_' . strtolower($rawplugin);
        [$resolvedtype, $resolvedname] = \core_component::normalize_component($component);
        if (
            !isset($plugintypes[$resolvedtype])
            || !\core_component::is_valid_plugin_name($resolvedtype, $resolvedname)
        ) {
            return null;
        }

        return $component;
    }

    /**
     * Format a PHP double-quoted string literal while preserving escaped newline separators.
     *
     * @param string $value Raw value.
     * @return string PHP string literal.
     */
    protected function format_string_literal(string $value): string {
        $escaped = str_replace([
            '\\',
            '"',
            "\n",
            "\r",
        ], [
            '\\\\',
            '\\"',
            '\\n',
            '\\r',
        ], $value);

        return '"' . $escaped . '"';
    }
}
