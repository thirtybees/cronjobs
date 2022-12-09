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

namespace CronJobsModule;

use Configuration;
use Context;
use CronJobs;
use Db;
use DbQuery;
use Module;
use PrestaShopDatabaseException;
use PrestaShopException;
use Tools;

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
    /** @var CronJobs $module */
    protected static $module;

    /**
     * @return CronJobs
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getModule()
    {
        if (!static::$module) {
            static::$module = Module::getInstanceByName('cronjobs');
        }

        return static::$module;
    }

    /**
     * @param string $title
     * @param bool   $update
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getJobForm($title = 'New cron task', $update = false)
    {
        $form = [
            [
                'form' => [
                    'legend' => [
                        'title' => static::getModule()->l($title),
                        'icon'  => 'icon-plus',
                    ],
                    'input'  => [],
                    'submit' => ['title' => static::getModule()->l('Save', 'CronJobsForms'), 'type' => 'submit', 'class' => 'btn btn-default pull-right'],
                ],
            ],
        ];

        $idShop = (int) Context::getContext()->shop->id;
        $idShopGroup = (int) Context::getContext()->shop->id_shop_group;

        $currenciesCronUrl = Tools::getShopDomain(true, true).__PS_BASE_URI__.basename(_PS_ADMIN_DIR_);
        $currenciesCronUrl .= '/cron_currency_rates.php?secure_key='.md5(_COOKIE_KEY_.Configuration::get('PS_SHOP_NAME'));

        if ($update && (\Tools::isSubmit('id_cronjob'))) {
            $idCronjob = (int) \Tools::getValue('id_cronjob');
            $idModule = (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                (new \DbQuery())
                    ->select('`id_module`')
                    ->from(\Cronjobs::TABLE)
                    ->where('`id_cronjob` = '.(int) $idCronjob)
                    ->where('`id_shop` = '.(int) $idShop)
                    ->where('`id_shop_group` = '.(int) $idShopGroup)
            );

            if ($idModule) {
                $form[0]['form']['input'][] = [
                    'type'        => 'free',
                    'name'        => 'description',
                    'label'       => static::getModule()->l('Task description', 'CronJobsForms'),
                    'placeholder' => static::getModule()->l('Update my currencies', 'CronJobsForms'),
                ];

                $form[0]['form']['input'][] = [
                    'type'  => 'free',
                    'name'  => 'task',
                    'label' => static::getModule()->l('Target link', 'CronJobsForms'),
                ];
            } else {
                $form[0]['form']['input'][] = [
                    'type'        => 'text',
                    'name'        => 'description',
                    'label'       => static::getModule()->l('Task description', 'CronJobsForms'),
                    'desc'        => static::getModule()->l('Enter a description for this task.', 'CronJobsForms'),
                    'placeholder' => static::getModule()->l('Update my currencies', 'CronJobsForms'),
                ];

                $form[0]['form']['input'][] = [
                    'type'        => 'text',
                    'name'        => 'task',
                    'label'       => static::getModule()->l('Target link', 'CronJobsForms'),
                    'desc'        => static::getModule()->l('Set the link of your cron task.', 'CronJobsForms'),
                    'placeholder' => $currenciesCronUrl,
                ];
            }
        } else {
            $form[0]['form']['input'][] = [
                'type'        => 'text',
                'name'        => 'description',
                'label'       => static::getModule()->l('Task description', 'CronJobsForms'),
                'desc'        => static::getModule()->l('Enter a description for this task.', 'CronJobsForms'),
                'placeholder' => static::getModule()->l('Update my currencies', 'CronJobsForms'),
            ];

            $form[0]['form']['input'][] = [
                'type'        => 'text',
                'name'        => 'task',
                'label'       => static::getModule()->l('Target link', 'CronJobsForms'),
                'desc'        => static::getModule()->l('Do not forget to use an absolute URL to make it valid! The link also has to be on the same domain as the shop.', 'CronJobsForms'),
                'placeholder' => $currenciesCronUrl,
            ];
        }

        $form[0]['form']['input'][] = [
            'type'    => 'select',
            'name'    => 'minute',
            'label'   => static::getModule()->l('Task frequency', 'CronJobsForms'),
            'desc'    => static::getModule()->l('At what minute should this task be executed?', 'CronJobsForms'),
            'options' => [
                'query' => static::getMinutesFormOptions(),
                'id'    => 'id', 'name' => 'name',
            ],
        ];
        $form[0]['form']['input'][] = [
            'type'    => 'select',
            'name'    => 'hour',
            'desc'    => static::getModule()->l('At what hour should this task be executed?', 'CronJobsForms'),
            'options' => [
                'query' => static::getHoursFormOptions(),
                'id'    => 'id', 'name' => 'name',
            ],
        ];
        $form[0]['form']['input'][] = [
            'type'    => 'select',
            'name'    => 'day',
            'desc'    => static::getModule()->l('On which day of the month should this task be executed?', 'CronJobsForms'),
            'options' => [
                'query' => static::getDaysFormOptions(),
                'id'    => 'id', 'name' => 'name',
            ],
        ];
        $form[0]['form']['input'][] = [
            'type'    => 'select',
            'name'    => 'month',
            'desc'    => static::getModule()->l('On what month should this task be executed?', 'CronJobsForms'),
            'options' => [
                'query' => static::getMonthsFormOptions(),
                'id'    => 'id', 'name' => 'name',
            ],
        ];
        $form[0]['form']['input'][] = [
            'type'    => 'select',
            'name'    => 'day_of_week',
            'desc'    => static::getModule()->l('On which day of the week should this task be executed?', 'CronJobsForms'),
            'options' => [
                'query' => static::getDaysofWeekFormOptions(),
                'id'    => 'id', 'name' => 'name',
            ],
        ];

        return $form;
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected static function getMinutesFormOptions()
    {
        $data = [['id' => '-1', 'name' => static::getModule()->l('Every minute', 'CronJobsForms')]];

        for ($minute = 0; $minute < 60; $minute += 1) {
            $data[] = ['id' => $minute, 'name' => date('H:i', mktime(0, $minute, 0, 0, 1))];
        }

        return $data;
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected static function getHoursFormOptions()
    {
        $data = [['id' => '-1', 'name' => static::getModule()->l('Every hour', 'CronJobsForms')]];

        for ($hour = 0; $hour < 24; $hour += 1) {
            $data[] = ['id' => $hour, 'name' => date('H:i', mktime($hour, 0, 0, 0, 1))];
        }

        return $data;
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected static function getDaysFormOptions()
    {
        $data = [['id' => '-1', 'name' => static::getModule()->l('Every day of the month', 'CronJobsForms')]];

        for ($day = 1; $day <= 31; $day += 1) {
            $data[] = ['id' => $day, 'name' => $day];
        }

        return $data;
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected static function getMonthsFormOptions()
    {
        $data = [['id' => '-1', 'name' => static::getModule()->l('Every month', 'CronJobsForms')]];

        for ($month = 1; $month <= 12; $month += 1) {
            $data[] = ['id' => $month, 'name' => static::getModule()->l(date('F', mktime(0, 0, 0, $month, 1)))];
        }

        return $data;
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected static function getDaysofWeekFormOptions()
    {
        $data = [['id' => '-1', 'name' => static::getModule()->l('Every day of the week', 'CronJobsForms')]];

        for ($day = 1; $day <= 7; $day += 1) {
            $data[] = ['id' => $day, 'name' => static::getModule()->l(date('l', strtotime('Sunday +'.$day.' days')))];
        }

        return $data;
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getTasksList()
    {
        return [
            'description' => ['title' => static::getModule()->l('Task description', 'CronJobsForms'), 'type' => 'text', 'orderby' => false],
            'task'        => ['title' => static::getModule()->l('Target link', 'CronJobsForms'),      'type' => 'text', 'orderby' => false],
            'minute'      => ['title' => static::getModule()->l('Minute', 'CronJobsForms'),           'type' => 'text', 'orderby' => false],
            'hour'        => ['title' => static::getModule()->l('Hour', 'CronJobsForms'),             'type' => 'text', 'orderby' => false],
            'day'         => ['title' => static::getModule()->l('Day', 'CronJobsForms'),              'type' => 'text', 'orderby' => false],
            'month'       => ['title' => static::getModule()->l('Month', 'CronJobsForms'),            'type' => 'text', 'orderby' => false],
            'day_of_week' => ['title' => static::getModule()->l('Day of week', 'CronJobsForms'),      'type' => 'text', 'orderby' => false],
            'updated_at'  => ['title' => static::getModule()->l('Last execution', 'CronJobsForms'),   'type' => 'text', 'orderby' => false],
            'one_shot'    => ['title' => static::getModule()->l('One shot', 'CronJobsForms'),         'active' => 'oneshot', 'type' => 'bool', 'align' => 'center'],
            'active'      => ['title' => static::getModule()->l('Active', 'CronJobsForms'),           'active' => 'status', 'type' => 'bool', 'align' => 'center', 'orderby' => false],
        ];
    }

    /**
     * @return array
     */
    public static function getNewJobFormValues()
    {
        return [
            'description' => Tools::safeOutput(Tools::getValue('description', null)),
            'task'        => Tools::safeOutput(Tools::getValue('task', null)),
            'minute'      => (int) Tools::getValue('minute', -1),
            'hour'        => (int) Tools::getValue('hour', -1),
            'day'         => (int) Tools::getValue('day', -1),
            'month'       => (int) Tools::getValue('month', -1),
            'day_of_week' => (int) Tools::getValue('day_of_week', -1),
        ];
    }

    /**
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getUpdateJobFormValues()
    {
        $idShop = (int) Context::getContext()->shop->id;
        $idShopGroup = (int) Context::getContext()->shop->id_shop_group;

        $idCronjob = (int) \Tools::getValue('id_cronjob');
        $cron = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            (new DbQuery())
                ->select('*')
                ->from(bqSQL(static::getModule()->name))
                ->where('`id_cronjob` = '.(int) $idCronjob)
                ->where('`id_shop` = '.(int) $idShop)
                ->where('`id_shop_group` = '.(int) $idShopGroup)
        );

        if (empty($cron['id_module'])) {
            $description = Tools::safeOutput(Tools::getValue('description', $cron['description']));
            $task = urldecode(Tools::getValue('task', $cron['task']));
        } else {
            $moduleName = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue((new DbQuery())->select('`name`')->from('module')->where('`id_module` = '.(int) $cron['id_module']));
            $description = '<p class="form-control-static"><strong>'.Tools::safeOutput(Module::getModuleName($moduleName)).'</strong></p>';
            $task = '<p class="form-control-static"><strong>'.static::getModule()->l('Module - Hook', 'CronJobsForms').'</strong></p>';
        }

        return [
            'description' => $description,
            'task'        => $task,
            'minute'      => (int) Tools::getValue('minute', $cron['minute']),
            'hour'        => (int) Tools::getValue('hour', $cron['hour']),
            'day'         => (int) Tools::getValue('day', $cron['day']),
            'month'       => (int) Tools::getValue('month', $cron['month']),
            'day_of_week' => (int) Tools::getValue('day_of_week', $cron['day_of_week']),
        ];
    }

    /**
     * @return array|bool|\PDOStatement
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getTasksListValues()
    {
        $idShop = (int) Context::getContext()->shop->id;
        $idShopGroup = (int) Context::getContext()->shop->id_shop_group;

        static::getModule()->addNewModulesTasks();
        $crons = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new DbQuery())
                ->select('*')
                ->from(bqSQL(static::getModule()->name))
                ->where('`id_shop` = '.(int) $idShop)
                ->where('`id_shop_group` = '.(int) $idShopGroup)
        );

        foreach ($crons as $key => &$cron) {
            if (!empty($cron['id_module'])) {
                $module = Module::getInstanceById((int) $cron['id_module']);
                if (!$module) {
                    Db::getInstance()->delete(\Cronjobs::TABLE, '`id_cronjob` = '.(int) $cron['id_cronjob']);
                    unset($crons[$key]);
                    break;
                }

                $moduleName = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                    (new DbQuery())
                        ->select('`name`')
                        ->from('module')
                        ->where('`id_module` = '.(int) $cron['id_module'])
                );

                $cron['description'] = Tools::safeOutput(Module::getModuleName($moduleName));
                $cron['task'] = static::getModule()->l('Module - Hook', 'CronJobsForms');
            } else {
                $cron['task'] = urldecode($cron['task']);
            }

            $cron['minute'] = ($cron['minute'] == -1) ? static::getModule()->l('Every minute', 'CronJobsForms') : date('H:i', mktime(0, (int) $cron['minute'], 0, 0, 1));
            $cron['hour'] = ($cron['hour'] == -1) ? static::getModule()->l('Every hour', 'CronJobsForms') : date('H:i', mktime((int) $cron['hour'], 0, 0, 0, 1));
            $cron['day'] = ($cron['day'] == -1) ? static::getModule()->l('Every day', 'CronJobsForms') : (int) $cron['day'];
            $cron['month'] = ($cron['month'] == -1) ? static::getModule()->l('Every month', 'CronJobsForms') : static::getModule()->l(date('F', mktime(0, 0, 0, (int) $cron['month'], 1)));
            $cron['day_of_week'] = ($cron['day_of_week'] == -1) ? static::getModule()->l('Every day of the week', 'CronJobsForms') : static::getModule()->l(date('l', mktime(0, 0, 0, 0, (int) $cron['day_of_week'] + 3)));
            $cron['updated_at'] = ($cron['updated_at'] == 0) ? static::getModule()->l('Never', 'CronJobsForms') : date('Y-m-d H:i:s', strtotime($cron['updated_at']));
            $cron['one_shot'] = (bool) $cron['one_shot'];
            $cron['active'] = (bool) $cron['active'];
        }

        return $crons;
    }
}
