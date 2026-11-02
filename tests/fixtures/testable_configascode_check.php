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
 * Test double for the configascode check.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode\check;

/**
 * Test double that lets us control the inputs to configascode_check.
 */
final class testable_configascode_check extends configascode_check {
    /**
     * @var ?string
     */
    private ?string $originalenv = null;

    /**
     * @var bool
     */
    private bool $testing = false;

    /**
     * @var bool
     */
    private bool $bootstrapincluded = false;

    /**
     * @var ?string
     */
    private ?string $storedhash = null;

    /**
     * @var ?string
     */
    private ?string $currenthash = null;

    /**
     * @var ?int
     */
    private ?int $storedtime = null;

    /**
     * Set the environment used by the test double.
     *
     * @param ?string $originalenv
     * @param bool $testing
     * @param bool $bootstrapincluded
     * @param ?string $storedhash
     * @param ?string $currenthash
     * @param ?int $storedtime
     */
    public function set_test_state(
        ?string $originalenv,
        bool $testing,
        bool $bootstrapincluded,
        ?string $storedhash = null,
        ?string $currenthash = null,
        ?int $storedtime = null
    ): void {
        $this->originalenv = $originalenv;
        $this->testing = $testing;
        $this->bootstrapincluded = $bootstrapincluded;
        $this->storedhash = $storedhash;
        $this->currenthash = $currenthash;
        $this->storedtime = $storedtime;
    }

    /**
     * {@inheritDoc}
     */
    protected function get_original_environment(): ?string {
        return $this->originalenv;
    }

    /**
     * {@inheritDoc}
     */
    protected function is_testing_environment(): bool {
        return $this->testing;
    }

    /**
     * {@inheritDoc}
     */
    protected function has_config_bootstrap(): bool {
        return $this->bootstrapincluded;
    }

    /**
     * {@inheritDoc}
     */
    protected function get_stored_policy_hash(): ?string {
        return $this->storedhash;
    }

    /**
     * {@inheritDoc}
     */
    protected function get_current_policy_hash(): string {
        return $this->currenthash ?? parent::get_current_policy_hash();
    }

    /**
     * {@inheritDoc}
     */
    protected function get_stored_policy_time(): ?int {
        return $this->storedtime;
    }

    /**
     * Exposes the real get_original_environment implementation for integration coverage.
     *
     * @return ?string
     */
    public function get_real_original_environment(): ?string {
        return parent::get_original_environment();
    }

    /**
     * Exposes the real is_testing_environment implementation for integration coverage.
     *
     * @return bool
     */
    public function is_real_testing_environment(): bool {
        return parent::is_testing_environment();
    }

    /**
     * Exposes the real policy path resolver for integration coverage.
     *
     * @param ?string $env
     * @param bool $testing
     * @return string
     */
    public function get_real_policy_path_for_environment(?string $env, bool $testing): string {
        return parent::get_policy_path_for_environment($env, $testing);
    }

    /**
     * Exposes the real has_config_bootstrap implementation for integration coverage.
     *
     * @return bool
     */
    public function has_real_config_bootstrap(): bool {
        return parent::has_config_bootstrap();
    }

    /**
     * Exposes the real get_stored_policy_hash implementation for integration coverage.
     *
     * @return ?string
     */
    public function get_real_stored_policy_hash(): ?string {
        return parent::get_stored_policy_hash();
    }

    /**
     * Exposes the real get_stored_policy_time implementation for integration coverage.
     *
     * @return ?int
     */
    public function get_real_stored_policy_time(): ?int {
        return parent::get_stored_policy_time();
    }
}
