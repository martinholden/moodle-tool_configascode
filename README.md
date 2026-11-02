# Moodle Configuration as Code

`tool_configascode` brings Configuration as Code (CaC) to Moodle. Define your Moodle site and plugin settings in environment-specific policy files, deploy them through your existing configuration process, and let Moodle apply the correct configuration automatically at startup.

Every setting declared by a policy is forced. Since the policy is loaded before Moodle's `lib/setup.php`, policy settings are locked and cannot be changed through the Moodle administration UI. This gives Moodle teams a reliable way to protect critical configuration while keeping each environment consistent and manageable.

## Why use this?

Moodle configuration is normally managed by site administrators through the administration UI. That is convenient for day-to-day administration, but it can make important settings difficult to audit, reproduce, and protect across multiple environments. Manual changes can also create configuration drift between development, staging, and production.

This plugin lets you manage selected Moodle configuration as deployable policy files instead. Policies can be reviewed, tested, promoted, and delivered by a separate configuration or deployment system; they do not need to live in the same repository as Moodle.

With `tool_configascode`, you can:

- protect security and operational settings from being changed in the UI
- apply different configuration baselines to production, staging, development, and local environments
- keep staging aligned with production while allowing development-specific exceptions
- make configuration changes repeatable and auditable
- reduce configuration drift by reconciling policy values with existing Moodle database settings
- manage plugin availability with environment-specific allowlists and denylists

The active policy is selected from the `ENV` environment variable and becomes the source of truth for the settings it declares. Policy values take effect immediately during Moodle bootstrap; the database reconciliation runs through Moodle cron.

The plugin supports the following standard environment values:

- `prod` - Used for your production environment
- `stage` - Used for your stage environment
- `dev` - Used for your development environment
- `local` - Used for a local development environment
- `default` - This is a fallback when the environment does not match any of the above.

Your environment variable `ENV` is normalised by trimming and lowercasing it, then checking whether it starts with one of these values. So if you use `PRODUCTION`, it will match `prod`.

## Configuration as Code Policies

Policy files are ordinary PHP source files. They should be managed through a controlled review and deployment process, but do not need to live in the same repository as Moodle. They may be supplied by a separate configuration repository, deployment package, image, or mounted configuration volume. They are not generated database exports and they are not a replacement for secrets management.

Keep deployment-specific bootstrap values such as `DB*` settings, `dataroot`, and credentials in `config.php` or your secrets/deployment system. Use this plugin for the Moodle settings that should be declared consistently and promoted as code.

The Configuration as Code flow is:

1. Select an environment with `ENV`.
2. Load that environment's policy during `config.php` bootstrap.
3. Apply the policy's `$CFG` values immediately for the current request.
4. Reconcile matching existing database rows during cron, so the Moodle UI and database reflect the policy where possible.

The bootstrap application is immediate; database reconciliation is eventual and requires Moodle cron.

