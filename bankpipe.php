<?php

define("IN_MYBB", 1);

require_once "./global.php";
require_once MYBB_ROOT . 'inc/plugins/BankPipe/core.php';
$PayPal = new BankPipe();

$lang->load('bankpipe');

if ($mybb->input['action'] == 'create-payment') {
	
	$errors = [];
	$requiredFields = array_filter(explode(',', trim($mybb->settings['bankpipe_required_fields'])));
	
	// Required fields
	if ($requiredFields) {
		
		$requiredFields = array_map('trim', $requiredFields);
		
		foreach ($requiredFields as $field) {
			
			if (!$mybb->input[$field]) {
				$errors[] = $lang->bankpipe_error_missing_required_field;
				break;
			}
			
		}
		
	}
	
	// Get items from cookies if using cart mode
	if ($mybb->settings['bankpipe_cart_mode'] and $mybb->input['fromCookies'] and $mybb->cookies['bankpipe-items']) {
		
		$existingItems = ($mybb->cookies['bankpipe-items']) ? array_filter((array) json_decode($mybb->cookies['bankpipe-items'])) : [];
		$existingItems = array_map('intval', $existingItems);
		
		$items = bankpipe_get_paid_attachments($existingItems);
		if ($items['bid']) {
			$items = [$items];
		}
		
	}
	// Get items from input
	else {
		$items = bankpipe_get_items([(int) $mybb->get_input('item')]);
	}
	
	if ($items['error']) {
		$errors[] = $items['error'];
	}

	// If no errors are found, go ahead by preparing the payment
	if (!$errors) {
		
		// Get first item out
		$first = reset($items);

		// Is there a third party payee we should send money to?
		$query = $db->simple_select('users', 'payee', 'uid = ' . (int) $first['uid']);
		$email = $db->fetch_field($query, 'payee');
		
		// Build the array to send
		$send = [
			'customs' => $items,
			'currency' => $mybb->settings['bankpipe_currency'],
			'payee' => ($email) ? $email : ''
		];

		$payment = $PayPal->createPayment($send);

		if (is_array($payment)) {
			$PayPal->send($payment);
		}
		else {
			$PayPal->send(['id' => $payment->getId()]);
		}

	}
	else {
		$PayPal->send(['error' => implode("<br>", $errors)]); // Send errors back
	}

}

if ($mybb->input['action'] == 'execute-payment') {

	$response = $PayPal->executePayment();

	if (!$response['error']) {
		
		// Wipe cart cookies
		if ($mybb->settings['bankpipe_cart_mode'] and $mybb->cookies['bankpipe-items']) {
			my_unsetcookie('bankpipe-items');
		}
		
		// Wipe discount cookies
		if ($mybb->cookies['bankpipe-discounts']) {
			my_unsetcookie('bankpipe-discounts');
		}
		
		// TO-DO: replace with purchase recap
		$PayPal->send(['success' => $lang->sprintf($lang->bankpipe_success_purchased_item, $response['transactions'][0]['item_list']['items'][0]['name']), 'data' => $response, 'reload' => 3]);
		
	}
	else {
		$response['reload'] = 8;
		$PayPal->send($response);
	}
}