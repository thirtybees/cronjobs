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
        if (Tools::getValue('token') != Configuration::getGlobalValue(\CronJobs::EXECUTION_TOKEN)) {
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
        $query = 'SELECT * FROM '._DB_PREFIX_.$this->module->name.' WHERE `active` = 1 AND `id_module` IS NOT NULL';
        $crons = Db::getInstance()->executeS($query);

        if (is_array($crons) && (count($crons) > 0)) {
            foreach ($crons as &$cron) {
                $module = Module::getInstanceById((int) $cron['id_module']);

                if ($module == false) {
                    Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.$this->module->name.' WHERE `id_cronjob` = \''.(int) $cron['id_cronjob'].'\'');
                    break;
                } elseif ($this->shouldBeExecuted($cron) == true) {
                    Hook::exec('actionCronJob', [], $cron['id_module']);
                    $query = 'UPDATE '._DB_PREFIX_.$this->module->name.' SET `updated_at` = NOW(), `active` = IF (`one_shot` = TRUE, FALSE, `active`) WHERE `id_cronjob` = \''.$cron['id_cronjob'].'\'';
                    Db::getInstance()->execute($query);
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
        $hour = ($cron['hour'] == -1) ? date('H') : $cron['hour'];
        $day = ($cron['day'] == -1) ? date('d') : $cron['day'];
        $month = ($cron['month'] == -1) ? date('m') : $cron['month'];
        $dayOfWeek = ($cron['day_of_week'] == -1) ? date('D') : date('D', strtotime('Sunday +'.$cron['day_of_week'].' days'));

        $day = date('Y').'-'.str_pad($month, 2, '0', STR_PAD_LEFT).'-'.str_pad($day, 2, '0', STR_PAD_LEFT);
        $execution = $dayOfWeek.' '.$day.' '.str_pad($hour, 2, '0', STR_PAD_LEFT);
        $now = date('D Y-m-d H');

        return !(bool) strcmp($now, $execution);
    }

    /**
     * @return void
     */
    protected function runTasksCrons()
    {
        $query = 'SELECT * FROM '._DB_PREFIX_.$this->module->name.' WHERE `active` = 1 AND `id_module` IS NULL';
        $crons = Db::getInstance()->executeS($query);

        $guzzle = new \GuzzleHttp\Client([
            'timeout' => 10000000000,
        ]);

        if (is_array($crons) && (count($crons) > 0)) {
            foreach ($crons as &$cron) {
                if ($this->shouldBeExecuted($cron) == true) {
                    try {
                        $guzzle->get(urldecode($cron['task']), false);
                    } catch (Exception $e) {
                    }

                    $query = 'UPDATE '._DB_PREFIX_.$this->module->name.' SET `updated_at` = NOW(), `active` = IF (`one_shot` = TRUE, FALSE, `active`) WHERE `id_cronjob` = \''.$cron['id_cronjob'].'\'';
                    Db::getInstance()->execute($query);
                }
            }
        }
    }

}
