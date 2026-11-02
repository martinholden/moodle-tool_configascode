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
 * Tests for tool_configascode preset_converter class.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode;

use basic_testcase;

/**
 * Tests for preset_converter class.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group tool
 * @group tool_configascode
 */
final class preset_converter_test extends basic_testcase {
    /** @var string Temporary directory for test files. */
    private string $tempdir;

    /**
     * Set up test fixtures.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->tempdir = sys_get_temp_dir() . '/lockdown_test_' . uniqid();
        mkdir($this->tempdir, 0777, true);
    }

    /**
     * Clean up test fixtures.
     *
     * @return void
     */
    protected function tearDown(): void {
        parent::tearDown();
        if (is_dir($this->tempdir)) {
            // Recursively remove all files and directories in temp directory.
            $this->recursive_delete($this->tempdir);
        }
    }

    /**
     * Recursively delete a directory and all its contents.
     *
     * Includes safety checks to prevent accidental deletion of system files,
     * Moodle files, or directory traversal attacks. Only deletes paths within
     * $this->tempdir and ensures tempdir is never within the Moodle installation.
     *
     * @param string $path Directory path to delete.
     * @return void
     */
    private function recursive_delete(string $path): void {
        global $CFG;

        // Resolve to actual paths to prevent symlink tricks.
        $realpath = realpath($path);
        $realtempdir = realpath($this->tempdir);

        // Safety check: both paths must resolve successfully.
        if ($realpath === false || $realtempdir === false) {
            return;
        }

        // Safety check: ensure temp directory is not within Moodle installation.
        // This prevents accidental deletion of Moodle files if tempdir somehow gets misconfigured.
        $moodleroot = realpath($CFG->dirroot);
        if ($moodleroot && strpos($realtempdir, $moodleroot) === 0) {
            return;
        }

        // Safety check: ensure the path is within the temp directory (prevent directory traversal).
        if (strpos($realpath, $realtempdir) !== 0) {
            return;
        }

        if (is_dir($realpath)) {
            $files = array_diff(scandir($realpath), ['.', '..']);
            foreach ($files as $file) {
                $filepath = $realpath . DIRECTORY_SEPARATOR . $file;
                if (is_dir($filepath)) {
                    $this->recursive_delete($filepath);
                } else {
                    unlink($filepath);
                }
            }
            rmdir($realpath);
        }
    }

    /**
     * Test normalize_source_path with an absolute path.
     *
     * @covers \tool_configascode\preset_converter::normalize_source_path
     * @return void
     */
    public function test_normalize_source_path_absolute(): void {
        $sourcefile = '/path/to/preset.xml';
        $result = preset_converter::normalize_source_path($sourcefile, '/moodle');

        $this->assertEquals($sourcefile, $result);
    }

    /**
     * Test normalize_source_path with a relative path.
     *
     * @covers \tool_configascode\preset_converter::normalize_source_path
     * @return void
     */
    public function test_normalize_source_path_relative(): void {
        $moodleroot = $this->tempdir;
        $relpath = 'presets/test.xml';

        // Create the file so the normalization can find it.
        mkdir("$moodleroot/presets", 0777, true);
        file_put_contents("$moodleroot/presets/test.xml", '<preset/>');

        $result = preset_converter::normalize_source_path($relpath, $moodleroot);

        $expected = $moodleroot . DIRECTORY_SEPARATOR . $relpath;
        $this->assertEquals($expected, $result);
    }

    /**
     * Test normalize_source_path with backslash path separators.
     *
     * @covers \tool_configascode\preset_converter::normalize_source_path
     * @return void
     */
    public function test_normalize_source_path_normalize_separators(): void {
        $input = 'presets\\test.xml';
        $moodleroot = $this->tempdir;

        // Create the file.
        mkdir("$moodleroot/presets", 0777, true);
        file_put_contents("$moodleroot/presets/test.xml", '<preset/>');

        $result = preset_converter::normalize_source_path($input, $moodleroot);

        $this->assertStringContainsString('presets' . DIRECTORY_SEPARATOR . 'test.xml', $result);
    }

    /**
     * Test validate with missing source file.
     *
     * @covers \tool_configascode\preset_converter::validate
     * @return void
     */
    public function test_validate_missing_source_file(): void {
        $converter = new preset_converter('/nonexistent/file.xml', 'PROD', "$this->tempdir/policy.xml");
        $validation = $converter->validate();

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('not readable', $validation['error']);
    }

