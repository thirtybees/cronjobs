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

namespace CronJobsModule;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class CronJobsForms
 *
 * @package CronJobsModule
 */
class CronJobsForms
{
    /** @var \CronJobs $module */
    protected static $module;

    /**
     * @return \CronJobs
     */
    public static function getModule()
    {
        if (!self::$module) {
            self::$module = \Module::getInstanceByName('cronjobs');
        }

        return self::$module;
    }

    /**
     * @param string $title
     * @param bool   $update
     *
     * @return array
     */
    public static function getJobForm($title = 'New cron task', $update = false)
    {
        $form = [
            [
                'form' => [
                    'legend' => [
                        'title' => self::getModule()->l($title),
                        'icon'  => 'icon-plus',
                    ],
                    'input'  => [],
                    'submit' => ['title' => self::getModule()->l('Save', 'CronJobsForms'), 'type' => 'submit', 'class' => 'btn btn-default pull-right'],
                ],
            ],
        ];

        $idShop = (int) \Context::getContext()->shop->id;
        $idShopGroup = (int) \Context::getContext()->shop->id_shop_group;

        $currenciesCronUrl = \Tools::getShopDomain(true, true).__PS_BASE_URI__.basename(_PS_ADMIN_DIR_);
        $currenciesCronUrl .= '/cron_currency_rates.php?secure_key='.md5(_COOKIE_KEY_.\Configuration::get('PS_SHOP_NAME'));

        if (($update == true) && (\Tools::isSubmit('id_cronjob'))) {
            $idCronjob = (int) \Tools::getValue('id_cronjob');
            $idModule = (int) \Db::getInstance()->getValue(
                'SELECT `id_module` FROM `'._DB_PREFIX_.self::getModule()->name.'`
				WHERE `id_cronjob` = \''.(int) $idCronjob.'\'
					AND `id_shop` = \''.$idShop.'\' AND `id_shop_group` = \''.$idShopGroup.'\''
            );

            if ((bool) $idModule == true) {
                $form[0]['form']['input'][] = [
                    'type'        => 'free',
                    'name'        => 'description',
                    'label'       => self::getModule()->l('Task description', 'CronJobsForms'),
                    'placeholder' => self::getModule()->l('Update my currencies', 'CronJobsForms'),
                ];

                $form[0]['form']['input'][] = [
                    'type'  => 'free',
                    'name'  => 'task',
                    'label' => self::getModule()->l('Target link', 'CronJobsForms'),
                ];
            } else {
                $form[0]['form']['input'][] = [
                    'type'        => 'text',
                    'name'        => 'description',
                    'label'       => self::getModule()->l('Task description', 'CronJobsForms'),
                    'desc'        => self::getModule()->l('Enter a description for this task.', 'CronJobsForms'),
                    'placeholder' => self::getModule()->l('Update my currencies', 'CronJobsForms'),
                ];

                $form[0]['form']['input'][] = [
                    'type'        => 'text',
                    'name'        => 'task',
                    'label'       => self::getModule()->l('Target link', 'CronJobsForms'),
                    'desc'        => self::getModule()->l('Set the link of your cron task.', 'CronJobsForms'),
                    'placeholder' => $currenciesCronUrl,
                ];
            }
        } else {
            $form[0]['form']['input'][] = [
                'type'        => 'text',
                'name'        => 'description',
                'label'       => self::getModule()->l('Task description', 'CronJobsForms'),
                'desc'        => self::getModule()->l('Enter a description for this task.', 'CronJobsForms'),
                'placeholder' => self::getModule()->l('Update my currencies', 'CronJobsForms'),
            ];

            $form[0]['form']['input'][] = [
                'type'        => 'text',
                'name'        => 'task',
                'label'       => self::getModule()->l('Target link', 'CronJobsForms'),
                'desc'        => self::getModule()->l('Do not forget to use an absolute URL to make it valid! The link also has to be on the same domain as the shop.', 'CronJobsForms'),
                'placeholder' => $currenciesCronUrl,
            ];
        }

        $form[0]['form']['input'][] = [
            'type'    => 'select',
            'name'    => 'hour',
            'label'   => self::getModule()->l('Task frequency', 'CronJobsForms'),
            'desc'    => self::getModule()->l('At what time should this task be executed?', 'CronJobsForms'),
            'options' => [
                'query' => self::getHoursFormOptions(),
                'id'    => 'id', 'name' => 'name',
            ],
        ];
        $form[0]['form']['input'][] = [
            'type'    => 'select',
            'name'    => 'day',
            'desc'    => self::getModule()->l('On which day of the month should this task be executed?', 'CronJobsForms'),
            'options' => [
                'query' => self::getDaysFormOptions(),
                'id'    => 'id', 'name' => 'name',
            ],
        ];
        $form[0]['form']['input'][] = [
            'type'    => 'select',
            'name'    => 'month',
            'desc'    => self::getModule()->l('On what month should this task be executed?', 'CronJobsForms'),
            'options' => [
                'query' => self::getMonthsFormOptions(),
                'id'    => 'id', 'name' => 'name',
            ],
        ];
        $form[0]['form']['input'][] = [
            'type'    => 'select',
            'name'    => 'day_of_week',
            'desc'    => self::getModule()->l('On which day of the week should this task be executed?', 'CronJobsForms'),
            'options' => [
                'query' => self::getDaysofWeekFormOptions(),
                'id'    => 'id', 'name' => 'name',
            ],
        ];

        return $form;
    }

