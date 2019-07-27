{*
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
*}

<div class="panel">
    <h3>{l s='What does this module do?' mod='cronjobs'}</h3>
    <p>
        <img src="{$module_dir|escape:'htmlall':'UTF-8'}/logo.png" class="pull-left" id="cronjobs-logo" style="padding-right:10px"/>
        {l s='Originally, cron is a Unix system tool that provides time-based job scheduling: you can create many cron jobs, which are then run periodically at fixed times, dates, or intervals.' mod='cronjobs'}
        <br/>
        {l s='This module provides you with a cron-like tool: you can create jobs which will call a given set of secure URLs to your thirty bees store, thus triggering updates and other automated tasks.' mod='cronjobs'}
    </p>

    {if $is_running}
        <div class="alert alert-success">
            {l s='Cron is up and running, last executed [1]%s[/1] (%s)' mod='cronjobs' sprintf=[$last_executed_text, $last_executed_date] tags=['<strong>']}
        </div>
    {else}
        <div class="alert alert-danger">
            {l s='Cron is not set up correctly. Please follow set up instructions' mod='cronjobs' }
        </div>
    {/if}

    <div class="alert alert-info">
        <p>{$curl_info|escape:'htmlall':'UTF-8'}</p>
        <br/>
        <ul class="list-unstyled">
            <li><code>{$cronjob_freq_php|escape:'htmlall':'UTF-8'}</code></li>
            <li>{l s='or' mod='cronjobs'}</li>
            <li><code>{$cronjob_freq_cli|escape:'htmlall':'UTF-8'}</code></li>
        </ul>
    </div>

</div>