    /**
     * Test validate with empty environment.
     *
     * @covers \tool_configascode\preset_converter::validate
     * @return void
     */
    public function test_validate_empty_environment(): void {
        $source = $this->create_temp_file('source.xml', '<preset/>');
        $converter = new preset_converter($source, '', "$this->tempdir/policy.xml");

        $validation = $converter->validate();

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('environment must not be empty', $validation['error']);
    }

    /**
     * Test validate with existing target file and replace disabled.
     *
     * @covers \tool_configascode\preset_converter::validate
     * @return void
     */
    public function test_validate_target_exists_no_replace(): void {
        $source = $this->create_temp_file('source.xml', '<preset/>');
        $target = $this->create_temp_file('target.xml', '');

        $converter = new preset_converter($source, 'PROD', $target, false);
        $validation = $converter->validate();

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('already exists', $validation['error']);
    }

    /**
     * Test validate with existing target file but replace enabled.
     *
     * @covers \tool_configascode\preset_converter::validate
     * @return void
     */
    public function test_validate_target_exists_with_replace(): void {
        $source = $this->create_temp_file('source.xml', '<preset/>');
        $target = $this->create_temp_file('target.xml', '');

        $converter = new preset_converter($source, 'PROD', $target, true);
        $validation = $converter->validate();

        $this->assertTrue($validation['valid']);
        $this->assertNull($validation['error']);
    }

    /**
     * Test validate with valid parameters.
     *
     * @covers \tool_configascode\preset_converter::validate
     * @return void
     */
    public function test_validate_success(): void {
        $source = $this->create_temp_file('source.xml', '<preset/>');
        $target = "$this->tempdir/policy.xml";

        $converter = new preset_converter($source, 'PROD', $target, false);
        $validation = $converter->validate();

        $this->assertTrue($validation['valid']);
        $this->assertNull($validation['error']);
    }

    /**
     * Test convert with validation failure.
     *
     * @covers \tool_configascode\preset_converter::convert
     * @return void
     */
    public function test_convert_validation_failure(): void {
        $converter = new preset_converter('/nonexistent/file.xml', 'PROD', "$this->tempdir/policy.xml");
        $result = $converter->convert();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not readable', $result['message']);
        $this->assertNull($result['file']);
    }