    /**
     * @return array
     */
    protected static function getHoursFormOptions()
    {
        $data = [['id' => '-1', 'name' => self::getModule()->l('Every hour', 'CronJobsForms')]];

        for ($hour = 0; $hour < 24; $hour += 1) {
            $data[] = ['id' => $hour, 'name' => date('H:i', mktime($hour, 0, 0, 0, 1))];
        }

        return $data;
    }

    /**
     * @return array
     */
    protected static function getDaysFormOptions()
    {
        $data = [['id' => '-1', 'name' => self::getModule()->l('Every day of the month', 'CronJobsForms')]];

        for ($day = 1; $day <= 31; $day += 1) {
            $data[] = ['id' => $day, 'name' => $day];
        }

        return $data;
    }

    /**
     * @return array
     */
    protected static function getMonthsFormOptions()
    {
        $data = [['id' => '-1', 'name' => self::getModule()->l('Every month', 'CronJobsForms')]];

        for ($month = 1; $month <= 12; $month += 1) {
            $data[] = ['id' => $month, 'name' => self::getModule()->l(date('F', mktime(0, 0, 0, $month, 1)))];
        }

        return $data;
    }

    /**
     * @return array
     */
    protected static function getDaysofWeekFormOptions()
    {
        $data = [['id' => '-1', 'name' => self::getModule()->l('Every day of the week', 'CronJobsForms')]];

        for ($day = 1; $day <= 7; $day += 1) {
            $data[] = ['id' => $day, 'name' => self::getModule()->l(date('l', strtotime('Sunday +'.$day.' days')))];
        }

        return $data;
    }

    /**
     * @return array
     */
    public static function getTasksList()
    {
        return [
            'description' => ['title' => self::getModule()->l('Task description', 'CronJobsForms'), 'type' => 'text', 'orderby' => false],
            'task'        => ['title' => self::getModule()->l('Target link', 'CronJobsForms'), 'type' => 'text', 'orderby' => false],
            'hour'        => ['title' => self::getModule()->l('Hour', 'CronJobsForms'), 'type' => 'text', 'orderby' => false],
            'day'         => ['title' => self::getModule()->l('Day', 'CronJobsForms'), 'type' => 'text', 'orderby' => false],
            'month'       => ['title' => self::getModule()->l('Month', 'CronJobsForms'), 'type' => 'text', 'orderby' => false],
            'day_of_week' => ['title' => self::getModule()->l('Day of week', 'CronJobsForms'), 'type' => 'text', 'orderby' => false],
            'updated_at'  => ['title' => self::getModule()->l('Last execution', 'CronJobsForms'), 'type' => 'text', 'orderby' => false],
            'one_shot'    => ['title' => self::getModule()->l('One shot', 'CronJobsForms'), 'active' => 'oneshot', 'type' => 'bool', 'align' => 'center'],
            'active'      => ['title' => self::getModule()->l('Active', 'CronJobsForms'), 'active' => 'status', 'type' => 'bool', 'align' => 'center', 'orderby' => false],
        ];
    }

    /**
     * @return array
     */
    public static function getNewJobFormValues()
    {
        return [
            'description' => \Tools::safeOutput(\Tools::getValue('description', null)),
            'task'        => \Tools::safeOutput(\Tools::getValue('task', null)),
            'hour'        => (int) \Tools::getValue('hour', -1),
            'day'         => (int) \Tools::getValue('day', -1),
            'month'       => (int) \Tools::getValue('month', -1),
            'day_of_week' => (int) \Tools::getValue('day_of_week', -1),
        ];
    }

