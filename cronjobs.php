<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2018 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017-2018 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

use CronJobsModule\CronJobsForms;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/classes/autoload.php';

/**
 * Class CronJobs
 */
class CronJobs extends Module
{
    const EACH = -1;

    const TABLE = 'cronjobs';

    const MODULE_VERSION = 'CRONJOBS_MODULE_VERSION';
    const EXECUTION_TOKEN = 'CRONJOBS_EXECUTION_TOKEN';
    const PHP_FASTCGI_FINISH_REQUEST = 'CRONJOBS_FASTCGI_FINISH_REQUEST';

    protected $successes;
    protected $warnings;

    /**
     * CronJobs constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'cronjobs';
        $this->tab = 'administration';
        $this->version = '2.1.0';
        $this->module_key = '';

        $this->controllers = ['cron'];

        $this->author = 'thirty bees';
        $this->need_instance = true;

        $this->bootstrap = true;
        $this->display = 'view';

        parent::__construct();

        $this->displayName = $this->l('Cron tasks manager');
        $this->description = $this->l('Manage all your automated web tasks from a single interface.');
    }

    /**
     * @return bool
     */
    protected function isLocalEnvironment()
    {
        if (isset($_SERVER['REMOTE_ADDR']) === false) {
            return true;
        }

        try {
            return in_array(Tools::getRemoteAddr(), ['127.0.0.1', '::1']) || preg_match('/^172\.16\.|^192\.168\.|^10\.|^127\.|^localhost|\.local$/', Configuration::get('PS_SHOP_DOMAIN'));
        } catch (PrestaShopException $e) {
            return false;
        }
    }

    /**
     * @param string $message
     *
     * @return bool
     */
    protected function setErrorMessage($message)
    {
        $this->_errors[] = $this->l($message);

        return false;
    }

    /**
     * @param string $message
     *
     * @return bool
     */
    protected function setSuccessMessage($message)
    {
        $this->successes[] = $this->l($message);

        return true;
    }

