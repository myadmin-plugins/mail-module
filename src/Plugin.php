<?php

namespace Detain\MyAdminMail;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminBackups
 */
class Plugin
{
    public static $name = 'Mail Services';
    public static $description = 'Allows selling of Mailing Services';
    public static $help = '';
    public static $module = 'mail';
    public static $type = 'module';
    public static $settings = [
        'SERVICE_ID_OFFSET' => 1100,
        'USE_REPEAT_INVOICE' => true,
        'USE_PACKAGES' => true,
        'BILLING_DAYS_OFFSET' => 0,
        'IMGNAME' => 'e-mail.png',
        'REPEAT_BILLING_METHOD' => PRORATE_BILLING,
        'DELETE_PENDING_DAYS' => 45,
        'SUSPEND_DAYS' => 14,
        'SUSPEND_WARNING_DAYS' => 7,
        'TITLE' => 'Mail Services',
        'MENUNAME' => 'Mail',
        'EMAIL_FROM' => 'support@interserver.net',
        'TBLNAME' => 'Mail',
        'TABLE' => 'mail',
        'TITLE_FIELD' => 'mail_username',
        'TITLE_FIELD2' => 'mail_ip',
        'PREFIX' => 'mail'];

    /**
     * Plugin constructor.
     */
    public function __construct()
    {
    }

