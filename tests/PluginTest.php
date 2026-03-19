<?php

namespace Detain\MyAdminMail\Tests;

use Detain\MyAdminMail\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for the Detain\MyAdminMail\Plugin class.
 */
class PluginTest extends TestCase
{
    /**
     * @var ReflectionClass<Plugin>
     */
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(Plugin::class);
    }

    // ─── Class structure ────────────────────────────────────────────────

    /**
     * Test that the Plugin class can be instantiated.
     */
    public function testClassCanBeInstantiated(): void
    {
        $plugin = new Plugin();
        self::assertInstanceOf(Plugin::class, $plugin);
    }

    /**
     * Test that the Plugin class is not abstract or final.
     */
    public function testClassIsConcreteAndNotFinal(): void
    {
        self::assertFalse($this->reflection->isAbstract());
        self::assertFalse($this->reflection->isFinal());
    }

    /**
     * Test that the class resides in the expected namespace.
     */
    public function testClassNamespace(): void
    {
        self::assertSame('Detain\\MyAdminMail', $this->reflection->getNamespaceName());
    }

    // ─── Static properties ──────────────────────────────────────────────

    /**
     * Test that $name is the expected string.
     */
    public function testStaticPropertyName(): void
    {
        self::assertSame('Mail Services', Plugin::$name);
    }

    /**
     * Test that $description is the expected string.
     */
    public function testStaticPropertyDescription(): void
    {
        self::assertSame('Allows selling of Mailing Services', Plugin::$description);
    }

    /**
     * Test that $help is an empty string by default.
     */
    public function testStaticPropertyHelp(): void
    {
        self::assertSame('', Plugin::$help);
    }

    /**
     * Test that $module equals 'mail'.
     */
    public function testStaticPropertyModule(): void
    {
        self::assertSame('mail', Plugin::$module);
    }

    /**
     * Test that $type equals 'module'.
     */
    public function testStaticPropertyType(): void
    {
        self::assertSame('module', Plugin::$type);
    }

    /**
     * Test that all static properties are public.
     */
    public function testStaticPropertiesArePublic(): void
    {
        $expected = ['name', 'description', 'help', 'module', 'type', 'settings'];
        foreach ($expected as $prop) {
            $rp = $this->reflection->getProperty($prop);
            self::assertTrue($rp->isPublic(), "Property \${$prop} should be public");
            self::assertTrue($rp->isStatic(), "Property \${$prop} should be static");
        }
    }

    // ─── $settings array ────────────────────────────────────────────────

    /**
     * Test that $settings is an array with expected keys.
     */
    public function testSettingsIsArray(): void
    {
        self::assertIsArray(Plugin::$settings);
    }

    /**
     * Test that $settings contains all required keys.
     */
    public function testSettingsContainsAllRequiredKeys(): void
    {
        $requiredKeys = [
            'SERVICE_ID_OFFSET',
            'USE_REPEAT_INVOICE',
            'USE_PACKAGES',
            'BILLING_DAYS_OFFSET',
            'IMGNAME',
            'REPEAT_BILLING_METHOD',
            'DELETE_PENDING_DAYS',
            'SUSPEND_DAYS',
            'SUSPEND_WARNING_DAYS',
            'TITLE',
            'MENUNAME',
            'EMAIL_FROM',
            'TBLNAME',
            'TABLE',
            'TITLE_FIELD',
            'TITLE_FIELD2',
            'PREFIX',
        ];
        foreach ($requiredKeys as $key) {
            self::assertArrayHasKey($key, Plugin::$settings, "Settings should contain key '{$key}'");
        }
    }

    /**
     * Test that SERVICE_ID_OFFSET is an integer.
     */
    public function testSettingsServiceIdOffsetIsInt(): void
    {
        self::assertSame(1100, Plugin::$settings['SERVICE_ID_OFFSET']);
    }

    /**
     * Test that USE_REPEAT_INVOICE is boolean true.
     */
    public function testSettingsUseRepeatInvoice(): void
    {
        self::assertTrue(Plugin::$settings['USE_REPEAT_INVOICE']);
    }

    /**
     * Test that USE_PACKAGES is boolean true.
     */
    public function testSettingsUsePackages(): void
    {
        self::assertTrue(Plugin::$settings['USE_PACKAGES']);
    }

    /**
     * Test that BILLING_DAYS_OFFSET is zero.
     */
    public function testSettingsBillingDaysOffset(): void
    {
        self::assertSame(0, Plugin::$settings['BILLING_DAYS_OFFSET']);
    }

    /**
     * Test that IMGNAME is set to the email icon filename.
     */
    public function testSettingsImgname(): void
    {
        self::assertSame('e-mail.png', Plugin::$settings['IMGNAME']);
    }

    /**
     * Test that DELETE_PENDING_DAYS is 45.
     */
    public function testSettingsDeletePendingDays(): void
    {
        self::assertSame(45, Plugin::$settings['DELETE_PENDING_DAYS']);
    }

    /**
     * Test that SUSPEND_DAYS is 14.
     */
    public function testSettingsSuspendDays(): void
    {
        self::assertSame(14, Plugin::$settings['SUSPEND_DAYS']);
    }

    /**
     * Test that SUSPEND_WARNING_DAYS is 7.
     */
    public function testSettingsSuspendWarningDays(): void
    {
        self::assertSame(7, Plugin::$settings['SUSPEND_WARNING_DAYS']);
    }

    /**
     * Test that TITLE matches the plugin name.
     */
    public function testSettingsTitle(): void
    {
        self::assertSame('Mail Services', Plugin::$settings['TITLE']);
    }

    /**
     * Test that MENUNAME is 'Mail'.
     */
    public function testSettingsMenuName(): void
    {
        self::assertSame('Mail', Plugin::$settings['MENUNAME']);
    }

    /**
     * Test that EMAIL_FROM is a valid email address.
     */
    public function testSettingsEmailFrom(): void
    {
        self::assertSame('support@interserver.net', Plugin::$settings['EMAIL_FROM']);
        self::assertNotFalse(filter_var(Plugin::$settings['EMAIL_FROM'], FILTER_VALIDATE_EMAIL));
    }

    /**
     * Test that TBLNAME is 'Mail'.
     */
    public function testSettingsTblname(): void
    {
        self::assertSame('Mail', Plugin::$settings['TBLNAME']);
    }

    /**
     * Test that TABLE is 'mail'.
     */
    public function testSettingsTable(): void
    {
        self::assertSame('mail', Plugin::$settings['TABLE']);
    }

    /**
     * Test that TITLE_FIELD is 'mail_username'.
     */
    public function testSettingsTitleField(): void
    {
        self::assertSame('mail_username', Plugin::$settings['TITLE_FIELD']);
    }

    /**
     * Test that TITLE_FIELD2 is 'mail_ip'.
     */
    public function testSettingsTitleField2(): void
    {
        self::assertSame('mail_ip', Plugin::$settings['TITLE_FIELD2']);
    }

    /**
     * Test that PREFIX is 'mail'.
     */
    public function testSettingsPrefix(): void
    {
        self::assertSame('mail', Plugin::$settings['PREFIX']);
    }

    /**
     * Test that there are no extra unexpected keys in $settings.
     */
    public function testSettingsKeyCount(): void
    {
        self::assertCount(17, Plugin::$settings);
    }

    // ─── getHooks() ─────────────────────────────────────────────────────

    /**
     * Test that getHooks returns an array.
     */
    public function testGetHooksReturnsArray(): void
    {
        $hooks = Plugin::getHooks();
        self::assertIsArray($hooks);
    }

    /**
     * Test that getHooks returns exactly three event hooks.
     */
    public function testGetHooksReturnsThreeEntries(): void
    {
        self::assertCount(3, Plugin::getHooks());
    }

    /**
     * Test that getHooks contains the load_processing hook.
     */
    public function testGetHooksContainsLoadProcessing(): void
    {
        $hooks = Plugin::getHooks();
        self::assertArrayHasKey('mail.load_processing', $hooks);
    }

    /**
     * Test that getHooks contains the settings hook.
     */
    public function testGetHooksContainsSettings(): void
    {
        $hooks = Plugin::getHooks();
        self::assertArrayHasKey('mail.settings', $hooks);
    }

    /**
     * Test that getHooks contains the deactivate hook.
     */
    public function testGetHooksContainsDeactivate(): void
    {
        $hooks = Plugin::getHooks();
        self::assertArrayHasKey('mail.deactivate', $hooks);
    }

    /**
     * Test that hook keys are prefixed with the module name.
     */
    public function testGetHooksKeysArePrefixedWithModule(): void
    {
        $hooks = Plugin::getHooks();
        foreach (array_keys($hooks) as $key) {
            self::assertStringStartsWith(Plugin::$module . '.', $key);
        }
    }

    /**
     * Test that each hook value is a callable array pointing to Plugin class.
     */
    public function testGetHooksValuesAreCallableArrays(): void
    {
        $hooks = Plugin::getHooks();
        foreach ($hooks as $key => $handler) {
            self::assertIsArray($handler, "Handler for '{$key}' should be an array");
            self::assertCount(2, $handler, "Handler for '{$key}' should have exactly two elements");
            self::assertSame(Plugin::class, $handler[0], "Handler class for '{$key}' should be Plugin");
            self::assertIsString($handler[1], "Handler method for '{$key}' should be a string");
        }
    }

    /**
     * Test that all hook handler methods exist on the Plugin class.
     */
    public function testGetHooksMethodsExistOnClass(): void
    {
        $hooks = Plugin::getHooks();
        foreach ($hooks as $key => $handler) {
            self::assertTrue(
                $this->reflection->hasMethod($handler[1]),
                "Method '{$handler[1]}' referenced by hook '{$key}' should exist on Plugin"
            );
        }
    }

    /**
     * Test that all hook handler methods are public and static.
     */
    public function testGetHooksMethodsArePublicStatic(): void
    {
        $hooks = Plugin::getHooks();
        foreach ($hooks as $key => $handler) {
            $method = $this->reflection->getMethod($handler[1]);
            self::assertTrue($method->isPublic(), "Method '{$handler[1]}' should be public");
            self::assertTrue($method->isStatic(), "Method '{$handler[1]}' should be static");
        }
    }

    // ─── Event handler signatures ───────────────────────────────────────

    /**
     * Test that getDeactivate accepts exactly one parameter.
     */
    public function testGetDeactivateParameterCount(): void
    {
        $method = $this->reflection->getMethod('getDeactivate');
        self::assertCount(1, $method->getParameters());
    }

    /**
     * Test that getDeactivate first parameter is type-hinted to GenericEvent.
     */
    public function testGetDeactivateParameterType(): void
    {
        $method = $this->reflection->getMethod('getDeactivate');
        $param = $method->getParameters()[0];
        self::assertNotNull($param->getType());
        self::assertSame('Symfony\\Component\\EventDispatcher\\GenericEvent', $param->getType()->getName());
    }

    /**
     * Test that loadProcessing accepts exactly one parameter.
     */
    public function testLoadProcessingParameterCount(): void
    {
        $method = $this->reflection->getMethod('loadProcessing');
        self::assertCount(1, $method->getParameters());
    }

    /**
     * Test that loadProcessing first parameter is type-hinted to GenericEvent.
     */
    public function testLoadProcessingParameterType(): void
    {
        $method = $this->reflection->getMethod('loadProcessing');
        $param = $method->getParameters()[0];
        self::assertNotNull($param->getType());
        self::assertSame('Symfony\\Component\\EventDispatcher\\GenericEvent', $param->getType()->getName());
    }

    /**
     * Test that getSettings accepts exactly one parameter.
     */
    public function testGetSettingsParameterCount(): void
    {
        $method = $this->reflection->getMethod('getSettings');
        self::assertCount(1, $method->getParameters());
    }

    /**
     * Test that getSettings first parameter is type-hinted to GenericEvent.
     */
    public function testGetSettingsParameterType(): void
    {
        $method = $this->reflection->getMethod('getSettings');
        $param = $method->getParameters()[0];
        self::assertNotNull($param->getType());
        self::assertSame('Symfony\\Component\\EventDispatcher\\GenericEvent', $param->getType()->getName());
    }

    /**
     * Test that all event handlers return void.
     */
    public function testEventHandlersReturnVoid(): void
    {
        foreach (['getDeactivate', 'loadProcessing', 'getSettings'] as $name) {
            $method = $this->reflection->getMethod($name);
            $returnType = $method->getReturnType();
            // Either no declared return type or explicitly void is acceptable
            if ($returnType !== null) {
                self::assertSame('void', $returnType->getName(), "Method {$name} return type should be void");
            } else {
                self::assertNull($returnType, "Method {$name} has no return type (implicitly void)");
            }
        }
    }

    // ─── Constructor ────────────────────────────────────────────────────

    /**
     * Test that the constructor has no required parameters.
     */
    public function testConstructorHasNoRequiredParameters(): void
    {
        $constructor = $this->reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertCount(0, $constructor->getParameters());
    }

    /**
     * Test that the constructor is public.
     */
    public function testConstructorIsPublic(): void
    {
        $constructor = $this->reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPublic());
    }

    // ─── getHooks() return type ─────────────────────────────────────────

    /**
     * Test that getHooks declares an array return type.
     */
    public function testGetHooksReturnType(): void
    {
        $method = $this->reflection->getMethod('getHooks');
        $returnType = $method->getReturnType();
        self::assertNotNull($returnType);
        self::assertSame('array', $returnType->getName());
    }

    /**
     * Test that getHooks is public and static.
     */
    public function testGetHooksIsPublicStatic(): void
    {
        $method = $this->reflection->getMethod('getHooks');
        self::assertTrue($method->isPublic());
        self::assertTrue($method->isStatic());
    }

    // ─── Settings consistency ───────────────────────────────────────────

    /**
     * Test that PREFIX matches TABLE (both should be 'mail').
     */
    public function testPrefixMatchesTable(): void
    {
        self::assertSame(Plugin::$settings['TABLE'], Plugin::$settings['PREFIX']);
    }

    /**
     * Test that TITLE_FIELD starts with PREFIX.
     */
    public function testTitleFieldStartsWithPrefix(): void
    {
        self::assertStringStartsWith(
            Plugin::$settings['PREFIX'] . '_',
            Plugin::$settings['TITLE_FIELD']
        );
    }

    /**
     * Test that TITLE_FIELD2 starts with PREFIX.
     */
    public function testTitleField2StartsWithPrefix(): void
    {
        self::assertStringStartsWith(
            Plugin::$settings['PREFIX'] . '_',
            Plugin::$settings['TITLE_FIELD2']
        );
    }

    /**
     * Test that SUSPEND_WARNING_DAYS is less than SUSPEND_DAYS.
     */
    public function testSuspendWarningDaysLessThanSuspendDays(): void
    {
        self::assertLessThan(
            Plugin::$settings['SUSPEND_DAYS'],
            Plugin::$settings['SUSPEND_WARNING_DAYS']
        );
    }

    /**
     * Test that DELETE_PENDING_DAYS is greater than SUSPEND_DAYS.
     */
    public function testDeletePendingDaysGreaterThanSuspendDays(): void
    {
        self::assertGreaterThan(
            Plugin::$settings['SUSPEND_DAYS'],
            Plugin::$settings['DELETE_PENDING_DAYS']
        );
    }

    // ─── Static analysis: source file ──────────────────────────────────

    /**
     * Test that the source file uses the Symfony GenericEvent import.
     */
    public function testSourceFileImportsGenericEvent(): void
    {
        $source = file_get_contents((string) $this->reflection->getFileName());
        self::assertStringContainsString(
            'use Symfony\\Component\\EventDispatcher\\GenericEvent;',
            $source
        );
    }

    /**
     * Test that the source file declares the correct namespace.
     */
    public function testSourceFileDeclaresCorrectNamespace(): void
    {
        $source = file_get_contents((string) $this->reflection->getFileName());
        self::assertStringContainsString('namespace Detain\\MyAdminMail;', $source);
    }

    /**
     * Test that loadProcessing references database operations via get_module_db.
     */
    public function testLoadProcessingReferencesGetModuleDb(): void
    {
        $source = file_get_contents((string) $this->reflection->getFileName());
        self::assertStringContainsString('get_module_db(', $source);
    }

    /**
     * Test that the source uses myadmin_log for logging.
     */
    public function testSourceUsesMyAdminLog(): void
    {
        $source = file_get_contents((string) $this->reflection->getFileName());
        self::assertStringContainsString('myadmin_log(', $source);
    }

    /**
     * Test that the source dispatches events for activate, reactivate and terminate.
     */
    public function testSourceDispatchesExpectedEvents(): void
    {
        $source = file_get_contents((string) $this->reflection->getFileName());
        self::assertStringContainsString("self::\$module.'.activate'", $source);
        self::assertStringContainsString("self::\$module.'.reactivate'", $source);
        self::assertStringContainsString("self::\$module.'.terminate'", $source);
    }

    /**
     * Test that getSettings references the outofstock_mail setting.
     */
    public function testGetSettingsReferencesOutOfStockMail(): void
    {
        $source = file_get_contents((string) $this->reflection->getFileName());
        self::assertStringContainsString('outofstock_mail', $source);
    }

    /**
     * Test that the class has exactly the expected public methods.
     */
    public function testPublicMethodList(): void
    {
        $methods = array_filter(
            $this->reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            fn (\ReflectionMethod $m) => $m->getDeclaringClass()->getName() === Plugin::class
        );
        $names = array_map(fn (\ReflectionMethod $m) => $m->getName(), $methods);
        sort($names);
        $expected = ['__construct', 'getDeactivate', 'getHooks', 'getSettings', 'loadProcessing'];
        self::assertSame($expected, $names);
    }
}
