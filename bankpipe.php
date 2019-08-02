<?php

define("IN_MYBB", 1);

require_once './global.php';
include './bankpipe/autoload.php';

use BankPipe\Messages\Handler as Messages;
use BankPipe\Items\Orders;
use BankPipe\Core;
use BankPipe\Logs\Handler as Logs;
use BankPipe\Helper\Cookies;

$messages = new Messages;
$orders = new Orders;
$log = new Logs;
$cookies = new Cookies;

// Initialize gateway
$gatewayName = ($mybb->input['gateway']) ? $mybb->input['gateway'] : 'PayPal';

try {

    $className = 'BankPipe\Gateway\\' . $gatewayName;
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

    // Permissions check
    $order = $orders->get(['invoice' => $mybb->input['orderId']]);
    $selectedOrder = $order[$mybb->input['orderId']];

    if (!$selectedOrder) {

        $messages->error([
            $lang->bankpipe_error_order_not_found
        ]);

    }

    if ($selectedOrder['buyer'] != $mybb->user['uid'] or $selectedOrder['type'] != Orders::CREATE) {

        $messages->error([
            $lang->bankpipe_error_cannot_cancel_order
        ]);

    }

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

    // Gateway not enabled?
    if (!$gateway->gateways[$gatewayName]['enabled']) {
        $messages->error($lang->bankpipe_error_gateway_not_enabled);
    }

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
    $itemsInCart = $cookies->read('items');
    $items = $gateway->items->getItems($itemsInCart);

    // No items?
    if (!$items) {
        $errors[] = $lang->bankpipe_error_items_not_found;
    }

    // Default merchant
    $wallet = $gateway->gateways[$gatewayName]['wallet'];

    // Get first item out
    $first = reset($items);

    // Is there a third party merchant we should send money to?
    // No need to sanitize gatewayName, if the class doesn't exist nobody can get this far
    $query = $db->simple_select('bankpipe_wallets', $gatewayName, 'uid = ' . (int) $first['uid']);
    $result = $db->fetch_field($query, $gatewayName);

    if ($result) {
        $wallet = $result;
    }

    // Is there a custom merchant wallet override? This should be only used with subscriptions
    if ($first[$gatewayName]) {
        $wallet = $first[$gatewayName];
    }

    if ($gatewayName == 'PayPal' and $wallet and !filter_var($wallet, FILTER_VALIDATE_EMAIL)) {
        $errors[] = $lang->bankpipe_error_email_not_valid;
    }

    // Still no merchant to send money to? Abort!
    if (!$wallet and $gatewayName != 'Coinbase') {
        $errors[] = $lang->bankpipe_error_wallet_not_valid;
    }

    $errors = $plugins->run_hooks('bankpipe_authorize_errors', $errors);

    // Check errors before authorizing
    if ($errors) {
        $messages->error($errors);
    }

    $parameters = [
        'wallet' => $wallet,
        'currency' => $mybb->settings['bankpipe_currency']
    ];

    // Add merchant uid if no wallet override is in place
    if (!$first[$gatewayName] and $first['uid']) {
        $parameters['merchant'] = $first['uid'];
    }

    $args = [&$parameters, &$items];
    $plugins->run_hooks('bankpipe_authorize_before', $args);

    // Main call
    $response = $gateway->purchase($parameters, $items);

    $response = $plugins->run_hooks('bankpipe_authorize_after', $response);

    // Redirect user to off-site authorization screen
    if ($response->isRedirect()) {

        $messages->display([
            'url' => $response->getRedirectUrl(),
            'data' => $response->getData(),
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

    // Success? Send notifications, wipe cookies
    if ($response['status']) {

        // Send success notifications to admins/merchants
        $gateway->notifications->send();

        // Wipe cart cookies
        if ($mybb->cookies['bankpipe-items']) {
            $cookies->destroy('items');
        }

        // Wipe discount cookies
        if ($mybb->cookies['bankpipe-discounts']) {
            $cookies->destroy('discounts');
        }

    }

    // Display message to user
    $message = [
        'status' => $response['status'],
        'invoice' => $response['invoice']
    ];

    if ($response['response']) {
        $message['reference'] = $response['response']->getTransactionReference();
        $message['message'] = $response['response']->getMessage();
        $message['data'] = $response['response']->getData();
    }

    $messages->display($message, 'popup');

}

exit;