    /**
     * @return array
     */
    public static function getHooks()
    {
        return [
            self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
            self::$module.'.settings' => [__CLASS__, 'getSettings'],
            self::$module.'.deactivate' => [__CLASS__, 'getDeactivate']
        ];
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getDeactivate(GenericEvent $event)
    {
        $serviceTypes = run_event('get_service_types', false, self::$module);
        $serviceClass = $event->getSubject();
        myadmin_log(self::$module, 'info', self::$name.' Deactivation', __LINE__, __FILE__, self::$module, $serviceClass->getId());
        if ($serviceTypes[$serviceClass->getType()]['services_type'] == get_service_define('MAIL_ZONEMTA')) {
        } else {
            $GLOBALS['tf']->history->add(self::$module.'queue', $serviceClass->getId(), 'delete', '', $serviceClass->getCustid());
        }
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function loadProcessing(GenericEvent $event)
    {
        /**
         * @var \ServiceHandler $service
         */
        $service = $event->getSubject();
        $service->setModule(self::$module)
            ->setEnable(function ($service) {
                $serviceInfo = $service->getServiceInfo();
                $settings = get_module_settings(self::$module);
                $serviceTypes = run_event('get_service_types', false, self::$module);
                $db = get_module_db(self::$module);
                if ($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_type'] == get_service_define('MAIL_ZONEMTA')) {
                    myadmin_log(self::$module, 'info', self::$name.' Activation - Process started.', __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                    $class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
                    /** @var \MyAdmin\Orm\Product $class **/
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
                        $GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.activate');
                    } catch (\Exception $e) {
                        myadmin_log('myadmin', 'error', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceClass->getId());
                        $subject = 'Cant Connect to DB to Activate';
                        $email = $subject.'<br>ID '.$serviceClass->getId().'<br>'.$e->getMessage();
                        (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_connect_error.tpl');
                        $success = false;
                    }
                    if ($success == true && !$subevent->isPropagationStopped()) {
                        myadmin_log(self::$module, 'error', 'Dont know how to Activate '.$settings['TBLNAME'].' '.$serviceInfo[$settings['PREFIX'].'_id'].' Type '.$serviceTypes[$serviceClass->getType()]['services_type'].' Category '.$serviceTypes[$serviceClass->getType()]['services_category'], __LINE__, __FILE__, self::$module, $serviceClass->getId());
                        $success = false;
                    }
                    if ($success == true) {
                        $serviceClass->setStatus('active')->save();
                        $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
                        $serviceClass->setServerStatus('running')->save();
                        $GLOBALS['tf']->history->add($settings['TABLE'], 'change_server_status', 'running', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);

                        //Email to customer letting know it takes 24hrs to activate.
                        $data = $GLOBALS['tf']->accounts->read($serviceInfo[$settings['PREFIX'].'_custid']);
                        $subject = 'Mail '.$serviceInfo[$settings['TITLE_FIELD']].' Is Setup';
                        $smarty = new \TFSmarty();
                        $smarty->assign('h1', "Mail {$serviceInfo[$settings['TITLE_FIELD']]} Is Setup");
                        $smarty->assign('name', $data['name']);
                        $body_rows = [];
                        $body_rows[] = 'Your account is setup and active.';
                        $body_rows[] = 'You can find your API keys or SMTP username and password from inside our control panel.';
                        $body_rows[] = 'https://my.interserver.net/view_mail_list';
                        $body_rows[] = "Please read over our FAQ here: https://www.mail.baby/faq/";
                        $body_rows[] = "To get started here: https://www.mail.baby/tips/getting-started-with-mailbaby/";
                        $body_rows[] = "Instructions on how to integrate with various mail servers and control panels are available here: https://www.mail.baby/tips-category/tutorials/";
                        $body_rows[] = "API documentation is located here: https://www.mail.baby/tips/api/";
                        $body_rows[] = "The Mail Baby WordPress plugin is available here: https://wordpress.org/plugins/mail-baby-smtp/";
                        $body_rows[] = "Thank you for the order!";
                        $smarty->assign('body_rows', $body_rows);
                        $email = $smarty->fetch('email/client/client_email.tpl');
                        (new \MyAdmin\Mail())->clientMail($subject, $email, $data['account_lid'], 'client/client_email.tpl');
                        myadmin_log(self::$module, 'info', self::$name.' Activation - client email sent.', __LINE__, __FILE__, self::$module, $serviceInfo[$settings['PREFIX'].'_id']);
                    }
                } else {
                    $db->query('update '.$settings['TABLE'].' set '.$settings['PREFIX']."_status='pending-setup' where ".$settings['PREFIX']."_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
                    $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
                    $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
                    $smarty = new \TFSmarty();
                    $smarty->assign('backup_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
                    $email = $smarty->fetch('email/admin/mail_pending_setup.tpl');
                    $subject = 'Backup '.$serviceInfo[$settings['TITLE_FIELD']].' Is Pending Setup';
                    (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/backup_pending_setup.tpl');
                }
            })->setReactivate(function ($service) {
                $serviceTypes = run_event('get_service_types', false, self::$module);
                $serviceInfo = $service->getServiceInfo();
                $settings = get_module_settings(self::$module);
                $db = get_module_db(self::$module);
                if ($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_type'] == get_service_define('MAIL_ZONEMTA')) {
                    $class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
                    /** @var \MyAdmin\Orm\Product $class **/
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
                        $GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.reactivate');
                    } catch (\Exception $e) {
                        myadmin_log('myadmin', 'error', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceClass->getId());
                        $subject = 'Cant Connect to DB to Reactivate';
                        $email = $subject.'<br>Username '.$serviceClass->getUsername().'<br>'.$e->getMessage();
                        (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_connect_error.tpl');
                        $success = false;
                    }
                    if ($success == true && !$subevent->isPropagationStopped()) {
                        myadmin_log(self::$module, 'error', 'Dont know how to reactivate '.$settings['TBLNAME'].' '.$serviceInfo[$settings['PREFIX'].'_id'].' Type '.$serviceTypes[$serviceClass->getType()]['services_type'].' Category '.$serviceTypes[$serviceClass->getType()]['services_category'], __LINE__, __FILE__, self::$module, $serviceClass->getId());
                        $success = false;
                    }
                    if ($success == true) {
                        $serviceClass->setStatus('active')->save();
                        $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
                        $serviceClass->setServerStatus('running')->save();
                        $GLOBALS['tf']->history->add($settings['TABLE'], 'change_server_status', 'running', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
                    }
                } else {
                    if ($serviceInfo[$settings['PREFIX'].'_server_status'] === 'deleted' || $serviceInfo[$settings['PREFIX'].'_ip'] == '') {
                        $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
                        $db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='pending-setup' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
                        $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
                    } else {
                        $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
                        $db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
                        $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'enable', '', $serviceInfo[$settings['PREFIX'].'_custid']);
                        $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'start', '', $serviceInfo[$settings['PREFIX'].'_custid']);
                    }
                }
                $smarty = new \TFSmarty();
                $smarty->assign('backup_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
                $email = $smarty->fetch('email/admin/backup_reactivated.tpl');
                $subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Reactivated';
                (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/backup_reactivated.tpl');
            })->setDisable(function ($service) {
                $serviceInfo = $service->getServiceInfo();
                $settings = get_module_settings(self::$module);
                if ($serviceInfo[$settings['PREFIX'].'_type'] == 10665) {
                    function_requirements('class.AcronisBackup');
                    $bkp = new \AcronisBackup($serviceInfo[$settings['PREFIX'].'_id']);
                    $response = $bkp->setCustomer(0);
                    if (isset($response->version)) {
                        $GLOBALS['tf']->history->add(self::$module, $serviceInfo[$settings['PREFIX'].'_id'], 'disable', '', $serviceInfo[$settings['PREFIX'].'_custid']);
                    }
                }
            })->setTerminate(function ($service) {
                $serviceInfo = $service->getServiceInfo();
                $settings = get_module_settings(self::$module);
                $serviceTypes = run_event('get_service_types', false, self::$module);
                if ($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_type'] == get_service_define('MAIL_ZONEMTA')) {
                    $class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
                    /** @var \MyAdmin\Orm\Product $class **/
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
                        $subject = 'Cant Connect to DB to Reactivate';
                        $email = $subject.'<br>Username '.$serviceClass->getUsername().'<br>'.$e->getMessage();
                        (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_connect_error.tpl');
                        $success = false;
                    }
                    if ($success == true && !$subevent->isPropagationStopped()) {
                        myadmin_log(self::$module, 'error', 'Dont know how to deactivate '.$settings['TBLNAME'].' '.$serviceInfo[$settings['PREFIX'].'_id'].' Type '.$serviceTypes[$serviceClass->getType()]['services_type'].' Category '.$serviceTypes[$serviceClass->getType()]['services_category'], __LINE__, __FILE__, self::$module, $serviceClass->getId());
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
    }

    /**
     * @param \Symfony\Component\EventDispatcher\GenericEvent $event
     */
    public static function getSettings(GenericEvent $event)
    {
        /**
         * @var \MyAdmin\Settings $settings
         **/
        $settings = $event->getSubject();
        $settings->setTarget('global');
        $settings->add_dropdown_setting(self::$module, _('General'), 'outofstock_mail', _('Out Of Stock Mail'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_MAIL'), ['0', '1'], ['No', 'Yes']);
    }
}
