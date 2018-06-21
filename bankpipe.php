<?php

define("IN_MYBB", 1);

require_once "./global.php";
require_once MYBB_ROOT . 'inc/plugins/BankPipe/core.php';
$PayPal = new BankPipe();

$lang->load('bankpipe');

if ($mybb->input['action'] == 'create-payment') {

	// Check if we're allowed to see this item
	$bid = (int) $mybb->get_input('item');
	$items = bankpipe_get_items([$bid]);

	if (!$items[$bid]['error']) {

		$items[$bid]['currency'] = $mybb->settings['bankpipe_currency'];

		// Is there a third party payee we should send money to?
		$query = $db->simple_select('users', 'payee', 'uid = ' . (int) $items[$bid]['uid']);
		$email = $db->fetch_field($query, 'payee');

		$items[$bid]['payee'] = ($email) ? $email : '';

		$payment = $PayPal->createPayment($items[$bid]);

		if (is_array($payment)) {
			$PayPal->send($payment);
		}
		else {
			$PayPal->send(['id' => $payment->getId()]);
		}

	}
	else {
		$PayPal->send($items[$bid]);
	}

}

if ($mybb->input['action'] == 'execute-payment') {

	$response = $PayPal->executePayment();

	if (!$response['error']) {
		$PayPal->send(['success' => $lang->sprintf($lang->bankpipe_success_purchased_item, $response['transactions'][0]['item_list']['items'][0]['name']), 'data' => $response, 'reload' => 3]);
	}
	else {
		$response['reload'] = 8;
		$PayPal->send($response);
	}
}