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
 * The tool_configascode CLI script to convert Moodle Admin presets to create policy file.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'source' => false,
        'env' => false,
        'replace' => false,
    ],
    [
        'h' => 'help',
        's' => 'source',
        'e' => 'env',
        'r' => 'replace',
    ]
);
if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized), 2);
}
if ($options['help']) {
    $help = "Create a policy file from Moodle Admin presets.
Options:
-h, --help            Print out this help
-s, --source          The source preset file to convert to a policy file, absolute path or relative to the Moodle root directory.
-e, --env             The environment for the policy file.
-r, --replace         Replace the existing policy file with the new one.
Example:
sudo -u www-data /usr/bin/php admin/tool/configascode/cli/convertpresets.php --source=\"/path/to/source.xml\" --env=\"PROD\" \n";

    cli_writeln($help);
    exit(0);
}

cli_heading("Convert Moodle Admin Presets to Lockdown Config Policy File");

if ($options['source'] == false) {
    cli_error("The --source option is required.", 1);
}
if ($options['env'] == false) {
    cli_error("The --env option is required.", 1);
}

// Normalize the environment and get the target file path.
$environment = tool_configascode\helper::normalise_environment($options['env']);
$targetfile = $CFG->dirroot . DIRECTORY_SEPARATOR . tool_configascode\helper::get_policy_path($environment);

// Normalize the source file path.
$sourcefile = tool_configascode\preset_converter::normalize_source_path(
    $options['source'],
    $CFG->dirroot
);

// Create the converter instance.
$converter = new tool_configascode\preset_converter(
    $sourcefile,
    $environment,
    $targetfile,
    $options['replace']
);

// Validate the converter parameters.
$validation = $converter->validate();
if (!$validation['valid']) {
    cli_error("Validation error: " . $validation['error'], 1);
}

// Inform user of the action.
if (file_exists($targetfile) && $options['replace']) {
    cli_writeln("The existing policy file will be replaced at: $targetfile");
} else {
    cli_writeln("The policy file for the environment '$environment' will be created at: $targetfile");
}

// Convert the preset file to a policy file.
$result = $converter->convert();
if (!$result['success']) {
    cli_error($result['message'], 1);
}

cli_writeln("✓ " . $result['message']);
