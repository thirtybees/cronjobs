<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 Thirty Bees
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
 * @author    Thirty Bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 Thirty Bees
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

    protected $successes;
    protected $warnings;

    /**
     * CronJobs constructor.
     */
    public function __construct()
    {
        $this->name = 'cronjobs';
        $this->tab = 'administration';
        $this->version = '2.0.0';
        $this->module_key = '';

        $this->controllers = ['callback', 'cron'];

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

        return in_array(Tools::getRemoteAddr(), ['127.0.0.1', '::1']) || preg_match('/^172\.16\.|^192\.168\.|^10\.|^127\.|^localhost|\.local$/', Configuration::get('PS_SHOP_DOMAIN'));
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

        $sql = new DbQuery();
        $sql->select('`active`');
        $sql->from('cronjobs');
        $sql->where('`id_module` = '.(int) $idModule);

        return (bool) Db::getInstance()->getValue($sql);
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
        if (self::isTaskURLValid($task) == false) {
            return false;
        }

        $idShop = (int) Context::getContext()->shop->id;
        $idShopGroup = (int) Context::getContext()->shop->id_shop_group;

        $sql = new DbQuery();
        $sql->select('`active`');
        $sql->from(self::TABLE);
        $sql->where('`task` = '.urlencode($task));
        $sql->where('`updated_at` = IS NULL');
        $sql->where('`one_shot` IS TRUE');
        $sql->where('`id_shop` = \''.$idShop.'\'');
        $sql->where('`id_shop_group` = \''.$idShopGroup.'\'');

        if ((bool) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql) == true) {
            return true;
        }

        if (count($execution) == 0) {
            return Db::getInstance()->insert(
                self::TABLE,
                [
                    'description'   => pSQL($description),
                    'task'          => urlencode($task),
                    'hour'          => '0',
                    'day'           => self::EACH,
                    'month'         => self::EACH,
                    'day_of_week'   => self::EACH,
                    'updated_at'    => null,
                    'one_shot'      => true,
                    'active'        => true,
                    'id_shop'       => $idShop,
                    'id_shop_group' => $idShopGroup,
                ]
            );
        } else {
            $isFrequencyValid = true;
            $hour = (int) $execution['hour'];
            $day = (int) $execution['day'];
            $month = (int) $execution['month'];
            $dayOfWeek = (int) $execution['day_of_week'];

            $isFrequencyValid = (($hour >= -1) && ($hour < 24) && $isFrequencyValid);
            $isFrequencyValid = (($day >= -1) && ($day <= 31) && $isFrequencyValid);
            $isFrequencyValid = (($month >= -1) && ($month <= 31) && $isFrequencyValid);
            $isFrequencyValid = (($dayOfWeek >= -1) && ($dayOfWeek < 7) && $isFrequencyValid);

            if ($isFrequencyValid == true) {
                return Db::getInstance()->insert(
                    self::TABLE,
                    [
                        'description' => psQL($description),
                        'task' => urlencode($task),
                        'hour' => $hour,
                        'day' => $day,
                        'month' => $month,
                        'day_of_week' => $dayOfWeek,
                        'updated_at' => null,
                        'one_shot' => true,
                        'active' => true,
                        'id_shop' => $idShop,
                        'id_shop_group' => $idShopGroup,
                    ]
                );
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function install()
    {
        Configuration::updateValue(self::MODULE_VERSION, $this->version);

        $token = Tools::encrypt(Tools::getShopDomainSsl().time());
        Configuration::updateGlobalValue(self::EXECUTION_TOKEN, $token);

        if (parent::install()) {
            return $this->installDb() && $this->installTab() &&
                $this->registerHook('actionModuleRegisterHookAfter') &&
                $this->registerHook('actionModuleUnRegisterHookAfter') &&
                $this->registerHook('backOfficeHeader');
        }

        return false;
    }

    /**
     * @return bool
     */
    public function installDb()
    {
        return Db::getInstance()->execute(
            '
			CREATE TABLE IF NOT EXISTS '._DB_PREFIX_.self::TABLE.' (
			`id_cronjob` INTEGER(10) NOT NULL AUTO_INCREMENT,
			`id_module` INTEGER(10) DEFAULT NULL,
			`description` TEXT DEFAULT NULL,
			`task` TEXT DEFAULT NULL,
			`hour` INTEGER DEFAULT \'-1\',
			`day` INTEGER DEFAULT \'-1\',
			`month` INTEGER DEFAULT \'-1\',
			`day_of_week` INTEGER DEFAULT \'-1\',
			`updated_at` DATETIME DEFAULT NULL,
			`one_shot` BOOLEAN NOT NULL DEFAULT 0,
			`active` BOOLEAN DEFAULT FALSE,
			`id_shop` INTEGER DEFAULT \'0\',
			`id_shop_group` INTEGER DEFAULT \'0\',
			PRIMARY KEY(`id_cronjob`),
			INDEX (`id_module`))
			ENGINE='._MYSQL_ENGINE_.' default CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    /**
     * @return int
     */
    public function installTab()
    {
        $languages = Language::getLanguages(true);
        if (empty($languages)) {
            return true;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->name = [];
        $tab->class_name = 'AdminCronJobs';

        foreach ($languages as $lang) {
            $tab->name[$lang['id_lang']] = 'Cron Jobs';
        }

        $tab->id_parent = -1;
        $tab->module = $this->name;

        return $tab->add();
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return $this->uninstallDb() &&
            $this->uninstallTab() &&
            parent::uninstall();
    }

    /**
     * @return bool
     */
    public function uninstallDb()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.self::TABLE);
    }

    /**
     * @return bool
     */
    public function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminCronJobs');

        if ($idTab) {
            $tab = new Tab($idTab);

            return $tab->delete();
        }

        return false;
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
        $module = Module::getInstanceById($idModule);
        $idShop = (int) Context::getContext()->shop->id;
        $idShopGroup = (int) Context::getContext()->shop->id_shop_group;

        if (method_exists($module, 'getCronFrequency')) {
            $frequency = $module->getCronFrequency();

            return Db::getInstance()->insert(
                self::TABLE,
                [
                    'id_module'     => $idModule,
                    'hour'          => $frequency['hour'],
                    'day'           => $frequency['day'],
                    'month'         => $frequency['month'],
                    'day_of_week'   => $frequency['day_of_week'],
                    'active'        => true,
                    'id_shop'       => $idShop,
                    'id_shop_group' => $idShopGroup,
                ]
            );
        } else {
            return Db::getInstance()->insert(
                self::TABLE,
                [
                    'id_module'     => $idModule,
                    'active'        => false,
                    'id_shop'       => $idShop,
                    'id_shop_group' => $idShopGroup,
                ]
            );
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
        return Db::getInstance()->delete(self::TABLE, '`id_module` = '.(int) $idModule);
    }

    /**
     * @return void
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            if (version_compare(_PS_VERSION_, '1.6', '<') == true) {
                $this->context->controller->addCSS($this->_path.'css/bootstrap.min.css');
                $this->context->controller->addCSS($this->_path.'css/configure-ps-15.css');
            } else {
                $this->context->controller->addCSS($this->_path.'css/configure-ps-16.css');
            }
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

        $this->context->smarty->assign(
            [
                'module_dir'       => $this->_path,
                'module_local_dir' => $this->local_path,
                'form_errors'      => $this->_errors,
                'form_infos'       => $this->warnings,
                'form_successes'   => $this->successes,
                'curl_info'        => $this->l('To execute your cron tasks, please insert the following line in your cron tasks manager:', 'CronJobsForms'),
                'cronjob_freq'     => '0 * * * * curl '.(\Configuration::get('PS_SSL_ENABLED') ? '-k ' : null).'"'.$this->context->link->getModuleLink($this->name, 'cron', ['token' => Configuration::get(self::EXECUTION_TOKEN)], true, (int) Configuration::get('PS_DEFAULT_LANG')),
            ]
        );

        if ((Tools::isSubmit('submitNewCronJob') || Tools::isSubmit('newcronjobs') || Tools::isSubmit('updatecronjobs')) &&
            ((isset($submitCron) == false) || ($submitCron === false))
        ) {
            $backUrl = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules');
        }

        $output = $output.$this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        if (Tools::isSubmit('newcronjobs') || ((isset($submitCron) == true) && ($submitCron === false))) {
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

        return $output.$this->renderTasksList();
    }

    /**
     * Check local environment
     */
    protected function checkLocalEnvironment()
    {
        if ($this->isLocalEnvironment() == true) {
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
        if ($this->isNewJobValid() == true) {
            $description = Db::getInstance()->escape(Tools::getValue('description'));
            $task = urlencode(Tools::getValue('task'));
            $hour = (int) Tools::getValue('hour');
            $day = (int) Tools::getValue('day');
            $month = (int) Tools::getValue('month');
            $dayOfWeek = (int) Tools::getValue('day_of_week');

            $result = Db::getInstance()->getRow(
                'SELECT id_cronjob FROM '._DB_PREFIX_.$this->name.'
				WHERE `task` = \''.$task.'\' AND `hour` = \''.$hour.'\' AND `day` = \''.$day.'\'
				AND `month` = \''.$month.'\' AND `day_of_week` = \''.$dayOfWeek.'\''
            );

            if ($result == false) {
                $idShop = (int) Context::getContext()->shop->id;
                $idShopGroup = (int) Context::getContext()->shop->id_shop_group;

                $query = 'INSERT INTO '._DB_PREFIX_.$this->name.'
					(`description`, `task`, `hour`, `day`, `month`, `day_of_week`, `updated_at`, `active`, `id_shop`, `id_shop_group`)
					VALUES (\''.$description.'\', \''.$task.'\', \''.$hour.'\', \''.$day.'\', \''.$month.'\', \''.$dayOfWeek.'\', NULL, TRUE, '.$idShop.', '.$idShopGroup.')';

                if (($result = Db::getInstance()->execute($query)) != false) {
                    return $this->setSuccessMessage('The task has been successfully added.');
                }

                return $this->setErrorMessage('An error happened: the task could not be added.');
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
        if ((Tools::isSubmit('description') == true) &&
            (Tools::isSubmit('task') == true) &&
            (Tools::isSubmit('hour') == true) &&
            (Tools::isSubmit('day') == true) &&
            (Tools::isSubmit('month') == true) &&
            (Tools::isSubmit('day_of_week') == true)
        ) {
            if (self::isTaskURLValid(Tools::getValue('task')) == false) {
                return $this->setErrorMessage('The target link you entered is not valid. It should be an absolute URL, on the same domain as your shop.');
            }

            $hour = Tools::getValue('hour');
            $day = Tools::getValue('day');
            $month = Tools::getValue('month');
            $dayOfWeek = Tools::getValue('day_of_week');

            return $this->isFrequencyValid($hour, $day, $month, $dayOfWeek);
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
        $shopUrl = urlencode(Tools::getShopDomain(true, true).__PS_BASE_URI__);
        $shopUrlSsl = urlencode(Tools::getShopDomainSsl(true, true).__PS_BASE_URI__);

        return ((strpos($task, $shopUrl) === 0) || (strpos($task, $shopUrlSsl) === 0));
    }

    /**
     * @param $hour
     * @param $day
     * @param $month
     * @param $dayOfWeek
     *
     * @return bool
     */
    protected function isFrequencyValid($hour, $day, $month, $dayOfWeek)
    {
        $success = true;

        if ((($hour >= -1) && ($hour < 24)) == false) {
            $success &= $this->setErrorMessage('The value you chose for the hour is not valid. It should be between 00:00 and 23:59.');
        }
        if ((($day >= -1) && ($day <= 31)) == false) {
            $success &= $this->setErrorMessage('The value you chose for the day is not valid.');
        }
        if ((($month >= -1) && ($month <= 31)) == false) {
            $success &= $this->setErrorMessage('The value you chose for the month is not valid.');
        }
        if ((($dayOfWeek >= -1) && ($dayOfWeek < 7)) == false) {
            $success &= $this->setErrorMessage('The value you chose for the day of the week is not valid.');
        }

        return $success;
    }

    /**
     * @return bool
     */
    protected function postProcessUpdateJob()
    {
        if (Tools::isSubmit('id_cronjob') == false) {
            return false;
        }

        $updated = Db::getInstance()->update(
            self::TABLE,
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
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = $action;

        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;

        if ($update == true) {
            $helper->currentIndex .= '&id_cronjob='.(int) Tools::getValue('id_cronjob');
        }

        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value'       => $formValues,
            'id_language'        => $this->context->language->id,
            'languages'          => $this->context->controller->getLanguages(),
            'back_url'           => $backUrl,
            'show_cancel_button' => $cancel,
        ];

        return $helper->generateForm($form);
    }

    /**
     * @return void
     */
    protected function postProcessDeleteCronJob()
    {
        $idCronjob = Tools::getValue('id_cronjob');
        $idModule = Db::getInstance()->getValue('SELECT `id_module` FROM '._DB_PREFIX_.$this->name.' WHERE `id_cronjob` = \''.(int) $idCronjob.'\'');

        if ((bool) $idModule == false) {
            Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.$this->name.' WHERE `id_cronjob` = \''.(int) $idCronjob.'\'');
        } else {
            Db::getInstance()->execute('UPDATE '._DB_PREFIX_.$this->name.' SET `active` = FALSE WHERE `id_cronjob` = \''.(int) $idCronjob.'\'');
        }

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
    }

    /**
     * @return void
     */
    protected function postProcessUpdateJobOneShot()
    {
        if (Tools::isSubmit('id_cronjob') == false) {
            return;
        }

        $idCronjob = (int) Tools::getValue('id_cronjob');

        Db::getInstance()->execute(
            'UPDATE '._DB_PREFIX_.self::TABLE.'
			SET `one_shot` = IF (`one_shot`, 0, 1) WHERE `id_cronjob` = \''.(int) $idCronjob.'\''
        );

        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules')
        );
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

        Db::getInstance()->execute(
            'UPDATE '._DB_PREFIX_.$this->name.'
			SET `active` = IF (`active`, 0, 1) WHERE `id_cronjob` = \''.(int) $idCronjob.'\''
        );

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
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

        $values = CronJobsForms::getTasksListValues();
        $helper->listTotal = count($values);
        $helper->tpl_vars = ['show_filters' => false];

        $helper->toolbar_btn['new'] = [
            'href' => $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&newcronjobs=1&token='.Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Add new task'),
        ];

        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;

        return $helper->generateList($values, CronJobsForms::getTasksList());
    }

    /**
     * @return void
     */
    public function sendCallback()
    {
        ignore_user_abort(true);
        set_time_limit(0);

        ob_start();
        echo $this->name.'_thirtybees';
        header('Connection: close');
        header('Content-Length: '.ob_get_length());
        ob_end_flush();
        ob_flush();
        flush();

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
            $module = Module::getInstanceById((int) $cron['id_module']);

            if ($module == false) {
                Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.$this->name.' WHERE `id_cronjob` = \''.(int) $cron['id_cronjob'].'\'');
                break;
            }

            $cronjob = (bool) Db::getInstance()->getValue(
                'SELECT `id_cronjob` FROM `'._DB_PREFIX_.$this->name.'`
				WHERE `id_module` = \''.$idModule.'\' AND `id_shop` = \''.$idShop.'\' AND `id_shop_group` = \''.$idShopGroup.'\''
            );

            if ($cronjob == false) {
                $this->registerModuleHook($idModule);
            }
        }
    }
}
