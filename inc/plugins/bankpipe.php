<?php
/**
 * BankPipe
 * 
 * A fully functional payment system for MyBB.
 *
 * @package BankPipe
 * @license Copyrighted ©
 * @author  Shade <shad3-@outlook.com>
 * @version beta 6
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
    define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

define ('BANKPIPE', MYBB_ROOT . "bankpipe/autoload.php");
include BANKPIPE;

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
            $revert = $PL->url_append('index.php',
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
        'version'       =>  'beta 6',
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
        'notification_cc' => [
            'title' => $lang->setting_bankpipe_notification_cc_uids,
            'description' => $lang->setting_bankpipe_notification_cc_uids_desc,
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
        'admin_notification_sender' => [
            'title' => $lang->setting_bankpipe_admin_notification_sender,
            'description' => $lang->setting_bankpipe_admin_notification_sender_desc,
            'optionscode' => 'text',
            'value' => ''
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
            email TEXT,
            name varchar(128) DEFAULT NULL,
            description varchar(128) DEFAULT NULL,
            htmldescription text,
            discount smallint(3) NOT NULL,
            expires int(10) UNSIGNED NOT NULL DEFAULT '0',
            primarygroup tinyint(1) NOT NULL DEFAULT '1',
            expirygid int(5) NOT NULL DEFAULT '0',
            type tinyint(1) NOT NULL,
            KEY aid (aid)
        ) ENGINE=MyISAM{$collation};
        ");

    }
    if (!$db->table_exists('bankpipe_log')) {

        $collation = $db->build_create_table_collation();

        $db->write_query("
        CREATE TABLE " . TABLE_PREFIX . "bankpipe_log (
            lid int(8) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            invoice varchar(32) NOT NULL DEFAULT '',
            type tinyint(1) NOT NULL,
            bids text,
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
            payee_email text,
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
            fee decimal(6,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT '',
            discounts text,
            type tinyint(1) NOT NULL,
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
            name varchar(128) DEFAULT NULL,
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
    $db->drop_table('bankpipe_downloadlogs');
    $db->drop_table('bankpipe_discounts');

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
    global $PL, $mybb;

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
            'replace' => 'if($forumpermissions[\'canview\'] == 0 || $forumpermissions[\'canviewthreads\'] == 0 || (isset($forumpermissions[\'canonlyviewownthreads\']) && $forumpermissions[\'canonlyviewownthreads\'] != 0 && $thread[\'uid\'] != $mybb->user[\'uid\']) || ($forumpermissions[\'candlattachments\'] == 0 && !$mybb->input[\'thumbnail\'] && !$mybb->input[\'skip\']))'
        ]
    ];

    $result = $PL->edit_core('bankpipe', 'attachment.php', $edits, $apply);

    if ($result !== true) {
        $errors[] = $result;
    }

    // Core edit is necessary for MyBB 1.8.19+
    if ($mybb->version_code > 1818) {

        $edits = [
            [
                'search' => [
                    'if(!empty($attachedfile[\'error\']))',
                    '{',
                        '$ret[\'errors\'][] = $attachedfile[\'error\'];',
                        '$mybb->input[\'action\'] = $action;',
                    '}'
                ],
                'after' => [
                    'else',
                    '{',
                        '$args = [\'attachedfile\' => $attachedfile];',
                        '$GLOBALS[\'plugins\']->run_hooks(\'bankpipe_core_add_attachment\', $args);',
                    '}'
                ]
            ]
        ];

        $result = $PL->edit_core('bankpipe', 'inc/functions_upload.php', $edits, $apply);

        if ($result !== true) {
            $errors[] = $result;
        }

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
    $PL->edit_core('bankpipe', 'inc/functions_upload.php', [], $apply);
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

    if ($mybb->version_code > 1818) {
        $plugins->add_hook('bankpipe_core_add_attachment', 'bankpipe_update_paid_attachment');
    }

    $plugins->add_hook('editpost_do_editpost_start', 'bankpipe_save_paid_item');
    $plugins->add_hook('newreply_do_newreply_start', 'bankpipe_save_paid_item');
    $plugins->add_hook('newthread_do_newthread_start', 'bankpipe_save_paid_item');
    $plugins->add_hook('pre_output_page', 'bankpipe_pre_output_page');

    $plugins->add_hook('attachment_start', 'bankpipe_attachment_start');
    $plugins->add_hook('attachment_end', 'bankpipe_attachment_end');
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

use BankPipe\Items\Items;
use BankPipe\Items\Orders;
use BankPipe\Helper\Permissions;
use BankPipe\Helper\Cookies;
use BankPipe\Core;

function bankpipe_global_start()
{
    global $mybb, $lang, $templatelist;

    if ($templatelist) {
        $templatelist = explode(',', $templatelist);
    }
    else {
        $templatelist = [];
    }

    if (THIS_SCRIPT == 'usercp.php') {

        if ($mybb->input['action'] == 'subscriptions') {
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

        if ($mybb->input['action'] == 'purchases') {
            $templatelist[] = 'bankpipe_purchases_payment';
            $templatelist[] = 'bankpipe_purchases_payment_item';
            $templatelist[] = 'bankpipe_purchases_payment_discounts';
            $templatelist[] = 'bankpipe_purchases_purchase_refunded';
            $templatelist[] = 'bankpipe_purchases_purchase_expired';
            $templatelist[] = 'bankpipe_purchases_purchase_inactive';
            $templatelist[] = 'bankpipe_purchases_purchase';
            $templatelist[] = 'bankpipe_purchases_no_purchases';
            $templatelist[] = 'bankpipe_purchases';
        }

        if ($mybb->input['action'] == 'manage') {
            $templatelist[] = 'bankpipe_manage_items_item';
            $templatelist[] = 'bankpipe_manage_items';
            $templatelist[] = 'bankpipe_manage_items_no_items';
            $templatelist[] = 'bankpipe_manage';
            $templatelist[] = 'attachment_icon';
        }

        if ($mybb->input['action'] == 'cart') {
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

    if (!$mybb->settings['bankpipe_cart_mode'] or !(new Permissions)->simpleCheck(['view'])) {
        return false;
    }

    bankpipe_load_lang();

    $cartItems = count((new Cookies)->read('items'));

    eval("\$cart = \"".$templates->get("bankpipe_header_cart")."\";");
}

function bankpipe_nav()
{
    global $mybb, $usercpmenu, $templates, $lang;

    $permissions = new Permissions;

    if (!$permissions->simpleCheck(['view'])) {
        return false;
    }

    bankpipe_load_lang();

    if ($permissions->simpleCheck(['manage'])) {
        eval("\$manage = \"".$templates->get("bankpipe_nav_manage")."\";");
    }

    if ($mybb->settings['bankpipe_cart_mode']) {

        $cartItems = count((new Cookies)->read('items'));

        eval("\$cart = \"".$templates->get("bankpipe_nav_cart")."\";");

    }

    eval("\$usercpmenu .= \"".$templates->get("bankpipe_nav")."\";");
}

function bankpipe_panel()
{
    new BankPipe\Usercp\Usercp;
}

function bankpipe_edit_attachments()
{
    global $templates, $db, $mybb, $forumpermissions, $attachedfile, $pid, $tid, $fid, $items, $plugins;

    if (!$mybb->settings['bankpipe_third_party']) {
        return false;
    }

    if (!(new Permissions)->simpleCheck([], $fid) or !$mybb->user['payee']) {
        return false;
    }

    $aids = [];
    $items = new Items;

    $attachedfile = $plugins->run_hooks('bankpipe_update_paid_attachment', $attachedfile);

    if (!empty($mybb->input['paidattachs']['update']) and $mybb->input['newattachment'] and $attachedfile and $attachedfile['aid'] and !$attachedfile['error'] and $mybb->settings['enableattachments'] == 1 and $mybb->request_method == 'post') {

        $update = (int) $mybb->input['paidattachs']['update'];

        if ($update > 0 and $update != $attachedfile['aid']) {

            // Get this attachment
            $attach = $items->getAttachment($update);

            if ($attach) {

                // Get name of attachment, and also update that
                $query = $db->simple_select('attachments', 'filename', 'aid = ' . (int) $attachedfile['aid']);
                $filename = $db->fetch_field($query, 'filename');

                // Update this item aid using the new attachment in order to retain purchases over this item
                $db->update_query('bankpipe_items', ['aid' => (int) $attachedfile['aid'], 'name' => $db->escape_string($filename)], 'bid = ' . (int) $attach['bid']);

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
            or ($mybb->input['action'] == 'editpost' and $pid)
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
    $items->getAttachments($aids);

    $plugins->run_hooks('bankpipe_edit_attachments');

    control_object($templates, '
        function get($title, $eslashes=1, $htmlcomments=1) {

            if (in_array($title, ["post_attachments_attachment", "post_attachments_attachment_unapproved"])) {
                bankpipe_attachment_options();
            }

            return parent::get($title, $eslashes, $htmlcomments);

        }
    ');
}

function bankpipe_attachment_options()
{
    global $attachment, $paidOptions, $templates, $attachcolspan, $mybb, $post_errors, $lang, $items, $plugins;

    if (!$mybb->settings['bankpipe_third_party']) {
        return false;
    }

    bankpipe_load_lang();

    $attachment['paid'] = $items->getAttachment($attachment['aid']);

    if (($mybb->input['previewpost'] or $post_errors or $mybb->input['newattachment'] or $mybb->input['updateattachment']) and $mybb->input['paidattachs'][$attachment['aid']]) {
        $attachment['paid'] = $mybb->input['paidattachs'][$attachment['aid']];
    }

    $attachcolspan = 2;

    $plugins->run_hooks('bankpipe_attachment_options');

    eval("\$paidOptions = \"".$templates->get("bankpipe_attachment_options")."\";");
}

// MyBB 1.8.19+ feature a different attachment system and need a tweak to update paid attachments
function bankpipe_update_paid_attachment($args)
{
    global $templates, $db, $mybb, $fid, $pid, $plugins;

    if (!(new Permissions)->simpleCheck([], $fid) or !$mybb->user['payee']) {
        return false;
    }

    $attachedfile = (array) $args['attachedfile'];

    $attachedfile = $plugins->run_hooks('bankpipe_update_paid_attachment', $attachedfile);

    if (!empty($mybb->input['paidattachs']['update']) and $attachedfile['aid']) {

        $update = (int) $mybb->input['paidattachs']['update'];

        if ($update > 0 and $update != $attachedfile['aid']) {

            // Get this attachment
            $attach = (new Items)->getAttachment($update);

            if ($attach) {

                // Get name of attachment, and also update that
                $query = $db->simple_select('attachments', 'filename', 'aid = ' . (int) $attachedfile['aid']);
                $filename = $db->fetch_field($query, 'filename');

                // Update this item aid using the new attachment in order to retain purchases over this item
                $db->update_query('bankpipe_items', ['aid' => (int) $attachedfile['aid'], 'name' => $db->escape_string($filename)], 'bid = ' . (int) $attach['bid']);

                // Remove the old attachment
                remove_attachment($pid, "", $update);

            }

        }
    }
}

function bankpipe_attachments_postbit($data)
{
    global $templates, $currentAttachment, $attachcache, $orders, $plugins, $payments, $items, $cookies;

    if (!$orders) {
        $orders = new Orders;
    }

    if (!$cookies) {
        $cookies = new Cookies;
    }

    bankpipe_load_lang();

    $search = [];

    // Is there an attachment cache already built?
    foreach ((array) $attachcache as $pid => $attachments) {

        foreach ($attachments as $aid => $att) {
            $search[] = (int) $aid;
        }

    };

    // Cache this thread attachments
    $items = (new Items)->getAttachments($search);

    $bids = array_keys($items);

    if ($bids) {

        $payments = $orders->get([
            'uid' => $mybb->user['uid'],
            'active' => 1,
            'bid IN (' . implode(',', $bids) . ')'
        ], [
            'includeItemsInfo' => true
        ]);

    }

    $data = $plugins->run_hooks('bankpipe_attachments_postbit', $data);

    $currentAttachment = $data['attachment'];
    if (!(new Permissions)->simpleCheck(['view', 'forums'], $data['post']['fid'])) {
        $currentAttachment['not_allowed'] = true;
    }

    control_object($templates, '
        function get($title, $eslashes=1, $htmlcomments=1)
        {
            $title = bankpipe_hijack_templates($title);

            return parent::get($title, $eslashes, $htmlcomments);

        }
    ');
}

function bankpipe_hijack_templates($title)
{
    if (!in_array($title, ['postbit_attachments_attachment', 'postbit_attachments_attachment_unapproved'])) {
        return $title;
    }

    global $currentAttachment, $paidAttachment, $db, $mybb, $showPayments, $lang, $forumpermissions, $items, $plugins, $payments, $cookies;

    $plugins->run_hooks('bankpipe_hijack_templates_start');

    if (!$showPayments) {
        $showPayments = false;
    }

    if ($forumpermissions['candownloadpaidattachments']) {
        return $title;
    }

    $key = array_search($currentAttachment['aid'], array_column($items, 'aid', 'bid'));
    $paidAttachment = $items[$key];

    // This attachment is paid
    if ($paidAttachment['aid']) {

        if ($currentAttachment['not_allowed']) {
            return 'bankpipe_' . $title . '_not_allowed';
        }

        $unlocked = false;
        foreach ($payments as $invoice => $payment) {

            if (in_array($paidAttachment['bid'], array_column($payment['items'], 'bid'))) {
                $unlocked = true;
                break;
            }

        }

        // This attachment has not been unlocked yet
        if (!$unlocked and $mybb->user['uid'] != $paidAttachment['itemuid']) {

            $showPayments = true;

            if ($mybb->settings['bankpipe_cart_mode']) {

                $existingItems = $cookies->read('items');

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

    $plugins->run_hooks('bankpipe_hijack_templates_end', $paidAttachment);

    return $title;
}

function bankpipe_pre_output_page(&$content)
{
    global $showPayments, $templates, $mybb, $fid, $lang;

    if (THIS_SCRIPT != 'showthread.php' or !$fid or !(new Permissions)->simpleCheck(['view', 'forums'], $fid)) {
        return $content;
    }

    bankpipe_load_lang();

    if ($showPayments) {

        eval("\$payments = \"".$templates->get("bankpipe_script")."\";");
        $content = str_replace('</head>', '</head>' . $payments, $content);

    }

    return $content;
}

function bankpipe_profile()
{
    global $memprofile, $mybb, $db, $lang, $templates, $theme, $plugins;

    if ($mybb->usergroup['cancp']) {

        $purchases = $inactive = $refunded = $expired = '';

        $exclude = [Orders::CREATE, Orders::ERROR, Orders::MANUAL];
        $orders = (new Orders)->get([
            'type NOT IN (' . implode(',', $exclude) . ')',
            'uid' => $memprofile['uid']
        ]);

        $orders = $plugins->run_hooks('bankpipe_profile', $orders);

        if ($orders) {

            foreach ($orders as $order) {

                $names = implode(', ', array_column($order['items'], 'name'));

                $order['date'] = my_date('relative', $order['date']);

                if ($order['refund']) {
                    eval("\$refunded .= \"".$templates->get("bankpipe_purchases_purchase_refunded")."\";");
                }
                else if ($order['expires'] and $order['expires'] < TIME_NOW and !$order['active']) {
                    eval("\$expired .= \"".$templates->get("bankpipe_purchases_purchase_expired")."\";");
                }
                else if (!$order['active']) {
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
    global $mybb, $attachment, $item, $payments;

    if ($mybb->user['uid'] == 0) {
        return false;
    }

    $item = (new Items)->getAttachment($attachment['aid']);

    if ($item['bid']) {

        $payments = (new Orders)->get([
            'uid' => $mybb->user['uid'],
            'active' => 1,
            'bid' => $item['bid']
        ]);

    }

    if ($item['aid'] == $attachment['aid']) {
        $mybb->input['skip'] = true;
    }
}

function bankpipe_attachment_end()
{
    global $mybb, $attachment, $forumpermissions, $db, $item, $payments, $plugins;

    $paid = ($item['aid'] == $attachment['aid']);

    $pid = 0;

    $plugins->run_hooks('bankpipe_view_attachment_start');

    if (!$forumpermissions['candownloadpaidattachments'] and $paid) {

        $unlocked = false;
        foreach ($payments as $invoice => $payment) {

            $pids = array_column($payment['items'], 'pid', 'bid');

            if (in_array($item['bid'], array_column($payment['items'], 'bid'))) {
                $pid = $pids[$item['bid']];
                $unlocked = true;
                break;
            }

        }

        // This attachment has not been unlocked yet
        if (!$unlocked and $mybb->user['uid'] != $item['itemuid']) {

            // Revert the download count update
            if (!isset($mybb->input['thumbnail'])) {
                $db->update_query("attachments", ['downloads' => $attachment['downloads']-1], "aid='{$attachment['aid']}'");
            }

            header('Location: ' . get_post_link($attachment['pid']));
            exit;

        }

    }

    // Log this download
    if ($paid and $mybb->user['uid'] != $item['itemuid']) {

        $log = [
            'aid' => (int) $attachment['aid'],
            'uid' => (int) $mybb->user['uid'],
            'title' => $db->escape_string($attachment['filename']),
            'date' => TIME_NOW
        ];

        // If he can download paid attachments, he has purchased a subscription or he's in an allowed group.
        // -1 is a special mark for these special cases
        if ($forumpermissions['candownloadpaidattachments']) {
            $log['pid'] = -1;
        }
        else {
            $log['pid'] = (int) $pid;
        }

        $db->insert_query('bankpipe_downloadlogs', $log);

    }

    $plugins->run_hooks('bankpipe_view_attachment_end');
}

function bankpipe_delete_attachment($attachment)
{
    global $db, $plugins;

    if ($attachment['aid']) {
        $db->delete_query('bankpipe_items', 'aid = ' . (int) $attachment['aid']);
    }

    $attachment = $plugins->run_hooks('bankpipe_delete_attachment', $attachment);

    return $attachment;
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

function bankpipe_save_paid_item()
{
    global $mybb, $db, $attachfile;

    if (!$mybb->settings['bankpipe_third_party']) {
        return false;
    }

    if ($mybb->input['paidattachs'] and is_array($mybb->input['paidattachs'])) {

        $items = [];

        foreach ($mybb->input['paidattachs'] as $aid => $item) {

            if ($aid == 'update') {
                continue;
            }

            $item['aid'] = $aid;
            $item['uid'] = $mybb->user['uid'];
            $item['type'] = Items::ATTACHMENT;

            $items[] = $item;

        }

        return (new Items)->insert($items);

    }
}

/**
 * Admin Routines
 */
function bankpipe_update()
{
    new BankPipe\Update\Update;
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
