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
 * Database-backed functional tests for tool_configascode policy synchronisation.
 *
 * @package     tool_configascode
 * @copyright   2026 Martin Holden
 * @author      Martin Holden <8504612+martinholden@users.noreply.github.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_configascode;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/fixtures/testable_policy_sync_tx_match.php');

/**
 * Database-backed functional tests for tool_configascode policy synchronisation.
 *
 * Note: execution may take many minutes especially on slower servers.
 *
 * PHP UNIT GROUPS:
 *
 * @group tool
 * @group tool_configascode
 */
final class policy_sync_db_test extends advanced_testcase {
    /** @var string Core config key for testing. */
    private const CORE_KEY = 'phpunit_tool_configascode_dbvalue';
    /** @var string Core missing config key for testing. */
    private const CORE_MISSING_KEY = 'phpunit_tool_configascode_missingcore';
    /** @var string Plugin name for testing. */
    private const PLUGIN = 'mod_forum';
    /** @var string Plugin config key for testing. */
    private const PLUGIN_KEY = 'phpunit_tool_configascode_dbplugin';
    /** @var string Plugin missing config key for testing. */
    private const PLUGIN_MISSING_KEY = 'phpunit_tool_configascode_missingplugin';
    /** @var string Core JSON config key for testing. */
    private const CORE_JSON_KEY = 'phpunit_tool_configascode_jsoncore';
    /** @var string Core null config key for testing. */
    private const CORE_NULL_KEY = 'phpunit_tool_configascode_nullcore';
    /** @var string Core object config key for testing. */
    private const CORE_OBJECT_KEY = 'phpunit_tool_configascode_objectcore';
    /** @var string Config time setting for testing. */
    private const CONFIG_TIME = 'configtime';
    /** @var bool Test seam flag for simulated write failure. */
    private static bool $forcewritefailure = false;

    /** @var array The initial state of the system. */
    private array $initialstate = [];

    /**
     * This method is called before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->initialstate = $this->capture_state();
    }

    /**
     * This method is called after each test.
     */
    protected function tearDown(): void {
        $this->restore_state($this->initialstate);
        parent::tearDown();
    }

    /**
     * Direct service calls sync policy values regardless of the scheduled-task toggle.
     *
     * @covers \tool_configascode\policy_sync::sync_if_needed
     * @covers \tool_configascode\policy_sync::set_logger
     * @covers \tool_configascode\policy_sync::log
     * @covers \tool_configascode\policy_sync::null_logger
     */
    public function test_sync_if_needed_ignores_task_toggle_and_uses_configured_logger(): void {
        global $DB;

        $this->seed_existing_rows();
        $this->set_policy_fixture([
            self::CORE_KEY => 'policy-core-value',
        ], [
            self::PLUGIN => [
                self::PLUGIN_KEY => 'policy-plugin-value',
            ],
        ]);
        \set_config('sync_policy_task_enabled', 0, 'tool_configascode');
        \unset_config('confighash', 'tool_configascode');
        \unset_config(self::CONFIG_TIME, 'tool_configascode');

        $messages = [];
        $logger = static function (string $message) use (&$messages): void {
            $messages[] = $message;
        };

        \tool_configascode\policy_sync::set_logger($logger);
        try {
            $synced = \tool_configascode\policy_sync::sync_if_needed();
        } finally {
            \tool_configascode\policy_sync::set_logger(null);
        }
        $this->assertTrue($synced);
        $this->assertSame('policy-core-value', $DB->get_field('config', 'value', ['name' => self::CORE_KEY]));
        $this->assertSame(
            'policy-plugin-value',
            $DB->get_field('config_plugins', 'value', ['plugin' => self::PLUGIN, 'name' => self::PLUGIN_KEY])
        );
        $this->assertSame(
            \tool_configascode\policy_sync::get_policy_config_hash(),
            \get_config('tool_configascode', 'confighash')
        );
        $this->assertContains('Starting policy sync check...', $messages);
        $this->assertContains('Writing config value for ' . self::CORE_KEY . ' to database.', $messages);

        ob_start();
        $this->assertFalse(\tool_configascode\policy_sync::sync_if_needed());
        $output = ob_get_clean();
        $this->assertSame('', $output);
    }

    /**
     * Updates existing config rows and stores the new hash.
     *
     * @covers \tool_configascode\policy_sync::sync_if_needed
     * @covers \tool_configascode\policy_sync::get_policy_config_hash
     */
    public function test_sync_policy_db_state_if_needed_updates_existing_rows(): void {
        global $DB;

        $this->seed_existing_rows();
        \set_config(self::CONFIG_TIME, 1111111111, 'tool_configascode');
        $this->set_policy_fixture([
            self::CORE_KEY => 'policy-core-value',
        ], [
            self::PLUGIN => [
                self::PLUGIN_KEY => 'policy-plugin-value',
            ],
        ]);

        ob_start();
        $this->assertTrue(\tool_configascode\policy_sync::sync_if_needed());
        $output = ob_get_clean();
        $this->assertSame('policy-core-value', $DB->get_field('config', 'value', ['name' => self::CORE_KEY]));
        $this->assertSame(
            'policy-plugin-value',
            $DB->get_field('config_plugins', 'value', ['plugin' => self::PLUGIN, 'name' => self::PLUGIN_KEY])
        );
        $this->assertSame(
            \tool_configascode\policy_sync::get_policy_config_hash(),
            \get_config('tool_configascode', 'confighash')
        );
        $this->assertGreaterThan(1111111111, (int) \get_config('tool_configascode', self::CONFIG_TIME));
        $this->assertSame('', $output);
    }

