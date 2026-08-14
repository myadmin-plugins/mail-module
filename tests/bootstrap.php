<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * ---------------------------------------------------------------------------------
 * WHY A CONSTANT HAS TO BE DEFINED BEFORE ANY TEST RUNS
 * ---------------------------------------------------------------------------------
 * `Plugin::$settings` holds `'REPEAT_BILLING_METHOD' => PRORATE_BILLING`, and a static
 * property initializer is evaluated when the class *loads* — not when the property is read.
 * So on an unprimed process, merely mentioning the class fatals with
 * `Undefined constant "Detain\MyAdminMail\PRORATE_BILLING"`, and every test in the file dies,
 * including the ones that only reflect over it. That was 39 of this repo's 79 tests.
 *
 * The value matches core's `include/config/config.inc.php`, which defines it as 2. It is
 * guarded, so running inside a real core checkout keeps core's definition rather than fighting
 * for it.
 *
 * The contract harness primes this itself for its own assertions — that is why
 * `tests/ContractTest.php` passed while the rest of the suite could not load the class at all.
 * This file is what gives the repo's own tests the same footing.
 */

require dirname(__DIR__).'/vendor/autoload.php';

if (!defined('PRORATE_BILLING')) {
    define('PRORATE_BILLING', 2);
}

// PluginTest reflects over handlers that reference \MyAdmin\App, which lives in the core tree
// and in no package. The harness ships a stand-in and stands down if the real one is present.
\MyAdmin\Plugins\Testing\Bootstrap::installApp();
