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

{extends file="helpers/form/form.tpl"}

{block name="defaultForm"}

	{if (isset($form_errors)) && (count($form_errors) > 0)}
		<div class="alert alert-danger">
			<h4>{l s='Error!' mod='cronjobs'}</h4>
			<ul class="list-unstyled">
				{foreach from=$form_errors item='message'}
					<li>{$message|escape:'htmlall':'UTF-8'}</li>
				{/foreach}
			</ul>
		</div>
	{/if}

	{if (isset($form_infos)) && (count($form_infos) > 0)}
		<div class="alert alert-warning">
			<h4>{l s='Warning!' mod='cronjobs'}</h4>
			<ul class="list-unstyled">
				{foreach from=$form_infos item='message'}
					<li>{$message|escape:'htmlall':'UTF-8'}</li>
				{/foreach}
			</ul>
		</div>
	{/if}

	{if (isset($form_successes)) && (count($form_successes) > 0)}
		<div class="alert alert-success">
			<h4>{l s='Success!' mod='cronjobs'}</h4>
			<ul class="list-unstyled">
				{foreach from=$form_successes item='message'}
					<li>{$message|escape:'htmlall':'UTF-8'}</li>
				{/foreach}
			</ul>
		</div>
	{/if}

	{$smarty.block.parent}
{/block}
