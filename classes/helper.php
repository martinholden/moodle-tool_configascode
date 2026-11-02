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
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Shared policy and environment helpers.
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
 * Shared policy and environment helpers.
 */
class helper {
    /**
     * Normalises an environment name to a known policy prefix.
     *
     * @param ?string $env Source environment name.
     * @return string Normalised environment name.
     */
    public static function normalise_environment(?string $env = 'default'): string {
        if (empty($env) || trim($env) === '') {
            return 'default';
        }

        $env = strtolower(trim($env));
        $prefixes = ['prod', 'stage', 'dev', 'local', 'default'];
        foreach ($prefixes as $prefix) {
            if (strpos($env, $prefix) === 0) {
                return $prefix;
            }
        }
        return $env;
    }

    /**
     * Returns the current normalised environment.
     *
     * @param ?string $env Source environment name, or null to read ENV.
     * @return string Normalised environment name.
     */
    public static function get_environment(?string $env = null): string {
        if ($env === null) {
            $env = getenv('ENV') !== false ? getenv('ENV') : 'default';
        }
        return self::normalise_environment($env);
    }

    /**
     * Returns the policy path for an environment.
     *
     * @param ?string $env Source environment name, or null to read ENV.
     * @param ?bool $testing Whether testing mode is active, or null to detect it.
     * @param bool $absolute Whether to return an absolute path.
     * @return string Policy path.
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public static function get_policy_path(?string $env = null, ?bool $testing = null, bool $absolute = false): string {
        $testing ??= self::is_testing();
        if ($testing) {
            return $absolute
                ? __DIR__ . '/../tests/fixtures/testingpolicy.php'
                : 'admin/tool/configascode/tests/fixtures/testingpolicy.php';
        }

        $env = self::get_environment($env);
        return $absolute
            ? __DIR__ . '/../policy/' . $env . '.php'
            : 'admin/tool/configascode/policy/' . $env . '.php';
    }

    /**
     * Returns whether the application is running in a test environment.
     *
     * @param ?bool $behat Whether Behat is running, or null to detect it.
     * @param ?bool $phpunit Whether PHPUnit is running, or null to detect it.
     * @return bool
     */
    public static function is_testing(?bool $behat = null, ?bool $phpunit = null): bool {
        $behat ??= defined('BEHAT_SITE_RUNNING');
        $phpunit ??= defined('PHPUNIT_TEST') && PHPUNIT_TEST;
        return $behat || $phpunit;
    }
}