    /**
     * Verifies a matching policy hash does not hide manually changed database values.
     *
     * @covers \tool_configascode\policy_sync::sync_if_needed
     * @covers \tool_configascode\policy_sync::get_policy_config_hash
     */
    public function test_sync_policy_db_state_if_needed_repairs_values_when_hash_matches(): void {
        global $DB;

        $this->seed_existing_rows();
        \set_config(self::CONFIG_TIME, 1111111111, 'tool_configascode');
        $this->set_policy_fixture([
            self::CORE_KEY => 'policy-core-value',
        ], [
            self::PLUGIN => [
                self::PLUGIN_KEY => 'policy-plugin-value',
            ],
        ]);

        \set_config('confighash', \tool_configascode\policy_sync::get_policy_config_hash(), 'tool_configascode');

        $this->assertTrue(\tool_configascode\policy_sync::sync_if_needed());
        $this->assertSame('policy-core-value', $DB->get_field('config', 'value', ['name' => self::CORE_KEY]));
        $this->assertSame(
            'policy-plugin-value',
            $DB->get_field('config_plugins', 'value', ['plugin' => self::PLUGIN, 'name' => self::PLUGIN_KEY])
        );
        $this->assertGreaterThan(1111111111, (int) \get_config('tool_configascode', self::CONFIG_TIME));
    }

