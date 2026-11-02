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
 * Policy database synchronisation.
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
 * Synchronises policy-backed configuration with existing database rows.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class policy_sync {
    /** The plugin component name. */
    private const PLUGIN = 'tool_configascode';
    /** The config hash setting name. */
    private const CONFIG_HASH = 'confighash';
    /** The config timestamp setting name. */
    private const CONFIG_TIME = 'configtime';
    /** @var callable Logger callback, defaulting to a no-op. */
    private static $logger = [self::class, 'null_logger'];
    /** @var ?callable Optional test seam callback invoked during sync write. */
    private static $syncwritehook = null;

    /**
     * Returns the SHA256 hash of the policy-backed configuration.
     *
     * @return string SHA256 hash.
     */
    public static function get_policy_config_hash(): string {
        return hash('sha256', serialize(self::normalise_for_hash(self::get_policy_config_payload())));
    }

    /**
     * Synchronises policy-backed config when policy or stored values differ.
     *
     * Existing rows are updated, but no rows are created or removed.
     *
     * @return bool True when a sync was performed, false when the DB was already current.
     * @throws \Exception If the sync write fails and the transaction is rolled back.
     */
    public static function sync_if_needed(): bool {
        self::log('Starting policy sync check...');
        $hash = self::get_policy_config_hash();
        $storedhash = static::get_stored_sync_hash();

        if (self::should_skip_sync($storedhash, $hash)) {
            $storedtime = get_config(self::PLUGIN, self::CONFIG_TIME);
            if (!is_numeric($storedtime) || (int)$storedtime <= 0) {
                set_config(self::CONFIG_TIME, time(), self::PLUGIN, false);
            }
            self::log('Policy sync check complete: no changes detected.');
            return false;
        }

        $transaction = static::start_sync_transaction();

        try {
            $storedhash = static::get_stored_sync_hash();
            if (self::should_skip_sync($storedhash, $hash)) {
                $transaction->allow_commit();
                return false;
            }

            static::perform_policy_sync_write($hash);
            $transaction->allow_commit();
            return true;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            // @codeCoverageIgnoreStart
            throw $e;
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Set the logger callback used by this service.
     *
     * Passing null restores the default no-op logger.
     *
     * @param ?callable $logger
     * @return void
     */
    public static function set_logger(?callable $logger): void {
        self::$logger = $logger ?? [self::class, 'null_logger'];
    }

    /**
     * Determine whether the sync should be skipped because the existing DB state matches the policy.
     *
     * @param mixed $storedhash Current stored hash.
     * @param string $hash Current policy hash.
     * @return bool
     */
    private static function should_skip_sync(mixed $storedhash, string $hash): bool {
        return is_string($storedhash) && hash_equals($storedhash, $hash) && self::policy_db_state_matches();
    }

    /**
     * Returns the current policy-backed config payload with matching database rows.
     *
     * @return array<string, mixed>
     */
    private static function get_policy_config_payload(): array {
        global $CFG;

        $payload = [
            'config_php_settings' => [],
            'forced_plugin_settings' => [],
        ];
        self::append_core_payload_entries($payload, $CFG->config_php_settings ?? []);
        self::append_plugin_payload_entries($payload, $CFG->forced_plugin_settings ?? []);
        return $payload;
    }

    /**
     * Appends core settings present in the database to a hash payload.
     *
     * @param array $payload Payload accumulator.
     * @param mixed $coresettings Source settings.
     * @return void
     */
    private static function append_core_payload_entries(array &$payload, $coresettings): void {
        global $DB;

        if (!is_array($coresettings)) {
            return;
        }
        foreach ($coresettings as $name => $value) {
            if ($name !== 'forced_plugin_settings' && $DB->record_exists('config', ['name' => $name])) {
                $payload['config_php_settings'][(string)$name] = self::normalise_for_hash($value);
            }
        }
    }

    /**
     * Appends plugin settings present in the database to a hash payload.
     *
     * @param array $payload Payload accumulator.
     * @param mixed $pluginsettings Source settings.
     * @return void
     */
    private static function append_plugin_payload_entries(array &$payload, $pluginsettings): void {
        global $DB;

        if (!is_array($pluginsettings)) {
            return;
        }
        foreach ($pluginsettings as $plugin => $settings) {
            if (!is_array($settings)) {
                continue;
            }
            foreach ($settings as $name => $value) {
                if ($DB->record_exists('config_plugins', ['plugin' => $plugin, 'name' => $name])) {
                    $payload['forced_plugin_settings'][(string)$plugin][(string)$name] = self::normalise_for_hash($value);
                }
            }
        }
    }

    /**
     * Starts the transaction used by synchronisation.
     *
     * @return \moodle_transaction
     */
    protected static function start_sync_transaction(): \moodle_transaction {
        global $DB;
        return $DB->start_delegated_transaction();
    }

    /**
     * Reads the stored sync hash.
     *
     * @return mixed
     */
    protected static function get_stored_sync_hash(): mixed {
        return get_config(self::PLUGIN, self::CONFIG_HASH);
    }

    /**
     * Writes policy values and updates sync metadata.
     *
     * @param string $hash Policy hash.
     * @return void
     */
    protected static function perform_policy_sync_write(string $hash): void {
        if (is_callable(self::$syncwritehook)) {
            call_user_func(self::$syncwritehook, $hash);
        }
        self::write_policy_config_values();
        set_config(self::CONFIG_HASH, $hash, self::PLUGIN, false);
        set_config(self::CONFIG_TIME, time(), self::PLUGIN, false);
    }

    /**
     * Sets a sync write hook used by tests.
     *
     * @param ?callable $hook
     * @return void
     */
    public static function set_sync_write_hook(?callable $hook): void {
        self::$syncwritehook = $hook;
    }

    /**
     * Checks whether existing policy-backed rows contain expected values.
     *
     * @return bool
     */
    private static function policy_db_state_matches(): bool {
        global $CFG, $DB;

        $coresettings = $CFG->config_php_settings ?? [];
        if (is_array($coresettings) && !self::config_values_match($coresettings, $DB)) {
            return false;
        }

        $pluginsettings = $CFG->forced_plugin_settings ?? [];
        if (is_array($pluginsettings) && !self::plugin_values_match($pluginsettings, $DB)) {
            return false;
        }

        return true;
    }

    /**
     * Check whether the core config settings in the database match the policy-backed values.
     *
     * @param array $settings The core settings to compare.
     * @param mixed $DB The Moodle DB API.
     * @return bool
     */
    private static function config_values_match(array $settings, $DB): bool {
        foreach ($settings as $name => $value) {
            if ($name === 'forced_plugin_settings' || $value === null) {
                continue;
            }
            $record = $DB->get_record('config', ['name' => (string)$name], 'value');
            if ($record && self::value_for_database($value) !== (string)$record->value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether the forced plugin settings in the database match the policy-backed values.
     *
     * @param array $settings The plugin settings to compare.
     * @param mixed $DB The Moodle DB API.
     * @return bool
     */
    private static function plugin_values_match(array $settings, $DB): bool {
        foreach ($settings as $plugin => $pluginsettings) {
            if (!is_array($pluginsettings)) {
                continue;
            }
            foreach ($pluginsettings as $name => $value) {
                if ($value === null) {
                    continue;
                }
                $record = $DB->get_record(
                    'config_plugins',
                    ['plugin' => (string)$plugin, 'name' => (string)$name],
                    'value'
                );
                if ($record && self::value_for_database($value) !== (string)$record->value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Writes current policy-backed values to existing configuration rows.
     *
     * @return void
     */
    private static function write_policy_config_values(): void {
        global $CFG;

        $coresettings = $CFG->config_php_settings ?? [];
        if (is_array($coresettings)) {
            foreach ($coresettings as $name => $value) {
                if ($name !== 'forced_plugin_settings') {
                    self::write_config_value((string)$name, $value);
                }
            }
        }

        $pluginsettings = $CFG->forced_plugin_settings ?? [];
        if (is_array($pluginsettings)) {
            foreach ($pluginsettings as $plugin => $settings) {
                if (!is_array($settings)) {
                    continue;
                }
                foreach ($settings as $name => $value) {
                    self::write_config_value((string)$name, $value, (string)$plugin);
                }
            }
        }
    }

    /**
     * Normalises a value recursively so its hash is stable.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function normalise_for_hash(mixed $value): mixed {
        if (is_object($value)) {
            $value = (array)$value;
        }
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::normalise_for_hash($item);
        }
        if (!array_is_list($value)) {
            ksort($value);
        }
        return $value;
    }

    /**
     * Persists a config value when its database row already exists.
     *
     * @param string $name Config name.
     * @param mixed $value Config value.
     * @param ?string $plugin Plugin component, or null for core config.
     * @return void
     */
    private static function write_config_value(string $name, mixed $value, ?string $plugin = null): void {
        global $DB;

        $table = empty($plugin) ? 'config' : 'config_plugins';
        $conditions = empty($plugin) ? ['name' => $name] : ['name' => $name, 'plugin' => $plugin];
        if (!$DB->record_exists($table, $conditions) || $value === null) {
            return;
        }
        self::log('Writing config value for ' . ($plugin ? $plugin . '/' : '') . $name . ' to database.');
        set_config($name, self::value_for_database($value), $plugin, true);
    }

    /**
     * Converts a policy value to the representation used in Moodle config tables.
     *
     * @param mixed $value
     * @return ?string
     */
    private static function value_for_database(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return (string)(int)$value;
        }
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
            return $value === false ? '' : $value;
        }
        return (string)$value;
    }

    /**
     * Send a sync message to the configured logger.
     *
     * @param string $message The message to write.
     * @return void
     */
    private static function log(string $message): void {
        (self::$logger)($message);
    }

    /**
     * Default no-op logger.
     *
     * @param string $message
     * @return void
     */
    private static function null_logger(string $message): void {
    }
}
