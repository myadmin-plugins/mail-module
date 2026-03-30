---
name: phpunit-plugin-test
description: Adds PHPUnit 9.6 test cases to tests/PluginTest.php for the myadmin-mail-module. Use when the user says 'add test', 'write unit test', 'test the plugin', 'cover this method', or modifies tests/PluginTest.php. Covers static property assertions, getHooks() registration, ReflectionClass-based signature checks, and source-file string assertions. Do NOT use for integration tests that hit a real database or for testing non-Plugin classes.
---
# phpunit-plugin-test

## Critical

- All tests live in `tests/PluginTest.php`, namespace `Detain\MyAdminMail\Tests`, class `PluginTest extends TestCase`.
- Config: `phpunit.xml.dist` — suite dir is `tests/`.
- **Never** instantiate `GenericEvent` or call lifecycle handlers (`loadProcessing`, `getDeactivate`, `getSettings`) directly — they depend on `$GLOBALS['tf']`, `run_event()`, `get_module_settings()`, etc. Use `ReflectionClass` to inspect signatures instead.
- Use `self::assert*` (not `$this->assert*`) to match existing style.
- Run tests with: `composer test` from the repo root.

## Instructions

1. **Read the existing file first.** Open `tests/PluginTest.php` and `src/Plugin.php` before writing anything. Note the section comments (`// ─── Section ───`) used to group tests.

2. **Add the `ReflectionClass` fixture** (already present — verify `$this->reflection` is set in `setUp()` before adding reflection-based tests):
   ```php
   private ReflectionClass $reflection;
   protected function setUp(): void {
       $this->reflection = new ReflectionClass(Plugin::class);
   }
   ```

3. **Static property tests** — assert exact values using `self::assertSame()`:
   ```php
   public function testStaticPropertyName(): void {
       self::assertSame('Mail Services', Plugin::$name);
   }
   ```
   Verify the value in `src/Plugin.php` matches before asserting.

4. **`$settings` array tests** — assert key presence with `assertArrayHasKey`, exact values with `assertSame`, and cross-field invariants:
   ```php
   // Invariant: PREFIX matches TABLE
   self::assertSame(Plugin::$settings['TABLE'], Plugin::$settings['PREFIX']);
   // Invariant: TITLE_FIELD starts with PREFIX
   self::assertStringStartsWith(Plugin::$settings['PREFIX'].'_', Plugin::$settings['TITLE_FIELD']);
   ```
   Verify `assertCount(17, Plugin::$settings)` matches the actual key count in `src/Plugin.php`.

5. **`getHooks()` tests** — assert array shape and that every handler references `Plugin::class` with a real method:
   ```php
   $hooks = Plugin::getHooks();
   self::assertArrayHasKey('mail.load_processing', $hooks);
   self::assertSame(Plugin::class, $hooks['mail.load_processing'][0]);
   // All hook keys must be prefixed with Plugin::$module.'.'
   foreach (array_keys($hooks) as $key) {
       self::assertStringStartsWith(Plugin::$module.'.', $key);
   }
   ```

6. **Handler signature tests** — use `ReflectionClass`, never call the method:
   ```php
   $method = $this->reflection->getMethod('loadProcessing');
   self::assertCount(1, $method->getParameters());
   self::assertSame(
       'Symfony\\Component\\EventDispatcher\\GenericEvent',
       $method->getParameters()[0]->getType()->getName()
   );
   ```
   Apply to `loadProcessing`, `getDeactivate`, `getSettings`.

7. **Source-file string assertions** — read the source once and assert tokens exist:
   ```php
   $source = file_get_contents((string) $this->reflection->getFileName());
   self::assertStringContainsString('myadmin_log(', $source);
   self::assertStringContainsString('get_module_db(', $source);
   self::assertStringContainsString("self::$module.'.activate'", $source);
   ```

8. **Verify** by running `composer test` — all tests must pass with no warnings (`failOnWarning="true"` in config).

## Examples

**User says:** "Add a test that confirms the hook for `mail.deactivate` points to a public static method."

**Actions taken:**
1. Read `tests/PluginTest.php` — find the `// ─── getHooks() ───` section.
2. Add inside that section:
```php
public function testDeactivateHookMethodIsPublicStatic(): void
{
    $handler = Plugin::getHooks()['mail.deactivate'];
    $method = $this->reflection->getMethod($handler[1]);
    self::assertTrue($method->isPublic());
    self::assertTrue($method->isStatic());
}
```
3. Run `composer test` — confirm 1 new passing test.

**Result:** Test added to the correct section, matches the `self::assert*` style, uses `$this->reflection`, no live method call.

## Common Issues

- **`Call to undefined function run_event()`** — you called a lifecycle handler directly. Use `ReflectionClass` to inspect it instead; never invoke `loadProcessing`, `getDeactivate`, or `getSettings` in tests.
- **`Failed asserting that 18 matches expected 17`** — a new key was added to `Plugin::$settings`. Update `testSettingsKeyCount()` to the new count and add a corresponding `testSettingsXxx()` assertion for the new key.
- **`failOnWarning` causes test failure on `ReflectionNamedType` deprecation** — ensure you call `$param->getType()->getName()` not `(string) $param->getType()` (both work but the cast emits a deprecation in PHP 8.1+).
- **`Class 'Detain\\MyAdminMail\\Plugin' not found`** — autoloader not loaded; run from repo root (`composer test`), not `php tests/PluginTest.php`.
- **Test file not discovered** — filename must end in `Test.php` and be inside `tests/` to match the `phpunit.xml.dist` suite config.
