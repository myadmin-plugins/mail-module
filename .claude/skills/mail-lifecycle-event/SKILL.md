---
name: mail-lifecycle-event
description: Implements a lifecycle handler (setEnable, setReactivate, setDisable, setTerminate) in src/Plugin.php using the GenericEvent dispatch pattern with ZoneMTA branching, try/catch dispatch, $success flag, and status/history updates. Use when user says 'add lifecycle handler', 'handle mail activation', 'implement terminate', 'implement reactivate', or edits setEnable/setReactivate/setDisable/setTerminate closures in src/Plugin.php. Do NOT use for adding new hooks to getHooks() or for the getDeactivate static handler.
---
# mail-lifecycle-event

## Critical

- All lifecycle closures live inside `loadProcessing()` in `src/Plugin.php`, chained on `$service` and closed with `->register()`.
- Every handler **must** branch on `get_service_define('MAIL_ZONEMTA')` — ZoneMTA path uses `GenericEvent` dispatch; non-ZoneMTA path uses `$GLOBALS['tf']->history->add(self::$module.'queue', ...)`.
- Never skip the `$success == true && !$subevent->isPropagationStopped()` check after dispatch — this detects unhandled event types and logs an error.
- Always call `myadmin_log()` with both `self::$module` (5th arg) **and** the service ID (6th arg).

## Instructions

1. **Resolve service context** — at the top of the closure, extract:
   ```php
   $serviceInfo = $service->getServiceInfo();
   $settings = get_module_settings(self::$module);
   $serviceTypes = run_event('get_service_types', false, self::$module);
   $db = get_module_db(self::$module); // include only if DB query needed
   ```
   Verify `$settings['PREFIX']` is `'mail'` and `$settings['TABLE']` is `'mail'`.

2. **Branch on ZoneMTA** — wrap all logic in:
   ```php
   if ($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_type'] == get_service_define('MAIL_ZONEMTA')) {
       // ZoneMTA path (steps 3–6)
   } else {
       // Queue path (step 7)
   }
   ```

3. **Load the ORM service class** (ZoneMTA path only):
   ```php
   $class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
   /** @var \MyAdmin\Orm\Product $class **/
   $serviceClass = new $class();
   $serviceClass->load_real($serviceInfo[$settings['PREFIX'].'_id']);
   ```

4. **Build and dispatch the GenericEvent**:
   ```php
   $subevent = new GenericEvent($serviceClass, [
       'field1'   => $serviceTypes[$serviceClass->getType()]['services_field1'],
       'field2'   => $serviceTypes[$serviceClass->getType()]['services_field2'],
       'type'     => $serviceTypes[$serviceClass->getType()]['services_type'],
       'category' => $serviceTypes[$serviceClass->getType()]['services_category'],
       'email'    => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid())
   ]);
   $success = true;
   try {
       $GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.<action>'); // e.g. .activate, .reactivate, .terminate
   } catch (\Exception $e) {
       myadmin_log('myadmin', 'error', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceClass->getId());
       $subject = 'Cant Connect to DB to <Action>';
       $email = $subject.'<br>Username '.$serviceClass->getUsername().'<br>'.$e->getMessage();
       (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_connect_error.tpl');
       $success = false;
   }
   ```

5. **Check propagation** immediately after the try/catch:
   ```php
   if ($success == true && !$subevent->isPropagationStopped()) {
       myadmin_log(self::$module, 'error', 'Dont know how to <action> '.$settings['TBLNAME'].' '.$serviceInfo[$settings['PREFIX'].'_id'].' Type '.$serviceTypes[$serviceClass->getType()]['services_type'].' Category '.$serviceTypes[$serviceClass->getType()]['services_category'], __LINE__, __FILE__, self::$module, $serviceClass->getId());
       $success = false;
   }
   ```

6. **Apply status updates on success** — use action-appropriate statuses:
   - **setEnable / setReactivate**: `$serviceClass->setStatus('active')->save()` + `->setServerStatus('running')->save()`
   - **setTerminate**: `$serviceClass->setServerStatus('deleted')->save()`
   - Follow each ORM save with: `$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', '<status>', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);`

7. **Non-ZoneMTA (queue) path** — queue the operation directly:
   ```php
   // setEnable:
   $db->query('update '.$settings['TABLE'].' set '.$settings['PREFIX']."_status='pending-setup' where ".$settings['PREFIX']."_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
   $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
   $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);

   // setTerminate:
   $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'destroy', '', $serviceInfo[$settings['PREFIX'].'_custid']);
   ```

