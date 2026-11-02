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
 * Preset converter to transform Moodle admin presets to configascode policy files.
 *
 * Handles file validation, path normalization, and orchestration of the conversion
 * process by delegating XML parsing to presetshelper.
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
 * Preset converter class.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preset_converter {
    /** @var string Path to source preset file. */
    private string $sourcefile;

    /** @var string Target environment name. */
    private string $environment;

    /** @var string Path to target policy file. */
    private string $targetfile;

    /** @var bool Whether to replace existing policy file. */
    private bool $replace;

    /**
     * Constructor.
     *
     * @param string $sourcefile Path to source preset file (absolute or relative to Moodle root).
     * @param string $environment Environment name (e.g., 'PROD', 'DEV').
     * @param string $targetfile Path to target policy file.
     * @param bool $replace Whether to replace existing policy file.
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function __construct(
        string $sourcefile,
        string $environment,
        string $targetfile,
        bool $replace = false
    ) {
        $this->sourcefile = $sourcefile;
        $this->environment = $environment;
        $this->targetfile = $targetfile;
        $this->replace = $replace;
    }

    /**
     * Normalize source file path.
     *
     * Handles both absolute and relative paths. For relative paths, attempts to resolve
     * relative to the Moodle root directory.
     *
     * @param string $sourceinput Raw source file path from CLI argument.
     * @param string $moodleroot Moodle root directory path.
     * @return string Normalized absolute path.
     */
    public static function normalize_source_path(string $sourceinput, string $moodleroot): string {
        $sourcefile = $sourceinput;

        // Normalize directory separators for current OS.
        $sourcefile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $sourcefile);

        // Dedupe consecutive separators.
        $sourcefile = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $sourcefile);

        // If file is readable as-is, return it.
        if (is_readable($sourcefile)) {
            return $sourcefile;
        }

        // If the path is absolute (starts with separator), we already tried it and it failed.
        if (strpos($sourcefile, DIRECTORY_SEPARATOR) === 0) {
            return $sourcefile;
        }

        // Path is relative, try to resolve it relative to Moodle root.
        $sourcefile = ltrim($sourceinput, DIRECTORY_SEPARATOR);
        $sourcefile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $sourcefile);
        $sourcefile = $moodleroot . DIRECTORY_SEPARATOR . $sourcefile;

        return $sourcefile;
    }

    /**
     * Validate the conversion parameters.
     *
     * @return array Associative array with 'valid' (bool) and 'error' (string|null).
     */
    public function validate(): array {
        // Check source file exists and is readable.
        if (!is_readable($this->sourcefile)) {
            return [
                'valid' => false,
                'error' => "The source preset file is not readable: {$this->sourcefile}",
            ];
        }

        // Check environment is not empty.
        if (empty($this->environment)) {
            return [
                'valid' => false,
                'error' => 'The environment must not be empty.',
            ];
        }

        // Check target file doesn't already exist (unless replace is enabled).
        if (file_exists($this->targetfile) && !$this->replace) {
            return [
                'valid' => false,
                'error' => "The policy file for the environment '{$this->environment}' already exists at: {$this->targetfile}",
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Execute the conversion.
     *
     * @return array Associative array with 'success' (bool), 'message' (string), and 'file' (string|null).
     */
    public function convert(): array {
        // Validate first.
        $validation = $this->validate();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['error'],
                'file' => null,
            ];
        }

        try {
            // Create helper and generate policy content.
            $helper = new presetshelper($this->sourcefile);
            $policy = $helper->get_policy_content();

            // Write to target file.
            if (file_put_contents($this->targetfile, $policy) === false) {
                return [
                    'success' => false,
                    'message' => "Failed to write the policy file to: {$this->targetfile}",
                    'file' => null,
                ];
            }

            // Generate success message.
            $action = $this->replace ? 'replaced' : 'created';
            return [
                'success' => true,
                'message' => "The policy file has been {$action} at: {$this->targetfile}",
                'file' => $this->targetfile,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Conversion error: ' . $e->getMessage(),
                'file' => null,
            ];
        }
    }
}
