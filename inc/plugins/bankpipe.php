<?php
/**
 * BankPipe
 * 
 * A fully functional payment system for MyBB.
 *
 * @package BankPipe
 * @license Copyrighted ©
 * @author  Shade <shad3-@outlook.com>
 * @version beta 3
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

function bankpipe_info()
{
	bankpipe_plugin_edit();

	if (bankpipe_is_installed()) {

		global $PL, $mybb;

		$PL or require_once PLUGINLIBRARY;

		if (bankpipe_apply_attachment_edits() !== true) {
			$apply = $PL->url_append('index.php',
				[
					'module' => 'config-plugins',
					'bankpipe' => 'apply',
					'my_post_key' => $mybb->post_code,
				]
			);
	        $description = "<br><br>Core edits missing. <a href='{$apply}'>Apply core edits.</a>";
		}
		else {
			$apply = $PL->url_append('index.php',
				[
					'module' => 'config-plugins',
					'bankpipe' => 'revert',
					'my_post_key' => $mybb->post_code,
				]
			);
			$description = "<br><br>Core edits in place. <a href='{$revert}'>Revert core edits.</a>";
		}

	}

	return [
		'name'          =>  'BankPipe',
		'description'   =>  'A fully functional payment system for MyBB.' . $description,
		'website'       =>  'https://www.mybboost.com/forum-bankpipe',
		'author'        =>  'Shade',
		'version'       =>  'beta 3',
		'compatibility' =>  '18*',
	];
}

function bankpipe_is_installed()
{
	global $cache;

    $installed = $cache->read("shade_plugins");
    if ($installed['BankPipe']) {
        return true;
    }

    return false;
}

function bankpipe_install()
{
	global $cache, $PL, $lang, $db;

	bankpipe_load_lang();

	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->bankpipe_pluginlibrary_missing, "error");
		admin_redirect("index.php?module=config-plugins");
	}

	$PL or require_once PLUGINLIBRARY;

	$settingsToAdd = [
		'client_id' => [
			'title' => $lang->setting_bankpipe_client_id,
			'description' => $lang->setting_bankpipe_client_id_desc,
			'optionscode' => 'text',
			'value' => ''
		],
		'client_secret' => [
			'title' => $lang->setting_bankpipe_client_secret_subject,
			'description' => $lang->setting_bankpipe_client_secret_desc,
			'optionscode' => 'text',
			'value' => ''
		],
		'sandbox' => [
			'title' => $lang->setting_bankpipe_sandbox,
			'description' => $lang->setting_bankpipe_sandbox_desc,
			'value' => 1
		],
		'subscription_payee' => [
			'title' => $lang->setting_bankpipe_subscription_payee,
			'description' => $lang->setting_bankpipe_subscription_payee_desc,
			'optionscode' => 'text',
			'value' => ''
		],
		'currency' => [
			'title' => $lang->setting_bankpipe_currency,
			'description' => $lang->setting_bankpipe_currency_desc,
			'optionscode' => 'text',
			'value' => 'EUR'
		],
		'usergroups_view' => [
			'title' => $lang->setting_bankpipe_usergroups_view,
			'description' => $lang->setting_bankpipe_usergroups_view_desc,
			'optionscode' => 'text',
			'value' => ''
		],
		'forums' => [
			'title' => $lang->setting_bankpipe_forums,
			'description' => $lang->setting_bankpipe_forums_desc,
			'optionscode' => 'text',
			'value' => ''
		],
		'third_party' => [
			'title' => $lang->setting_bankpipe_third_party,
			'description' => $lang->setting_bankpipe_third_party_desc,
			'value' => 0
		],
		'usergroups_manage' => [
			'title' => $lang->setting_bankpipe_usergroups_manage,
			'description' => $lang->setting_bankpipe_usergroups_manage_desc,
			'optionscode' => 'text',
			'value' => ''
		],
		'notification_uid' => [
			'title' => $lang->setting_bankpipe_notification_uid,
			'description' => $lang->setting_bankpipe_notification_uid_desc,
			'optionscode' => 'text',
			'value' => ''
		],
		'admin_notification' => [
			'title' => $lang->setting_bankpipe_admin_notification,
			'description' => $lang->setting_bankpipe_admin_notification_desc,
			'optionscode' => 'text',
			'value' => ''
		],
		'admin_notification_method' => [
			'title' => $lang->setting_bankpipe_admin_notification_method,
			'description' => $lang->setting_bankpipe_admin_notification_method_desc,
			'optionscode' => "select
pm=Private message
email=Email",
			'value' => 'pm'
		],
		'cart_mode' => [
			'title' => $lang->setting_bankpipe_cart_mode,
			'description' => $lang->setting_bankpipe_cart_mode_desc,
			'value' => 1
		],
		'required_fields' => [
			'title' => $lang->setting_bankpipe_required_fields,
			'description' => $lang->setting_bankpipe_required_fields_desc,
			'optionscode' => 'text',
			'value' => ''
		]

	];

	$PL->settings('bankpipe', $lang->setting_group_bankpipe, $lang->setting_group_bankpipe_desc, $settingsToAdd);

	bankpipe_apply_attachment_edits(true);

	// Add tables
	if (!$db->table_exists('bankpipe_items')) {

		$collation = $db->build_create_table_collation();

		$db->write_query("
		CREATE TABLE " . TABLE_PREFIX . "bankpipe_items (
			bid int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			uid int(10) NOT NULL,
			price decimal(6,2) NOT NULL,
			gid int(10) NOT NULL DEFAULT '0',
			aid int(10) NOT NULL DEFAULT '0',
			name varchar(128) DEFAULT NULL,
			description varchar(128) DEFAULT NULL,
			htmldescription text,
			discount smallint(3) NOT NULL,
			expires int(10) UNSIGNED NOT NULL DEFAULT '0',
			primarygroup tinyint(1) NOT NULL DEFAULT '1',
			expirygid int(5) NOT NULL DEFAULT '0',
			KEY aid (aid)
        ) ENGINE=MyISAM{$collation};
		");

	}
	if (!$db->table_exists('bankpipe_log')) {

		$collation = $db->build_create_table_collation();

		$db->write_query("
		CREATE TABLE " . TABLE_PREFIX . "bankpipe_log (
			lid int(8) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			type varchar(32) NOT NULL DEFAULT '',
			bid int(10) NOT NULL DEFAULT '0',
			uid int(10) NOT NULL DEFAULT '0',
			pid int(10) NOT NULL DEFAULT '0',
			message text,
			date int(10) NOT NULL DEFAULT '0'
        ) ENGINE=MyISAM{$collation};
		");

	}
	if (!$db->table_exists('bankpipe_downloadlogs')) {

		$collation = $db->build_create_table_collation();

		$db->write_query("
		CREATE TABLE " . TABLE_PREFIX . "bankpipe_downloadlogs (
			lid int(8) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			pid int(10) NOT NULL DEFAULT '0',
			uid int(10) NOT NULL DEFAULT '0',
			aid int(10) NOT NULL DEFAULT '0',
			title text,
			date int(10) NOT NULL DEFAULT '0'
        ) ENGINE=MyISAM{$collation};
		");

	}
	if (!$db->table_exists('bankpipe_notifications')) {

		$collation = $db->build_create_table_collation();

		$db->write_query("
		CREATE TABLE " . TABLE_PREFIX . "bankpipe_notifications (
			nid int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			title text,
			description text,
			daysbefore int(5) NOT NULL,
			method varchar(5) NOT NULL DEFAULT ''
        ) ENGINE=MyISAM{$collation};
		");

	}
	if (!$db->table_exists('bankpipe_payments')) {

		$collation = $db->build_create_table_collation();

		$db->write_query("
		CREATE TABLE " . TABLE_PREFIX . "bankpipe_payments (
			pid int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			uid int(10) NOT NULL DEFAULT '0',
			payee int(10) NOT NULL DEFAULT '0',
			payment_id varchar(32) NOT NULL DEFAULT '',
			sale text,
			refund text,
			email text,
			price decimal(6,2) NOT NULL,
			payer_id varchar(32) NOT NULL DEFAULT '',
			country varchar(8) NOT NULL DEFAULT '',
			invoice varchar(32) NOT NULL DEFAULT '',
			bid int(10) NOT NULL DEFAULT '0',
			date int(10) NOT NULL DEFAULT '0',
			expires int(10) UNSIGNED NOT NULL DEFAULT '0',
			oldgid int(5) NOT NULL DEFAULT '0',
			newgid int(5) NOT NULL DEFAULT '0',
			sentnotification int(5) NOT NULL DEFAULT '0',
			active tinyint(1) NOT NULL DEFAULT '1',
			KEY uid (uid)
        ) ENGINE=MyISAM{$collation};
		");

	}
	if (!$db->table_exists('bankpipe_discounts')) {

		$collation = $db->build_create_table_collation();

		$db->write_query("
		CREATE TABLE " . TABLE_PREFIX . "bankpipe_discounts (
			did int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			bids text,
			uids text,
			gids text,
			code text,
			value int(10) NOT NULL DEFAULT '0',
			type tinyint(1) NOT NULL DEFAULT '0',
			date int(10) NOT NULL DEFAULT '0',
			expires int(10) UNSIGNED NOT NULL DEFAULT '0',
			stackable tinyint(1) NOT NULL DEFAULT '0'
        ) ENGINE=MyISAM{$collation};
		");

	}

	$db->add_column('users', 'payee', "text");
	$db->add_column('forumpermissions', 'candownloadpaidattachments', "tinyint(1) DEFAULT 0");
	$db->add_column('usergroups', 'candownloadpaidattachments', "tinyint(1) DEFAULT 0");

	// Add templates
	$dir       = new DirectoryIterator(dirname(__FILE__) . '/BankPipe/templates');
	$templates = [];
	foreach ($dir as $file) {
		if (!$file->isDot() and !$file->isDir() and pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'html') {
			$templates[$file->getBasename('.html')] = file_get_contents($file->getPathName());
		}
	}

	$PL->templates('bankpipe', 'BankPipe', $templates);

	// Add the plugin to cache
    $info = bankpipe_info();
    $shade_plugins = $cache->read('shade_plugins');
    $shade_plugins[$info['name']] = [
        'title' => $info['name'],
        'version' => $info['version']
    ];
    $cache->update('shade_plugins', $shade_plugins);
}

function bankpipe_uninstall()
{
	global $cache, $PL, $db;

	$PL or require_once PLUGINLIBRARY;

	bankpipe_revert_attachment_edits(true);

	$PL->settings_delete('bankpipe');

	// Drop tables
	$db->drop_table('bankpipe_log');
	$db->drop_table('bankpipe_notifications');
	$db->drop_table('bankpipe_items');
	$db->drop_table('bankpipe_payments');

	if ($db->field_exists('payee', 'users')) {
		$db->drop_column('users', 'payee');
	}

	if ($db->field_exists('candownloadpaidattachments', 'forumpermissions')) {
		$db->drop_column('forumpermissions', 'candownloadpaidattachments');
	}

	if ($db->field_exists('candownloadpaidattachments', 'usergroups')) {
		$db->drop_column('usergroups', 'candownloadpaidattachments');
	}

	// Drop templates
	$PL->templates_delete('bankpipe');

	// Remove the plugin from cache
	$info = bankpipe_info();
    $shade_plugins = $cache->read('shade_plugins');
    unset($shade_plugins[$info['name']]);
    $cache->update('shade_plugins', $shade_plugins);
}

function bankpipe_activate()
{
	global $cache, $db;

	// Create new task to check updates to users
	if (!$db->fetch_array($db->simple_select('tasks', '*', "file = 'bankpipe'"))) {
		$new_task = [
			"title" => 'BankPipe Subscriptions Cleanup',
			"description" => 'Checks for expired subscriptions and sends notifications.',
			"file" => 'bankpipe',
			"minute" => '30,59',
			"hour" => '*',
			"day" => '*',
			"month" => '*',
			"weekday" => '*',
			"enabled" => 1,
			"logging" => 1,
			"locked" => 0
		];

		require_once MYBB_ROOT . "inc/functions_task.php";

		$new_task['nextrun'] = fetch_next_run($new_task);
		$tid = $db->insert_query("tasks", $new_task);
		$cache->update_tasks();
	}
}

function bankpipe_deactivate()
{
	global $cache, $db;

	if ($db->fetch_array($db->simple_select('tasks', '*', "file = 'bankpipe'"))) {
		$db->delete_query('tasks', "file = 'bankpipe'");
	}

	$cache->update_tasks();
}

function bankpipe_plugin_edit()
{
    global $mybb;

    if ($mybb->input['my_post_key'] == $mybb->post_code) {

        if ($mybb->input['bankpipe'] == 'apply') {
            if (bankpipe_apply_attachment_edits(true) === true) {
                flash_message('Successfully applied core edits.', 'success');
                admin_redirect('index.php?module=config-plugins');
            }
            else {
                flash_message('There was an error applying core edits.', 'error');
                admin_redirect('index.php?module=config-plugins');
            }

        }

        if ($mybb->input['bankpipe'] == 'revert') {

            if (bankpipe_revert_attachment_edits(true) === true) {
                flash_message('Successfully reverted core edits.', 'success');
                admin_redirect('index.php?module=config-plugins');
            }
            else {
                flash_message('There was an error reverting core edits.', 'error');
                admin_redirect('index.php?module=config-plugins');
            }

        }

    }
}

function bankpipe_apply_attachment_edits($apply = false)
{
	global $PL;

	$PL or require_once PLUGINLIBRARY;

	$errors = [];

	$edits = [
		[
			'search' => 'if($attachment[\'visible\'])',
			'before' => [
				'global $paidAttachment;',
				'$args = [\'attachment\' => $attachment, \'post\' => $post];',
				'$GLOBALS[\'plugins\']->run_hooks(\'postbit_attachment\', $args);'
			]
		]
	];

	$result = $PL->edit_core('bankpipe', 'inc/functions_post.php', $edits, $apply);

	if ($result !== true) {
		$errors[] = $result;
	}

	$edits = [
		[
			'search' => 'if($forumpermissions[\'canview\'] == 0 || $forumpermissions[\'canviewthreads\'] == 0 || (isset($forumpermissions[\'canonlyviewownthreads\']) && $forumpermissions[\'canonlyviewownthreads\'] != 0 && $thread[\'uid\'] != $mybb->user[\'uid\']) || ($forumpermissions[\'candlattachments\'] == 0 && !$mybb->input[\'thumbnail\']))',
			'replace' => [
				'if($forumpermissions[\'canview\'] == 0 || $forumpermissions[\'canviewthreads\'] == 0 || (isset($forumpermissions[\'canonlyviewownthreads\']) && $forumpermissions[\'canonlyviewownthreads\'] != 0 && $thread[\'uid\'] != $mybb->user[\'uid\']) || ($forumpermissions[\'candlattachments\'] == 0 && !$mybb->input[\'thumbnail\'] && !$mybb->input[\'skip\']))'
			]
		]
	];

	$result = $PL->edit_core('bankpipe', 'attachment.php', $edits, $apply);

	if ($result !== true) {
		$errors[] = $result;
	}

	if (count($errors) >= 1) {
		return $errors;
	}
	else {
		return true;
	}
}

function bankpipe_revert_attachment_edits($apply = false)
{
	global $PL;

	$PL or require_once PLUGINLIBRARY;

	$PL->edit_core('bankpipe', 'inc/functions_post.php', [], $apply);
	return $PL->edit_core('bankpipe', 'attachment.php', [], $apply);
}

global $mybb;

if ($mybb->settings['bankpipe_client_id'] and $mybb->settings['bankpipe_client_secret']) {

	$plugins->add_hook('global_start', 'bankpipe_global_start');
	$plugins->add_hook('global_intermediate', 'bankpipe_header_link');

	// UserCP
	$plugins->add_hook('usercp_menu', 'bankpipe_nav');
	$plugins->add_hook('usercp_start', 'bankpipe_panel');

	// Attachments
	$plugins->add_hook('postbit_attachment', 'bankpipe_attachments_postbit');
	$plugins->add_hook('editpost_action_start', 'bankpipe_edit_attachments');
	$plugins->add_hook('newthread_start', 'bankpipe_edit_attachments');
	$plugins->add_hook('newreply_start', 'bankpipe_edit_attachments');

	$plugins->add_hook('editpost_do_editpost_start', 'bankpipe_save_paid_item');
	$plugins->add_hook('newreply_do_newreply_start', 'bankpipe_save_paid_item');
	$plugins->add_hook('newthread_do_newthread_start', 'bankpipe_save_paid_item');
	$plugins->add_hook('pre_output_page', 'bankpipe_pre_output_page');

	$plugins->add_hook('attachment_start', 'bankpipe_attachment_start');
	$plugins->add_hook('attachment_end', 'bankpipe_attachment');
	$plugins->add_hook('remove_attachment_do_delete', 'bankpipe_delete_attachment');
	
	// Xmlhttp
	$plugins->add_hook('xmlhttp', 'bankpipe_xmlhttp_get_items');

	// Profile
	$plugins->add_hook('member_profile_end', 'bankpipe_profile');

}

// AdminCP
if (defined('IN_ADMINCP')) {

	// Advertising
	$plugins->add_hook("admin_load", "bankpipe_ad");

	// Update
	$plugins->add_hook("admin_page_output_header", "bankpipe_update");

	// Module
	$plugins->add_hook("admin_config_menu", "bankpipe_admin_config_menu");
	$plugins->add_hook("admin_config_action_handler", "bankpipe_admin_config_action_handler");

	// Replace text inputs to select boxes dinamically
	$plugins->add_hook("admin_config_settings_change", "bankpipe_settings_saver");
	$plugins->add_hook("admin_formcontainer_output_row", "bankpipe_settings_replacer");

	// Permissions
	$plugins->add_hook("admin_forum_management_permissions", "bankpipe_load_lang");
	$plugins->add_hook("admin_forum_management_permission_groups", "bankpipe_forumpermissions");
	$plugins->add_hook("admin_user_groups_edit_graph_tabs", "bankpipe_usergroups_tab");
	$plugins->add_hook("admin_user_groups_edit_graph", "bankpipe_edit_graph");
	$plugins->add_hook("admin_user_groups_edit_commit", "bankpipe_update_group_permissions");

}

// Advertising
function bankpipe_ad()
{
	global $cache, $mybb;

	$plugins = $cache->read('shade_plugins');
	if (!in_array($mybb->user['uid'], (array) $plugins['BankPipe']['ad_shown'])) {

		flash_message('Thank you for purchasing BankPipe! You might also be interested in other great plugins on <a href="https://www.mybboost.com">MyBBoost</a>, where you can also get support for BankPipe itself.<br /><small>This message will not be shown again to you.</small>', 'success');

		$plugins['BankPipe']['ad_shown'][] = $mybb->user['uid'];
		$cache->update('shade_plugins', $plugins);

	}

}

function bankpipe_load_lang()
{
	global $lang;

	if (!$lang->bankpipe) {
		$lang->load('bankpipe');
	}
}

function bankpipe_global_start()
{
	global $mybb, $lang, $templatelist;

	if (!$lang->bankpipe_currency) {

		$currencies = [
			'AUD' => '&#36;',
			'BRL' => 'R&#36;',
			'CAD' => '&#36;',
			'CZK' => 'Kč',
			'DKK' => 'kr.',
			'EUR' => '&#8364;',
			'HKD' => '&#36;',
			'HUF' => 'Ft',
			'INR' => 'Rupees',
			'ILS' => '&#8362;',
			'JPY' => '&#165;',
			'MYR' => 'RM',
			'MXN' => '&#36;',
			'TWD' => 'NT&#36;',
			'NZD' => '&#36;',
			'NOK' => 'kr',
			'PHP' => '&#8369;',
			'PLN' => 'zł',
			'GBP' => '&#163;',
			'RUB' => '&#8381;',
			'SGD' => '&#36;',
			'SEK' => 'kr',
			'CHF' => 'CHF',
			'THB' => '&#3647;',
			'USD' => '&#36;'
		];

		$lang->bankpipe_currency = $currencies[$mybb->settings['bankpipe_currency']];

	}

	if ($templatelist) {
		$templatelist = explode(',', $templatelist);
	}
	else {
		$templatelist = [];
	}

	if (THIS_SCRIPT == 'usercp.php') {

		if ($mybb->input['action'] == 'bankpipe') {
			$templatelist[] = 'bankpipe_script';
			$templatelist[] = 'bankpipe_subscriptions_subscription_price_discounted';
			$templatelist[] = 'bankpipe_subscriptions_subscription_price';
			$templatelist[] = 'bankpipe_subscriptions_subscription_purchased';
			$templatelist[] = 'bankpipe_subscriptions_subscription';
			$templatelist[] = 'bankpipe_subscriptions_no_subscriptions';
			$templatelist[] = 'bankpipe_subscriptions';
			$templatelist[] = 'bankpipe_discounts';
			$templatelist[] = 'bankpipe_discounts_code';
		}

		if ($mybb->input['action'] == 'bankpipe-purchases') {
			$templatelist[] = 'bankpipe_purchases_purchase_refunded';
			$templatelist[] = 'bankpipe_purchases_purchase_expired';
			$templatelist[] = 'bankpipe_purchases_purchase_inactive';
			$templatelist[] = 'bankpipe_purchases_purchase';
			$templatelist[] = 'bankpipe_purchases_no_purchases';
			$templatelist[] = 'bankpipe_purchases';
		}

		if ($mybb->input['action'] == 'bankpipe-manage') {
			$templatelist[] = 'bankpipe_manage_items_item';
			$templatelist[] = 'bankpipe_manage_items';
			$templatelist[] = 'bankpipe_manage_items_no_items';
			$templatelist[] = 'bankpipe_manage';
			$templatelist[] = 'attachment_icon';
		}

		if ($mybb->input['action'] == 'bankpipe-cart') {
			$templatelist[] = 'bankpipe_script';
			$templatelist[] = 'bankpipe_discounts';
			$templatelist[] = 'bankpipe_discounts_code';
			$templatelist[] = 'bankpipe_cart_item';
			$templatelist[] = 'bankpipe_cart_item_discounts';
			$templatelist[] = 'bankpipe_cart_no_items';
			$templatelist[] = 'bankpipe_cart_total';
			$templatelist[] = 'bankpipe_cart';
		}

		$templatelist[] = 'bankpipe_nav';
		$templatelist[] = 'bankpipe_nav_manage';
		$templatelist[] = 'bankpipe_nav_cart';

	}

	if (THIS_SCRIPT == 'showthread.php') {

		$templatelist[] = 'bankpipe_script';
		$templatelist[] = 'bankpipe_postbit_attachments_attachment';
		$templatelist[] = 'bankpipe_postbit_attachments_attachment_cart';
		$templatelist[] = 'bankpipe_postbit_attachments_attachment_not_allowed';

	}

	if (THIS_SCRIPT == 'member.php' and $mybb->input['action'] == 'profile') {

		$templatelist[] = 'bankpipe_profile_purchases';
		$templatelist[] = 'bankpipe_profile_no_purchases';
		$templatelist[] = 'bankpipe_purchases_purchase_refunded';
		$templatelist[] = 'bankpipe_purchases_purchase_expired';
		$templatelist[] = 'bankpipe_purchases_purchase_inactive';
		$templatelist[] = 'bankpipe_purchases_purchase';
		$templatelist[] = 'bankpipe_purchases_no_purchases';
		$templatelist[] = 'bankpipe_purchases';

	}
	
	$templatelist[] = 'bankpipe_header_cart';

	if (in_array(THIS_SCRIPT, ['newthread.php', 'newreply.php', 'editpost.php'])) {
		$templatelist[] = 'bankpipe_attachment_options';
	}

	$templatelist = implode(',', array_filter($templatelist));
}

function bankpipe_header_link()
{
	global $mybb, $templates, $lang, $cart;
	
	if (!$mybb->settings['bankpipe_cart_mode']) {
		return false;
	}

	bankpipe_load_lang();
	
	$cartItems = count(bankpipe_read_cookie('items'));

	eval("\$cart = \"".$templates->get("bankpipe_header_cart")."\";");
}

function bankpipe_nav()
{
	global $mybb, $usercpmenu, $templates, $lang;

	bankpipe_load_lang();

	if (bankpipe_check_permissions(['can_manage'])) {
		eval("\$manage = \"".$templates->get("bankpipe_nav_manage")."\";");
	}

	if ($mybb->settings['bankpipe_cart_mode']) {

		$cartItems = count(bankpipe_read_cookie('items'));

		eval("\$cart = \"".$templates->get("bankpipe_nav_cart")."\";");

	}

	eval("\$usercpmenu .= \"".$templates->get("bankpipe_nav")."\";");
}

function bankpipe_panel()
{
	global $usercpnav, $usercpmenu, $mybb, $db, $lang, $templates, $header, $headerinclude, $footer, $theme;

	if (!in_array($mybb->input['action'], ['bankpipe', 'bankpipe-cart', 'bankpipe-purchases', 'bankpipe-manage', 'bankpipe-discounts'])) {
		return false;
	}

	bankpipe_load_lang();

	if ($mybb->input['action'] == 'bankpipe-discounts') {

		verify_post_check($mybb->input['my_post_key']);
			
		$existingDiscounts = bankpipe_read_cookie('discounts');
			
		$return = ($mybb->input['return-to']) ? (string) $mybb->input['return-to'] : 'bankpipe';
		
		if ($mybb->input['discount']) {
				
			$errors = [];
			$newCode = (string) $mybb->input['code'];
			
			$query = $db->simple_select('bankpipe_discounts', '*', "code = '" . $db->escape_string($newCode) . "'", ['limit' => 1]);
			$discount = $db->fetch_array($query);
			
			// Already there buddy! We got ya
			if ($discount['did'] and in_array($discount['did'], $existingDiscounts)) {
				$errors[] = $lang->bankpipe_error_code_already_applied;
			}
			
			if (!$discount['did']) {
				$errors[] = $lang->bankpipe_error_code_not_found;
			}
			else if ($discount['expires'] and $discount['expires'] < TIME_NOW) {
				$errors[] = $lang->bankpipe_error_code_expired;
			}
			
			// Check for permissions
			if (!$errors and !bankpipe_check_discount_permissions($discount)) {
				$errors[] = $lang->bankpipe_error_code_not_allowed;
			}
			
			// Is THIS CODE stackable?
			if (!$errors and !$discount['stackable'] and count($existingDiscounts) > 0) {
				$errors[] = $lang->bankpipe_error_code_not_allowed_stackable;
			}
			
			// Is THE CURRENT APPLIED CODE stackable? Account for the first value, as if there are many, they are all stackable by design
			if (!$errors and count($existingDiscounts) > 0) {
				
				$query = $db->simple_select('bankpipe_discounts', 'did, stackable', "did = '" . (int) reset($existingDiscounts) . "'");
				$existingCode = $db->fetch_array($query);
				
				if ($existingCode['did'] and !$existingCode['stackable']) {
					$errors[] = $lang->bankpipe_error_other_codes_not_allowed_stackable;
				}
				
			}
			
			if (!$errors) {
				
				$existingDiscounts[] = $discount['did'];
				
				bankpipe_set_cookie('discounts', $existingDiscounts);
				
				redirect('usercp.php?action=' . $return, $lang->bankpipe_discount_applied_desc, $lang->bankpipe_discount_applied);
				
			}
			else {
				$mybb->input['action'] = $return;
			}
			
		}
		else if ($mybb->input['delete']) {

			$discountId = (int) $mybb->input['did'];
			
			if (($key = array_search($discountId, $existingDiscounts)) !== false) {
				unset($existingDiscounts[$key]);
			}
			
			if ($existingDiscounts) {
				bankpipe_set_cookie('discounts', $existingDiscounts);
			}
			else {
				my_unsetcookie('bankpipe-discounts');
			}
			
			redirect('usercp.php?action=' . $return, $lang->bankpipe_discounts_removed_desc, $lang->bankpipe_discounts_removed);
			
		}

	}

	add_breadcrumb($lang->bankpipe_nav);

	if ($mybb->input['action'] == 'bankpipe') {

		add_breadcrumb($lang->bankpipe_nav_subscriptions);
		
		if ($errors) {
			$errors = inline_error($errors);
		}
		
		$appliedDiscounts = '';
		
		if ($mybb->cookies['bankpipe-discounts']) {
			
			$existingDiscounts = bankpipe_read_cookie('discounts');
			
			if ($existingDiscounts) {
				
				$search = implode(',', array_map('intval', $existingDiscounts));
				
				$query = $db->simple_select('bankpipe_discounts', '*', 'did IN (' . $search . ')');
				while ($discount = $db->fetch_array($query)) {
					
					$discount['suffix'] = ($discount['type'] == 1) ? '%' : ' ' . $mybb->settings['bankpipe_currency'];
			
					eval("\$appliedDiscounts .= \"".$templates->get("bankpipe_discounts_code")."\";");

					$discounts[] = $discount;
					
				}
				
			}
		
		}

		$environment = ($mybb->settings['bankpipe_sandbox']) ? 'sandbox' : 'production';

		eval("\$script = \"".$templates->get("bankpipe_script")."\";");
		eval("\$discountArea = \"".$templates->get("bankpipe_discounts")."\";");

		$highestPurchased = $subs = $purchases = [];

		$query = $db->simple_select('bankpipe_payments', 'bid', 'active = 1 AND uid = ' . (int) $mybb->user['uid']);
		while ($pid = $db->fetch_field($query, 'bid')) {
			$purchases[$pid] = true;
		}

		$query = $db->simple_select('bankpipe_items', '*', 'gid <> 0', ['order_by' => 'price ASC']);
		while ($subscription = $db->fetch_array($query)) {

			$subscription['price'] += 0;

			// Determine highest purchased subscription
			if ($purchases[$subscription['bid']]) {
				$highestPurchased = $subscription;
			}

			$subs[] = $subscription;

		}

		if ($subs) {

			foreach ($subs as $subscription) {
				
				$skip = false;
				
				if ($discounts) {
				
					foreach ($discounts as $discount) {
					
						if ($discount['bids']) {
							
							$bids = explode(',', $discount['bids']);
							
							if (!in_array($subscription['bid'], $bids)) {
								$skip = true;
							}
							
						}
					
					}
				
				}

				// Discounts?
				if (($subscription['discount'] and $highestPurchased['price'] and $subscription['price'] >= $highestPurchased['price']) or ($discounts and !$skip)) {
					
					$subscription['discounted'] = $subscription['price'];
					
					if ($subscription['discount']) {
						$subscription['discounted'] = ($subscription['discounted'] - ($highestPurchased['price'] * $subscription['discount'] / 100));
					}

					if ($discounts) {
						
						foreach ($discounts as $discount) {
					
							// Percentage
							if ($discount['type'] == 1) {
								$subscription['discounted'] = $subscription['discounted'] - (($subscription['discounted'] / 100) * $discount['value']);
							}
							// Absolute value
							else {
								$subscription['discounted'] = $subscription['discounted'] - $discount['value'];
							}
						
						}
							
						if ($subscription['discounted'] <= 0) {
							$subscription['discounted'] = 0;
						}

					}

					eval("\$price = \"".$templates->get("bankpipe_subscriptions_subscription_price_discounted")."\";");

				}
				else {
					eval("\$price = \"".$templates->get("bankpipe_subscriptions_subscription_price")."\";");
				}

				// Bought or not?
				if ($purchases[$subscription['bid']] and $highestPurchased['bid'] == $subscription['bid']) {
					eval("\$subscriptions .= \"".$templates->get("bankpipe_subscriptions_subscription_purchased")."\";");
				}
				else {
					eval("\$subscriptions .= \"".$templates->get("bankpipe_subscriptions_subscription")."\";");
				}

			}

		}
		else {
			eval("\$subscriptions = \"".$templates->get("bankpipe_subscriptions_no_subscription")."\";");
		}

		eval("\$page = \"".$templates->get("bankpipe_subscriptions")."\";");
		output_page($page);

	}
	
	if ($mybb->input['action'] == 'bankpipe-cart') {
		
		$existingItems = bankpipe_read_cookie('items');
		
		if ($mybb->input['add']) {
			
			$errors = [];
			
			$aid = (int) $mybb->input['aid'];
			
			// Get first item out of existing items
			$firstItem = (int) reset($existingItems);
			if ($firstItem and $aid) {
				
				$infos = [];
				
				// Payee check – since PayPal can't handle multiple payees at once, we must block every attempt to stack
				// items from different payees. The first item is enough, as if there are more, this check will prevent
				// stacked items to be of a different payee.
				$query = $db->query('
					SELECT a.aid, u.payee
					FROM ' . TABLE_PREFIX . 'attachments a
					LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid = a.uid)
					WHERE a.aid IN (\'' . $aid .  "','" . $firstItem . '\')
				');
				while ($info = $db->fetch_array($query)) {
					$infos[$info['aid']] = $info['payee'];
				}
				
				if ($infos[$firstItem] and $infos[$firstItem] != $infos[$aid]) {
					$errors[] = $lang->bankpipe_cart_payee_different;
				}
				
			}
			
			if (!$errors) {
			
				if (!in_array($aid, $existingItems)) {
					$existingItems[] = $aid;
				}
				
				bankpipe_set_cookie('items', $existingItems);
			
				bankpipe_redirect([
					'url' => 'usercp.php?action=bankpipe-cart',
					'title' => $lang->bankpipe_cart_item_added,
					'message' => $lang->bankpipe_cart_item_added_desc,
					'action' => 'add'
				]);
			
			}
			else {
				
				if ($mybb->input['ajax']) {
					bankpipe_ajax([
						'error' => reset($errors)
					]);
				}
				else {
					error(implode("\n", $errors));
				}
				
			}
			
		}
		
		if ($mybb->input['remove']) {
			
			$aid = (int) $mybb->input['aid'];
			
			if (($key = array_search($aid, $existingItems)) !== false) {
				unset($existingItems[$key]);
			}

			if (!array_filter($existingItems)) {
				my_unsetcookie('bankpipe-items');
			}
			else {
				bankpipe_set_cookie('items', $existingItems);
			}
			
			bankpipe_redirect([
				'url' => 'usercp.php?action=bankpipe-cart',
				'title' => $lang->bankpipe_cart_item_removed,
				'message' => $lang->bankpipe_cart_item_removed_desc,
				'action' => 'remove'
			]);
			
		}

		add_breadcrumb($lang->bankpipe_nav_cart);
		
		$errors = ($errors) ? inline_error($errors) : '';
		
		$items = $appliedDiscounts = $script = $discountArea = '';
		
		// Items
		if ($existingItems) {
		
			// Discounts
			if ($mybb->cookies['bankpipe-discounts']) {
				
				$existingDiscounts = bankpipe_read_cookie('discounts');
				
				if ($existingDiscounts) {
					
					$search = implode(',', array_map('intval', $existingDiscounts));
					
					$query = $db->simple_select('bankpipe_discounts', '*', 'did IN (' . $search . ')');
					while ($discount = $db->fetch_array($query)) {
						
						$discount['suffix'] = ($discount['type'] == 1) ? '%' : ' ' . $mybb->settings['bankpipe_currency'];
				
						eval("\$appliedDiscounts .= \"".$templates->get("bankpipe_discounts_code")."\";");
	
						$discounts[] = $discount;
						
					}
					
				}
			
			}
	
			$environment = ($mybb->settings['bankpipe_sandbox']) ? 'sandbox' : 'production';
		
			eval("\$script = \"".$templates->get("bankpipe_script")."\";");
			eval("\$discountArea = \"".$templates->get("bankpipe_discounts")."\";");
			
			$search = array_map('intval', $existingItems);
			$paidItems = bankpipe_get_paid_attachments($search);
			
			$tot = 0;
			if ($paidItems) {
			
				$query = $db->simple_select('attachments', 'aid, pid', 'aid IN (' . implode(',', $search) . ')');
				while ($attach = $db->fetch_array($query)) {
					$pids[$attach['aid']] = $attach['pid'];
				}
				
				if ($paidItems['bid']) {
					$paidItems = [$paidItems];
				}
				
				foreach ($paidItems as $item) {
			
					if (!$item['aid']) {
						continue;
					}
					
					$itemDiscounts = '';
					$discountsList = [];
					$originalPrice = $item['price'];
					
					// Apply discounts
					if ($discounts) {
	
						foreach ($discounts as $k => $discount) {
							
							if (!bankpipe_check_discount_permissions($discount, $item)) {
								continue;
							}
							
							$discountsList[$k] = '– ' . $discount['value'];
							
							// Apply
							if ($discount['type'] == 1) {
								
								$discountsList[$k] .= '%';
								$item['price'] = $item['price'] - ($item['price'] * $discount['value'] / 100);
								
							}
							else {
								
								$item['price'] = $item['price'] - $discount['value'];
								
							}
							
						}
						
						if ($discountsList) {
							
							foreach ($discountsList as $singleDiscount) {
								eval("\$itemDiscounts .= \"".$templates->get("bankpipe_cart_item_discounts")."\";");
							}
						
						}
					
					}
					
					$tot += $item['price'];
	
					$item['postlink'] = get_post_link($pids[$item['aid']]);
	
					eval("\$items .= \"".$templates->get("bankpipe_cart_item")."\";");
	
				}
			
			}
			
			if ($tot) {
				eval("\$total = \"".$templates->get("bankpipe_cart_total")."\";");
			}
			
		}
	
		if (!$items) {
			eval("\$items = \"".$templates->get("bankpipe_cart_no_items")."\";");
		}

		eval("\$page = \"".$templates->get("bankpipe_cart")."\";");
		output_page($page);

	}

	if ($mybb->input['action'] == 'bankpipe-purchases') {

		add_breadcrumb($lang->bankpipe_nav_purchases);

		$_purchases = bankpipe_get_user_purchased_attachments($mybb->user['uid']);

		$purchases = $inactive = $refunded = $expired = '';

		if ($_purchases) {

			foreach ($_purchases as $purchase) {

				if ($purchase['refund']) {
					eval("\$refunded .= \"".$templates->get("bankpipe_purchases_purchase_refunded")."\";");
				}
				else if ($purchase['expires'] and $purchase['expires'] < TIME_NOW and !$purchase['active']) {
					eval("\$expired .= \"".$templates->get("bankpipe_purchases_purchase_expired")."\";");
				}
				else if (!$purchase['active']) {
					eval("\$inactive .= \"".$templates->get("bankpipe_purchases_purchase_inactive")."\";");
				}
				else {
					eval("\$purchases .= \"".$templates->get("bankpipe_purchases_purchase")."\";");
				}

			}

		}
		else {
			eval("\$purchases = \"".$templates->get("bankpipe_purchases_no_purchases")."\";");
		}

		eval("\$page = \"".$templates->get("bankpipe_purchases")."\";");
		output_page($page);

	}

	if ($mybb->input['action'] == 'bankpipe-manage' and bankpipe_check_permissions(['can_manage'])) {

		$uid = (int) $mybb->user['uid'];

		$payee = $mybb->user['payee'];

		if ($mybb->request_method == 'post') {

			// Delete all items if no payee is specified
			if ($payee and !$mybb->input['payee']) {
				$db->delete_query('bankpipe_items', 'gid = 0 AND uid = ' . $uid);
			}

			// Add payee
			if ($mybb->input['payee'] != $payee) {
				$db->update_query('users', ['payee' => $db->escape_string($mybb->input['payee'])], 'uid = ' . $uid);
			}

			if ($mybb->input['items']) {

				$items = (array) $mybb->input['items'];
				$olditems = (array) $mybb->input['olditems'];

				foreach ($items as $bid => $price) {

					$price = filter_var(str_replace(',', '.', $price), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
					$oldprice = filter_var(str_replace(',', '.', $olditems[$bid]), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

					if (!$price or $price <= 0 or $price == $oldprice) {
						continue;
					}

					$update = [
						'price' => $price
					];

					$db->update_query('bankpipe_items', $update, 'bid = ' . (int) $bid . ' AND uid = ' . $uid);

				}

			}

			redirect('usercp.php?action=bankpipe-manage', $lang->bankpipe_success_settings_edited_desc, $lang->bankpipe_success_settings_edited);

		}

		add_breadcrumb($lang->bankpipe_nav_purchases);

		$query = $db->simple_select('bankpipe_items', 'COUNT(bid) AS total', 'uid = ' . $uid);
		$totalItems = $db->fetch_field($query, 'total');

		$perpage = 20;

		if ($mybb->input['page'] > 0) {

			$page = $mybb->input['page'];
			$start = ($page-1) * $perpage;
			$pages = $totalItems / $perpage;
			$pages = ceil($pages);

			if ($page > $pages || $page <= 0) {
				$start = 0;
				$page = 1;
			}

		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($totalItems, $perpage, $page, 'usercp.php?action=bankpipe-manage');

		$items = '';

		$query = $db->query('
			SELECT i.*, a.*, p.subject
			FROM ' . TABLE_PREFIX . 'bankpipe_items i
			LEFT JOIN ' . TABLE_PREFIX . 'attachments a ON (i.aid = a.aid)
			LEFT JOIN ' . TABLE_PREFIX . 'posts p ON (p.pid = a.pid)
			WHERE i.uid = ' . $uid . '
			ORDER BY a.dateuploaded DESC
			LIMIT ' . $start . ', ' . $perpage
		);
		if ($db->num_rows($query) > 0) {

			$orphaned = [];

			while ($item = $db->fetch_array($query)) {

				if (!$item['aid']) {
					$orphaned[] = (int) $item['bid'];
					continue;
				}

				$item['postlink'] = get_post_link($item['pid']);
				$size = get_friendly_size($item['filesize']);
				$ext = get_extension($item['filename']);
				$icon = get_attachment_icon($ext);

				eval("\$items .= \"".$templates->get("bankpipe_manage_items_item")."\";");

			}

			// Delete orphaned items
			if ($orphaned) {
				$db->delete_query('bankpipe_items', 'bid IN (' . implode(',', $orphaned) . ')');
			}

			eval("\$items = \"".$templates->get("bankpipe_manage_items")."\";");

		}
		else {
			eval("\$items = \"".$templates->get("bankpipe_manage_items_no_items")."\";");
		}

		eval("\$page = \"".$templates->get("bankpipe_manage")."\";");
		output_page($page);

	}
}

function bankpipe_edit_attachments()
{
	global $templates, $db, $mybb, $forumpermissions, $attachedfile, $pid, $tid, $fid;

	if (!bankpipe_check_permissions('', $fid) or !$mybb->user['payee']) {
		return false;
	}

	$aids = [];

	if (!empty($mybb->input['paidattachs']['update']) and $mybb->input['newattachment'] and $attachedfile and $attachedfile['aid'] and !$attachedfile['error'] and $mybb->settings['enableattachments'] == 1 and $mybb->request_method == 'post') {

		$update = (int) $mybb->input['paidattachs']['update'];

		if ($update > 0 and $update != $attachedfile['aid']) {

			// Get this attachment
			$attach = bankpipe_get_paid_attachments($update);

			if ($attach) {

				// Update this item aid using the new attachment in order to retain purchases over this item
				$db->update_query('bankpipe_items', ['aid' => (int) $attachedfile['aid']], 'bid = ' . (int) $attach['bid']);

				// Remove the old attachment
				remove_attachment($pid, "", $update);
			}

		}
	}

	$posthash = htmlspecialchars_uni($mybb->get_input('posthash'));

	// Get a list of attachment ids – will be useful to gather all attachments at once afterwards
	if ($mybb->settings['enableattachments'] != 0 and $forumpermissions['canpostattachments'] != 0) {

		if ($mybb->input['action'] == 'editdraft'
			or ($mybb->input['action'] == 'newthread' and $tid and $pid)
			or (($mybb->input['action'] == 'newreply' or !$mybb->input['action']) and $pid)) {
			$attachwhere = "pid='$pid'";
		}
		else {
			$attachwhere = "posthash='" . $db->escape_string($posthash) . "'";
		}

		$query = $db->simple_select("attachments", "aid", $attachwhere);
		while ($aid = $db->fetch_field($query, 'aid')) {
			$aids[] = $aid;
		}

	}

	// Cache this post attachments
	bankpipe_get_paid_attachments($aids);

	control_object($templates, '
		function get($title, $eslashes=1, $htmlcomments=1) {

			if (in_array($title, ["post_attachments_attachment"])) {
				bankpipe_attachment_options();
			}

			return parent::get($title, $eslashes, $htmlcomments);

		}
	');
}

function bankpipe_attachment_options()
{
	global $attachment, $paidOptions, $templates, $attachcolspan, $mybb, $post_errors, $lang;

	bankpipe_load_lang();

	$attachment['paid'] = bankpipe_get_paid_attachments($attachment['aid']);

	if (($mybb->input['previewpost'] or $post_errors or $mybb->input['newattachment'] or $mybb->input['updateattachment']) and $mybb->input['paidattachs'][$attachment['aid']]) {
		$attachment['paid'] = $mybb->input['paidattachs'][$attachment['aid']];
	}

	$attachcolspan = 2;

	eval("\$paidOptions = \"".$templates->get("bankpipe_attachment_options")."\";");
}

function bankpipe_attachments_postbit($data)
{
	global $templates, $currentAttachment, $attachcache, $mybb, $lang;

	bankpipe_load_lang();

	$search = [];

	// Is there an attachment cache already built?
	foreach ((array) $attachcache as $pid => $attachments) {

		foreach ($attachments as $aid => $att) {
			$search[] = (int) $aid;
		}

	};

	// Cache this thread attachments
	bankpipe_get_paid_attachments($search);

	$currentAttachment = $data['attachment'];
	if (!bankpipe_check_permissions(['can_view', 'allowed_forum'], $data['post']['fid'])) {
		$currentAttachment['not_allowed'] = true;
	}

	control_object($templates, '
		function get($title, $eslashes=1, $htmlcomments=1) {

			$title = bankpipe_choose_attachment_template($title);

			return parent::get($title, $eslashes, $htmlcomments);

		}
	');
}

function bankpipe_choose_attachment_template($title)
{
	if (!in_array($title, ['postbit_attachments_attachment'])) {
		return $title;
	}

	global $currentAttachment, $paidAttachment, $db, $mybb, $showPayments, $lang, $forumpermissions;

	if (!$showPayments) {
		$showPayments = false;
	}

	if ($forumpermissions['candownloadpaidattachments']) {
		return $title;
	}

	$paidAttachment = bankpipe_get_paid_attachments($currentAttachment['aid']);

	// This attachment is paid
	if ($paidAttachment['aid']) {

		if ($currentAttachment['not_allowed']) {
			return 'bankpipe_' . $title . '_not_allowed';
		}

		// This attachment has not been unlocked yet
		if (!$paidAttachment['payment_id'] and $mybb->user['uid'] != $paidAttachment['itemuid']) {
			
			$showPayments = true;
			
			if ($mybb->settings['bankpipe_cart_mode']) {
				
				$existingItems = bankpipe_read_cookie('items');
				
				if (in_array($paidAttachment['aid'], $existingItems)) {
					return 'bankpipe_' . $title . '_cart_added';
				}
				else {
					return 'bankpipe_' . $title . '_cart';
				}
				
			}
			else {
				return 'bankpipe_' . $title;
			}
			
			
		}

	}

	return $title;
}

function bankpipe_pre_output_page(&$content)
{
	global $showPayments, $templates, $mybb, $fid, $lang;

	if (THIS_SCRIPT != 'showthread.php' or !$fid or !bankpipe_check_permissions(['can_view', 'allowed_forum'], $fid)) {
		return $content;
	}
	
	bankpipe_load_lang();

	$environment = ($mybb->settings['bankpipe_sandbox']) ? 'sandbox' : 'production';

	if ($showPayments) {

		eval("\$payments = \"".$templates->get("bankpipe_script")."\";");

		$content = str_replace('</head>', $payments . '</head>', $content);

	}

	return $content;
}

function bankpipe_profile()
{
	global $memprofile, $mybb, $db, $lang, $templates, $theme;

	if ($mybb->usergroup['cancp']) {

		$purchases = $inactive = $refunded = $expired = '';

		$_purchases = bankpipe_get_user_purchased_attachments($memprofile['uid']);

		if ($_purchases) {

			foreach ($_purchases as $purchase) {

				if ($purchase['refund']) {
					eval("\$refunded .= \"".$templates->get("bankpipe_purchases_purchase_refunded")."\";");
				}
				else if ($purchase['expires'] and $purchase['expires'] < TIME_NOW and !$purchase['active']) {
					eval("\$expired .= \"".$templates->get("bankpipe_purchases_purchase_expired")."\";");
				}
				else if (!$purchase['active']) {
					eval("\$inactive .= \"".$templates->get("bankpipe_purchases_purchase_inactive")."\";");
				}
				else {
					eval("\$purchases .= \"".$templates->get("bankpipe_purchases_purchase")."\";");
				}

			}

		}
		else {
			eval("\$purchases = \"".$templates->get("bankpipe_profile_no_purchases")."\";");
		}

		eval("\$memprofile['purchases'] = \"".$templates->get("bankpipe_profile_purchases")."\";");

	}

}

function bankpipe_attachment_start()
{
	global $mybb, $attachment;

	if ($mybb->user['uid'] == 0) {
		return false;
	}

	$paidAttach = bankpipe_get_paid_attachments($attachment['aid']);

	if ($paidAttach['aid'] == $attachment['aid']) {
		$mybb->input['skip'] = true;
	}
}

function bankpipe_attachment()
{
	global $mybb, $attachment, $forumpermissions, $db;

	$paidAttach = bankpipe_get_paid_attachments($attachment['aid']);
	$paid = ($paidAttach['aid'] == $attachment['aid']);

	if (!$forumpermissions['candownloadpaidattachments'] and $paid) {

		// This attachment has not been unlocked yet
		if (!$paidAttach['payment_id'] and $mybb->user['uid'] != $paidAttach['itemuid']) {

			// Revert the download count update
			if (!isset($mybb->input['thumbnail'])) {
				$db->update_query("attachments", ['downloads' => $attachment['downloads']-1], "aid='{$attachment['aid']}'");
			}

			header('Location: ' . get_post_link($attachment['pid']));
			exit;

		}

	}

	// Log this download
	if ($paid and $mybb->user['uid'] != $paidAttach['itemuid']) {

		$log = [
			'aid' => (int) $attachment['aid'],
			'uid' => (int) $mybb->user['uid'],
			'title' => $db->escape_string($attachment['filename']),
			'date' => TIME_NOW
		];

		// If he can download paid attachments, he has purchased a subscription or he's in an allowed group. -1 is a special mark for this
		if ($forumpermissions['candownloadpaidattachments']) {
			$log['pid'] = -1;
		}
		else {
			$log['pid'] = (int) $paidAttach['pid'];
		}

		$db->insert_query('bankpipe_downloadlogs', $log);

	}

}

function bankpipe_delete_attachment($attachment)
{
	if ($attachment['aid']) {
		$GLOBALS['db']->delete_query('bankpipe_items', 'aid = ' . (int) $attachment['aid']);
	}

	return $attachment;
}

function bankpipe_check_permissions($type = '', $fid = '')
{
	global $mybb, $forumpermissions;

	// Disable for guests
	if ($mybb->user['uid'] == 0) {
		return false;
	}

	$type = (!$type) ? ['can_view', 'can_manage', 'allowed_forum'] : $type;
	$type = (!is_array($type)) ? [$type] : $type;

	$permissions = [
		'can_view' => array_filter(explode(',', $mybb->settings['bankpipe_usergroups_view'])),
		'can_manage' => array_filter(explode(',', $mybb->settings['bankpipe_usergroups_manage'])),
		'allowed_forum' => array_filter(explode(',', $mybb->settings['bankpipe_forums']))
	];

	// Check if available in this forum
	if ($fid and !empty($permissions['allowed_forum']) and in_array('allowed_forum', $type) and !in_array($fid, $permissions['allowed_forum'])) {
		return false;
	}

	if ($forumpermissions and $forumpermissions['candownloadpaidattachments']) {
		return true;
	}

	unset($type['allowed_forum']);

	// Not allowed if the main settings is disabled
	if (in_array('can_manage', $type) and !$mybb->settings['bankpipe_third_party'] and !$mybb->usergroup['cancp']) {
		return false;
	}

	// Check if available for this user's usergroup
	$usergroups = array_unique(array_merge([$mybb->user['usergroup']], (array) explode(',', $mybb->user['additionalgroups'])));

	foreach ($type as $permission) {

		if (!empty($permissions[$permission]) and !array_intersect($usergroups, $permissions[$permission])) {
			return false;
		}

	}

	return true;
}

// PROCESS ROUTINES
function bankpipe_get_user_purchased_attachments($uid = 0)
{
	global $mybb, $db;

	if (!$uid) {
		return false;
	}

	$uid = (int) $uid;

	$purchases = [];

	$query = $db->query('
		SELECT i.*, p.price as realprice, p.uid AS payeruid, p.payment_id, p.date, p.refund, p.expires, p.active, a.*, po.subject
		FROM ' . TABLE_PREFIX . 'bankpipe_payments p
		LEFT JOIN ' . TABLE_PREFIX . 'bankpipe_items i ON (p.bid = i.bid)
		LEFT JOIN ' . TABLE_PREFIX . 'attachments a ON (i.aid = a.aid)
		LEFT JOIN ' . TABLE_PREFIX . 'posts po ON (a.pid = po.pid)
		WHERE i.gid = 0 AND p.uid = ' . $uid . '
		ORDER BY p.date DESC
	');

	if ($db->num_rows($query) > 0) {

		while ($purchase = $db->fetch_array($query)) {

			$purchase['subject'] = htmlspecialchars_uni($purchase['subject']);

			$purchase['size'] = get_friendly_size($purchase['filesize']);
			$purchase['icon'] = get_attachment_icon(get_extension($purchase['filename']));
			$purchase['filename'] = htmlspecialchars_uni($purchase['filename']);

			$purchase['date'] = my_date('relative', $purchase['date']);

			$purchase['price'] += 0;
			$purchase['realprice'] += 0;

			$purchases[$purchase['bid']] = $purchase;

		}

	}

	return $purchases;
}

function bankpipe_get_items($bids = [], $uid = '')
{
	global $mybb, $db;

	$bids = (array) $bids;
	$uid = (int) $uid;

	if (!$bids) {
		return ['error' => 'No bids provided'];
	}

	$extra = ($uid) ? ' AND uid = ' . $uid : '';

	$items = [];

	$query = $db->simple_select('bankpipe_items', '*', 'bid IN (' . implode(',', $bids) . ')' . $extra);
	while ($item = $db->fetch_array($query)) {
		$items[$item['bid']] = $item;
	}

	foreach ($bids as $bid) {

		if (!$items[$bid]) {
			$items[$bid] = [
				'error' => 'Invalid item'
			];
		}

	}

	return $items;
}

function bankpipe_xmlhttp_get_items()
{
	global $mybb, $db;
	
	if (!in_array($mybb->input['action'], ['bankpipe_get_items', 'bankpipe_get_users'])) {
		return false;
	}
	
	header("Content-type: application/json; charset={$charset}");
	
	$data = [];
	
	if ($mybb->input['action'] == 'bankpipe_get_items') {
	
		$query = $db->simple_select('bankpipe_items', 'bid, name', "name LIKE '%" . $db->escape_string_like($mybb->input['query']) . "%'", ['limit' => 15]);
		while ($item = $db->fetch_array($query)) {
			$data[] = [
				'id' => $item['bid'],
				'text' => $item['name']
			];
		}
	
	}
	
	if ($mybb->input['action'] == 'bankpipe_get_users') {
		
		$query = $db->simple_select('users', 'uid, username', "username LIKE '%" . $db->escape_string_like($mybb->input['query']) . "%'", ['limit' => 15]);
		while ($user = $db->fetch_array($query)) {
			$data[] = [
				'id' => $user['uid'],
				'text' => $user['username']
			];
		}
		
	}
	
	echo json_encode($data);
	exit;
}

function bankpipe_get_paid_attachments($aids)
{
	static $paidattachs = [];

	if (!is_array($aids)) {

		if (!is_numeric($aids)) {
			return false;
		}

		$aids = [(int) $aids];

	}

	$search = [];

	foreach ($aids as $aid) {

		if (!isset($paidattachs[$aid])) {
			$search[] = $aid;
			$paidattachs[$aid] = []; // Fill the cache with this attachment, even if it's void
		}

	}

	if ($search) {

		global $mybb, $db;

		$bids = [];

		// Get items
		$query = $db->simple_select('bankpipe_items', '*, uid AS itemuid', 'aid IN (' . implode(',', $search) . ')');
		while ($item = $db->fetch_array($query)) {
			$paidattachs[$item['aid']] = $item;
			$bids[] = $item['bid'];
		}

		// Get purchases
		if ($bids) {

			$query = $db->simple_select('bankpipe_payments', '*', 'active = 1 AND uid = ' . (int) $mybb->user['uid'] . ' AND bid IN (' . implode(',', $bids) . ')');
			while ($purchase = $db->fetch_array($query)) {

				$key = array_search($purchase['bid'], array_column($paidattachs, 'bid', 'aid'));

				if ($key !== false) {
					$paidattachs[$key] = array_merge($paidattachs[$key], $purchase);
				}

			}

		}

	}

	if (count($aids) == 1) {
		return $paidattachs[reset($aids)];
	}
	else {
		return $paidattachs;
	}
}

function bankpipe_save_paid_item()
{
	global $mybb, $db, $attachfile;

	if (!$mybb->settings['bankpipe_third_party']) {
		return false;
	}

	if ($mybb->input['paidattachs'] and is_array($mybb->input['paidattachs'])) {

		$insert = [];

		foreach ($mybb->input['paidattachs'] as $aid => $att) {

			if (!empty($att['price'])) {

				$aid = (int) $aid;

				$insert[$aid] = [
					'uid' => $mybb->user['uid'],
					'price' => filter_var(str_replace(',', '.', $att['price']), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
					'aid' => $aid,
					'name' => $db->escape_string((string) $att['name'])
				];

			}

		}

		// Update existing item
		if ($insert) {

			$query = $db->simple_select('bankpipe_items', 'aid', "aid IN (" . implode(',', array_keys($insert)) . ")");
			while ($existing = $db->fetch_field($query, 'aid')) {

				$db->update_query('bankpipe_items', $insert[$existing], "aid = '" . $existing . "'");

				unset($insert[$existing]);

			}

		}

		// Add new
		if ($insert) {
			$db->insert_query_multiple('bankpipe_items', array_values($insert));
		}

	}
}

// ADMINCP ROUTINES
function bankpipe_update()
{
	$file = MYBB_ROOT . "inc/plugins/BankPipe/update.php";

	if (file_exists($file)) {
		require_once $file;
	}
}

function bankpipe_admin_config_menu($sub_menu)
{
	global $lang;

	$lang->load("bankpipe");

	$sub_menu[] = [
		"id" => "bankpipe",
		"title" => $lang->bankpipe,
		"link" => "index.php?module=config-bankpipe"
	];

	return $sub_menu;
}

function bankpipe_admin_config_action_handler($actions)
{
	$actions['bankpipe'] = [
		"active" => "bankpipe",
		"file" => "bankpipe.php"
	];

	return $actions;
}

function bankpipe_usergroups_tab(&$tabs)
{
	global $lang, $mybb;

	if ($mybb->input['gid'] == 1) {
		return false;
	}

	bankpipe_load_lang();

	$tabs['bankpipe'] = $lang->bankpipe;

	return $tabs;
}

function bankpipe_edit_graph()
{
	global $lang, $form, $mybb;

	if ($mybb->input['gid'] == 1) {
		return false;
	}

	echo "<div id='tab_bankpipe'>";

	$form_container = new FormContainer($lang->bankpipe);

	$form_container->output_row($lang->forum_post_options, "", "<div class='group_settings_bit'>".implode("</div><div class='group_bankpipe_bit'>", [
		$form->generate_check_box("candownloadpaidattachments", 1, $lang->bankpipe_can_dl_paid_attachs, ["checked" => $mybb->input['candownloadpaidattachments']])
	])."</div>");

	$form_container->end();

	echo "</div>";

}

function bankpipe_update_group_permissions()
{
	global $updated_group, $mybb;

	if ($mybb->input['gid'] == 1) {
		return false;
	}

	$updated_group['candownloadpaidattachments'] = $mybb->get_input('candownloadpaidattachments', MyBB::INPUT_INT);
}

function bankpipe_settings_gid()
{
	global $db;

	$query = $db->simple_select("settinggroups", "gid", "name = 'bankpipe'", array(
		"limit" => 1
	));
	$gid   = (int) $db->fetch_field($query, "gid");

	return $gid;
}

$GLOBALS['customFields'] = [
	'usergroups_view' => 'groups',
	'usergroups_manage' => 'groups',
	'forums' => 'forums',
	'currency' => 'currency'
];

function bankpipe_settings_saver()
{
	global $mybb, $page, $customFields;

	if ($mybb->request_method == "post" and $mybb->input['upsetting'] and $page->active_action == "settings" and $mybb->input['gid'] == bankpipe_settings_gid()) {

		foreach ($customFields as $field => $type) {

			$value = $mybb->input['bankpipe_'.$field.'_select'];

			if (in_array($type, ['groups', 'forums'])) {
				$value = implode(',', (array) $mybb->input['bankpipe_'.$field.'_select']);
			}

			$mybb->input['upsetting']['bankpipe_'.$field] = $value;

		}

	}
}

function bankpipe_settings_replacer($args)
{
	global $db, $lang, $form, $mybb, $page, $customFields, $cache;

	if ($page->active_action != "settings" and $mybb->input['action'] != "change" and $mybb->input['gid'] != bankpipe_settings_gid()) {
		return false;
	}

	foreach ($customFields as $field => $type) {

		if ($args['row_options']['id'] == "row_setting_bankpipe_".$field) {

			$tempKey = 'bankpipe_'.$field;

			if (in_array($type, ['groups', 'forums'])) {
				$values = explode(',', $mybb->settings[$tempKey]);
			}

			if ($type == 'groups') {

				$usergroups = [];

				$groups_cache = $cache->read('usergroups');
				unset($groups_cache[1]); // 1 = guests. Exclude them

				foreach ($groups_cache as $group) {
					$usergroups[$group['gid']] = $group['title'];
				}

				$args['content'] = $form->generate_select_box($tempKey."_select[]", $usergroups, $values, ['multiple' => 1]);

			}
			else if ($type == 'forums') {
				$args['content'] = $form->generate_forum_select($tempKey."_select[]", $values, ['multiple' => 1]);
			}
			else if ($type == 'currency') {
				$args['content'] = $form->generate_select_box($tempKey."_select", [
					'AUD' => 'Australian dollar',
					'BRL' => 'Brazilian real',
					'CAD' => 'Canadian dollar',
					'CZK' => 'Czech koruna',
					'DKK' => 'Danish krone',
					'EUR' => 'Euro',
					'HKD' => 'Hong Kong dollar',
					'HUF' => 'Hungarian forint',
					'INR' => 'Indian rupee',
					'ILS' => 'Israeli new shekel',
					'JPY' => 'Japanese yen',
					'MYR' => 'Malaysian ringgit',
					'MXN' => 'Mexican peso',
					'TWD' => 'New Taiwan dollar',
					'NZD' => 'New Zealand dollar',
					'NOK' => 'Norwegian krone',
					'PHP' => 'Philippine peso',
					'PLN' => 'Polish złoty',
					'GBP' => 'Pound sterling',
					'RUB' => 'Russian ruble',
					'SGD' => 'Singapore dollar',
					'SEK' => 'Swedish krona',
					'CHF' => 'Swiss franc',
					'THB' => 'Thai baht',
					'USD' => 'United States dollar'
				], [$mybb->settings[$tempKey]]);
			}

		}

	}

}

function bankpipe_forumpermissions(&$groups)
{
	global $lang;

	bankpipe_load_lang();

	$groups['candownloadpaidattachments'] = 'viewing';

	return $groups;
}

function bankpipe_check_discount_permissions($code, $item = [])
{
	global $mybb;
	
	$permissions = [
		'codes' => $code['bids'],
		'users' => $code['uids'],
		'usergroups' => $code['gids']
	];
	
	foreach ($permissions as $permission => $value) {
		
		$value = array_filter(explode(',', $value));
	
		if ($value) {
			
			if ($permission == 'codes' and $item and !in_array($item['bid'], $value)) {
				return false;
			}
			
			if ($permission == 'users' and !in_array($mybb->user['uid'], $value)) {
				return false;
			}
			
			if ($permission == 'usergroups') {
				
				// Count additional groups in
				$usergroups = [$mybb->user['usergroup']];
				$usergroups += explode(',', $mybb->user['additionalgroups']);
				
				if (count(array_intersect($value, $usergroups)) == 0) {
					return false;
				}
				
			}
			
		}
		
	}
	
	return true;
}

function bankpipe_read_cookie($name = '')
{
	global $mybb;
	return ($mybb->cookies['bankpipe-' . $name]) ? (array) json_decode($mybb->cookies['bankpipe-' . $name]) : [];
}

function bankpipe_set_cookie($name = '', $data)
{
	return my_setcookie('bankpipe-' . $name, json_encode(array_values(array_unique($data)))); // array_values() is necessary to reset the keys
}

function bankpipe_redirect($data)
{
	global $mybb;
	
	if ($mybb->input['ajax']) {
		bankpipe_ajax($data);
	}
	else {
		redirect($data['url'], $data['message'], $data['title']);
	}
}

function bankpipe_ajax($data)
{
	
	if ($data) {
		echo json_encode($data);
		exit;
	}
	
}

if (!function_exists('control_object')) {
	function control_object(&$obj, $code) {
		static $cnt = 0;
		$newname = '_objcont_'.(++$cnt);
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		if(substr($objserial, 0, $checkstr_len) == $checkstr) {
			$vars = array();
			// grab resources/object etc, stripping scope info from keys
			foreach((array)$obj as $k => $v) {
				if($p = strrpos($k, "\0"))
					$k = substr($k, $p+1);
				$vars[$k] = $v;
			}
			if(!empty($vars))
				$code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
			eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
			$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
			if(!empty($vars))
				$obj->___setvars($vars);
		}
		// else not a valid object or PHP serialize has changed
	}
}