    /**
     * @param int $idModule
     *
     * @return bool
     */
    public static function isActive($idModule)
    {
        $module = Module::getInstanceByName('cronjobs');

        if (($module == false) || ($module->active == false)) {
            return false;
        }

        try {
            return (bool) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())->select('`active`')
                    ->from('cronjobs')
                    ->where('`id_module` = '.(int) $idModule)
            );
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * $taks should be a valid URL
     *
     * @param string $task
     * @param string $description
     * @param array  $execution
     *
     * @return bool
     */
    public static function addOneShotTask($task, $description, $execution = [])
    {
        if (static::isTaskURLValid($task) == false) {
            return false;
        }

        $idShop = (int) Context::getContext()->shop->id;
        $idShopGroup = (int) Context::getContext()->shop->id_shop_group;

        try {
            if (Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())->select('`active`')
                    ->from(static::TABLE)
                    ->where('`task` = '.urlencode($task))
                    ->where('`updated_at` = IS NULL')
                    ->where('`one_shot` IS TRUE')
                    ->where('`id_shop` = \''.$idShop.'\'')
                    ->where('`id_shop_group` = \''.$idShopGroup.'\''))
            ) {
                return true;
            }
        } catch (PrestaShopException $e) {
        }

        if (count($execution) == 0) {
            try {
                return Db::getInstance()->insert(
                    static::TABLE,
                    [
                        'description'   => pSQL($description),
                        'task'          => urlencode($task),
                        'hour'          => '0',
                        'day'           => static::EACH,
                        'month'         => static::EACH,
                        'day_of_week'   => static::EACH,
                        'updated_at'    => null,
                        'one_shot'      => true,
                        'active'        => true,
                        'id_shop'       => $idShop,
                        'id_shop_group' => $idShopGroup,
                    ]
                );
            } catch (PrestaShopException $e) {
                Logger::addLog("Cronjobs module error: {$e->getMessage()}");
            }
        } else {
            $isFrequencyValid = true;
            $minute = (int) $execution['minute'];
            $hour = (int) $execution['hour'];
            $day = (int) $execution['day'];
            $month = (int) $execution['month'];
            $dayOfWeek = (int) $execution['day_of_week'];

            $isFrequencyValid = (($minute >= -1) && ($minute < 60) && $isFrequencyValid);
            $isFrequencyValid = (($hour >= -1) && ($hour < 24) && $isFrequencyValid);
            $isFrequencyValid = (($day >= -1) && ($day <= 31) && $isFrequencyValid);
            $isFrequencyValid = (($month >= -1) && ($month <= 31) && $isFrequencyValid);
            $isFrequencyValid = (($dayOfWeek >= -1) && ($dayOfWeek < 7) && $isFrequencyValid);

            if ($isFrequencyValid) {
                try {
                    return Db::getInstance()->insert(
                        static::TABLE,
                        [
                            'description'   => psQL($description),
                            'task'          => urlencode($task),
                            'minute'        => $minute,
                            'hour'          => $hour,
                            'day'           => $day,
                            'month'         => $month,
                            'day_of_week'   => $dayOfWeek,
                            'updated_at'    => null,
                            'one_shot'      => true,
                            'active'        => true,
                            'id_shop'       => $idShop,
                            'id_shop_group' => $idShopGroup,
                        ]
                    );
                } catch (PrestaShopException $e) {
                    Logger::addLog("Cronjobs module error: {$e->getMessage()}");
                }
            }
        }

        return false;
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function install()
    {
        Configuration::updateValue(static::MODULE_VERSION, $this->version);

        $token = Tools::hash(Tools::getShopDomainSsl().time());
        Configuration::updateGlobalValue(static::EXECUTION_TOKEN, $token);

        if (!parent::install()) {
            return false;
        }

        $this->registerHook('actionModuleRegisterHookAfter');
        $this->registerHook('actionModuleUnRegisterHookAfter');

        return $this->installDb();
    }

    /**
     * @return bool
     */
    public function installDb()
    {
        try {
            return Db::getInstance()->execute(
                '
                CREATE TABLE IF NOT EXISTS '._DB_PREFIX_.static::TABLE.' (
                `id_cronjob`    INTEGER(10) NOT NULL AUTO_INCREMENT,
                `id_module`     INTEGER(10) DEFAULT NULL,
                `description`   TEXT DEFAULT NULL,
                `task`          TEXT DEFAULT NULL,
                `minute`        INTEGER DEFAULT \'-1\',
                `hour`          INTEGER DEFAULT \'-1\',
                `day`           INTEGER DEFAULT \'-1\',
                `month`         INTEGER DEFAULT \'-1\',
                `day_of_week`   INTEGER DEFAULT \'-1\',
                `updated_at`    DATETIME DEFAULT NULL,
                `one_shot`      BOOLEAN NOT NULL DEFAULT 0,
                `active`        BOOLEAN DEFAULT FALSE,
                `id_shop`       INTEGER DEFAULT \'0\',
                `id_shop_group` INTEGER DEFAULT \'0\',
                PRIMARY KEY(`id_cronjob`),
                INDEX (`id_module`))
                ENGINE='._MYSQL_ENGINE_.' default CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        } catch (PrestaShopException $e) {
            $this->context->controller->errors[] = $e->getMessage();

            return false;
        }
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function uninstall()
    {
        return $this->uninstallDb() &&
            parent::uninstall();
    }

    /**
     * @return bool
     */
    public function uninstallDb()
    {
        try {
            return Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.static::TABLE);
        } catch (PrestaShopException $e) {
            $this->context->controller->errors[] = $e->getMessage();

            return false;
        }
    }

    /**
     * @param array $params
     */
    public function hookActionModuleRegisterHookAfter($params)
    {
        $hookName = $params['hook_name'];

        if ($hookName == 'actionCronJob') {
            $module = $params['object'];
            $this->registerModuleHook($module->id);
        }
    }

    /**
     * @param int $idModule
     *
     * @return bool
     */
    protected function registerModuleHook($idModule)
    {
        try {
            $module = Module::getInstanceById($idModule);
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return false;
        }
        $idShop = (int) Context::getContext()->shop->id;
        $idShopGroup = (int) Context::getContext()->shop->id_shop_group;

        if (method_exists($module, 'getCronFrequency')) {
            $frequency = $module->getCronFrequency();

            try {
                return Db::getInstance()->insert(
                    static::TABLE,
                    [
                        'id_module'     => $idModule,
                        'minute'        => $frequency['minute'],
                        'hour'          => $frequency['hour'],
                        'day'           => $frequency['day'],
                        'month'         => $frequency['month'],
                        'day_of_week'   => $frequency['day_of_week'],
                        'active'        => true,
                        'id_shop'       => $idShop,
                        'id_shop_group' => $idShopGroup,
                    ]
                );
            } catch (PrestaShopException $e) {
                Logger::addLog("Cronjobs module error: {$e->getMessage()}");

                return false;
            }
        } else {
            try {
                return Db::getInstance()->insert(
                    static::TABLE,
                    [
                        'id_module'     => $idModule,
                        'active'        => false,
                        'id_shop'       => $idShop,
                        'id_shop_group' => $idShopGroup,
                    ]
                );
            } catch (PrestaShopException $e) {
                Logger::addLog("Cronjobs module error: {$e->getMessage()}");

                return false;
            }
        }
    }

    /**
     * @param array $params
     */
    public function hookActionModuleUnRegisterHookAfter($params)
    {
        $hookName = $params['hook_name'];

        if ($hookName == 'actionCronJob') {
            $module = $params['object'];
            $this->unregisterModuleHook($module->id);
        }
    }

    /**
     * @param int $idModule Module ID
     *
     * @return bool
     */
    protected function unregisterModuleHook($idModule)
    {
        try {
            return Db::getInstance()->delete(static::TABLE, '`id_module` = '.(int) $idModule);
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Get module configuration page
     *
     * @return string
     */
    public function getContent()
    {
        $output = null;
        $this->checkLocalEnvironment();

        if (Tools::isSubmit('submitNewCronJob')) {
            $submitCron = $this->postProcessNewJob();
        } elseif (Tools::isSubmit('submitUpdateCronJob')) {
            $submitCron = $this->postProcessUpdateJob();
        }

        try {
            $this->context->smarty->assign(
                [
                    'module_dir'       => $this->_path,
                    'module_local_dir' => $this->local_path,
                    'form_errors'      => $this->_errors,
                    'form_infos'       => $this->warnings,
                    'form_successes'   => $this->successes,
                    'curl_info'        => $this->l('To execute your cron tasks, please insert the following line in your cron tasks manager:', 'CronJobsForms'),
                    'cronjob_freq'     => '* * * * * curl '.(\Configuration::get('PS_SSL_ENABLED') ? '-k ' : null).'"'.$this->context->link->getModuleLink($this->name, 'cron', ['token' => Configuration::get(static::EXECUTION_TOKEN)], true, (int) Configuration::get('PS_DEFAULT_LANG')).'"',
                ]
            );
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return '';
        }

        if ((Tools::isSubmit('submitNewCronJob') || Tools::isSubmit('newcronjobs') || Tools::isSubmit('updatecronjobs')) &&
            ((isset($submitCron) == false) || ($submitCron === false))
        ) {
            try {
                $backUrl = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules');
            } catch (PrestaShopException $e) {
                Logger::addLog("Cronjobs module error: {$e->getMessage()}");

                return '';
            }
        }

        $output = $output.$this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        try {
            if (Tools::isSubmit('newcronjobs') || ((isset($submitCron)) && ($submitCron === false))) {
                $output = $output.$this->renderForm(CronJobsForms::getJobForm(), CronJobsForms::getNewJobFormValues(), 'submitNewCronJob', true, $backUrl);
            } elseif (Tools::isSubmit('updatecronjobs') && Tools::isSubmit('id_cronjob')) {
                $formStructure = CronJobsForms::getJobForm('Update cron task', true);
                $form = $this->renderForm($formStructure, CronJobsForms::getUpdateJobFormValues(), 'submitUpdateCronJob', true, $backUrl, true);
                $output = $output.$form;
            } elseif (Tools::isSubmit('deletecronjobs') && Tools::isSubmit('id_cronjob')) {
                $this->postProcessDeleteCronJob();
            } elseif (Tools::isSubmit('oneshotcronjobs')) {
                $this->postProcessUpdateJobOneShot();
            } elseif (Tools::isSubmit('statuscronjobs')) {
                $this->postProcessUpdateJobStatus();
            }
        } catch (Exception $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return '';
        }

        return $output.$this->renderTasksList();
    }

    /**
     * Check local environment
     */
    protected function checkLocalEnvironment()
    {
        if ($this->isLocalEnvironment()) {
            $this->setWarningMessage(
                'You are using the Cron jobs module on a local installation:
				you will not be able to use the Basic mode or reliably call remote cron tasks in your current environment.
				To use this module at its best, you should switch to an online installation.'
            );
        }
    }

    /**
     * @param string $message
     *
     * @return bool
     */
    protected function setWarningMessage($message)
    {
        $this->warnings[] = $this->l($message);

        return false;
    }

    /**
     * @return bool
     */
    protected function postProcessNewJob()
    {
        if ($this->isNewJobValid()) {
            $description = Db::getInstance()->escape(Tools::getValue('description'));
            $task = urlencode(Tools::getValue('task'));
            $minute = (int) Tools::getValue('minute');
            $hour = (int) Tools::getValue('hour');
            $day = (int) Tools::getValue('day');
            $month = (int) Tools::getValue('month');
            $dayOfWeek = (int) Tools::getValue('day_of_week');

            try {
                $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
                    (new DbQuery())
                        ->select('`id_cronjob`')
                        ->from(static::TABLE)
                        ->where('`task` = \''.pSQL($task).'\'')
                        ->where('`minute` = \''.pSQL($minute).'\'')
                        ->where('`hour` = \''.pSQL($hour).'\'')
                        ->where('`day` = \''.pSQL($day).'\'')
                        ->where('`month` = \''.pSQL($month).'\'')
                        ->where('`day_of_week` = \''.pSQL($dayOfWeek).'\'')
                );
            } catch (PrestaShopException $e) {
            }

            if (!$result) {
                $idShop = (int) Context::getContext()->shop->id;
                $idShopGroup = (int) Context::getContext()->shop->id_shop_group;
                try {
                    if (Db::getInstance()->insert(
                        static::TABLE,
                        [
                            'description'   => pSQL($description),
                            'task'          => pSQL($task),
                            'minute'        => (int) $minute,
                            'hour'          => (int) $hour,
                            'day'           => (int) $day,
                            'month'         => (int) $month,
                            'day_of_week'   => (int) $dayOfWeek,
                            'updated_at'    => ['type' => 'sql', 'value' => 'NOW()'],
                            'active'        => 1,
                            'id_shop'       => (int) $idShop,
                            'id_shop_group' => (int) $idShopGroup,
                        ]
                    )) {
                        return $this->setSuccessMessage('The task has been successfully added.');
                    }
                } catch (PrestaShopException $e) {
                    return $this->setErrorMessage('An error occurred: the task could not be added.').' '.$e->getMessage();
                }

                return $this->setErrorMessage('An error occurred: the task could not be added.');
            }

            return $this->setErrorMessage('This cron task already exists.');
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function isNewJobValid()
    {
        if ((Tools::isSubmit('description')) &&
            (Tools::isSubmit('task')) &&
            (Tools::isSubmit('hour')) &&
            (Tools::isSubmit('day')) &&
            (Tools::isSubmit('month')) &&
            (Tools::isSubmit('day_of_week'))
        ) {
            $minute = Tools::getValue('minute');
            $hour = Tools::getValue('hour');
            $day = Tools::getValue('day');
            $month = Tools::getValue('month');
            $dayOfWeek = Tools::getValue('day_of_week');

            return $this->isFrequencyValid($minute, $hour, $day, $month, $dayOfWeek);
        }

        return false;
    }

    /**
     * @param $task
     *
     * @return bool
     */
    protected static function isTaskURLValid($task)
    {
        $task = urlencode($task);
        try {
            $shopUrl = urlencode(Tools::getShopDomain(true, true).__PS_BASE_URI__);
        } catch (PrestaShopException $e) {
            return false;
        }
        try {
            $shopUrlSsl = urlencode(Tools::getShopDomainSsl(true, true).__PS_BASE_URI__);
        } catch (PrestaShopException $e) {
            return false;
        }

        return ((strpos($task, $shopUrl) === 0) || (strpos($task, $shopUrlSsl) === 0));
    }

    /**
     * @param $minute
     * @param $hour
     * @param $day
     * @param $month
     * @param $dayOfWeek
     *
     * @return bool
     */
    protected function isFrequencyValid($minute, $hour, $day, $month, $dayOfWeek)
    {
        $success = true;

        if (!(($minute >= -1) && ($minute < 60))) {
            $success &= $this->setErrorMessage('The value you chose for the minute and/or hour is not valid. It should be between 00:00 and 23:59.');
        }
        if (!(($hour >= -1) && ($hour < 24))) {
            $success &= $this->setErrorMessage('The value you chose for the minute and/or hour is not valid. It should be between 00:00 and 23:59.');
        }
        if (!(($day >= -1) && ($day <= 31))) {
            $success &= $this->setErrorMessage('The value you chose for the day is not valid.');
        }
        if (!(($month >= -1) && ($month <= 31))) {
            $success &= $this->setErrorMessage('The value you chose for the month is not valid.');
        }
        if (!(($dayOfWeek >= -1) && ($dayOfWeek < 7))) {
            $success &= $this->setErrorMessage('The value you chose for the day of the week is not valid.');
        }

        return $success;
    }

    /**
     * @return bool
     */
    protected function postProcessUpdateJob()
    {
        if (!Tools::isSubmit('id_cronjob')) {
            return false;
        }

        try {
            $updated = Db::getInstance()->update(
                static::TABLE,
                [
                    'description' => pSQL(Tools::getValue('description')),
                    'task'        => urlencode(Tools::getValue('task')),
                    'hour'        => (int) Tools::getValue('hour'),
                    'day'         => (int) Tools::getValue('day'),
                    'month'       => (int) Tools::getValue('month'),
                    'day_of_week' => (int) Tools::getValue('day_of_week'),
                ],
                '`id_cronjob` = '.(int) Tools::getValue('id_cronjob')
            );
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");
        }

        if ($updated) {
            return $this->setSuccessMessage('The task has been updated.');
        }

        return $this->setErrorMessage('The task has not been updated');
    }

    /**
     * @param      $form
     * @param      $formValues
     * @param      $action
     * @param bool $cancel
     * @param bool $backUrl
     * @param bool $update
     *
     * @return string
     */
    protected function renderForm($form, $formValues, $action, $cancel = false, $backUrl = false, $update = false)
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        try {
            $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return '';
        }

        $helper->identifier = $this->identifier;
        $helper->submit_action = $action;

        try {
            $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return '';
        }

        if ($update) {
            $helper->currentIndex .= '&id_cronjob='.(int) Tools::getValue('id_cronjob');
        }

        try {
            $helper->token = Tools::getAdminTokenLite('AdminModules');
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return '';
        }

        try {
            $helper->tpl_vars = [
                'fields_value'       => $formValues,
                'id_language'        => $this->context->language->id,
                'languages'          => $this->context->controller->getLanguages(),
                'back_url'           => $backUrl,
                'show_cancel_button' => $cancel,
            ];

            return $helper->generateForm($form);
        } catch (Exception $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return '';
        }
    }

    /**
     * @return void
     */
    protected function postProcessDeleteCronJob()
    {
        $idCronjob = Tools::getValue('id_cronjob');
        try {
            $idModule = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new DbQuery())
                    ->select('`id_module`')
                    ->from(static::TABLE)
                    ->where('`id_cronjob` = '.(int) $idCronjob)
            );
        } catch (PrestaShopException $e) {
            $idModule = false;
        }

        try {
            if (!$idModule) {
                Db::getInstance()->delete(static::TABLE, '`id_cronjob` = \''.(int) $idCronjob.'\'');
            } else {
                Db::getInstance()->update(static::TABLE, ['active' => 0], '`id_cronjob` = '.(int) $idCronjob);
            }
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return;
        }

        try {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");
        }
    }

    /**
     * @return void
     */
    protected function postProcessUpdateJobOneShot()
    {
        if (!Tools::isSubmit('id_cronjob')) {
            return;
        }

        $idCronjob = (int) Tools::getValue('id_cronjob');

        try {
            Db::getInstance()->update(
                static::TABLE,
                [
                    'one_shot' => ['type' => 'sql', 'value' => 'IF (`one_shot`, 0, 1)'],
                ],
                '`id_cronjob` = '.(int) $idCronjob
            );
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return;
        }

        try {
            Tools::redirectAdmin(
                $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules')
            );
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return;
        }
    }

    /**
     * @return void
     */
    protected function postProcessUpdateJobStatus()
    {
        if (Tools::isSubmit('id_cronjob') == false) {
            return;
        }

        $idCronjob = (int) Tools::getValue('id_cronjob');

        try {
            Db::getInstance()->update(
                bqSQL($this->name),
                [
                    'active' => ['type' => 'sql', 'value' => 'IF(`active`, 0, 1)'],
                ],
                '`id_cronjob` = '.(int) $idCronjob
            );

            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return;
        }
    }

    /**
     * @return string
     */
    protected function renderTasksList()
    {
        $helper = new HelperList();

        $helper->title = $this->l('Cron tasks');
        $helper->table = $this->name;
        $helper->no_link = true;
        $helper->shopLinkType = '';
        $helper->identifier = 'id_cronjob';
        $helper->actions = ['edit', 'delete'];

        try {
            $values = CronJobsForms::getTasksListValues();
        } catch (PrestaShopException $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return '';
        }
        $helper->listTotal = count($values);
        $helper->tpl_vars = ['show_filters' => false];

        try {
            $helper->toolbar_btn['new'] = [
                'href' => $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&newcronjobs=1&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Add new task'),
            ];

            $helper->token = Tools::getAdminTokenLite('AdminModules');
            $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;

            return $helper->generateList($values, CronJobsForms::getTasksList());
        } catch (Exception $e) {
            Logger::addLog("Cronjobs module error: {$e->getMessage()}");

            return '';
        }
    }

    /**
     * @return void
     */
    public function sendCallback()
    {
        @ignore_user_abort(true);
        @set_time_limit(0);

        @ob_start();
        echo $this->name.'_thirtybees';
        header('Connection: close');
        header('Content-Length: '.ob_get_length());
        @ob_end_flush();
        @ob_flush();
        @flush();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * @return void
     */
    public function addNewModulesTasks()
    {
        $crons = Hook::getHookModuleExecList('actionCronJob');

        if ($crons == false) {
            return;
        }

        $idShop = (int) Context::getContext()->shop->id;
        $idShopGroup = (int) Context::getContext()->shop->id_shop_group;

        foreach ($crons as $cron) {
            $idModule = (int) $cron['id_module'];
            try {
                $module = Module::getInstanceById((int) $cron['id_module']);
            } catch (PrestaShopException $e) {
                $module = false;
            }

            if (!$module) {
                try {
                    Db::getInstance()->delete($this->name, '`id_cronjob` = \''.(int) $cron['id_cronjob'].'\'');
                } catch (PrestaShopException $e) {
                    Logger::addLog("Cronjobs module error: {$e->getMessage()}");
                }
                break;
            }

            try {
                $cronjob = (bool) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                    (new DbQuery())
                        ->select('`id_cronjob`')
                        ->from(bqSQL($this->name))
                        ->where('`id_module` = '.(int) $idModule)
                        ->where('`id_shop` = '.(int) $idShop)
                        ->where('`id_shop_group` = '.(int) $idShopGroup)
                );
            } catch (PrestaShopException $e) {
                Logger::addLog("Cronjobs module error: {$e->getMessage()}");
            }

            if ($cronjob == false) {
                $this->registerModuleHook($idModule);
            }
        }
    }
}