    /**
     * Reapplies the policy when new matching rows appear in the DB.
     *
     * @covers \tool_configascode\policy_sync::sync_if_needed
     * @covers \tool_configascode\policy_sync::get_policy_config_hash
     */
    public function test_sync_policy_db_state_if_needed_reapplies_when_rows_appear_later(): void {
        global $DB;

        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            $this->seed_existing_rows();
            \set_config(self::CONFIG_TIME, 1111111111, 'tool_configascode');
            $this->set_policy_fixture([
                self::CORE_KEY => 'policy-core-value',
                self::CORE_MISSING_KEY => 'policy-missing-core-value',
            ], [
                self::PLUGIN => [
                    self::PLUGIN_KEY => 'policy-plugin-value',
                    self::PLUGIN_MISSING_KEY => 'policy-missing-plugin-value',
                ],
            ]);

            $this->assertTrue(\tool_configascode\policy_sync::sync_if_needed());
            $this->assertSame('policy-core-value', $DB->get_field('config', 'value', ['name' => self::CORE_KEY]));
            $this->assertSame(
                'policy-plugin-value',
                $DB->get_field('config_plugins', 'value', ['plugin' => self::PLUGIN, 'name' => self::PLUGIN_KEY])
            );
            $this->assertFalse(
                $DB->record_exists(
                    'config',
                    [
                        'name' => self::CORE_MISSING_KEY,
                    ]
                )
            );
            $this->assertFalse(
                $DB->record_exists(
                    'config_plugins',
                    [
                        'plugin' => self::PLUGIN,
                        'name' => self::PLUGIN_MISSING_KEY,
                    ]
                )
            );

            \set_config(self::CORE_MISSING_KEY, 'db-missing-core-value');
            \set_config(self::PLUGIN_MISSING_KEY, 'db-missing-plugin-value', self::PLUGIN);

            $this->assertTrue(\tool_configascode\policy_sync::sync_if_needed());
            $this->assertSame('policy-missing-core-value', $DB->get_field('config', 'value', ['name' => self::CORE_MISSING_KEY]));
            $this->assertSame(
                'policy-missing-plugin-value',
                $DB->get_field('config_plugins', 'value', ['plugin' => self::PLUGIN, 'name' => self::PLUGIN_MISSING_KEY])
            );
            $this->assertGreaterThan(1111111111, (int) \get_config('tool_configascode', self::CONFIG_TIME));
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies the config hash is stable when array key order changes.
     *
     * @covers \tool_configascode\policy_sync::get_policy_config_hash
     */
    public function test_get_policy_config_hash_is_stable_for_reordered_arrays(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            $this->seed_existing_rows();
            $CFG->config_php_settings = [
                self::CORE_KEY => [
                    'b' => 2,
                    'a' => 1,
                ],
            ];
            $CFG->forced_plugin_settings = [
                self::PLUGIN => [
                    self::PLUGIN_KEY => [
                        'y' => 'two',
                        'x' => 'one',
                    ],
                ],
            ];

            $hashone = \tool_configascode\policy_sync::get_policy_config_hash();

            $CFG->config_php_settings = [
                self::CORE_KEY => [
                    'a' => 1,
                    'b' => 2,
                ],
            ];
            $CFG->forced_plugin_settings = [
                self::PLUGIN => [
                    self::PLUGIN_KEY => [
                        'x' => 'one',
                        'y' => 'two',
                    ],
                ],
            ];

            $hashtwo = \tool_configascode\policy_sync::get_policy_config_hash();

            $this->assertSame($hashone, $hashtwo);
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies the config hash normalises objects to arrays for hashing.
     *
     * @covers \tool_configascode\policy_sync::get_policy_config_hash
     */
    public function test_get_policy_config_hash_normalises_objects(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            // Set up a DB row for the object key.
            \set_config(self::CORE_OBJECT_KEY, 'db-object-value');

            $objectvalue = new \stdClass();
            $objectvalue->b = 2;
            $objectvalue->a = 1;

            $CFG->config_php_settings = [
                self::CORE_OBJECT_KEY => $objectvalue,
            ];
            $CFG->forced_plugin_settings = [];

            $hashone = \tool_configascode\policy_sync::get_policy_config_hash();

            // Create the same object with properties in different order.
            $objectvalue2 = new \stdClass();
            $objectvalue2->a = 1;
            $objectvalue2->b = 2;

            $CFG->config_php_settings = [
                self::CORE_OBJECT_KEY => $objectvalue2,
            ];

            $hashtwo = \tool_configascode\policy_sync::get_policy_config_hash();

            $this->assertSame($hashone, $hashtwo);
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies the config hash ignores invalid policy payload shapes.
     *
     * @param array|string $initialcore The initial core settings to set for the test.
     * @param array $initialplugin The initial plugin settings to set for the test.
     * @param array|string $normalizedcore The core settings with invalid entries removed for hash comparison.
     * @param array $normalizedplugin The plugin settings with invalid entries removed for hash comparison.
     *
     * @dataProvider get_policy_config_hash_ignores_invalid_policy_payload_shapes
     * @covers \tool_configascode\policy_sync::get_policy_config_hash
     */
    public function test_get_policy_config_hash_ignores_invalid_policy_payload_shapes(
        array|string $initialcore,
        array $initialplugin,
        array|string $normalizedcore,
        array $normalizedplugin
    ): void {
        global $CFG;

        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            $this->seed_existing_rows();
            $CFG->config_php_settings = $initialcore;
            $CFG->forced_plugin_settings = $initialplugin;

            $hashinvalid = \tool_configascode\policy_sync::get_policy_config_hash();

            $CFG->config_php_settings = $normalizedcore;
            $CFG->forced_plugin_settings = $normalizedplugin;
            $hashnormalized = \tool_configascode\policy_sync::get_policy_config_hash();

            $this->assertNotEmpty($hashinvalid);
            $this->assertSame($hashinvalid, $hashnormalized);
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Data provider for invalid policy payload shapes.
     *
     * @return array
     */
    public static function get_policy_config_hash_ignores_invalid_policy_payload_shapes(): array {
        return [
            'invalid plugin entries ignored' => [
                'initialcore' => [
                    self::CORE_KEY => 'policy-core-value',
                    self::CORE_MISSING_KEY => 'policy-missing-core-value',
                ],
                'initialplugin' => [
                    self::PLUGIN => [
                        self::PLUGIN_KEY => 'policy-plugin-value',
                    ],
                    'mod_assign' => 'not-an-array',
                ],
                'normalizedcore' => [
                    self::CORE_KEY => 'policy-core-value',
                    self::CORE_MISSING_KEY => 'policy-missing-core-value',
                ],
                'normalizedplugin' => [
                    self::PLUGIN => [
                        self::PLUGIN_KEY => 'policy-plugin-value',
                    ],
                ],
            ],
            'invalid core settings shape ignored' => [
                'initialcore' => 'not-an-array',
                'initialplugin' => [
                    self::PLUGIN => [
                        self::PLUGIN_KEY => 'policy-plugin-value',
                    ],
                ],
                'normalizedcore' => [],
                'normalizedplugin' => [
                    self::PLUGIN => [
                        self::PLUGIN_KEY => 'policy-plugin-value',
                    ],
                ],
            ],
        ];
    }

    /**
     * Verifies write_config_value handles null, arrays, objects, and missing rows.
     *
     * @covers \tool_configascode\policy_sync::write_config_value
     */
    public function test_write_config_value_handles_edge_cases(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            \set_config(self::CORE_JSON_KEY, 'existing-json');
            \set_config(self::CORE_NULL_KEY, 'existing-null');
            \set_config(self::CORE_OBJECT_KEY, 'existing-object');

            $CFG->config_php_settings = [];
            $CFG->forced_plugin_settings = [];

            $this->invoke_write_config_value(self::CORE_NULL_KEY, null);
            $this->assertSame('existing-null', $DB->get_field('config', 'value', ['name' => self::CORE_NULL_KEY]));

            $this->invoke_write_config_value(self::CORE_JSON_KEY, ['b' => 2, 'a' => 1]);
            $this->assertSame('{"b":2,"a":1}', $DB->get_field('config', 'value', ['name' => self::CORE_JSON_KEY]));

            $objectvalue = new \stdClass();
            $objectvalue->foo = 'bar';
            $this->invoke_write_config_value(self::CORE_OBJECT_KEY, $objectvalue);
            $this->assertSame('{"foo":"bar"}', $DB->get_field('config', 'value', ['name' => self::CORE_OBJECT_KEY]));

            $this->invoke_write_config_value('phpunit_tool_configascode_missingwrite', 'value');
            $this->assertFalse($DB->record_exists('config', ['name' => 'phpunit_tool_configascode_missingwrite']));
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies a changed policy syncs once and a sequential repeat call is skipped.
     *
     * @covers \tool_configascode\policy_sync::sync_if_needed
     */
    public function test_sync_policy_db_state_if_needed_syncs_changed_policy_once_across_repeat_calls(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            // Set up initial state with a known hash.
            $this->seed_existing_rows();
            $CFG->config_php_settings = [
                self::CORE_KEY => 'initial-value',
            ];
            $CFG->forced_plugin_settings = [];

            // Perform initial sync.
            $this->assertTrue(\tool_configascode\policy_sync::sync_if_needed());
            $initialhash = \tool_configascode\policy_sync::get_policy_config_hash();

            // Change the config.
            $CFG->config_php_settings = [
                self::CORE_KEY => 'changed-value',
            ];

            // The first call syncs the changed policy; the repeat call sees current state and skips.
            $result1 = \tool_configascode\policy_sync::sync_if_needed();
            $result2 = \tool_configascode\policy_sync::sync_if_needed();

            // Exactly one call should perform the sync.
            $this->assertTrue($result1 || $result2);
            $this->assertFalse($result1 && $result2);

            // Verify final state is consistent.
            $finalhash = get_config('tool_configascode', 'confighash');
            $this->assertIsString($finalhash);
            $this->assertNotEquals($initialhash, $finalhash);
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies sync_policy_db_state_if_needed updates timestamp when state already matches.
     *
     * @covers \tool_configascode\policy_sync::sync_if_needed
     * @covers \tool_configascode\policy_sync::get_policy_config_hash
     */
    public function test_sync_policy_db_state_repairs_timestamp_when_hash_matches(): void {
        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            $this->seed_existing_rows();
            $this->set_policy_fixture([
                self::CORE_KEY => 'policy-core-value',
            ], [
                self::PLUGIN => [
                    self::PLUGIN_KEY => 'policy-plugin-value',
                ],
            ]);
            \set_config(self::CORE_KEY, 'policy-core-value');
            \set_config(self::PLUGIN_KEY, 'policy-plugin-value', self::PLUGIN);

            \set_config('confighash', \tool_configascode\policy_sync::get_policy_config_hash(), 'tool_configascode');
            \unset_config(self::CONFIG_TIME, 'tool_configascode');

            \tool_configascode\policy_sync::sync_if_needed();

            $this->assertIsNumeric(\get_config('tool_configascode', self::CONFIG_TIME));
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies the database matcher accepts aligned existing rows and ignores missing/null entries.
     *
     * @covers \tool_configascode\policy_sync::policy_db_state_matches
     * @covers \tool_configascode\policy_sync::value_for_database
     */
    public function test_policy_db_state_matches_aligned_values(): void {
        $this->seed_existing_rows();
        $this->set_policy_fixture([
            self::CORE_KEY => 'db-core-value',
            self::CORE_MISSING_KEY => 'missing',
            self::CORE_NULL_KEY => null,
            'forced_plugin_settings' => 'reserved',
        ], [
            self::PLUGIN => [
                self::PLUGIN_KEY => 'db-plugin-value',
                self::PLUGIN_MISSING_KEY => 'missing',
                self::CORE_NULL_KEY => null,
            ],
            'mod_assign' => 'invalid',
        ]);

        $method = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'policy_db_state_matches');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null));
    }

    /**
     * Verifies the database matcher detects mismatches and handles typed values.
     *
     * @covers \tool_configascode\policy_sync::policy_db_state_matches
     * @covers \tool_configascode\policy_sync::value_for_database
     */
    public function test_policy_db_state_matches_detects_mismatch_and_typed_values(): void {
        \set_config(self::CORE_KEY, '1');
        \set_config(self::CORE_JSON_KEY, '{"foo":"bar"}');
        \set_config(self::PLUGIN_KEY, '[1,2]', self::PLUGIN);

        $objectvalue = new \stdClass();
        $objectvalue->foo = 'bar';
        $this->set_policy_fixture([
            self::CORE_KEY => true,
            self::CORE_JSON_KEY => $objectvalue,
        ], [
            self::PLUGIN => [
                self::PLUGIN_KEY => [1, 2],
            ],
        ]);

        $matchmethod = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'policy_db_state_matches');
        $matchmethod->setAccessible(true);
        $valuemethod = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'value_for_database');
        $valuemethod->setAccessible(true);

        $this->assertTrue($matchmethod->invoke(null));
        \set_config(self::CORE_KEY, '0');
        $this->assertFalse($matchmethod->invoke(null));
        \set_config(self::CORE_KEY, '1');
        \set_config(self::PLUGIN_KEY, '[3]', self::PLUGIN);
        $this->assertFalse($matchmethod->invoke(null));
        $this->assertNull($valuemethod->invoke(null, null));
        $this->assertSame('1', $valuemethod->invoke(null, true));
        $this->assertSame('{"foo":"bar"}', $valuemethod->invoke(null, $objectvalue));
        $this->assertSame('', $valuemethod->invoke(null, ["bad" => "\xB1\x31"]));
    }

    /**
     * Verifies the helper methods for skip and match checks cover their hash and value branches.
     *
     * @covers \tool_configascode\policy_sync::should_skip_sync
     * @covers \tool_configascode\policy_sync::config_values_match
     * @covers \tool_configascode\policy_sync::plugin_values_match
     */
    public function test_policy_sync_helpers_cover_hash_and_match_branches(): void {
        global $DB;

        $this->seed_existing_rows();
        $this->set_policy_fixture([
            self::CORE_KEY => 'policy-core-value',
            self::CORE_NULL_KEY => null,
            'forced_plugin_settings' => 'reserved',
        ], [
            self::PLUGIN => [
                self::PLUGIN_KEY => 'policy-plugin-value',
                self::CORE_NULL_KEY => null,
            ],
            'mod_assign' => 'not-an-array',
        ]);

        \set_config(self::CORE_KEY, 'policy-core-value');
        \set_config(self::PLUGIN_KEY, 'policy-plugin-value', self::PLUGIN);

        $hash = \tool_configascode\policy_sync::get_policy_config_hash();

        $shouldskipmethod = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'should_skip_sync');
        $shouldskipmethod->setAccessible(true);
        $configmatchmethod = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'config_values_match');
        $configmatchmethod->setAccessible(true);
        $pluginmatchmethod = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'plugin_values_match');
        $pluginmatchmethod->setAccessible(true);

