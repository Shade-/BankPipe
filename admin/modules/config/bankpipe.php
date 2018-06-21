<?php

if (!defined("IN_MYBB")) {
	header("HTTP/1.0 404 Not Found");
	exit;
}

define(MODULE, "bankpipe");
define(MAINURL, "index.php?module=config-bankpipe");

$lang->load(MODULE);

$sub_tabs['general']  = [
	'title' => $lang->bankpipe_overview,
	'link' => MAINURL,
	'description' => $lang->bankpipe_overview_desc
];
$sub_tabs['logs'] = [
	'title' => $lang->bankpipe_logs,
	'link' => MAINURL . '&amp;action=logs',
	'description' => $lang->bankpipe_logs_desc
];
$sub_tabs['history'] = [
	'title' => $lang->bankpipe_history,
	'link' => MAINURL . '&amp;action=history',
	'description' => $lang->bankpipe_history_desc
];
$sub_tabs['subscriptions'] = [
	'title' => $lang->bankpipe_subscriptions,
	'link' => MAINURL . '&amp;action=manage_subscription',
	'description' => $lang->bankpipe_subscriptions_desc
];
$sub_tabs['notifications'] = [
	'title' => $lang->bankpipe_notifications,
	'link' => MAINURL . '&amp;action=notifications',
	'description' => $lang->bankpipe_notifications_desc
];
$sub_tabs['manual_add'] = [
	'title' => $lang->bankpipe_manual_add,
	'link' => MAINURL . '&amp;action=manual_add',
	'description' => $lang->bankpipe_manual_add_desc
];

if (in_array($mybb->input['action'], ['edit_purchase', 'refund_purchase'])) {

	$sub_tabs['manage_purchase'] = [
		'title' => $lang->bankpipe_manage_purchase,
		'link' => MAINURL,
		'description' => $lang->bankpipe_manage_purchase_desc
	];

}