    /**
     * @return array
     */
    public static function getUpdateJobFormValues()
    {
        $idShop = (int) \Context::getContext()->shop->id;
        $idShopGroup = (int) \Context::getContext()->shop->id_shop_group;

        $idCronjob = (int) \Tools::getValue('id_cronjob');
        $cron = \Db::getInstance()->getRow(
            'SELECT * FROM `'._DB_PREFIX_.self::getModule()->name.'`
			WHERE `id_cronjob` = \''.$idCronjob.'\'
			AND `id_shop` = \''.$idShop.'\' AND `id_shop_group` = \''.$idShopGroup.'\''
        );

        if ((bool) $cron['id_module'] == false) {
            $description = \Tools::safeOutput(\Tools::getValue('description', $cron['description']));
            $task = urldecode(\Tools::getValue('task', $cron['task']));
        } else {
            $moduleName = \Db::getInstance()->getValue('SELECT `name` FROM `'._DB_PREFIX_.'module` WHERE `id_module` = \''.(int) $cron['id_module'].'\'');
            $description = '<p class="form-control-static"><strong>'.\Tools::safeOutput(\Module::getModuleName($moduleName)).'</strong></p>';
            $task = '<p class="form-control-static"><strong>'.self::getModule()->l('Module - Hook', 'CronJobsForms').'</strong></p>';
        }

        return [
            'description' => $description,
            'task'        => $task,
            'hour'        => (int) \Tools::getValue('hour', $cron['hour']),
            'day'         => (int) \Tools::getValue('day', $cron['day']),
            'month'       => (int) \Tools::getValue('month', $cron['month']),
            'day_of_week' => (int) \Tools::getValue('day_of_week', $cron['day_of_week']),
        ];
    }

    /**
     * @return array|false|\mysqli_result|null|\PDOStatement|resource
     */
    public static function getTasksListValues()
    {
        $idShop = (int) \Context::getContext()->shop->id;
        $idShopGroup = (int) \Context::getContext()->shop->id_shop_group;

        self::getModule()->addNewModulesTasks();
        $crons = \Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.self::getModule()->name.'` WHERE `id_shop` = \''.$idShop.'\' AND `id_shop_group` = \''.$idShopGroup.'\'');

        foreach ($crons as $key => &$cron) {
            if (empty($cron['id_module']) == false) {
                $module = \Module::getInstanceById((int) $cron['id_module']);

                if ($module == false) {
                    \Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.self::getModule()->name.' WHERE `id_cronjob` = \''.(int) $cron['id_cronjob'].'\'');
                    unset($crons[$key]);
                    break;
                }

                $query = 'SELECT `name` FROM `'._DB_PREFIX_.'module` WHERE `id_module` = \''.(int) $cron['id_module'].'\'';
                $moduleName = \Db::getInstance()->getValue($query);

                $cron['description'] = \Tools::safeOutput(\Module::getModuleName($moduleName));
                $cron['task'] = self::getModule()->l('Module - Hook', 'CronJobsForms');
            } else {
                $cron['task'] = urldecode($cron['task']);
            }

            $cron['hour'] = ($cron['hour'] == -1) ? self::getModule()->l('Every hour', 'CronJobsForms') : date('H:i', mktime((int) $cron['hour'], 0, 0, 0, 1));
            $cron['day'] = ($cron['day'] == -1) ? self::getModule()->l('Every day', 'CronJobsForms') : (int) $cron['day'];
            $cron['month'] = ($cron['month'] == -1) ? self::getModule()->l('Every month', 'CronJobsForms') : self::getModule()->l(date('F', mktime(0, 0, 0, (int) $cron['month'], 1)));
            $cron['day_of_week'] = ($cron['day_of_week'] == -1) ? self::getModule()->l('Every day of the week', 'CronJobsForms') : self::getModule()->l(date('l', mktime(0, 0, 0, 0, (int) $cron['day_of_week'])));
            $cron['updated_at'] = ($cron['updated_at'] == 0) ? self::getModule()->l('Never', 'CronJobsForms') : date('Y-m-d H:i:s', strtotime($cron['updated_at']));
            $cron['one_shot'] = (bool) $cron['one_shot'];
            $cron['active'] = (bool) $cron['active'];
        }

        return $crons;
    }
}
