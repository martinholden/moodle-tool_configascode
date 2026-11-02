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
 * Tests for tool_configascode presetshelper class.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We disable the warning for invalid tags in commments so we can add
// phpmd @SuppressWarnings tags to the docblocks without triggering a warning
// in the Moodle codebase which does not use those tags.
// phpcs:disable moodle.Commenting.ValidTags.Invalid

namespace tool_configascode;

use basic_testcase;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/fixtures/testable_presetshelper_unreadable.php');
require_once(__DIR__ . '/fixtures/testable_presetshelper_cached.php');
require_once(__DIR__ . '/fixtures/testable_presetshelper_exposed.php');

/**
 * Functional test for tool_configascode helper class.
 *
 * Note: execution may take many minutes especially on slower servers.
 *
 * PHP UNIT GROUPS:
 *
 * @group tool
 * @group tool_configascode
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class presetshelper_test extends basic_testcase {
    /**
     * Ensures the helper stores the preset path on construction.
     *
     * @covers \tool_configascode\presetshelper::__construct
     * @return void
     */
    public function test_construct(): void {
        $helper = new presetshelper(__DIR__ . '/fixtures/import_settings.xml');

        $this->assertInstanceOf(presetshelper::class, $helper);
    }

    /**
     * Ensures a preset XML file is converted into a policy PHP file containing the admin settings.
     *
     * @covers \tool_configascode\presetshelper::get_policy_content
     * @return void
     */
    public function test_get_policy_content(): void {
        $fixturepath = __DIR__ . '/fixtures/import_settings.xml';

        $helper = new presetshelper($fixturepath);
        $policy = $helper->get_policy_content();

        $this->assertStringContainsString('$CFG->allowemojipicker = 1;', $policy);
        $this->assertStringContainsString('$CFG->enableportfolios = 1;', $policy);
        $this->assertStringContainsString("\$CFG->forced_plugin_settings['mod_lesson']['maxanswers'] = 2;", $policy);
        $this->assertStringContainsString("\$CFG->forced_plugin_settings['mod_lesson']['mediawidth'] = 900;", $policy);
    }

    /**
     * Ensures boolean, string, and nested plugin names are exported correctly.
     *
     * @covers \tool_configascode\presetshelper::get_policy_content
     * @return void
     */
    public function test_get_policy_content_formats_values(): void {
        $filepath = __DIR__ . '/fixtures/formatting_settings.xml';
        $helper = new presetshelper($filepath);
        $policy = $helper->get_policy_content();

        $this->assertStringContainsString("\$CFG->forced_plugin_settings['core/auth']['foobar'] = true;", $policy);
        $this->assertStringContainsString("\$CFG->forced_plugin_settings['core/auth']['baz'] = 'hello world';", $policy);
        $this->assertStringContainsString('$CFG->customsetting = false;', $policy);
    }

    /**
     * Ensures the raw file reader returns the file contents.
     *
     * @covers \tool_configascode\presetshelper::read_preset_file
     * @return void
     */
    public function test_read_preset_file(): void {
        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');

        $contents = $helper->expose_read_preset_file();

        $this->assertIsString($contents);
        $this->assertStringContainsString('<ADMIN_SETTINGS>', $contents);
    }

    /**
     * Ensures value formatting handles numbers, booleans, and strings.
     *
     * @covers \tool_configascode\presetshelper::format_value
     * @return void
     */
    public function test_format_value(): void {
        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');

        $this->assertSame('42', $helper->expose_format_value('42'));
        $this->assertSame('true', $helper->expose_format_value('TRUE'));
        $this->assertSame('false', $helper->expose_format_value('False'));
        $this->assertSame("'hello world'", $helper->expose_format_value('hello world'));
    }

    /**
     * Ensures the denylist parser filters invalid plugin types, unsupported plugin types,
     * enabled plugins, and invalid names while keeping valid disabled plugins.
     *
     * @covers \tool_configascode\presetshelper::build_plugin_denylist
     * @return void
     */
    public function test_build_plugin_denylist_filters_valid_and_invalid_entries(): void {
        // Create a mock XML structure with various plugin entries.
        // We use MOD as valid type that can be disabled, THEME as valid type that cannot be disabled,
        // and BADTYPE as an invalid type.
        $xml = simplexml_load_string(
            '<?xml version="1.0"?>
            <PRESET>
              <PLUGINS>
                <BADTYPE>
                  <FOO>0</FOO>
                </BADTYPE>
                <THEME>
                  <BOOST>0</BOOST>
                </THEME>
                <MOD>
                  <CHAT>0</CHAT>
                  <LESSON>1</LESSON>
                  <BAD-NAME>0</BAD-NAME>
                </MOD>
              </PLUGINS>
            </PRESET>'
        );

        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');

        $this->assertSame(['mod_chat'], $helper->expose_build_plugin_denylist($xml));
    }

    /**
     * Ensures string literals escape newline, quote, carriage return, and backslash characters.
     *
     * @covers \tool_configascode\presetshelper::format_string_literal
     * @return void
     */
    public function test_format_string_literal_escapes_special_characters(): void {
        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');

        $value = "line1\n\"quoted\"\r\\slash";
        $result = $helper->expose_format_string_literal($value);

        $expected = "\"line1\\n\\\"quoted\\\"\\r\\\\slash\"";
        $this->assertSame($expected, $result);
    }

    /**
     * Ensures admin settings are appended for both nested plugin paths and top-level config values.
     *
     * @covers \tool_configascode\presetshelper::append_admin_settings
     * @return void
     */
    public function test_append_admin_settings_handles_nested_and_none_settings(): void {
        $xml = simplexml_load_string(
            '<?xml version="1.0"?>
            <PRESET>
              <ADMIN_SETTINGS>
                <core__auth>
                  <SETTINGS>
                    <foobar>1</foobar>
                  </SETTINGS>
                </core__auth>
                <none>
                  <SETTINGS>
                    <allowemojipicker>1</allowemojipicker>
                  </SETTINGS>
                </none>
              </ADMIN_SETTINGS>
            </PRESET>'
        );

        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');
        $lines = $helper->expose_append_admin_settings($xml, ['<?php']);

        $this->assertStringContainsString("\$CFG->forced_plugin_settings['core/auth']['foobar'] = 1;", implode("\n", $lines));
        $this->assertStringContainsString("\$CFG->allowemojipicker = 1;", implode("\n", $lines));
    }

    /**
     * Ensures per-type denylist collection accepts valid disabled plugin entries and rejects invalid ones.
     *
     * @covers \tool_configascode\presetshelper::collect_denylist_for_type
     * @covers \tool_configascode\presetshelper::build_deny_component
     * @return void
     */
    public function test_collect_denylist_for_type_and_build_deny_component_cover_all_branches(): void {
        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');
        $plugintypes = \core_component::get_plugin_types();

        $xml = simplexml_load_string(
            '<?xml version="1.0"?>
            <PRESET>
              <PLUGINS>
                <MOD>
                  <FORUM>0</FORUM>
                  <LESSON>1</LESSON>
                  <bad-name>0</bad-name>
                </MOD>
                <THEME>
                  <BOOST>0</BOOST>
                </THEME>
              </PLUGINS>
            </PRESET>'
        );

        $modplugins = $xml->PLUGINS->MOD;
        $this->assertSame(
            ['mod_forum' => 'mod_forum'],
            $helper->expose_collect_denylist_for_type('mod', $modplugins, $plugintypes)
        );
        $this->assertSame([], $helper->expose_collect_denylist_for_type('theme', $xml->PLUGINS->THEME, $plugintypes));

        $this->assertSame('mod_forum', $helper->expose_build_deny_component('mod', 'forum', '0', $plugintypes));
        $this->assertNull($helper->expose_build_deny_component('mod', 'forum', '1', $plugintypes));
        $this->assertNull($helper->expose_build_deny_component('mod', 'bad-name', '0', $plugintypes));
    }

    /**
     * Ensures admin settings without a root admin-settings node or a settings block are skipped cleanly.
     *
     * @covers \tool_configascode\presetshelper::append_admin_settings
     * @return void
     */
    public function test_append_admin_settings_skips_missing_admin_node_and_empty_settings_blocks(): void {
        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');

        $missing = simplexml_load_string(
            '<?xml version="1.0"?>
            <PRESET>
              <OTHER>
                <VALUE>1</VALUE>
              </OTHER>
            </PRESET>'
        );
        $this->assertSame(['<?php'], $helper->expose_append_admin_settings($missing, ['<?php']));

        $emptysettings = simplexml_load_string(
            '<?xml version="1.0"?>
            <PRESET>
              <ADMIN_SETTINGS>
                <mod>
                  <INVALID>1</INVALID>
                </mod>
              </ADMIN_SETTINGS>
            </PRESET>'
        );
        $this->assertSame(['<?php'], $helper->expose_append_admin_settings($emptysettings, ['<?php']));
    }

    /**
     * Ensures denylist appending and per-type collection skip empty and invalid plugin-type cases.
     *
     * @covers \tool_configascode\presetshelper::append_denylist_settings
     * @covers \tool_configascode\presetshelper::collect_denylist_for_type
     * @return void
     */
    public function test_append_denylist_settings_and_collect_denylist_for_type_skip_empty_and_unknown_types(): void {
        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');
        $plugintypes = \core_component::get_plugin_types();

        $emptyxml = simplexml_load_string(
            '<?xml version="1.0"?>
            <PRESET>
              <PLUGINS>
                <BADTYPE>
                  <FAKE>1</FAKE>
                </BADTYPE>
              </PLUGINS>
            </PRESET>'
        );
        $this->assertSame(['<?php'], $helper->expose_append_denylist_settings($emptyxml, ['<?php']));
        $this->assertSame([], $helper->expose_collect_denylist_for_type('badtype', $emptyxml->PLUGINS->BADTYPE, $plugintypes));
    }

    /**
     * Ensures the policy header and denylist list generation remain stable for generated policy files.
     *
     * @covers \tool_configascode\presetshelper::build_policy_header
     * @covers \tool_configascode\presetshelper::append_denylist_settings
     * @return void
     */
    public function test_build_policy_header_and_denylist_block_are_generated(): void {
        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');
        $xml = simplexml_load_string(
            '<?xml version="1.0"?>
            <PRESET>
              <PLUGINS>
                <MOD>
                  <FORUM>0</FORUM>
                </MOD>
              </PLUGINS>
            </PRESET>'
        );

        // Get the policy header and check that it contains the expected lines.
        $headerarray = $helper->expose_build_policy_header();
        // Check that the header array exists and is not empty.
        $this->assertIsArray($headerarray);
        $this->assertNotEmpty($headerarray);

        // The $header is an array of lines, convert it to a string for easier assertions.
        $headerstring = implode("\n", $headerarray);

        $this->assertStringContainsString('<?php', $headerstring);
        $this->assertStringContainsString('require_once', $headerstring);

        // Get the denylist lines for the policy and check that it contains the expected lines.
        $denylistarray = $helper->expose_append_denylist_settings($xml, []);
        // Check that the denylist array exists and is not empty.
        $this->assertIsArray($denylistarray);
        $this->assertNotEmpty($denylistarray);

        // The $denylist is an array of lines, convert it to a string for easier assertions.
        $denyliststring = implode("\n", $denylistarray);

        $this->assertStringContainsString(
            "\$CFG->forced_plugin_settings['tool_configascode']['plugindenylist']",
            $denyliststring
        );
    }

    /**
     * Ensures the denylist builder returns an empty list when the preset has no PLUGINS section.
     *
     * @covers \tool_configascode\presetshelper::build_plugin_denylist
     * @return void
     */
    public function test_build_plugin_denylist_returns_empty_array_when_plugins_section_missing(): void {
        $xml = simplexml_load_string('<?xml version="1.0"?><PRESET><ADMIN_SETTINGS/></PRESET>');

        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');

        $this->assertSame([], $helper->expose_build_plugin_denylist($xml));
    }

    /**
     * Ensures unsupported plugin types are ignored when calculating the denylist.
     *
     * @covers \tool_configascode\presetshelper::build_plugin_denylist
     * @return void
     */
    public function test_build_plugin_denylist_ignores_unsupported_plugin_types(): void {
        // Create a mock XML structure with various plugin entries.
        // We use THEME as valid type that cannot be disabled,
        // and BADTYPE as an invalid type.
        $xml = simplexml_load_string(
            '<?xml version="1.0"?>
            <PRESET>
              <PLUGINS>
                <THEME>
                  <BOOST>0</BOOST>
                </THEME>
                <BADTYPE>
                  <FOO>0</FOO>
                </BADTYPE>
              </PLUGINS>
            </PRESET>'
        );

        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');

        $this->assertSame([], $helper->expose_build_plugin_denylist($xml));
    }

    /**
     * Ensures duplicate case-variant plugin names are de-duped in the denylist.
     *
     * @covers \tool_configascode\presetshelper::build_plugin_denylist
     * @return void
     */
    public function test_build_plugin_denylist_dedupes_duplicate_case_variants(): void {
        // Create a mock XML structure with various plugin entries.
        // We use MOD as valid type that can be disabled,
        // and mix cases to ensure the denylist is case-insensitive and de-dupes duplicates.
        $xml = simplexml_load_string(
            '<?xml version="1.0"?>
            <PRESET>
              <PLUGINS>
                <MOD>
                  <CHAT>0</CHAT>
                  <chat>0</chat>
                  <DATA>1</DATA>
                </MOD>
              </PLUGINS>
            </PRESET>'
        );

        $helper = new testable_presetshelper_exposed(__DIR__ . '/fixtures/import_settings.xml');

        $this->assertSame(['mod_chat'], $helper->expose_build_plugin_denylist($xml));
    }

    /**
     * Ensures a missing preset file fails with the expected Moodle exception.
     *
     * @covers \tool_configascode\presetshelper::load_preset
     * @return void
     */
    public function test_load_preset_missing_file(): void {
        $helper = new presetshelper(__DIR__ . '/fixtures/missing.xml');

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('filenotfound', 'error', __DIR__ . '/fixtures/missing.xml'));

        $helper->load_preset();
    }

    /**
     * Ensures an unreadable preset file fails with the expected Moodle exception.
     *
     * @covers \tool_configascode\presetshelper::load_preset
     * @return void
     */
    public function test_load_preset_unreadable_file(): void {
        $helper = new testable_presetshelper_unreadable(__DIR__ . '/fixtures/import_settings.xml');

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('filenotreadable', 'error', __DIR__ . '/fixtures/import_settings.xml'));

        $helper->load_preset();
    }

    /**
     * Ensures a loaded preset is cached and reused on repeated calls.
     *
     * @covers \tool_configascode\presetshelper::load_preset
     * @return void
     */
    public function test_load_preset_returns_cached_xml_on_repeat_calls(): void {
        $helper = new testable_presetshelper_cached(__DIR__ . '/fixtures/import_settings.xml');

        $first = $helper->load_preset();
        $second = $helper->load_preset();

        $this->assertInstanceOf(\SimpleXMLElement::class, $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $helper->get_read_count());
    }

    /**
     * Ensures admin settings blocks without SETTINGS children are skipped cleanly.
     *
     * @covers \tool_configascode\presetshelper::get_policy_content
     * @return void
     */
    public function test_get_policy_content_skips_admin_settings_without_settings_node(): void {
        $helper = new presetshelper(__DIR__ . '/fixtures/no_settings_preset.xml');
        $policy = $helper->get_policy_content();

        $this->assertStringNotContainsString('$CFG->mod_assign', $policy);
        $this->assertStringContainsString('$CFG->allowemojipicker = 1;', $policy);
    }

    /**
     * Ensures disabled plugins in the preset XML are converted into a denylist.
     *
     * @covers \tool_configascode\presetshelper::load_preset
     * @covers \tool_configascode\presetshelper::get_policy_content
     * @return void
     */
    public function test_get_policy_content_includes_plugin_denylist_from_plugins_section(): void {
        $helper = new presetshelper(__DIR__ . '/fixtures/import_settings_with_plugins.xml');
        $policy = $helper->get_policy_content();

        $expecteddenylist = [
            "\$denylist = [];",
            "\$denylist[] = 'mod_chat';",
            "\$denylist[] = 'mod_assign';",
            "\$denylist[] = 'block_html';",
        ];

        foreach ($expecteddenylist as $line) {
            $this->assertStringContainsString($line, $policy);
        }

        $unexpecteddenylist = [
            "\$denylist[] = 'mod_data';",
            "\$denylist[] = 'mod_lesson';",
            "\$denylist[] = 'block_activity_modules';",
            "\$denylist[] = 'badtype_foo';",
        ];

        foreach ($unexpecteddenylist as $line) {
            $this->assertStringNotContainsString($line, $policy);
        }

        $this->assertStringContainsString(
            "\$CFG->forced_plugin_settings['tool_configascode']['plugindenylist'] = implode(\"\\n\", \$denylist);",
            $policy
        );
    }

    /**
     * Ensures denylist is empty when no plugins listed.
     *
     * @covers \tool_configascode\presetshelper::load_preset
     * @covers \tool_configascode\presetshelper::get_policy_content
     * @return void
     */
    public function test_get_policy_content_does_not_include_plugin_denylist_when_no_plugins_listed(): void {
        $helper = new presetshelper(__DIR__ . '/fixtures/import_settings_with_no_plugins.xml');
        $policy = $helper->get_policy_content();

        $unexpected = [
            "\$denylist = [];",
            "\$CFG->forced_plugin_settings['tool_configascode']['plugindenylist'] = implode(\"\\n\", \$denylist);",
        ];

        foreach ($unexpected as $line) {
            $this->assertStringNotContainsString($line, $policy);
        }
    }

    /**
     * Ensures denylist is empty when all plugins enabled.
     *
     * @covers \tool_configascode\presetshelper::load_preset
     * @covers \tool_configascode\presetshelper::get_policy_content
     * @return void
     */
    public function test_get_policy_content_does_not_include_plugin_denylist_when_all_plugins_enabled(): void {
        $helper = new presetshelper(__DIR__ . '/fixtures/import_settings_with_only_enabled_plugins.xml');
        $policy = $helper->get_policy_content();

        $unexpected = [
            "\$denylist = [];",
            "\$CFG->forced_plugin_settings['tool_configascode']['plugindenylist'] = implode(\"\\n\", \$denylist);",
        ];

        foreach ($unexpected as $line) {
            $this->assertStringNotContainsString($line, $policy);
        }
    }

    /**
     * Ensures denylist is empty when all plugins non-existent.
     *
     * @covers \tool_configascode\presetshelper::load_preset
     * @covers \tool_configascode\presetshelper::get_policy_content
     * @return void
     */
    public function test_get_policy_content_does_not_include_plugin_denylist_when_all_plugins_non_existent(): void {
        $helper = new presetshelper(__DIR__ . '/fixtures/import_settings_with_only_none_existent_plugins.xml');
        $policy = $helper->get_policy_content();

        $unexpected = [
            "\$denylist = [];",
            "\$CFG->forced_plugin_settings['tool_configascode']['plugindenylist'] = implode(\"\\n\", \$denylist);",
        ];

        foreach ($unexpected as $line) {
            $this->assertStringNotContainsString($line, $policy);
        }
    }

    /**
     * Ensures malformed XML throws a Moodle exception.
     *
     * @covers \tool_configascode\presetshelper::load_preset
     * @covers \tool_configascode\presetshelper::get_policy_content
     * @return void
     */
    public function test_invalid_xml_throws_exception(): void {
        $path = __DIR__ . '/fixtures/invalid_settings.xml';
        $helper = new presetshelper($path);
        $warnings = [];

        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            try {
                $helper->load_preset();
                $this->fail('Malformed XML should throw a Moodle exception.');
            } catch (moodle_exception $exception) {
                $this->assertStringContainsString(
                    get_string('invalidpresetxml', 'tool_configascode', $path),
                    $exception->getMessage()
                );
            }
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings, 'Malformed XML should not emit parser warnings.');
    }

    /**
     * Ensures empty and whitespace-only files receive the empty-file exception.
     *
     * @covers \tool_configascode\presetshelper::load_preset
     * @return void
     */
    public function test_load_preset_rejects_empty_and_whitespace_only_files(): void {
        foreach (['', " \t\n"] as $content) {
            $path = tempnam(sys_get_temp_dir(), 'configascode_preset_');
            try {
                file_put_contents($path, $content);
                $helper = new presetshelper($path);

                try {
                    $helper->load_preset();
                    $this->fail('Invalid XML should throw a Moodle exception.');
                } catch (moodle_exception $exception) {
                    $this->assertStringContainsString(
                        get_string('emptypresetfile', 'tool_configascode', $path),
                        $exception->getMessage()
                    );
                }
            } finally {
                unlink($path);
            }
        }
    }

    /**
     * Ensures well-formed presets with no relevant settings generate an empty policy.
     *
     * @covers \tool_configascode\presetshelper::load_preset
     * @covers \tool_configascode\presetshelper::get_policy_content
     * @return void
     */
    public function test_get_policy_content_for_valid_preset_without_relevant_settings(): void {
        $presets = [
            '<PRESET><ADMIN_SETTINGS/></PRESET>',
            '<PRESET><OTHER_SETTINGS><setting>valid value</setting></OTHER_SETTINGS></PRESET>',
        ];

        foreach ($presets as $content) {
            $path = tempnam(sys_get_temp_dir(), 'configascode_preset_');
            try {
                file_put_contents($path, $content);
                $helper = new presetshelper($path);
                $policy = $helper->get_policy_content();

                $this->assertStringStartsWith('<?php', $policy);
                $this->assertStringContainsString("require_once(__DIR__ . '/common.php');", $policy);
                $this->assertStringNotContainsString('$CFG->', $policy);
            } finally {
                unlink($path);
            }
        }
    }
}
