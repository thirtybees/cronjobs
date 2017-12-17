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

if (!defined('_TB_VERSION_')) {
    if (php_sapi_name() !== 'cli') {
        exit;
    } else {
        $first = true;
        foreach ($argv as $arg) {
            if ($first) {
                $first = false;
                continue;
            }

            $arg = substr($arg, 2); // --
            $e = explode('=', $arg);
            if (count($e) == 2) {
                $_GET[$e[0]] = $e[1];
            } else {
                $_GET[$e[0]] = true;
            }
        }
        $_GET['module'] = 'cronjobs';
        $_GET['fc'] = 'module';
        $_GET['controller'] = 'cron';

        require_once __DIR__.'/../../../../config/config.inc.php';
        require_once __DIR__.'/../../cronjobs.php';
    }
}

/**
 * Class AdminCronJobsController
 */
class CronJobscronModuleFrontController extends ModuleFrontController
{
    /** @var CronJobs $module */
    public $module;

    /**
     * AdminCronJobsController constructor.
     */
    public function __construct()
    {
        try {
            if (Tools::getValue('token') != Configuration::getGlobalValue(\CronJobs::EXECUTION_TOKEN)) {
                die('Invalid token');
            }
        } catch (PrestaShopException $e) {
            die('Invalid token');
        }

        parent::__construct();

        $this->postProcess();

        die;
    }

    /**
     * @return void
     */
    public function postProcess()
    {
        $this->module->sendCallback();

        ob_start();

        $this->runModulesCrons();
        $this->runTasksCrons();

        ob_end_clean();
    }

    /**
     * @return void
     */
    protected function runModulesCrons()
    {
        try {
            $crons = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                (new DbQuery())
                    ->select('*')
                    ->from(\Cronjobs::TABLE)
                    ->where('`active` = 1')
                    ->where('`id_module` IS NOT NULL')
            );
        } catch (PrestaShopException $e) {
            $crons = false;
        }

        if (is_array($crons) && (count($crons) > 0)) {
            foreach ($crons as &$cron) {
                try {
                    $module = Module::getInstanceById((int) $cron['id_module']);
                } catch (PrestaShopException $e) {
                    $module = false;
                }

                if (!$module) {
                    try {
                        Db::getInstance()->delete(\Cronjobs::TABLE, '`id_cronjob` = '.(int) $cron['id_cronjob']);
                    } catch (PrestaShopException $e) {
                        Logger::AddLog("Cronjobs module error: {$e->getMessage()}");
                    }
                    break;
                } elseif ($this->shouldBeExecuted($cron)) {
                    Hook::exec('actionCronJob', [], $cron['id_module']);
                    try {
                        Db::getInstance()->update(
                            \Cronjobs::TABLE,
                            [
                                'updated_at' => ['type' => 'sql', 'value' => 'NOW()'],
                                'active'     => ['type' => 'sql', 'value' => 'IF(`one_shot` = TRUE, FALSE, `active`)'],
                            ],
                            '`id_cronjob` = '.(int) $cron['id_cronjob']
                        );
                    } catch (PrestaShopException $e) {
                        Logger::addLog("Cronjobs module error: {$e->getMessage()}");
                    }
                }
            }
        }
    }

    /**
     * @param array $cron
     *
     * @return bool
     */
    protected function shouldBeExecuted($cron)
    {
        $minute = ($cron['minute'] == -1) ? date('i') : $cron['minute'];
        $hour = ($cron['hour'] == -1) ? date('H') : $cron['hour'];
        $day = ($cron['day'] == -1) ? date('d') : $cron['day'];
        $month = ($cron['month'] == -1) ? date('m') : $cron['month'];
        $dayOfWeek = ($cron['day_of_week'] == -1) ? date('D') : date('D', strtotime('Sunday +'.$cron['day_of_week'].' days'));

        $day = date('Y').'-'.str_pad($month, 2, '0', STR_PAD_LEFT).'-'.str_pad($day, 2, '0', STR_PAD_LEFT);
        $execution = $dayOfWeek.' '.$day.' '.str_pad($hour, 2, '0', STR_PAD_LEFT).':'.str_pad($minute, 2, '0', STR_PAD_LEFT);
        $now = date('D Y-m-d H:i');

        return !(bool) strcmp($now, $execution);
    }

    /**
     * @return void
     */
    protected function runTasksCrons()
    {
        try {
            $crons = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                (new DbQuery())
                    ->select('*')
                    ->from(\Cronjobs::TABLE)
                    ->where('`active` = 1')
                    ->where('`id_module` IS NULL')
            );
        } catch (PrestaShopException $e) {
            $crons = false;
        }

        $guzzle = new \GuzzleHttp\Client([
            'timeout' => 10000000,
        ]);

        if (is_array($crons) && (count($crons) > 0)) {
            foreach ($crons as &$cron) {
                if ($this->shouldBeExecuted($cron)) {
                    try {
                        $guzzle->get(urldecode($cron['task']));
                    } catch (Exception $e) {
                    }
                    try {
                        Db::getInstance()->update(
                            \Cronjobs::TABLE,
                            [
                                'updated_at' => ['type' => 'sql', 'value' => 'IF (`one_shot` = TRUE, FALSE, `active`)'],
                            ],
                            '`id_cronjob` = '.(int) $cron['id_cronjob']
                        );
                    } catch (PrestaShopException $e) {
                    }
                }
            }
        }
    }
}

if (php_sapi_name() === 'cli') {
    new CronJobscronModuleFrontController();
}
