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
 * The tool_configascode Server check.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode\check;

use core\check\check;
use core\check\result;

/**
 * The tool_configascode Server check.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

 */
class configascode_check extends check {
    /**
     * Returns the raw environment value from the process.
     *
     * @return ?string
     */
    protected function get_original_environment(): ?string {
        $originalenv = getenv('ENV');
        return ($originalenv === false) ? null : $originalenv;
    }

    /**
     * Returns whether the current execution is a testing environment.
     *
     * @return bool
     */
    protected function is_testing_environment(): bool {
        return \tool_configascode\helper::is_testing();
    }

    /**
     * Returns the policy path for the current environment.
     *
     * @param ?string $env Original environment value.
     * @param bool $testing Whether testing mode is active.
     * @return string
     */
    protected function get_policy_path_for_environment(?string $env, bool $testing): string {
        return \tool_configascode\helper::get_policy_path($env, $testing, false);
    }

    /**
     * Checks whether the configascode bootstrap has been included.
     *
     * @return bool
     */
    protected function has_config_bootstrap(): bool {
        return defined('TOOL_LOCKDOWN_CONFIG_APPLYPOLICY_INCLUDED') && TOOL_LOCKDOWN_CONFIG_APPLYPOLICY_INCLUDED === true;
    }

    /**
     * Returns the stored policy sync hash from the database.
     *
     * @return ?string
     */
    protected function get_stored_policy_hash(): ?string {
        $storedhash = get_config('tool_configascode', 'confighash');
        return is_string($storedhash) ? $storedhash : null;
    }

    /**
     * Returns the current effective policy sync hash.
     *
     * @return string
     */
    protected function get_current_policy_hash(): string {
        return \tool_configascode\policy_sync::get_policy_config_hash();
    }

    /**
     * Returns the stored policy sync time from the database.
     *
     * @return ?int
     */
    protected function get_stored_policy_time(): ?int {
        $storedtime = get_config('tool_configascode', 'configtime');
        if (!is_numeric($storedtime)) {
            return null;
        }

        $storedtime = (int) $storedtime;
        return ($storedtime > 0) ? $storedtime : null;
    }

    /**
     * Performs check and returns result.
     *
     * @return result
     */
    public function get_result(): result {
        global $OUTPUT, $CFG;

        // Get the current environment, default to 'default' if not set.
        $originalenv = $this->get_original_environment();
        // Normalise the environment to get the correct policy path.
        $env = \tool_configascode\helper::get_environment($originalenv);
        $istesting = $this->is_testing_environment();
        $bootstrapexists = $this->has_config_bootstrap();
        $currenthash = $this->get_current_policy_hash();
        $storedhash = $this->get_stored_policy_hash();
        $storedtime = $this->get_stored_policy_time();
        $hashmatches = is_string($storedhash) && hash_equals($storedhash, $currenthash);
        $synctaskenabled = (string)get_config('tool_configascode', 'sync_policy_task_enabled') !== '0';
        $policypath = $this->get_policy_path_for_environment($originalenv, $istesting);
        $policyfileexists = file_exists($CFG->dirroot . '/' . $policypath);

        // Get the information in the same way as the admin/tool/configascode policy path.
        // file, then check if the corresponding policy file exists.
        // This is to check if the expected secure configuration policy has been applied.
        if (!$bootstrapexists) {
            $resultstatus = result::CRITICAL;
            $statussummary = get_string('policy:bootstrapmissing', 'tool_configascode');
        } else if ($istesting) {
            // During testing we want to ensure we always load the testing policy to avoid any issues with
            // tests running in different environments and needing specific policies. If we need to test
            // specific policies the feature file itself should set the correct config.
            $resultstatus = result::WARNING;
            $statussummary = get_string('policy:istesting', 'tool_configascode');
        } else if ($policyfileexists) {
            if ($env == 'default') {
                $resultstatus = result::WARNING;
                $statussummary = get_string('policy:default', 'tool_configascode');
            } else {
                $resultstatus = result::OK;
                $statussummary = get_string('policy:ok', 'tool_configascode', $env);
            }
        } else {
            $resultstatus = result::WARNING;
            $statussummary = get_string('policy:notfound', 'tool_configascode', $env);
            $policypath = 'admin/tool/configascode/policy/default.php';
        }

        if (!$synctaskenabled && $resultstatus !== result::CRITICAL) {
            $resultstatus = result::WARNING;
            $statussummary = get_string('policy:synctaskdisabled', 'tool_configascode', $statussummary);
        }

        // Get link to sync task logs.
        $synclogsurl = \core\task\logmanager::get_url_for_task_class('\tool_configascode\task\sync_policy_task')
                        ->out_as_local_url(true);

        $context = [
            'env' => $originalenv,
            'normalisedenv' => $env,
            'bootstrapexists' => $bootstrapexists,
            'policypath' => $policypath,
            'policyfileexists' => $policyfileexists,
            'istesting' => $istesting,
            'currenthash' => $currenthash,
            'storedhash' => $storedhash,
            'storedtime' => $storedtime,
            'hashmatches' => $hashmatches,
            'synctaskenabled' => $synctaskenabled,
            'synclogsurl' => $synclogsurl,
        ];
        $statusdetails = $OUTPUT->render_from_template('tool_configascode/configascode_check', $context);
        return new result($resultstatus, $statussummary, $statusdetails);
    }
}