### Why is this different to 'Site admin presets'?
The [Site Admin Presets](https://docs.moodle.org/405/en/Site_admin_presets) functionality allows the site and plugin config values to be set, but it does not prevent a user with sufficient capabilities from changing the setting afterwards via the UI.

The plugin's policies are loaded before `lib/setup.php`, so Moodle treats every setting declared by a policy as `forced`. This is Moodle's standard way of preventing UI changes.

## Installation
Install the plugin in the normal Moodle way.

### Enable the policy loader
Update `config.php` so the policy is loaded before Moodle setup runs. Add the relevant include before `require_once(__DIR__ . '/lib/setup.php'); // Do not edit`.

#### Moodle versions earlier than 5.1
```php
// Include the tool_configascode plugin policy code.
// Add a file_exists guardrail in case plugin is removed.
if (file_exists(__DIR__ . '/admin/tool/configascode/applypolicy.php')) {
    require_once(__DIR__ . '/admin/tool/configascode/applypolicy.php');
}

require_once(__DIR__ . '/lib/setup.php'); // Do not edit
```

#### Moodle versions 5.1 or later
```php
// Include the tool_configascode plugin policy code.
// Add a file_exists guardrail in case plugin is removed.
if (file_exists(__DIR__ . '/public/admin/tool/configascode/applypolicy.php')) {
    require_once(__DIR__ . '/public/admin/tool/configascode/applypolicy.php');
}

require_once(__DIR__ . '/lib/setup.php'); // Do not edit
```

## Create your policy files
Policy files live in the plugin's `policy/` directory (`admin/tool/configascode/policy/` before Moodle 5.1, or `public/admin/tool/configascode/policy/` in Moodle 5.1 and later).

Common files in this plugin are:

- `common.php` for shared settings used by multiple policies
- `default.php` for the fallback policy
- `prod.php`, `stage.php`, `dev.php`, `local.php` for environment-specific policies

Each policy file can declare settings that Moodle will treat as forced because the policy is loaded before `lib/setup.php`:

- set top-level Moodle config values using `$CFG->settingname`
- force plugin settings using `$CFG->forced_plugin_settings['pluginname']['setting']`
- define constants if needed
- remove config settings using `unset` for example `unset($CFG->forced_plugin_settings['pluginname']['setting']);`

Example:

```php
$CFG->forcelogin = 1;
$CFG->forced_plugin_settings['core_competency']['enabled'] = 0;
if (!defined('MYDEFINE')) {
    define('MYDEFINE', true);
}
```

### Custom policy files
You can create additional policy files if you need more environment names, such as `qa.php`.

For example, if you want a QA environment, copy `policy/prod.php` to `policy/qa.php` and adjust the settings for that environment.

Unlike the built-in environment names, custom policy files do not get prefix matching or normalisation support. The file name must exactly match the trimmed, lowercase value of `ENV`.

So if `ENV` is set to `QA`, Moodle will load `policy/qa.php`. If `ENV` is set to `QA01` or `qa-test`, it will not match `qa.php` unless you create a matching file such as `qa01.php`.

### Converting a site preset file
You can use the command line utility `cli/convertpresets.php` to convert [Site Admin Presets](https://docs.moodle.org/405/en/Site_admin_presets) files into a policy file.

Pass the CLI a Site Admin Presets XML file and a target environment, for example:

```PHP
php admin/tool/configascode/cli/convertpresets.php \
    --source="mypresets.xml" \
    --env="myenv"
```

This copies the preset's supported `$CFG` settings into a policy file and sets the disabled-plugin list through `tool_configascode/plugindenylist`. See [Plugin allowlist and denylist](#plugin-allowlist-and-denylist). Use `--replace` when you intentionally want to replace an existing policy file.

### Recommended pattern
A practical deployment pattern is:

1. Keep core Moodle values such as `DB*` settings and `dataroot` in `config.php`, usually sourced from your external secrets or deployment system.
2. Put shared configuration in `common.php`; it is loaded first, so environment-specific files can override it later. For example you may choose here to disable settings related to `debugging`, such as `$CFG->debug=0` to fully disable it.
3. Put environment-specific values in `prod.php`, `stage.php`, `dev.php`, or `local.php`. For example you may choose here to enable settings related to `debugging`, such as `$CFG->debug = (E_ALL);` to fully enable it in yout `local.php` so you can get debug information on your `local` development machine.
4. Use `default.php` as a safe fallback if no environment-specific file exists.
5. Deploy the policy files through your configuration/deployment process and run cron to reconcile existing database rows.

## How it works
During `config.php` bootstrap, the plugin reads the `ENV` environment variable and normalises it to a policy name.

Examples:
- `prod001` becomes `prod`
- `STAGE-2` becomes `stage`
- `DEV.local` becomes `dev`
- `local-dev` becomes `local`

The plugin then loads the matching policy file from the plugin's `policy/` directory.

If a matching policy file does not exist, the `default.php` policy is used instead.

> In BEHAT and PHPUnit, no environment policy file is applied. Instead, a dedicated testing policy is loaded so the plugin can prove it is resolving and applying a policy correctly without affecting normal lockdown settings.

The plugin stores a SHA256 hash of the effective policy-backed config state based on the policy-backed `$CFG->config_php_settings` and `$CFG->forced_plugin_settings` values in `tool_configascode/confighash`, and the epoch time it was last synchronised in `tool_configascode/configtime`.

Every 15 minutes, a scheduled task reconciles the forced config settings and the values stored in the database. The forced values remain effective immediately from `config.php`; the database reconciliation is eventual and requires Moodle cron to be running.

The policy sync and plugin allowlist enforcement tasks can each be paused from the Configuration as Code settings page. When a task is disabled, it skips its work and records that it was skipped in the task log; both tasks are enabled by default.

The policy hash and an explicit comparison of existing database rows avoid unnecessary database updates while still repairing settings changed manually after a previous sync.

Only existing `config` and `config_plugins` rows are updated. Rows are not created for new policy entries, and rows removed from a policy are retained as historical configuration.

> Any `define` constants are not persisted to the database.

## Checking which policy was applied
You can confirm the active policy in Moodle under `Site administration -> Reports -> System status`.

The status checks help verify that the expected policy is being applied, and confirm that the secure config bootstrap ran before `lib/setup.php`.

## Plugin allowlist and denylist
The plugin can also enforce an allowlist and denylist of plugins that support being disabled/enabled such as `Activity Modules (MOD)`. You configure these in `Site administration -> Plugins -> Admin tools -> Configuration as Code`.

The `allowlist` is a newline-delimited list of plugins to `allow`, which means they will be enabled. Enter one complete plugin component name on each line:

```text
mod_scorm
mod_quiz
filter_activitynames
auth_cas
```

Adding a plugin component manages the entire plugin type. For example, allowing only `mod_scorm` and `mod_quiz` causes every other installed activity module to be disabled. Plugin types that do not support disabling are skipped.

To allow every installed plugin of a known type, use the type wildcard form `type_*`, for example:

```text
mod_*
```

The wildcard enables every installed and upgraded activity module that can be managed, and does not disable any of them. Only the complete `type_*` form is supported; partial wildcards and unknown plugin types are ignored. A wildcard takes precedence over concrete entries for the same type. Missing, uninstalled, unavailable, and non-actionable plugins are not changed.

The `denylist` is a newline-delimited list of plugins to explicitly `deny`, which means they will be disabled.

The `denylist` entries always override the `allowlist`, this means you can for example have all installed activity modules enabled by setting `allowlist` to `mod_*`, but then explicitly disable the `mod_scorm` activity module.

The `denylist` DOES NOT support wildcards.

These properties could also be set in a policy file using config settings:

```php
// Allow all installed activity modules.
// Build an array to improve readability, and then implode using "\n" delimiter.
$allowlist = [];
$allowlist[] = 'mod_*';

$CFG->forced_plugin_settings['tool_configascode']['pluginallowlist'] = implode("\n", $allowlist);

// Deny use of mod_scorm activity module.
// Build an array to improve readability, and then implode using "\n" delimiter.
$denylist = [];
$denylist[] = 'mod_scorm';

$CFG->forced_plugin_settings['tool_configascode']['plugindenylist'] = implode("\n", $denylist);
```

Moodle runs the plugin enforcement task hourly.

## Notes/Limitations
- The plugin does not store any personal data.
- In BEHAT and PHPUnit, the plugin uses `tests/fixtures/testingpolicy.php` to verify the policy-loading logic.
- Forced defaults can still be changed per activity instance when Moodle allows instance-level configuration. For example, setting `$CFG->forced_plugin_settings['scorm']['hidetoc'] = 3;` sets the SCORM default to `hidden (3)`, but an user with permissions to create a `SCORM Activity` can still choose any of the supported option.
