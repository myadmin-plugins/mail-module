# MyAdmin Mail Module

Composer plugin package providing mail service lifecycle management for MyAdmin. Namespace: `Detain\MyAdminMail\` → `src/`.

## Commands

```bash
composer install              # install deps
composer test                 # run tests (phpunit.xml.dist)
```

## Architecture

- **Plugin entry**: `src/Plugin.php` — static class, PSR-4 autoloaded
- **Tests**: `tests/PluginTest.php` — PHPUnit 9.6, autoload-dev `Detain\MyAdminMail\Tests\`
- **CI/CD**: `.github/` contains workflows for automated testing and deployment of the module
- **Event system**: `Symfony\Component\EventDispatcher\GenericEvent` dispatched via `$GLOBALS['tf']->dispatcher->dispatch($event, 'mail.action')`
- **Hook registration**: `Plugin::getHooks()` returns map of `mail.*` events → static handlers
- **Lifecycle hooks**: `mail.load_processing` · `mail.settings` · `mail.deactivate`
- **Service actions**: `setEnable()` · `setReactivate()` · `setDisable()` · `setTerminate()` chained on `$service`, closed with `->register()`

## Settings Pattern

`Plugin::$settings` static array defines module config. Key fields:

| Key | Value |
|-----|-------|
| `MODULE` | `'mail'` |
| `PREFIX` | `'mail'` |
| `TABLE` | `'mail'` |
| `TBLNAME` | `'Mail'` |
| `SERVICE_ID_OFFSET` | `1100` |
| `EMAIL_FROM` | `'support@interserver.net'` |
| `TITLE_FIELD` | `'mail_username'` |

Access at runtime: `$settings = get_module_settings(self::$module);`

## ZoneMTA Branching

All lifecycle handlers branch on service type:

```php
$serviceTypes = run_event('get_service_types', false, self::$module);
if ($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_type'] == get_service_define('MAIL_ZONEMTA')) {
    // ZoneMTA path: dispatch GenericEvent via $GLOBALS['tf']->dispatcher
} else {
    // Queue path: $GLOBALS['tf']->history->add(self::$module.'queue', ...)
}
```

## Dispatch Pattern

```php
$class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
$serviceClass = new $class();
$serviceClass->load_real($serviceInfo[$settings['PREFIX'].'_id']);
$subevent = new GenericEvent($serviceClass, [
    'field1' => $serviceTypes[$serviceClass->getType()]['services_field1'],
    'field2' => $serviceTypes[$serviceClass->getType()]['services_field2'],
    'type'   => $serviceTypes[$serviceClass->getType()]['services_type'],
    'email'  => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid())
]);
try {
    $GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.activate');
} catch (\Exception $e) {
    myadmin_log('myadmin', 'error', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceClass->getId());
    (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_connect_error.tpl');
    $success = false;
}
```

## Logging & Email

- Log: `myadmin_log(self::$module, 'info'|'error', $message, __LINE__, __FILE__, self::$module, $id)`
- Client email: `(new \MyAdmin\Mail())->clientMail($subject, $email, $account_lid, 'client/client_email.tpl')`
- Admin email: `(new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/template.tpl')`
- Smarty render: `$smarty = new \TFSmarty(); $smarty->assign('key', $val); $body = $smarty->fetch('email/...')`

## Conventions

- All static methods — no instance state in `Plugin`
- Always check `$subevent->isPropagationStopped()` after dispatch to detect unhandled service types
- Status updates: `$serviceClass->setStatus('active')->save()` + `$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', ...)`
- Never use PDO — `get_module_db(self::$module)` only
- Escape user input: `$db->real_escape()`

<!-- caliber:managed:pre-commit -->
## Plugin contract harness

This package is on the shared contract harness from `detain/myadmin-plugin-installer`.
`tests/ContractTest.php` is **generated** — run `composer myadmin:scaffold-tests` (add
`--force --write` to re-emit it), never hand-edit it.

The harness **executes** the plugin: it defines the bare constants the class body references
and then calls `getHooks()`, `getSettings()`, `getMenu()`, `apiRegister()` and — for
`type=service` packages — the activate/deactivate/change-ip/queue handlers, for real.

**So do not write reflection-only tests for the plugin class.** Asserting a handler exists,
is public, is static and takes one parameter passes whether or not the handler works; three
production bugs in this fleet were sitting behind assertions of exactly that shape. Older
guidance in this repo that says those methods must not be called predates the harness.

The harness is **additive**: it runs alongside this package's existing tests, and nothing is
deleted to make room for it. Run the whole suite, never `--filter ContractTest` alone — the
contract class primes constants and calls `register_module()`, neither of which can be undone.

See the `plugin-contract-tests` skill for the full workflow, and `docs/testing-harness.md` in
the installer.

## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->
