{*
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
*}

<div class="panel">
	<h3>{l s='What does this module do?' mod='cronjobs'}</h3>
	<p>
		<img src="{$module_dir|escape:'htmlall':'UTF-8'}/logo.png" class="pull-left" id="cronjobs-logo" />
		{l s='Originally, cron is a Unix system tool that provides time-based job scheduling: you can create many cron jobs, which are then run periodically at fixed times, dates, or intervals.' mod='cronjobs'}
		<br/>
		{l s='This module provides you with a cron-like tool: you can create jobs which will call a given set of secure URLs to your thirty bees store, thus triggering updates and other automated tasks.' mod='cronjobs'}
	</p>

	<div class="alert alert-info">
		<p>{$curl_info|escape:'htmlall':'UTF-8'}</p>
		<br />
		<ul class="list-unstyled">
			<li><code>{$cronjob_freq|escape:'htmlall':'UTF-8'}</code></li>
		</ul>
	</div>
</div>