8. **Send email** (where required) — client email uses `TFSmarty` + `clientMail()`; admin notifications use `adminMail()`:
   ```php
   $data = $GLOBALS['tf']->accounts->read($serviceInfo[$settings['PREFIX'].'_custid']);
   $smarty = new \TFSmarty();
   $smarty->assign('name', $data['name']);
   $smarty->assign('body_rows', $body_rows);
   $email = $smarty->fetch('email/client/client_email.tpl');
   (new \MyAdmin\Mail())->clientMail($subject, $email, $data['account_lid'], 'client/client_email.tpl');
   ```

9. **Verify** by running `vendor/bin/phpunit` — all tests in `tests/PluginTest.php` must pass.

## Examples

**User says:** "implement the terminate handler for ZoneMTA mail"

**Actions taken:**
- Inside `setTerminate(function ($service) { ... })` in `src/Plugin.php`
- Extract `$serviceInfo`, `$settings`, `$serviceTypes`
- Branch: ZoneMTA → load ORM class, build `GenericEvent`, dispatch `self::$module.'.terminate'`
- On success: `$serviceClass->setServerStatus('deleted')->save()` + `history->add('change_server_status', 'deleted', ...)`
- Non-ZoneMTA: `history->add(self::$module.'queue', $id, 'destroy', '', $custid)`

**Result:**
```php
})->setTerminate(function ($service) {
    $serviceInfo = $service->getServiceInfo();
    $settings = get_module_settings(self::$module);
    $serviceTypes = run_event('get_service_types', false, self::$module);
    if ($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_type'] == get_service_define('MAIL_ZONEMTA')) {
        $class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
        $serviceClass = new $class();
        $serviceClass->load_real($serviceInfo[$settings['PREFIX'].'_id']);
        $subevent = new GenericEvent($serviceClass, [
            'field1' => $serviceTypes[$serviceClass->getType()]['services_field1'],
            'field2' => $serviceTypes[$serviceClass->getType()]['services_field2'],
            'type' => $serviceTypes[$serviceClass->getType()]['services_type'],
            'category' => $serviceTypes[$serviceClass->getType()]['services_category'],
            'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid())
        ]);
        $success = true;
        try {
            $GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.terminate');
        } catch (\Exception $e) {
            myadmin_log('myadmin', 'error', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceClass->getId());
            $subject = 'Cant Connect to DB to Terminate';
            $email = $subject.'<br>Username '.$serviceClass->getUsername().'<br>'.$e->getMessage();
            (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_connect_error.tpl');
            $success = false;
        }
        if ($success == true && !$subevent->isPropagationStopped()) {
            myadmin_log(self::$module, 'error', 'Dont know how to deactivate '.$settings['TBLNAME'].' '.$serviceInfo[$settings['PREFIX'].'_id'], __LINE__, __FILE__, self::$module, $serviceClass->getId());
            $success = false;
        }
        if ($success == true) {
            $serviceClass->setServerStatus('deleted')->save();
            $GLOBALS['tf']->history->add($settings['TABLE'], 'change_server_status', 'deleted', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
        }
    } else {
        $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'destroy', '', $serviceInfo[$settings['PREFIX'].'_custid']);
    }
})->register();
```

## Common Issues

- **`$success` stays `true` but nothing happens**: `$subevent->isPropagationStopped()` returned `false` and the propagation-stopped block logged an error and set `$success = false`. Verify the ZoneMTA dispatcher listener is registered and listening on `self::$module.'.activate'` (or relevant action).
- **`Call to undefined function get_service_define()`**: `function_requirements('get_service_define')` is not loaded. Ensure the MyAdmin autoloader and `function_requirements` bootstrap are in place (set up by `tests/phpunit/prepend.php` for tests).
- **`history->add()` records wrong module**: The queue path uses `self::$module.'queue'` (e.g. `'mailqueue'`) as the first arg; the table path uses `$settings['TABLE']` (`'mail'`). Do not swap them.
- **Tests fail with `Class '\MyAdmin\Orm\...' not found`**: `get_orm_class_from_table($settings['TABLE'])` returns a class name that must be autoloaded. Confirm `composer dump-autoload` has been run and the ORM class exists in the parent MyAdmin installation.
- **`dispatch()` signature error** (`Too few arguments`): The project uses `dispatcher->dispatch($event, $eventName)` — event first, name second (Symfony 4.3+ style). Do not use the legacy `dispatch($eventName, $event)` order.