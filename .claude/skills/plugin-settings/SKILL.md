---
name: plugin-settings
description: Defines or modifies Plugin::$settings static array and implements getSettings() GenericEvent handler with add_dropdown_setting(). Use when user says 'add a setting', 'change default value', 'add config option', or modifies the $settings array in src/Plugin.php. Includes pattern for accessing settings at runtime via get_module_settings(). Do NOT use for lifecycle handlers (setEnable, setReactivate, setDisable, setTerminate) or event dispatch logic.
---
# plugin-settings

## Critical

- `Plugin::$settings` is a flat `public static array` — never nest arrays or add dynamic values.
- `TITLE_FIELD` and `TITLE_FIELD2` **must** start with `PREFIX.'_'` (e.g. `'mail_username'`). Tests assert this with `assertStringStartsWith`.
- `TABLE` and `PREFIX` **must** be identical strings (both `'mail'`). Tests assert `$settings['TABLE'] === $settings['PREFIX']`.
- `SUSPEND_WARNING_DAYS` must be less than `SUSPEND_DAYS`; `DELETE_PENDING_DAYS` must be greater than `SUSPEND_DAYS`.
- `testSettingsKeyCount` asserts exactly 17 keys — adding or removing a key **will break** the count test. Update `tests/PluginTest.php` when the count changes.
- Never call `get_module_settings()` inside `$settings` — it is a runtime helper, not a compile-time value.

## Instructions

1. **Locate the settings array** in `src/Plugin.php` lines 19–36. The canonical key order is:
   ```
   SERVICE_ID_OFFSET, USE_REPEAT_INVOICE, USE_PACKAGES, BILLING_DAYS_OFFSET,
   IMGNAME, REPEAT_BILLING_METHOD, DELETE_PENDING_DAYS, SUSPEND_DAYS,
   SUSPEND_WARNING_DAYS, TITLE, MENUNAME, EMAIL_FROM, TBLNAME, TABLE,
   TITLE_FIELD, TITLE_FIELD2, PREFIX
   ```
   Append new keys before `PREFIX` (keep `PREFIX` last).

2. **Add the new key** with a scalar value:
   ```php
   public static $settings = [
       // ... existing keys ...
       'MY_NEW_KEY' => 'default_value',
       'PREFIX' => 'mail'];
   ```
   Verify: value is `int`, `bool`, `string`, or a named constant (e.g. `PRORATE_BILLING`). No arrays, no function calls.

3. **Expose the setting via `getSettings()`** (lines 258–266). For a yes/no dropdown follow the exact existing pattern:
   ```php
   public static function getSettings(GenericEvent $event)
   {
       /** @var \MyAdmin\Settings $settings **/
       $settings = $event->getSubject();
       $settings->setTarget('global');
       $settings->add_dropdown_setting(
           self::$module,
           _('General'),
           'my_new_key',          // snake_case UI key
           _('My New Setting'),
           _('Description of what this controls'),
           $settings->get_setting('MY_NEW_KEY'),
           ['0', '1'],
           ['No', 'Yes']
       );
   }
   ```
   The third argument to `add_dropdown_setting` is the lowercase UI key; `get_setting()` takes the UPPER_CASE constant name.

4. **Access settings at runtime** in any lifecycle handler:
   ```php
   $settings = get_module_settings(self::$module);
   $value = $settings['MY_NEW_KEY'];
   ```
   Never read from `Plugin::$settings` directly at runtime — always use `get_module_settings()`.

5. **Update `tests/PluginTest.php`** — add two tests and update the count assertion:
   ```php
   public function testSettingsMyNewKey(): void
   {
       self::assertSame('default_value', Plugin::$settings['MY_NEW_KEY']);
   }
   ```
   Update `testSettingsContainsAllRequiredKeys` required keys array and `testSettingsKeyCount` count from `17` to `18`.

6. **Run tests** to verify no regressions:
   ```bash
   vendor/bin/phpunit
   ```
   All assertions in `PluginTest.php` must pass before committing.

## Examples

**User says:** "Add a setting to control the max daily send limit, defaulting to 500"

**Actions taken:**
1. In `src/Plugin.php`, add `'MAX_DAILY_SEND' => 500` before `'PREFIX'` in `$settings`.
2. In `getSettings()`, add:
   ```php
   $settings->add_dropdown_setting(
       self::$module,
       _('General'),
       'max_daily_send',
       _('Max Daily Send Limit'),
       _('Maximum number of emails sent per day'),
       $settings->get_setting('MAX_DAILY_SEND'),
       ['100', '250', '500', '1000'],
       ['100', '250', '500', '1000']
   );
   ```
3. In `tests/PluginTest.php`, add `testSettingsMaxDailySend()`, add `'MAX_DAILY_SEND'` to `testSettingsContainsAllRequiredKeys`, and change `assertCount(17, ...)` to `assertCount(18, ...)`.
4. Run `vendor/bin/phpunit` — all tests pass.

**Result:** New key present in `Plugin::$settings`, exposed in admin UI via `getSettings()`, accessible at runtime via `get_module_settings('mail')['MAX_DAILY_SEND']`.

## Common Issues

- **`testSettingsKeyCount` fails with "18 does not match expected 17"**: You added a key to `$settings` but forgot to update `assertCount` in `PluginTest.php`. Change the count to match the new total.
- **`testTitleFieldStartsWithPrefix` or `testTitleField2StartsWithPrefix` fails**: `TITLE_FIELD` value does not start with `'mail_'`. Fix: ensure value is `'mail_<column_name>'`.
- **`testPrefixMatchesTable` fails**: `PREFIX` and `TABLE` have diverged. Both must equal `'mail'`.
- **`testSuspendWarningDaysLessThanSuspendDays` fails**: `SUSPEND_WARNING_DAYS` >= `SUSPEND_DAYS`. Keep warning days strictly less (default: 7 < 14).
- **`add_dropdown_setting` has no effect in UI**: `setTarget('global')` call is missing before `add_dropdown_setting`. It must be called first in `getSettings()`.
- **`get_setting('MY_NEW_KEY')` returns null**: The key name passed to `get_setting()` must be UPPER_CASE and match exactly the key in `$settings`. Check for typos.