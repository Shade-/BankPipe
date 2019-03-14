<?php

define("IN_MYBB", 1);

require_once './global.php';
include './bankpipe/autoload.php';

use BankPipe\Messages\Handler as Messages;
use BankPipe\Items\Orders;
use BankPipe\Core;
use BankPipe\Logs\Handler as Logs;

$messages = new Messages;
$orders = new Orders;
$log = new Logs;

// Initialize gateway
$defaultGateway = ($mybb->input['gateway']) ? $mybb->input['gateway'] : 'PayPal';

try {

	$className = 'BankPipe\Gateway\\' . $defaultGateway;
	$gateway = new $className();

}
catch (Throwable $t) {
	error($t->getMessage());
}

$lang->load('bankpipe');

$errors = [];

// User has cancelled the payment
if ($mybb->input['action'] == 'cancel') {
	
	$plugins->run_hooks('bankpipe_cancel');
	
	$orders->destroy($mybb->input['orderId']);
	
	$log->save([
        'type' => Orders::CANCEL,
    	'invoice' => $mybb->input['orderId']
	]);
	
	$messages->display([
		'cancelled' => true
	]);
	
}

if ($mybb->input['action'] == 'webhooks') {
	
	$plugins->run_hooks('bankpipe_webhooks');
	
	$gateway->webhookListener();
	
}

// User wants to pay
if ($mybb->input['action'] == 'authorize') {
    
    $items = [];
	
	// Required fields check
	$requiredFields = Core::normalizeArray(explode(',', $mybb->settings['bankpipe_required_fields']));

	if ($requiredFields) {

		$requiredFields = array_map('trim', $requiredFields);

		foreach ($requiredFields as $field) {

			if (!$mybb->input[$field]) {
				$errors[] = $lang->bankpipe_error_missing_required_field;
				break;
			}

		}

	}
	
	// Get items to buy from cookies
	if ($mybb->settings['bankpipe_cart_mode'] and $mybb->input['fromCookies'] and $mybb->cookies['bankpipe-items']) {

		$aids = ($mybb->cookies['bankpipe-items'])
			? array_filter((array) json_decode($mybb->cookies['bankpipe-items']))
			: [];

		$items = $gateway->items->getAttachments($aids);

	}
	// Get items to buy from input
	else if ($mybb->input['items']) {
    	
    	$bids = (array) explode(',', $mybb->input['items']);
    	
    	if ($bids) {
		    $items = $gateway->items->getItems($bids);
        }
        
	}
	
    // No items?
	if (!$items) {
		$errors[] = 'No items found';
	}
	
	// Get first item out
	$first = reset($items);

	// Is there a third party merchant we should send money to?
	$query = $db->simple_select('users', 'payee', 'uid = ' . (int) $first['uid']);
	$email = $db->fetch_field($query, 'payee');

	// Is there a merchant email?
	if ($first['email']) {
		$email = $first['email'];
	}
	
	if ($email and !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$errors[] = 'Merchant email is not valid';
	}
	
	$errors = $plugins->run_hooks('bankpipe_authorize_errors', $errors);
	
	// Check errors before authorizing
	if ($errors) {
		$messages->error($errors);
	}
	
	$parameters = [
		'merchant' => $email,
		'currency' => $mybb->settings['bankpipe_currency']
	];
	
	$args = [&$parameters, &$items];
    $plugins->run_hooks('bankpipe_authorize_before', $args);
	
	// Main call
	$response = $gateway->purchase($parameters, $items);
	
	$response = $plugins->run_hooks('bankpipe_authorize_after', $response);

	// Redirect user to off-site authorization screen
    if ($response->isRedirect()) {
        
        $messages->display([
	        'url' => $response->getRedirectUrl(),
	        'data' => $response->getRedirectData(),
	        'invoice' => $gateway->getOrderId()
        ]);
        
    }
    else {
	    $messages->error($response->getMessage());
    }

}

// User has approved the payment
if ($mybb->input['action'] == 'complete') {
	
	$response = $gateway->complete([
		'currency' => $mybb->settings['bankpipe_currency']
	]);
	
	$response = $plugins->run_hooks('bankpipe_complete', $response);
	
	// Send success notifications to admins/merchants
	$gateway->notifications->send();
    
    // Display message to user
    $messages->display([
    	'reference' => $response['response']->getTransactionReference(),
    	'message' => $response['response']->getMessage(),
    	'data' => $response['response']->getData(),
    	'status' => $response['status'],
    	'invoice' => $response['invoice']
    ]);
	
}
    
exit;