        $this->assertTrue($shouldskipmethod->invoke(null, $hash, $hash));
        $this->assertFalse($shouldskipmethod->invoke(null, 'wronghash', $hash));
        $this->assertFalse($shouldskipmethod->invoke(null, $hash, 'wronghash'));

        $this->assertTrue($configmatchmethod->invoke(null, [
            self::CORE_KEY => 'policy-core-value',
            self::CORE_NULL_KEY => null,
            'forced_plugin_settings' => 'reserved',
        ], $DB));
        $this->assertFalse($configmatchmethod->invoke(null, [
            self::CORE_KEY => 'different-value',
            self::CORE_NULL_KEY => null,
        ], $DB));

        $this->assertTrue($pluginmatchmethod->invoke(null, [
            self::PLUGIN => [
                self::PLUGIN_KEY => 'policy-plugin-value',
                self::CORE_NULL_KEY => null,
            ],
            'mod_assign' => 'not-an-array',
        ], $DB));
        $this->assertFalse($pluginmatchmethod->invoke(null, [
            self::PLUGIN => [
                self::PLUGIN_KEY => 'wrong-value',
                self::CORE_NULL_KEY => null,
            ],
        ], $DB));
    }

    /**
     * Verifies sync_policy_db_state_if_needed applies changed policy values.
     *
     * @covers \tool_configascode\policy_sync::sync_if_needed
     * @covers \tool_configascode\policy_sync::get_policy_config_hash
     */
    public function test_sync_policy_db_state_applies_changed_values(): void {
        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            $this->seed_existing_rows();
            $this->set_policy_fixture([
                self::CORE_KEY => 'changed-value',
            ], [
                self::PLUGIN => [
                    self::PLUGIN_KEY => 'changed-plugin-value',
                ],
            ]);

            \unset_config('confighash', 'tool_configascode');
            \tool_configascode\policy_sync::sync_if_needed();

            $this->assertSame('changed-value', get_config('', self::CORE_KEY));
            $this->assertSame('changed-plugin-value', get_config(self::PLUGIN, self::PLUGIN_KEY));
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies write_policy_config_values skips non-array plugin blocks and reserved core key.
     *
     * @covers \tool_configascode\policy_sync::write_policy_config_values
     * @covers \tool_configascode\policy_sync::write_config_value
     */
    public function test_write_policy_config_values_skips_invalid_blocks_and_reserved_key(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            \set_config(self::CORE_KEY, 'db-core-value');
            \set_config(self::PLUGIN_KEY, 'db-plugin-value', self::PLUGIN);

            $CFG->config_php_settings = [
                self::CORE_KEY => 'policy-core-value',
                'forced_plugin_settings' => 'should-not-be-written',
            ];
            $CFG->forced_plugin_settings = [
                self::PLUGIN => [
                    self::PLUGIN_KEY => 'policy-plugin-value',
                ],
                'mod_assign' => 'not-an-array',
            ];

            $method = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'write_policy_config_values');
            $method->setAccessible(true);
            $method->invoke(null);

            $this->assertSame('policy-core-value', $DB->get_field('config', 'value', ['name' => self::CORE_KEY]));
            $this->assertSame(
                'policy-plugin-value',
                $DB->get_field('config_plugins', 'value', ['plugin' => self::PLUGIN, 'name' => self::PLUGIN_KEY])
            );
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies write_config_value converts boolean values to integer strings.
     *
     * @covers \tool_configascode\policy_sync::write_config_value
     */
    public function test_write_config_value_converts_boolean_values(): void {
        global $DB;

        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            \set_config(self::CORE_KEY, 'db-core-value');

            $this->invoke_write_config_value(self::CORE_KEY, true);
            $this->assertSame('1', $DB->get_field('config', 'value', ['name' => self::CORE_KEY]));

            $this->invoke_write_config_value(self::CORE_KEY, false);
            $this->assertSame('0', $DB->get_field('config', 'value', ['name' => self::CORE_KEY]));
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies normalise_for_hash recursively normalises nested objects and associative keys.
     *
     * @covers \tool_configascode\policy_sync::normalise_for_hash
     */
    public function test_normalise_for_hash_normalises_nested_structures(): void {
        $objectvalue = new \stdClass();
        $objectvalue->z = 9;
        $objectvalue->a = 1;

        $input = [
            'b' => $objectvalue,
            'a' => [
                'y' => 2,
                'x' => 1,
            ],
            'list' => [3, 2, 1],
        ];

        $method = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'normalise_for_hash');
        $method->setAccessible(true);
        $normalised = $method->invoke(null, $input);

        $this->assertSame(['x' => 1, 'y' => 2], $normalised['a']);
        $this->assertSame(['a' => 1, 'z' => 9], $normalised['b']);
        $this->assertSame([3, 2, 1], $normalised['list']);
        $this->assertSame(['a', 'b', 'list'], array_keys($normalised));
    }

    /**
     * Verifies payload builder includes only existing DB rows and skips reserved/invalid blocks.
     *
     * @covers \tool_configascode\policy_sync::get_policy_config_payload
     * @covers \tool_configascode\policy_sync::append_core_payload_entries
     * @covers \tool_configascode\policy_sync::append_plugin_payload_entries
     * @covers \tool_configascode\policy_sync::normalise_for_hash
     */
    public function test_get_policy_config_payload_filters_and_normalises_entries(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            $this->seed_existing_rows();
            \set_config(self::CORE_OBJECT_KEY, 'db-object-value');

            $objectvalue = new \stdClass();
            $objectvalue->z = 9;
            $objectvalue->a = 1;

            $CFG->config_php_settings = [
                self::CORE_KEY => 'policy-core-value',
                self::CORE_MISSING_KEY => 'missing-core-should-be-ignored',
                'forced_plugin_settings' => 'reserved-key-should-be-ignored',
                self::CORE_OBJECT_KEY => $objectvalue,
            ];
            $CFG->forced_plugin_settings = [
                self::PLUGIN => [
                    self::PLUGIN_KEY => ['y' => 2, 'x' => 1],
                    self::PLUGIN_MISSING_KEY => 'missing-plugin-should-be-ignored',
                ],
                'mod_assign' => 'not-an-array',
            ];

            $method = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'get_policy_config_payload');
            $method->setAccessible(true);
            $payload = $method->invoke(null);

            $this->assertArrayHasKey('config_php_settings', $payload);
            $this->assertArrayHasKey('forced_plugin_settings', $payload);
            $this->assertSame('policy-core-value', $payload['config_php_settings'][self::CORE_KEY]);
            $this->assertSame(['a' => 1, 'z' => 9], $payload['config_php_settings'][self::CORE_OBJECT_KEY]);
            $this->assertArrayNotHasKey(self::CORE_MISSING_KEY, $payload['config_php_settings']);
            $this->assertArrayNotHasKey('forced_plugin_settings', $payload['config_php_settings']);
            $this->assertSame(['x' => 1, 'y' => 2], $payload['forced_plugin_settings'][self::PLUGIN][self::PLUGIN_KEY]);
            $this->assertArrayNotHasKey(self::PLUGIN_MISSING_KEY, $payload['forced_plugin_settings'][self::PLUGIN]);
            $this->assertArrayNotHasKey('mod_assign', $payload['forced_plugin_settings']);
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies write_config_value falls back to an empty string when JSON encoding fails.
     *
     * @covers \tool_configascode\policy_sync::write_config_value
     */
    public function test_write_config_value_uses_empty_string_when_json_encode_fails(): void {
        global $DB;

        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            \set_config(self::CORE_JSON_KEY, 'existing-json');
            $invalidutf8 = ["bad" => "\xB1\x31"];
            $this->invoke_write_config_value(self::CORE_JSON_KEY, $invalidutf8);

            $this->assertSame('', $DB->get_field('config', 'value', ['name' => self::CORE_JSON_KEY]));
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies append_*_payload_entries returns early when given a non-array source.
     *
     * @param string $methodname The method to test (append_core_payload_entries or append_plugin_payload_entries).
     * @param array $payload The initial payload to pass by reference.
     * @param array $expectedpayload The expected payload after the method call.
     *
     * @dataProvider append_payload_entries_returns_early_for_non_array
     * @covers \tool_configascode\policy_sync::append_core_payload_entries
     * @covers \tool_configascode\policy_sync::append_plugin_payload_entries
     */
    public function test_append_payload_entries_returns_early_for_non_array(
        string $methodname,
        array $payload,
        array $expectedpayload
    ): void {
        $method = new \ReflectionMethod(\tool_configascode\policy_sync::class, $methodname);
        $method->setAccessible(true);
        $method->invokeArgs(null, [&$payload, 'not-an-array']);

        $this->assertSame($expectedpayload, $payload);
    }

    /**
     * Data provider for testing append payload entries early returns.
     *
     * @return array
     */
    public static function append_payload_entries_returns_early_for_non_array(): array {
        return [
            'core payload non-array returns early' => [
                'methodname' => 'append_core_payload_entries',
                'payload' => [
                    'config_php_settings' => ['existing' => 'value'],
                    'forced_plugin_settings' => [],
                ],
                'expectedpayload' => [
                    'config_php_settings' => ['existing' => 'value'],
                    'forced_plugin_settings' => [],
                ],
            ],
            'plugin payload non-array returns early' => [
                'methodname' => 'append_plugin_payload_entries',
                'payload' => [
                    'config_php_settings' => [],
                    'forced_plugin_settings' => [self::PLUGIN => [self::PLUGIN_KEY => 'value']],
                ],
                'expectedpayload' => [
                    'config_php_settings' => [],
                    'forced_plugin_settings' => [self::PLUGIN => [self::PLUGIN_KEY => 'value']],
                ],
            ],
        ];
    }

    /**
     * Verifies sync returns false when hash matches during transactional re-check.
     *
     * @covers \tool_configascode\policy_sync::sync_if_needed
     */
    public function test_sync_policy_db_state_if_needed_returns_false_when_hash_matches_inside_transaction(): void {
        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            $this->seed_existing_rows();
            $this->set_policy_fixture([self::CORE_KEY => 'policy-core-value'], []);
            \set_config(self::CORE_KEY, 'policy-core-value');
            unset_config('confighash', 'tool_configascode');
            testable_policy_sync_tx_match::reset_state();

            $result = testable_policy_sync_tx_match::sync_if_needed();
            $this->assertFalse($result);
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies sync rolls back and rethrows when write step throws.
     *
     * @covers \tool_configascode\policy_sync::sync_if_needed
     */
    public function test_sync_policy_db_state_if_needed_rolls_back_and_rethrows_on_write_exception(): void {
        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            $this->seed_existing_rows();
            $this->set_policy_fixture([self::CORE_KEY => 'policy-core-value'], []);
            unset_config('confighash', 'tool_configascode');

            \tool_configascode\policy_sync::set_sync_write_hook(function (): void {
                throw new \Exception('forced sync write failure');
            });
            try {
                \tool_configascode\policy_sync::sync_if_needed();
                $this->fail('Expected forced sync write failure was not thrown.');
            } catch (\Exception $e) {
                $this->assertSame('forced sync write failure', $e->getMessage());
            }
        } finally {
            \tool_configascode\policy_sync::set_sync_write_hook(null);
            $this->restore_state($state);
        }
    }

    /**
     * Verifies start_sync_transaction returns a delegated transaction instance.
     *
     * @covers \tool_configascode\policy_sync::start_sync_transaction
     */
    public function test_start_sync_transaction_returns_transaction(): void {
        $this->resetAfterTest(true);

        $method = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'start_sync_transaction');
        $method->setAccessible(true);
        $transaction = $method->invoke(null);

        $this->assertInstanceOf(\moodle_transaction::class, $transaction);
        $transaction->allow_commit();
    }

    /**
     * Verifies get_stored_sync_hash returns the plugin hash config value.
     *
     * @covers \tool_configascode\policy_sync::get_stored_sync_hash
     */
    public function test_get_stored_sync_hash_returns_configured_hash(): void {
        $this->resetAfterTest(true);
        \set_config('confighash', 'phpunit-test-hash', 'tool_configascode');

        $method = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'get_stored_sync_hash');
        $method->setAccessible(true);
        $value = $method->invoke(null);

        $this->assertSame('phpunit-test-hash', $value);
    }

    /**
     * Verifies perform_policy_sync_write updates sync hash and sync timestamp metadata.
     *
     * @covers \tool_configascode\policy_sync::perform_policy_sync_write
     */
    public function test_perform_policy_sync_write_updates_sync_metadata(): void {
        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            $this->seed_existing_rows();
            $this->set_policy_fixture([], []);
            \unset_config(self::CONFIG_TIME, 'tool_configascode');
            \unset_config('confighash', 'tool_configascode');

            $method = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'perform_policy_sync_write');
            $method->setAccessible(true);
            $method->invoke(null, 'phpunit-test-hash-write');

            $this->assertSame('phpunit-test-hash-write', \get_config('tool_configascode', 'confighash'));
            $this->assertIsNumeric(\get_config('tool_configascode', self::CONFIG_TIME));
        } finally {
            $this->restore_state($state);
        }
    }

    /**
     * Verifies perform_policy_sync_write invokes the configured sync write hook.
     *
     * @covers \tool_configascode\policy_sync::set_sync_write_hook
     * @covers \tool_configascode\policy_sync::perform_policy_sync_write
     */
    public function test_perform_policy_sync_write_invokes_sync_write_hook(): void {
        $this->resetAfterTest(true);
        $state = $this->capture_state();

        try {
            $this->seed_existing_rows();
            $this->set_policy_fixture([], []);
            \unset_config(self::CONFIG_TIME, 'tool_configascode');
            \unset_config('confighash', 'tool_configascode');

            $hookcalled = false;
            \tool_configascode\policy_sync::set_sync_write_hook(function (string $hash) use (&$hookcalled): void {
                $hookcalled = ($hash === 'phpunit-test-hash-hook');
            });

            $method = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'perform_policy_sync_write');
            $method->setAccessible(true);
            $method->invoke(null, 'phpunit-test-hash-hook');

            $this->assertTrue($hookcalled);
            $this->assertSame('phpunit-test-hash-hook', \get_config('tool_configascode', 'confighash'));
            $this->assertIsNumeric(\get_config('tool_configascode', self::CONFIG_TIME));
        } finally {
            \tool_configascode\policy_sync::set_sync_write_hook(null);
            $this->restore_state($state);
        }
    }

    /**
     * Indicates whether helper write should throw for rollback-path coverage.
     *
     * @return bool
     */
    public static function should_force_sync_write_failure(): bool {
        return self::$forcewritefailure;
    }

    /**
     * Captures the current config state so the test can restore it afterwards.
     *
     * @return array<string, mixed>
     */
    private function capture_state(): array {
        global $CFG;

        return [
            'hash' => \get_config('tool_configascode', 'confighash'),
            'time' => \get_config('tool_configascode', self::CONFIG_TIME),
            'core' => \get_config(null, self::CORE_KEY),
            'missingcore' => \get_config(null, self::CORE_MISSING_KEY),
            'objectcore' => \get_config(null, self::CORE_OBJECT_KEY),
            'plugin' => \get_config(self::PLUGIN, self::PLUGIN_KEY),
            'missingplugin' => \get_config(self::PLUGIN, self::PLUGIN_MISSING_KEY),
            'cfgsettings' => $CFG->config_php_settings ?? null,
            'forced' => $CFG->forced_plugin_settings ?? null,
        ];
    }

    /**
     * Restores the captured config state.
     *
     * @param array $state The state to restore.
     * @return void
     */
    private function restore_state(array $state): void {
        $this->restore_config_value('confighash', $state['hash'], 'tool_configascode');
        $this->restore_config_value(self::CONFIG_TIME, $state['time'], 'tool_configascode');
        $this->restore_config_value(self::CORE_KEY, $state['core']);
        $this->restore_config_value(self::CORE_MISSING_KEY, $state['missingcore']);
        $this->restore_config_value(self::CORE_OBJECT_KEY, $state['objectcore']);
        $this->restore_config_value(self::PLUGIN_KEY, $state['plugin'], self::PLUGIN);
        $this->restore_config_value(self::PLUGIN_MISSING_KEY, $state['missingplugin'], self::PLUGIN);

        $this->restore_cfg_property('forced_plugin_settings', $state['forced']);
        $this->restore_cfg_property('config_php_settings', $state['cfgsettings']);
    }

    /**
     * Restores a Moodle config value from captured state.
     *
     * @param string $name Config key.
     * @param mixed $value Captured value, or false when absent.
     * @param ?string $plugin Plugin component for plugin config.
     * @return void
     */
    private function restore_config_value(string $name, $value, ?string $plugin = null): void {
        if ($value === false) {
            \unset_config($name, $plugin);
            return;
        }
        \set_config($name, $value, $plugin);
    }

    /**
     * Restores an optional property on the CFG object.
     *
     * @param string $property Property name.
     * @param mixed $value Captured value, or null when absent.
     * @return void
     */
    private function restore_cfg_property(string $property, $value): void {
        global $CFG;

        if ($value === null) {
            unset($CFG->{$property});
            return;
        }
        $CFG->{$property} = $value;
    }

    /**
     * Seeds the rows that already exist in the DB for the tests.
     *
     * @return void
     */
    private function seed_existing_rows(): void {
        \set_config(self::CORE_KEY, 'db-core-value');
        \set_config(self::PLUGIN_KEY, 'db-plugin-value', self::PLUGIN);
    }

    /**
     * Invokes the private write_config_value helper for direct behavior testing.
     *
     * @param string $name The config name to write.
     * @param mixed $value The config value to write.
     * @param ?string $plugin The plugin name if writing a plugin config, or null for core config.
     * @return void
     */
    private function invoke_write_config_value(string $name, mixed $value, ?string $plugin = null): void {
        $method = new \ReflectionMethod(\tool_configascode\policy_sync::class, 'write_config_value');
        $method->setAccessible(true);
        $method->invoke(null, $name, $value, $plugin);
    }

    /**
     * Sets the policy fixture arrays on the global config object.
     *
     * @param array $coresettings The core settings to set.
     * @param array $pluginsettings The plugin settings to set.
     * @return void
     */
    private function set_policy_fixture(array $coresettings, array $pluginsettings): void {
        global $CFG;

        $CFG->config_php_settings = $coresettings;
        $CFG->forced_plugin_settings = $pluginsettings;
    }
}