// Add subscription
if ($mybb->input['action'] == 'manage_subscription') {

	// Get this subscription
	$bid = (int) $mybb->get_input('bid');

	if ($bid) {

		$query = $db->simple_select('bankpipe_items', '*', "bid = '" . $bid . "'", ['limit' => 1]);
		$subscription = $db->fetch_array($query);

		if (!$subscription['bid']) {
			flash_message($lang->bankpipe_error_invalid_item);
			admin_redirect(MAINURL);
		}

	}

	if ($mybb->request_method == 'post') {

		if (!$mybb->settings['bankpipe_subscription_payee']) {
			flash_message($lang->bankpipe_error_missing_default_payee, 'error');
			admin_redirect(MAINURL);
		}

		$price = $mybb->input['price'];

		if (!$price or $price <= 0) {
			flash_message($lang->bankpipe_error_price_not_valid, 'error');
			admin_redirect(MAINURL);
		}

		$data = [
			'name' => $db->escape_string($mybb->input['name']),
			'description' => $db->escape_string($mybb->input['description']),
			'htmldescription' => $db->escape_string($mybb->input['htmldescription']),
			'price' => $price,
			'uid' => (int) $mybb->settings['bankpipe_subscription_payee'],
			'gid' => (int) $mybb->input['gid'],
			'discount' => (int) $mybb->input['discount'],
			'expires' => (int) $mybb->input['expires'],
			'primarygroup' => (int) $mybb->input['primarygroup'],
			'expirygid' => (int) $mybb->input['expirygid']
		];

		if (!$bid) {
			$message = $lang->bankpipe_success_subscription_added;
			$db->insert_query('bankpipe_items', $data);
		}
		else {
			$message = $lang->bankpipe_success_subscription_edited;
			$db->update_query('bankpipe_items', $data, "bid = '" . $subscription['bid'] . "'");
		}

		// Redirect
		flash_message($message, 'success');
		admin_redirect(MAINURL);

	}

	// Default values
	if ($bid) {

		foreach ($subscription as $field => $value) {
			$mybb->input[$field] = $value;
		}

	}

	$title = ($bid) ? $lang->sprintf($lang->bankpipe_edit_subscription, $subscription['name']) : $lang->bankpipe_add_subscription;

	$page->add_breadcrumb_item($title, MAINURL . '&amp;action=manage_subscription');

	$page->output_header($title);

	$page->output_nav_tabs($sub_tabs, 'subscriptions');

	// Determine the post request attributes
	$extraAction = ($bid) ? "&amp;bid=" . $subscription['bid'] : '';

	$form = new Form(MAINURL . "&amp;action=manage_subscription" . $extraAction, "post", "manage_subscription");

	$form_container = new FormContainer($title);

	$form_container->output_row($lang->bankpipe_manage_subscription_name, $lang->bankpipe_manage_subscription_name_desc, $form->generate_text_box('name', $mybb->input['name'], [
		'id' => 'name',
		'maxlength' => 127
	]), 'name');

	$form_container->output_row($lang->bankpipe_manage_subscription_description, $lang->bankpipe_manage_subscription_description_desc, $form->generate_text_area('description', $mybb->input['description'], [
		'id' => 'description',
		'maxlength' => 127
	]), 'description');

	$form_container->output_row($lang->bankpipe_manage_subscription_htmldescription, $lang->bankpipe_manage_subscription_htmldescription_desc, $form->generate_text_area('htmldescription', $mybb->input['htmldescription'], [
		'id' => 'htmldescription'
	]), 'htmldescription');

	$form_container->output_row($lang->bankpipe_manage_subscription_price, $lang->bankpipe_manage_subscription_price_desc, $form->generate_text_box('price', $mybb->input['price'], [
		'id' => 'price'
	]), 'price');

	if (!$mybb->input['gid']) {
		$mybb->input['gid'] = 2;
	}

	// Subscription usergroup
	$subusergroups = [];

	$groups_cache = $cache->read('usergroups');
	unset($groups_cache[1]); // 1 = guests. Exclude them

	foreach ($groups_cache as $group) {
		$subusergroups[$group['gid']] = $group['title'];
	}

	$form_container->output_row($lang->bankpipe_manage_subscription_usergroup, $lang->bankpipe_manage_subscription_usergroup_desc, $form->generate_select_box('gid', $subusergroups, [$mybb->input['gid']], [
		'id' => 'gid'
	]));

	$form_container->output_row($lang->bankpipe_manage_subscription_change_primary, $lang->bankpipe_manage_subscription_change_primary_desc, $form->generate_yes_no_radio('primarygroup', $mybb->input['primarygroup'], true));

	$form_container->output_row($lang->bankpipe_manage_subscription_discount, $lang->bankpipe_manage_subscription_discount_desc, $form->generate_text_box('discount', $mybb->input['discount'], [
		'id' => 'discount'
	]));

	$form_container->output_row($lang->bankpipe_manage_subscription_expires, $lang->bankpipe_manage_subscription_expires_desc, $form->generate_text_box('expires', $mybb->input['expires'], [
		'id' => 'expires'
	]));

	// Expiry usergroup
	$expirygid = [
		$lang->bankpipe_manage_subscription_use_default_usergroup
	];

	foreach ($groups_cache as $group) {
		$expirygid[$group['gid']] = $group['title'];
	}

	$form_container->output_row($lang->bankpipe_manage_subscription_expiry_usergroup, $lang->bankpipe_manage_subscription_expiry_usergroup_desc, $form->generate_select_box('expirygid', $expirygid, [$mybb->input['expirygid']], [
		'id' => 'expirygid'
	]));

	$form_container->end();

	$buttons = [
		$form->generate_submit_button($lang->bankpipe_save)
	];
	$form->output_submit_wrapper($buttons);
	$form->end();

}
// Logs
else if ($mybb->input['action'] == 'logs') {

	if ($mybb->input['delete'] and $mybb->request_method == 'post') {

		if ($mybb->input['delete']) {
			$db->delete_query('bankpipe_log', 'lid IN (' . implode(',', array_keys((array) $mybb->input['delete'])) . ')');
		}

		// Redirect
		flash_message($lang->bankpipe_success_deleted_selected_logs, 'success');
		admin_redirect(MAINURL . "&amp;action=logs");

	}

	$query = $db->simple_select('bankpipe_log', 'COUNT(lid) AS num_results');
	$num_results = $db->fetch_field($query, 'num_results');

	$page->add_breadcrumb_item($lang->bankpipe_logs, MAINURL);

	$page->output_header($lang->bankpipe_logs);

	$page->output_nav_tabs($sub_tabs, 'logs');

	if ($num_results > 0) {
		$form = new Form(MAINURL . "&amp;action=logs", "post", "logs");
	}

	$perpage = 20;

	if (!isset($mybb->input['page'])) {
		$mybb->input['page'] = 1;
	}
	else {
		$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$start = 0;
	if ($mybb->input['page']) {
		$start = ($mybb->input['page'] - 1) * $perpage;
	}
	else {
		$mybb->input['page'] = 1;
	}

	if ($num_results > $perpage) {
		echo draw_admin_pagination($mybb->input['page'], $perpage, $num_results, MAINURL . "&amp;action=logs");
	}

	$table = new Table;

	$table->construct_header($lang->bankpipe_logs_header_user, ['width' => '15%']);
	$table->construct_header($lang->bankpipe_logs_header_action);
	$table->construct_header($lang->bankpipe_logs_header_item, ['width' => '40%']);
	$table->construct_header($lang->bankpipe_logs_header_date, ['width' => '10%']);
	$table->construct_header($lang->bankpipe_logs_header_delete, ['width' => '1px']);

	$query = $db->query('
		SELECT l.*, i.name, i.price, u.username, u.usergroup, u.displaygroup, u.avatar
		FROM ' . TABLE_PREFIX . 'bankpipe_log l
		LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid = l.uid)
		LEFT JOIN ' . TABLE_PREFIX . 'bankpipe_items i ON (i.bid = l.bid)
		ORDER BY l.date DESC
		LIMIT ' . (int) $start . ', ' . (int) $perpage . '
	');
	while ($log = $db->fetch_array($query)) {

		// User
		$username = format_name($log['username'], $log['usergroup'], $log['displaygroup']);
		$username = build_profile_link($username, $log['uid']);

		$avatar = format_avatar($log['avatar']);

		$table->construct_cell('<img src="' . $avatar['image'] . '" style="height: 20px; width: 20px; vertical-align: middle" /> ' . $username);

		// Action
		switch ($log['type']) {

			case 'error':
			default:
				$action = $lang->bankpipe_logs_error;
				break;

			case 'created':
				$action = $lang->bankpipe_logs_created;
				break;

			case 'refund':
				$action = $lang->bankpipe_logs_refunded;
				break;

			case 'pending':
				$action = $lang->bankpipe_logs_pending;
				break;

			case 'executed':
				$action = $lang->bankpipe_logs_executed;
				break;

		}

		if ($log['message']) {
			$action .= '<br><span class="smalltext">' . $log['message'] . '</span>';
		}

		$table->construct_cell($action);

		// Item
		$item = '';
		if ($log['name']) {

			if ($log['pid']) {
				$item .= "<a href='" . MAINURL . "&amp;action=edit_purchase&amp;pid=" . $log['pid'] . "'>" . htmlspecialchars_uni($log['name']) . "</a>";
			}
			else {
				$item .= htmlspecialchars_uni($log['name']);
			}

		}

		if ($log['price']) {
			$item .= ', ' . $log['price'] . ' ' . $mybb->settings['bankpipe_currency'];
		}

		$table->construct_cell($item);

		// Date
		$table->construct_cell(my_date('relative', $log['date']));

		// Delete
		$table->construct_cell($form->generate_check_box("delete[{$log['lid']}]", 1));
		$table->construct_row();

	}

	if ($db->num_rows($query) == 0) {
		$table->construct_cell($lang->bankpipe_logs_no_logs, ['colspan' => 5, 'style' => 'text-align: center']);
		$table->construct_row();
	}

	$table->output($lang->bankpipe_logs);

	if ($num_results > 0) {

		$buttons = [
			$form->generate_submit_button($lang->bankpipe_logs_delete)
		];
		$form->output_submit_wrapper($buttons);
		$form->end();

	}

}
// Manage purchases
else if (in_array($mybb->input['action'], ['edit_purchase', 'revoke_purchase', 'refund_purchase'])) {

	// Get this purchase
	$pid = (int) $mybb->get_input('pid');

	if ($pid) {

		$query = $db->query('
			SELECT p.*, i.name, i.price, i.primarygroup, u.username, u.usergroup, u.displaygroup, u.avatar, u.additionalgroups
			FROM ' . TABLE_PREFIX . 'bankpipe_payments p
			LEFT JOIN ' . TABLE_PREFIX . 'users u ON (p.uid = u.uid)
			LEFT JOIN ' . TABLE_PREFIX . 'bankpipe_items i ON (i.bid = p.bid)
			WHERE p.pid = ' . $pid . '
			LIMIT 1
		');
		$purchase = $db->fetch_array($query);

		if (!$purchase['pid']) {
			flash_message($lang->bankpipe_error_invalid_purchase);
			admin_redirect(MAINURL);
		}

	}

	if ($mybb->request_method == 'post') {

		// Revoke?
		if ($mybb->input['action'] == 'revoke_purchase') {

			if (!$mybb->input['no']) {

				$db->update_query('bankpipe_payments', ['active' => 0], 'pid = ' . $pid);

				if ($purchase['active']) {
					bankpipe_revert_usergroup($purchase);
				}

				flash_message($lang->bankpipe_success_purchase_revoked, 'success');

			}

			admin_redirect(MAINURL . '&amp;action=history');

		}

		// Refund?
		if ($mybb->input['action'] == 'refund_purchase') {

			require_once MYBB_ROOT . 'inc/plugins/BankPipe/core.php';
			$PayPal = new BankPipe();

			$refund = $PayPal->refundPurchase($purchase, $mybb->input['amount']);

			if ($refund['error']) {
				flash_message($refund['error'], 'error');
			}
			else {
				flash_message($lang->sprintf($lang->bankpipe_success_purchase_refunded, $refund['amount']['total'] . ' ' . $refund['amount']['currency'], $refund['refund_from_transaction_fee']['value'] . ' ' . $refund['refund_from_transaction_fee']['currency']), 'success');
			}

			admin_redirect(MAINURL . '&amp;action=history');

		}

		$mybb->input['expires'] = strtotime(str_replace('/', '-', $mybb->input['expires']));

		$data = [
			'newgid' => (int) $mybb->input['newgid'],
			'oldgid' => (int) $mybb->input['oldgid'],
			'active' => (int) $mybb->input['active'],
			'expires' => (int) $mybb->input['expires']
		];

		// Revert usergroup
		if ($data['active'] === 0 and $purchase['active']) {
			bankpipe_revert_usergroup(array_merge($purchase, $data));
		}

		$db->update_query('bankpipe_payments', $data, "pid = '" . $purchase['pid'] . "'");

		// Redirect
		flash_message($lang->bankpipe_success_purchase_edited, 'success');
		admin_redirect(MAINURL . '&amp;action=history');

	}

	// Default values
	if ($pid) {

		foreach ($purchase as $field => $value) {
			$mybb->input[$field] = $value;
		}

	}

	// Revoke
	if ($mybb->input['action'] == 'revoke_purchase') {
		$page->output_confirm_action(MAINURL . "&amp;action=revoke_purchase&amp;pid=" . $pid, $lang->bankpipe_revoke_purchase, $lang->bankpipe_revoke_purchase_title);
	}

	$page->add_breadcrumb_item($lang->bankpipe_manage_purchase, MAINURL . '&amp;action=' . $mybb->input['action']);

	$page->output_header($lang->bankpipe_manage_purchase);

	$page->output_nav_tabs($sub_tabs, 'manage_purchase');

	$form = new Form(MAINURL . "&amp;action=" . $mybb->input['action'] . "&amp;pid=" . $pid, "post", $mybb->input['action']);

	$form_container = new FormContainer($lang->bankpipe_manage_purchase);

	$form_container->output_row($lang->bankpipe_edit_purchase_name, $purchase['name']);
	$form_container->output_row($lang->bankpipe_edit_purchase_bought_by, build_profile_link(format_name($purchase['username'], $purchase['usergroup'], $purchase['displaygroup']), $purchase['uid']) . ', ' . my_date('relative', $purchase['date']));

	if ($purchase['sale']) {
		$form_container->output_row($lang->bankpipe_edit_purchase_sale_id, $purchase['sale']);
	}

	// Edit
	if ($mybb->input['action'] == 'edit_purchase') {

		if ($purchase['refund']) {

			$query = $db->simple_select('bankpipe_log', 'date', "type = 'refund' AND pid = " . (int) $pid);
			$date = $db->fetch_field($query, 'date');

			$form_container->output_row($lang->bankpipe_edit_purchase_refunded, $lang->sprintf($lang->bankpipe_edit_purchase_refunded_on, my_date('relative', $date)));

		}

		$oldgid = [
			$lang->bankpipe_edit_purchase_no_group
		];

		$groups_cache = $cache->read('usergroups');
		foreach ($groups_cache as $group) {
			$oldgid[$group['gid']] = $group['title'];
		}

		$form_container->output_row($lang->bankpipe_edit_purchase_oldgid, $lang->bankpipe_edit_purchase_oldgid_desc, $form->generate_select_box('oldgid', $oldgid, [$mybb->input['oldgid']], [
			'id' => 'oldgid'
		]), 'oldgid');

		$expires = '';
		if ($mybb->input['expires']) {
			$expires = date('d/m/Y', $mybb->input['expires']);
		}

		$form_container->output_row($lang->bankpipe_edit_purchase_expires, $lang->bankpipe_edit_purchase_expires_desc, $form->generate_text_box('expires', $expires, [
			'id' => 'expires'
		]));

		$form_container->output_row($lang->bankpipe_edit_purchase_active, $lang->bankpipe_edit_purchase_active_desc, $form->generate_check_box('active', 1, $lang->bankpipe_edit_purchase_active, [
			'checked' => $mybb->input['active']
		]));

	}
	// Refund
	else if ($mybb->input['action'] == 'refund_purchase') {

		$form_container->output_row($lang->bankpipe_refund_purchase_cost, $purchase['price'] . ' ' . $mybb->settings['bankpipe_currency']);

		$form_container->output_row($lang->bankpipe_refund_purchase_amount, $lang->bankpipe_refund_purchase_amount_desc, $form->generate_text_box('amount', $mybb->input['amount'], [
			'id' => 'amount'
		]));

	}

	$form_container->end();

	$buttons = [
		$form->generate_submit_button($lang->bankpipe_save)
	];
	$form->output_submit_wrapper($buttons);
	$form->end();

	echo '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.css" type="text/css" />
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.js"></script>
<script type="text/javascript">
<!--
// Date picking
var expiry = $("#expires").datepicker({
	autoHide: true,
	format: \'dd/mm/yyyy\'
})
-->
</script>';

}
// History
else if ($mybb->input['action'] == 'history') {

	$page->add_breadcrumb_item($lang->bankpipe_history, MAINURL);

	$page->output_header($lang->bankpipe_history);

	$page->output_nav_tabs($sub_tabs, 'history');

	$perpage = 20;

	if (!isset($mybb->input['page'])) {
		$mybb->input['page'] = 1;
	}
	else {
		$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$start = 0;
	if ($mybb->input['page']) {
		$start = ($mybb->input['page'] - 1) * $perpage;
	}
	else {
		$mybb->input['page'] = 1;
	}

	$query = $db->simple_select('bankpipe_payments', 'COUNT(pid) AS num_results');
	$num_results = $db->fetch_field($query, 'num_results');

	if ($num_results > $perpage) {
		echo draw_admin_pagination($mybb->input['page'], $perpage, $num_results, MAINURL . "&amp;action=history");
	}

	$table = new Table;

	$table->construct_header($lang->bankpipe_history_header_user, ['width' => '15%']);
	$table->construct_header($lang->bankpipe_history_header_item);
	$table->construct_header($lang->bankpipe_history_header_date, ['width' => '10%']);
	$table->construct_header($lang->bankpipe_history_header_expires, ['width' => '10%']);
	$table->construct_header($lang->bankpipe_history_header_options, ['width' => '10%']);

	$query = $db->query('
		SELECT p.*, i.name, i.price, u.username, u.usergroup, u.displaygroup, u.avatar
		FROM ' . TABLE_PREFIX . 'bankpipe_payments p
		LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid = p.uid)
		LEFT JOIN ' . TABLE_PREFIX . 'bankpipe_items i ON (i.bid = p.bid)
		ORDER BY p.date DESC
		LIMIT ' . (int) $start . ', ' . (int) $perpage . '
	');
	while ($purchase = $db->fetch_array($query)) {

		// User
		$username = format_name($purchase['username'], $purchase['usergroup'], $purchase['displaygroup']);
		$username = build_profile_link($username, $purchase['uid']);

		$avatar = format_avatar($purchase['avatar']);

		$table->construct_cell('<img src="' . $avatar['image'] . '" style="height: 20px; width: 20px; vertical-align: middle" /> ' . $username);

		// Expires
		$class = $extra = '';
		$expires = ($purchase['expires']) ? my_date('relative', $purchase['expires']) : $lang->bankpipe_history_expires_never;		
		if ($purchase['refund']) {

			$extra = $lang->bankpipe_history_refunded;
			$class = 'refunded';

		}
		else if ($purchase['expires'] and $purchase['expires'] < TIME_NOW) {

			$expires = $lang->bankpipe_history_expires_expired;
			$class = 'expired';

		}
		else if (!$purchase['active']) {

			$extra = $lang->bankpipe_history_inactive;
			$class = 'inactive';

		}

		// Item
		$table->construct_cell(htmlspecialchars_uni($purchase['name']) . $extra);

		// Date
		$table->construct_cell(my_date('relative', $purchase['date']));

		// Expires
		$table->construct_cell($expires);

		// Options
		$popup = new PopupMenu("options_" . $purchase['pid'], $lang->bankpipe_options);
		$popup->add_item($lang->bankpipe_history_edit, MAINURL . '&amp;action=edit_purchase&amp;pid=' . $purchase['pid']);

		if ($purchase['sale'] and !$purchase['refund']) {
			$popup->add_item($lang->bankpipe_history_refund, MAINURL . '&amp;action=refund_purchase&amp;pid=' . $purchase['pid']);
		}

		$popup->add_item($lang->bankpipe_history_revoke, MAINURL . '&amp;action=revoke_purchase&amp;pid=' . $purchase['pid']);
		$table->construct_cell($popup->fetch());
		$table->construct_row(['class' => $class]);

	}

	if ($db->num_rows($query) == 0) {
		$table->construct_cell($lang->bankpipe_history_no_payments, ['colspan' => 5, 'style' => 'text-align: center']);
		$table->construct_row();
	}

	$table->output($lang->bankpipe_history);

	echo <<<HTML
<style type="text/css">
	.expired td {
		background: #e0e0e0!important;
		text-decoration: line-through
	}
	.inactive td {
		background: #a1a1a1!important
	}
	.refunded td {
		background: lightblue!important
	}
</style>
HTML;

}
// Notifications
else if ($mybb->input['action'] == 'notifications') {

	$page->add_breadcrumb_item($lang->bankpipe_notifications, MAINURL);

	$page->output_header($lang->bankpipe_notifications);

	$page->output_nav_tabs($sub_tabs, 'notifications');

	$table = new Table;

	$table->construct_header($lang->bankpipe_notifications_header_notification);
	$table->construct_header($lang->bankpipe_notifications_header_days_before, ['width' => '200px']);

	$query = $db->simple_select('bankpipe_notifications', '*');
	while ($notification = $db->fetch_array($query)) {

		$table->construct_cell("<a href='" . MAINURL . "&amp;action=manage_notification&amp;nid={$notification['nid']}'>{$notification['title']}</a>");
		$table->construct_cell($notification['daysbefore']);
		$table->construct_row();

	}

	if ($db->num_rows($query) == 0) {
		$table->construct_cell($lang->bankpipe_notifications_no_notification, ['colspan' => 2]);
		$table->construct_row();
	}

	$table->output($lang->bankpipe_notifications . $lang->bankpipe_new_notification);

}
// Manual mode
else if ($mybb->input['action'] == 'manual_add') {

	$subscriptions = $users = $uids = [];

	$query = $db->simple_select('bankpipe_items', 'bid, name, gid, primarygroup', "gid <> 0", ['order_by' => 'price ASC']);
	while ($subscription = $db->fetch_array($query)) {
		$subscriptions[$subscription['bid']] = $subscription;
	}

	if (!array_filter($subscriptions)) {
		flash_message($lang->bankpipe_error_missing_subscriptions, 'error');
		admin_redirect(MAINURL);
	}

	if ($mybb->request_method == 'post') {

		$startDate = strtotime(str_replace('/', '-', (string) $mybb->input['startdate']));
		$endDate = ($mybb->input['enddate']) ? strtotime(str_replace('/', '-', (string) $mybb->input['enddate'])) + 60*60*24 : 0; // strtotime calculates dates from midnight, add a day to the ending one

		// Get users
		$where = explode(',', (string) $mybb->input['users']);

		if ($where) {

			$query = $db->simple_select('users', 'uid, usergroup, additionalgroups', "username IN ('" . implode("','", $where) . "')");
			while ($user = $db->fetch_array($query)) {
				$uids[] = (int) $user['uid'];
				$users[$user['uid']] = $user;
			}

		}

		// Get users in selected usergroups
		$usergroups = (array) $mybb->input['usergroups'];

		if ($usergroups) {

			$query = $db->simple_select('users', 'uid, usergroup, additionalgroups', "usergroup IN ('" . implode("','", $usergroups) . "')");
			while ($user = $db->fetch_array($query)) {
				$uids[] = (int) $user['uid'];
				$users[$user['uid']] = $user;
			}

		}

		// Normalize uids array
		if ($uids) {
			$uids = array_filter($uids);
		}

		if ($startDate > TIME_NOW or ($endDate and $endDate < TIME_NOW)) {
			flash_message($lang->bankpipe_error_incorrect_dates, 'error');
			admin_redirect(MAINURL . '&amp;action=manual_add');
		}

		// For multiple users, sale ID is disabled
		if (count($uids) > 1) {
			$mybb->input['sale'] = '';
		}

		$bid = (int) $mybb->input['subscription'];

		// Check if not existing already
		$activeSubs = $data = [];

		$query = $db->simple_select('bankpipe_payments', 'uid', 'uid IN (' . implode(',', $uids) . ') AND active = 1');
		while ($activeSub = $db->fetch_field($query, 'uid')) {
			$activeSubs[] = $activeSub;
		}

		foreach ($uids as $uid) {

			if (!$uid or in_array($uid, $activeSubs)) {
				continue;
			}

			// Change usergroup
			if ($subscriptions[$bid]['primarygroup']) {
				$update = [
					'usergroup' => (int) $subscriptions[$bid]['gid'],
					'displaygroup' => (int) $subscriptions[$bid]['gid']
				];
			}
			else {

				$additionalGroups = (array) explode(',', $users[$uid]['additionalgroups']);

				// Check if the new gid is already present and eventually add it
				if (!in_array($subscriptions[$bid]['gid'], $additionalGroups)) {
					$additionalGroups[] = $subscriptions[$bid]['gid'];
				}

				$update = [
					'additionalgroups' => implode(',', $additionalGroups)
				];

			}

			$db->update_query('users', $update, "uid = '" . (int) $uid . "'");

			$arr = [
				'bid' => $bid,
				'uid' => (int) $uid,
				'sale' => $db->escape_string($mybb->input['sale']),
				'date' => $startDate,
				'expires' => $endDate,
				'newgid' => (int) $subscriptions[$bid]['gid']
			];

			$arr['oldgid'] = ($subscription[$bid]['expirygid']) ? (int) $subscription[$bid]['expirygid'] : (int) $users[$uid]['usergroup'];

			$data[] = $arr;

		}

		$data = array_filter($data);

		if ($data) {
			$db->insert_query_multiple('bankpipe_payments', $data);
		}

		// Redirect
		flash_message($lang->bankpipe_success_users_added, 'success');
		admin_redirect(MAINURL);

	}

	$displaySubscriptions = [];
	// Generate sub list
	foreach ($subscriptions as $sub) {
		$displaySubscriptions[$sub['bid']] = $sub['name'];
	}

	$page->add_breadcrumb_item($lang->bankpipe_manual_add, MAINURL . '&amp;action=manual_add');

	$page->output_header($lang->bankpipe_manual_add);

	$page->output_nav_tabs($sub_tabs, 'manual_add');

	// Determine the post request attributes
	$form = new Form(MAINURL . "&amp;action=manual_add", "post", "manual_add");

	$form_container = new FormContainer($lang->bankpipe_manual_add);

	$form_container->output_row($lang->bankpipe_manual_add_user, $lang->bankpipe_manual_add_user_desc, $form->generate_text_box('users', $mybb->input['users'], [
		'id' => 'users'
	]), 'users');

	$usergroups = [];

	$groups_cache = $cache->read('usergroups');
	foreach ($groups_cache as $group) {
		$usergroups[$group['gid']] = $group['title'];
	}

	$form_container->output_row($lang->bankpipe_manual_add_usergroup, $lang->bankpipe_manual_add_usergroup_desc, $form->generate_select_box('usergroups[]', $usergroups, (array) $mybb->input['usergroups'], [
		'id' => 'usergroup',
		'multiple' => true
	]));

	$form_container->output_row($lang->bankpipe_manual_add_subscription, $lang->bankpipe_manual_add_subscription_desc, $form->generate_select_box('subscription', $displaySubscriptions, [$mybb->input['subscription']], [
		'id' => 'subscription'
	]));

	$form_container->output_row($lang->bankpipe_manual_add_start_date, $lang->bankpipe_manual_add_start_date_desc, $form->generate_text_box('startdate', $mybb->input['startdate'], [
		'id' => 'startdate'
	]), 'startdate');

	$form_container->output_row($lang->bankpipe_manual_add_end_date, $lang->bankpipe_manual_add_end_date_desc, $form->generate_text_box('enddate', $mybb->input['enddate'], [
		'id' => 'enddate'
	]), 'enddate');

	$form_container->output_row($lang->bankpipe_manual_add_sale_id, $lang->bankpipe_manual_add_sale_id_desc, $form->generate_text_box('sale', $mybb->input['sale'], [
		'id' => 'sale'
	]), 'sale');

	$form_container->end();

	$buttons = [
		$form->generate_submit_button($lang->bankpipe_save)
	];
	$form->output_submit_wrapper($buttons);
	$form->end();

	// JS routines
	echo '
<link rel="stylesheet" href="../jscripts/select2/select2.css" type="text/css" />
<script type="text/javascript" src="../jscripts/select2/select2.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.css" type="text/css" />
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.js"></script>
<script type="text/javascript">
<!--
// Date picking
var start = $("#startdate").datepicker({
	autoPick: true,
	endDate: new Date(),
	autoHide: true,
	format: \'dd/mm/yyyy\'
});

var end = $("#enddate").datepicker({
	autoPick: true,
	autoHide: true,
	format: \'dd/mm/yyyy\'
});

start.on("pick.datepicker", (e) => {
	return end.datepicker("show");
});

// Autocomplete
$("#users").select2({
	placeholder: "'.$lang->search_for_a_user.'",
	minimumInputLength: 2,
	multiple: true,
	ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
		url: "../xmlhttp.php?action=get_users",
		dataType: \'json\',
		data: function (term, page) {
			return {
				query: term // search term
			};
		},
		results: function (data, page) { // parse the results into the format expected by Select2.
			// since we are using custom formatting functions we do not need to alter remote JSON data
			return {results: data};
		}
	},
	initSelection: function(element, callback) {
		var query = $(element).val();
		if (query !== "") {
			$.ajax("../xmlhttp.php?action=get_users&getone=1", {
				data: {
					query: query
				},
				dataType: "json"
			}).done(function(data) { callback(data); });
		}
	}
});
// -->
</script>';

}
// Edit/add notification
else if ($mybb->input['action'] == 'manage_notification') {

	// Get this notification
	$nid = (int) $mybb->get_input('nid');

	if ($nid) {

		$query = $db->simple_select('bankpipe_notifications', '*', "nid = '" . $nid . "'", ['limit' => 1]);
		$notification = $db->fetch_array($query);

		if (!$notification['nid']) {
			flash_message($lang->bankpipe_error_invalid_notification);
			admin_redirect(MAINURL);
		}

	}

	if ($mybb->request_method == 'post') {

		$data = [
			'title' => $db->escape_string($mybb->input['title']),
			'description' => $db->escape_string($mybb->input['description']),
			'daysbefore' => (int) $mybb->input['daysbefore'],
			'method' => $db->escape_string($mybb->input['method'])
		];

		if (!$nid) {
			$message = $lang->bankpipe_success_notification_added;
			$db->insert_query('bankpipe_notifications', $data);
		}
		else {
			$message = $lang->bankpipe_success_notification_edited;
			$db->update_query('bankpipe_notifications', $data, "nid = '" . $notification['nid'] . "'");
		}

		// Redirect
		flash_message($message, 'success');
		admin_redirect(MAINURL);

	}

	// Default values
	if ($nid) {

		foreach ($notification as $field => $value) {
			$mybb->input[$field] = $value;
		}

	}

	$title = ($nid) ? $lang->sprintf($lang->bankpipe_edit_notification, $notification['title']) : $lang->bankpipe_add_notification;

	$page->add_breadcrumb_item($title, MAINURL . '&amp;action=notifications');

	$page->output_header($title);

	$page->output_nav_tabs($sub_tabs, 'notifications');

	// Determine the post request attributes
	$extraAction = ($nid) ? "&amp;nid=" . $notification['nid'] : '';

	$form = new Form(MAINURL . "&amp;action=manage_notification" . $extraAction, "post", "manage_notification");

	$form_container = new FormContainer($title);

	$form_container->output_row($lang->bankpipe_manage_notification_title, $lang->bankpipe_manage_notification_title_desc, $form->generate_text_box('title', $mybb->input['title'], [
		'id' => 'title'
	]), 'title');

	$form_container->output_row($lang->bankpipe_manage_notification_description, $lang->bankpipe_manage_notification_description_desc, $form->generate_text_area('description', $mybb->input['description'], [
		'id' => 'description'
	]), 'description');

	$form_container->output_row($lang->bankpipe_manage_notification_method, $lang->bankpipe_manage_notification_method_desc, $form->generate_select_box('method', [
		'pm' => 'Private message',
		'email' => 'Email'
	], $mybb->input['method'], [
		'id' => 'method'
	]));

	$form_container->output_row($lang->bankpipe_manage_notification_daysbefore, $lang->bankpipe_manage_notification_daysbefore_desc, $form->generate_text_box('daysbefore', $mybb->input['daysbefore'], [
		'id' => 'daysbefore'
	]));

	$form_container->end();

	$buttons = [
		$form->generate_submit_button($lang->bankpipe_save)
	];
	$form->output_submit_wrapper($buttons);
	$form->end();

}
// Main page
else if (!$mybb->input['action']) {

	$page->add_breadcrumb_item($lang->bankpipe, MAINURL);

	$page->output_header($lang->bankpipe);

	$page->output_nav_tabs($sub_tabs, 'general');

	$table = new Table;

	$table->construct_header($lang->bankpipe_subscriptions_name);
	$table->construct_header($lang->bankpipe_subscriptions_price);

	$query = $db->simple_select('bankpipe_items', '*', "gid <> 0", ['order_by' => 'price ASC']);
	while ($subscription = $db->fetch_array($query)) {

		$table->construct_cell("<a href='" . MAINURL . "&amp;action=manage_subscription&amp;bid={$subscription['bid']}'>{$subscription['name']}</a>");
		$table->construct_cell($subscription['price']);
		$table->construct_row();

	}

	if ($db->num_rows($query) == 0) {
		$table->construct_cell($lang->bankpipe_subscriptions_no_subscription, ['colspan' => 2]);
		$table->construct_row();
	}

	$table->output($lang->bankpipe_overview_available_subscriptions . $lang->bankpipe_new_subscription);

}

$page->output_footer();

// Requires oldgid, primarygroup, additionalgroups, newgid
function bankpipe_revert_usergroup($subscription)
{
	global $mybb, $db;

	// Revert usergroup
	$oldGroup = (int) $subscription['oldgid'];

	if ($oldGroup) {

		if ($subscription['primarygroup']) {
			$data = [
				'usergroup' => $oldGroup,
				'displaygroup' => $oldGroup
			];
		}
		else {

			$additionalGroups = (array) explode(',', $subscription['additionalgroups']);

			// Check if the old gid is already present and eventually add it
			if (!in_array($oldGroup, $additionalGroups)) {
				$additionalGroups[] = $oldGroup;
			}

			// Remove the new gid
			if (($key = array_search($subscription['newgid'], $additionalGroups)) !== false) {
				unset($additionalGroups[$key]);
			}

			$data = [
				'additionalgroups' => implode(',', $additionalGroups)
			];

		}

		$db->update_query('users', $data, "uid = '" . (int) $subscription['uid'] . "'");

	}
}