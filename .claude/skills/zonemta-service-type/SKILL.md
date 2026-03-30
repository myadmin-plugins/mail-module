---
name: zonemta-service-type
description: Adds or modifies the ZoneMTA conditional branch inside a lifecycle closure (setEnable, setReactivate, setTerminate) or standalone handler (getDeactivate) in src/Plugin.php. Pattern: run_event('get_service_types'), compare services_type to get_service_define('MAIL_ZONEMTA'), dispatch GenericEvent subevent vs queue fallback. Use when user says 'ZoneMTA support', 'add service type branch', 'non-ZoneMTA queue path', or asks about branching on mail service type. Do NOT use for non-mail module plugins or for getSettings/getHooks changes.
---
# ZoneMTA Service Type Branch

## Critical

- Only applies to `src/Plugin.php` in the `Detain\MyAdminMail` namespace.
- Every lifecycle closure (`setEnable`, `setReactivate`, `setTerminate`) and the standalone `getDeactivate` handler **must** branch on service type — never assume ZoneMTA or queue-only.
- Always wrap `$GLOBALS['tf']->dispatcher->dispatch(...)` in `try/catch (\Exception $e)` and set `$success = false` on failure.
- Check `$success == true && !$subevent->isPropagationStopped()` after dispatch — log an error and set `$success = false` if propagation was not stopped (means no handler claimed the event).
- Never use PDO. Never interpolate `$_GET`/`$_POST` directly into queries; use `$db->real_escape()`.

## Instructions

1. **Resolve settings and service types** at the top of the closure/handler:
   ```php
   $serviceTypes = run_event('get_service_types', false, self::$module);
   $serviceInfo  = $service->getServiceInfo();   // inside closures
   // OR: $serviceClass = $event->getSubject();  // inside standalone handlers
   $settings = get_module_settings(self::$module);
   ```
   Verify `$serviceTypes` is populated before writing the branch.

2. **Write the ZoneMTA conditional** using the exact comparison from the codebase:
   ```php
   if ($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_type'] == get_service_define('MAIL_ZONEMTA')) {
       // ZoneMTA path — steps 3–5
   } else {
       // Queue path — step 6
   }
   ```
   Inside `getDeactivate` (subject is already a `$serviceClass` ORM object), use:
   ```php
   if ($serviceTypes[$serviceClass->getType()]['services_type'] == get_service_define('MAIL_ZONEMTA')) {
   ```

3. **Build the ORM object and subevent** (ZoneMTA path only):
   ```php
   $class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
   /** @var \MyAdmin\Orm\Product $class **/
   $serviceClass = new $class();
   $serviceClass->load_real($serviceInfo[$settings['PREFIX'].'_id']);
   $subevent = new GenericEvent($serviceClass, [
       'field1'   => $serviceTypes[$serviceClass->getType()]['services_field1'],
       'field2'   => $serviceTypes[$serviceClass->getType()]['services_field2'],
       'type'     => $serviceTypes[$serviceClass->getType()]['services_type'],
       'category' => $serviceTypes[$serviceClass->getType()]['services_category'],
       'email'    => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid())
   ]);
   $success = true;
   ```
   Confirm `GenericEvent` is imported: `use Symfony\Component\EventDispatcher\GenericEvent;`

4. **Dispatch with try/catch** (uses output from Step 3):
   ```php
   try {
       $GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.activate'); // or .reactivate / .terminate
   } catch (\Exception $e) {
       myadmin_log('myadmin', 'error', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceClass->getId());
       $subject = 'Cant Connect to DB to Activate';
       $email   = $subject.'<br>ID '.$serviceClass->getId().'<br>'.$e->getMessage();
       (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_connect_error.tpl');
       $success = false;
   }
   if ($success == true && !$subevent->isPropagationStopped()) {
       myadmin_log(self::$module, 'error', 'Dont know how to Activate '.$settings['TBLNAME'].' '.$serviceInfo[$settings['PREFIX'].'_id'].' Type '.$serviceTypes[$serviceClass->getType()]['services_type'].' Category '.$serviceTypes[$serviceClass->getType()]['services_category'], __LINE__, __FILE__, self::$module, $serviceClass->getId());
       $success = false;
   }
   ```

5. **On success**, update ORM status and history (action-specific):
   - `setEnable` / `setReactivate`: `$serviceClass->setStatus('active')->save();` + `setServerStatus('running')->save();`
   - `setTerminate`: `$serviceClass->setServerStatus('deleted')->save();`
   - Always add a history entry: `$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);`

6. **Queue fallback (else branch)** — add the appropriate queue action:
   ```php
   // setEnable / initial setup:
   $db->query('update '.$settings['TABLE'].' set '.$settings['PREFIX']."_status='pending-setup' where ".$settings['PREFIX']."_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
   $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
   $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);

   // setTerminate / delete:
   $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'destroy', '', $serviceInfo[$settings['PREFIX'].'_custid']);

   // getDeactivate:
   $GLOBALS['tf']->history->add(self::$module.'queue', $serviceClass->getId(), 'delete', '', $serviceClass->getCustid());
   ```

7. **Run tests** to verify no regressions:
   ```bash
   vendor/bin/phpunit
   ```

## Examples

**User says:** "Add ZoneMTA support to the setTerminate closure"

**Actions taken:**
1. Confirm `run_event('get_service_types', ...)` and `get_module_settings(...)` are called at the top of the closure.
2. Wrap existing terminate logic in the conditional from Step 2.
3. ZoneMTA path: build ORM, construct `GenericEvent` with `field1/field2/type/category/email`, dispatch `self::$module.'.terminate'`, catch exceptions, check propagation, then call `setServerStatus('deleted')->save()`.
4. Else path: `$GLOBALS['tf']->history->add(self::$module.'queue', ..., 'destroy', ...)` only.
5. Run `vendor/bin/phpunit` — all existing tests in `tests/PluginTest.php` must pass.

**Result:** `setTerminate` closure in `src/Plugin.php:215` mirrors the pattern at lines 218–251.

## Common Issues

- **`Call to undefined function get_service_define()`**: This function is a MyAdmin global helper. Ensure the full MyAdmin framework bootstrap is loaded; in tests this will not be available — mock or skip integration paths in PHPUnit.
- **`$subevent->isPropagationStopped()` always true (nothing handled)**:  The event name must exactly match `self::$module.'.activate'` (etc.). Check `self::$module === 'mail'` and that a listener is registered for that event name in the dispatcher.
- **`Undefined index: services_type`**: `run_event('get_service_types', false, self::$module)` returned empty or the type ID key is missing. Log `$serviceTypes` with `myadmin_log` to inspect; the service type row may not be seeded in the DB.
- **`Class '\MyAdmin\Orm\...' not found`**: `get_orm_class_from_table($settings['TABLE'])` depends on the ORM autoloader. Confirm `$settings['TABLE'] === 'mail'` and the ORM class exists in the MyAdmin framework installation.
- **Tests fail after adding branch**: `testPublicMethodList` in `tests/PluginTest.php:622` enumerates exact public methods — do not add new public methods without updating that test's `$expected` array.