    /**
     * Test convert with valid preset file.
     *
     * @covers \tool_configascode\preset_converter::convert
     * @return void
     */
    public function test_convert_success(): void {
        $source = $this->create_temp_file('source.xml', '<?xml version="1.0"?>
<PRESET>
  <ADMIN_SETTINGS>
    <none>
      <SETTINGS>
        <allowemojipicker>1</allowemojipicker>
      </SETTINGS>
    </none>
  </ADMIN_SETTINGS>
</PRESET>');

        $target = "$this->tempdir/policy.xml";

        $converter = new preset_converter($source, 'PROD', $target, false);
        $result = $converter->convert();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('created', $result['message']);
        $this->assertEquals($target, $result['file']);
        $this->assertFileExists($target);
        $this->assertStringContainsString('allowemojipicker', file_get_contents($target));
    }

    /**
     * Test convert with replace enabled.
     *
     * @covers \tool_configascode\preset_converter::convert
     * @return void
     */
    public function test_convert_success_with_replace(): void {
        $source = $this->create_temp_file('source.xml', '<?xml version="1.0"?>
<PRESET>
  <ADMIN_SETTINGS>
    <none>
      <SETTINGS>
        <allowemojipicker>1</allowemojipicker>
      </SETTINGS>
    </none>
  </ADMIN_SETTINGS>
</PRESET>');

        $target = $this->create_temp_file('policy.xml', 'old content');
        $originalcontent = file_get_contents($target);

        $converter = new preset_converter($source, 'PROD', $target, true);
        $result = $converter->convert();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('replaced', $result['message']);
        $this->assertEquals($target, $result['file']);

        $newcontent = file_get_contents($target);
        $this->assertNotEquals($originalcontent, $newcontent);
        $this->assertStringContainsString('allowemojipicker', $newcontent);
    }

    /**
     * Invalid XML, empty files, and whitespace-only files must not create a policy.
     *
     * @covers \tool_configascode\preset_converter::convert
     * @return void
     */
    public function test_convert_invalid_xml_and_empty_files_do_not_create_target(): void {
        $invalidinputs = [
            'malformed' => ['content' => '<PRESET>', 'error' => 'invalidpresetxml'],
            'empty' => ['content' => '', 'error' => 'emptypresetfile'],
            'whitespace' => ['content' => " \t\n", 'error' => 'emptypresetfile'],
        ];

        foreach ($invalidinputs as $name => $input) {
            $source = $this->create_temp_file("{$name}_source.xml", $input['content']);
            $target = "$this->tempdir/{$name}_policy.php";

            $converter = new preset_converter($source, 'PROD', $target);
            $result = $converter->convert();

            $this->assertFalse($result['success']);
            $this->assertStringContainsString('Conversion error', $result['message']);
            $this->assertStringContainsString(
                get_string($input['error'], 'tool_configascode', $source),
                $result['message']
            );
            $this->assertStringContainsString($source, $result['message']);
            $this->assertNull($result['file']);
            $this->assertFileDoesNotExist($target);
        }
    }

    /**
     * Invalid XML and empty files must not replace an existing policy.
     *
     * @covers \tool_configascode\preset_converter::convert
     * @return void
     */
    public function test_convert_invalid_xml_and_empty_files_do_not_replace_target(): void {
        $invalidinputs = [
            'malformed' => ['content' => '<PRESET>'],
            'empty' => ['content' => ''],
            'whitespace' => ['content' => " \t\n"],
        ];

        foreach ($invalidinputs as $name => $input) {
            $source = $this->create_temp_file("{$name}_source.xml", $input['content']);
            $target = $this->create_temp_file("{$name}_policy.php", 'existing policy');

            $converter = new preset_converter($source, 'PROD', $target, true);
            $result = $converter->convert();

            $this->assertFalse($result['success']);
            $this->assertStringContainsString('Conversion error', $result['message']);
            $this->assertNull($result['file']);
            $this->assertSame('existing policy', file_get_contents($target));
        }
    }

    /**
     * A well-formed preset without settings relevant to this helper converts successfully.
     *
     * @covers \tool_configascode\preset_converter::convert
     * @return void
     */
    public function test_convert_valid_preset_without_relevant_settings(): void {
        $source = $this->create_temp_file(
            'empty_source.xml',
            '<PRESET><OTHER_SETTINGS><setting>valid value</setting></OTHER_SETTINGS></PRESET>'
        );
        $target = "$this->tempdir/empty_policy.php";

        $converter = new preset_converter($source, 'PROD', $target);
        $result = $converter->convert();

        $this->assertTrue($result['success']);
        $this->assertFileExists($target);
        $policy = file_get_contents($target);
        $this->assertStringStartsWith('<?php', $policy);
        $this->assertStringNotContainsString('$CFG->', $policy);
    }

    /**
     * Test constructor stores parameters correctly.
     *
     * @covers \tool_configascode\preset_converter::__construct
     * @return void
     */
    public function test_constructor(): void {
        $source = '/path/to/source.xml';
        $target = '/path/to/target.xml';
        $converter = new preset_converter($source, 'PROD', $target, true);

        $this->assertInstanceOf(preset_converter::class, $converter);
    }

    /**
     * Test normalize_source_path with absolute path that is already readable.
     *
     * @covers \tool_configascode\preset_converter::normalize_source_path
     * @return void
     */
    public function test_normalize_source_path_absolute_readable(): void {
        // Create a readable absolute path.
        $source = $this->create_temp_file('readable.xml', '<preset/>');

        $result = preset_converter::normalize_source_path($source, '/moodle');

        // Should return the same path since it's already readable.
        $this->assertEquals($source, $result);
    }

    /**
     * Test normalize_source_path with relative path that becomes readable.
     *
     * @covers \tool_configascode\preset_converter::normalize_source_path
     * @return void
     */
    public function test_normalize_source_path_relative_becomes_readable(): void {
        // Create file structure: tempdir/subdir/file.xml.
        $this->create_temp_file('subdir/source.xml', '<preset/>');

        $result = preset_converter::normalize_source_path('subdir/source.xml', $this->tempdir);

        $expected = $this->tempdir . DIRECTORY_SEPARATOR . 'subdir' . DIRECTORY_SEPARATOR . 'source.xml';
        $this->assertEquals($expected, $result);
    }

    /**
     * Test convert with multiple successful conversions (idempotency).
     *
     * @covers \tool_configascode\preset_converter::convert
     * @return void
     */
    public function test_convert_idempotent_with_replace(): void {
        $source = $this->create_temp_file('source.xml', '<?xml version="1.0"?>
<PRESET>
  <ADMIN_SETTINGS>
    <none>
      <SETTINGS>
        <allowemojipicker>1</allowemojipicker>
      </SETTINGS>
    </none>
  </ADMIN_SETTINGS>
</PRESET>');

        $target = "$this->tempdir/policy.xml";

        // First conversion.
        $converter = new preset_converter($source, 'PROD', $target, false);
        $result1 = $converter->convert();
        $this->assertTrue($result1['success']);
        $content1 = file_get_contents($target);

        // Second conversion with replace flag.
        $converter2 = new preset_converter($source, 'PROD', $target, true);
        $result2 = $converter2->convert();
        $this->assertTrue($result2['success']);
        $content2 = file_get_contents($target);

        // Content should be identical (same source).
        $this->assertEquals($content1, $content2);
    }

    /**
     * Test convert returns correct environment in success message.
     *
     * @covers \tool_configascode\preset_converter::convert
     * @return void
     */
    public function test_convert_success_message_includes_environment(): void {
        $source = $this->create_temp_file('source.xml', '<?xml version="1.0"?>
<PRESET>
  <ADMIN_SETTINGS>
    <none>
      <SETTINGS>
        <setting1>value1</setting1>
      </SETTINGS>
    </none>
  </ADMIN_SETTINGS>
</PRESET>');

        $target = "$this->tempdir/policy.xml";

        $converter = new preset_converter($source, 'STAGING', $target, false);
        $result = $converter->convert();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('created', $result['message']);
        $this->assertStringContainsString($target, $result['message']);
    }

    /**
     * Test convert when target path is a directory (file_put_contents fails).
     *
     * Creates a directory at the target location to force file_put_contents to fail.
     *
     * @covers \tool_configascode\preset_converter::convert
     * @return void
     */
    public function test_convert_write_failure_target_is_directory(): void {
        $source = $this->create_temp_file('source.xml', '<?xml version="1.0"?>
<PRESET>
  <ADMIN_SETTINGS>
    <none>
      <SETTINGS>
        <setting1>value1</setting1>
      </SETTINGS>
    </none>
  </ADMIN_SETTINGS>
</PRESET>');

        // Create a directory where the file should be written.
        // file_put_contents will fail when trying to write to a directory.
        // Use replace=true to bypass validation which checks file_exists() (true for dirs).
        $target = "$this->tempdir/target_is_dir";
        mkdir($target, 0777, true);

        $converter = new preset_converter($source, 'PROD', $target, true);

        // PHP emits a warning when file_put_contents() is given a directory. Suppress
        // only that expected warning so the converter can handle its false return.
        set_error_handler(static function (): bool {
            return true;
        });
        try {
            $result = $converter->convert();
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to write the policy file', $result['message']);
        $this->assertNull($result['file']);
    }

    /**
     * Test convert catches exceptions raised while loading the preset.
     *
     * A readable directory passes the converter's readability validation, but is
     * rejected by presetshelper as a non-file when the conversion starts.
     *
     * @covers \tool_configascode\preset_converter::convert
     * @return void
     */
    public function test_convert_catches_preset_loading_exception(): void {
        $source = "$this->tempdir/source_directory";
        mkdir($source, 0777, true);
        $target = "$this->tempdir/policy.php";

        $converter = new preset_converter($source, 'PROD', $target);
        $result = $converter->convert();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Conversion error', $result['message']);
        $this->assertNull($result['file']);
    }

    /**
     * Helper method to create a temporary test file.
     *
     * @param string $name File name (can include subdirectories).
     * @param string $content File content.
     * @return string Path to created file.
     */
    private function create_temp_file(string $name, string $content): string {
        $path = $this->tempdir . '/' . $name;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
        return $path;
    }
}
