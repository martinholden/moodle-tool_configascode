<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Global functions for plugin.
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

/**
 * Applies the environment-specific policy file.
 *
 * This resolves the policy path using the same helper logic as the bootstrap entrypoint,
 * loads the matching policy when it exists, and falls back to the default policy otherwise.
 *
 * The optional callback exists to make the existence check unit-testable.
 *
 * The optional $forceinclude parameter allows tests to force re-inclusion of the policy file, which is necessary
 * because the policy file is only included once per request by default, and we need to be able to test the policy
 * loading logic multiple times within the same request.
 *
 * PHPMD flags boolean arguments as a possible SRP smell, but this one is intentional:
 * for testing purposes, we need a simple way to force the function to include the policy file
 * again even if it has already been included once, and this boolean flag is the simplest way
 * to achieve that without adding unnecessary complexity to the function signature or implementation.
 *
 * PHPMD flags unused local variables, but we need include $CFG in this function to ensure that the policy
 * files have access to the global config when they are included,
 *
 * @param ?callable $fileexists Optional file existence callback. Defaults to file_exists.
 * @param bool $forceinclude Whether to force re-including the policy file during tests.
 * @return string The policy file that was loaded.
 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 */
function tool_configascode_apply_policy(?callable $fileexists = null, bool $forceinclude = false): string {
    global $CFG;
    // We need to include the global $CFG here so that when we include the policy file,
    // we have access to the config values within it.

    require_once(__DIR__ . '/classes/helper.php');

    $fileexists ??= 'file_exists';
    $resolvedpath = \tool_configascode\helper::get_policy_path(null, null, true);

    if ($fileexists($resolvedpath)) {
        if ($forceinclude) {
            include($resolvedpath);
        } else {
            require_once($resolvedpath);
        }
        return $resolvedpath;
    }

    $defaultpath = __DIR__ . '/policy/default.php';
    if ($forceinclude) {
        include($defaultpath);
    } else {
        require_once($defaultpath);
    }
    return $defaultpath;
}

/**
 * Returns the status checks for this plugin.
 * @return array
 */
function tool_configascode_status_checks(): array {
    return [
        new \tool_configascode\check\configascode_check(),
    ];
}
