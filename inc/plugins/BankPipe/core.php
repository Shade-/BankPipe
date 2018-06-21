<?php

class BankPipe
{
	public $context = [];
	public $payment;
	public function __construct()
	{
		global $mybb, $lang;

		if (!$lang->bankpipe) {
			$lang->load('bankpipe');
		}

		if (!$mybb->settings['bankpipe_client_id'] or !$mybb->settings['bankpipe_client_secret']) {
			error($lang->bankpipe_error_missing_tokens);
		}

		require_once MYBB_ROOT  . 'bankpipe/PayPal-PHP-SDK/autoload.php';

		$this->context = new \PayPal\Rest\ApiContext(
	        new \PayPal\Auth\OAuthTokenCredential(
	            $mybb->settings['bankpipe_client_id'],
	            $mybb->settings['bankpipe_client_secret']
	        )
		);

		// Go live
		if (!$mybb->settings['bankpipe_sandbox']) {

			$this->context->setConfig([
				    'mode' => 'live',
				    'log.LogEnabled' => true,
				    'log.FileName' => '../PayPal.log',
				    'log.LogLevel' => 'INFO',
				]
			);

		}
	}

	public function createPayment($item = [])
	{
		global $mybb, $db;

		$payee = ($item['payee']) ? $item['payee'] : $mybb->settings['bankpipe_subscription_payee'];

		$input = [
			'uid' => (int) $mybb->user['uid'],
			'bid' => (int) $item['bid'],
			'payee' => $payee
		];

		if (!$input['bid']) {
			return $this->error($item);
		}

		$input = base64_encode(serialize($input));

		$data = [
	        "intent" => "sale",
	        "redirect_urls" => [
	            "return_url" => $mybb->settings['bburl'] . "/bankpipe.php?action=execute-payment",
	            "cancel_url" => $mybb->settings['bburl'] . "/bankpipe.php?action=cancel-payment"
	        ],
	        "payer" => [
	            "payment_method" => "paypal"
	        ],
	        "transactions" => [
	            [
	                "amount" => [
	                    "total" => $item['price'],
	                    "currency" => $item['currency']
	                ],
					"invoice_number" => uniqid(),
					"payee" => [
						"email" => $payee,
					],
					"item_list" => [
						"items" => [
							[
								"name" => $item['name'],
								"price" => $item['price'],
								"currency" => $item['currency'],
								"quantity" => 1
							]
						]
					],
					"custom" => $input
	            ]
	        ]
	    ];

		// Apply discount
		$item['discount'] = (int) $item['discount'];

		if ($item['discount'] > 0) {

			// Search for the highest previous subscription of this user
			$query = $db->query('
				SELECT MAX(i.price) AS price, p.payment_id
				FROM ' . TABLE_PREFIX . 'bankpipe_items i
				LEFT JOIN ' . TABLE_PREFIX . 'bankpipe_payments p ON (p.uid = ' . (int) $mybb->user['uid'] . ' AND p.bid = i.bid AND p.active = 1)
				WHERE i.gid <> 0 AND p.payment_id IS NOT NULL AND i.bid <> ' . (int) $item['bid'] . '
				LIMIT 1
			');
			$oldSubscription = $db->fetch_array($query);

			// If found, calculate discount relative to that
			if ($oldSubscription['price']) {
				$price = ($item['price'] - ($oldSubscription['price'] * $item['discount'] / 100));
			}
			// Not found, apply discount relative to the current price
			else {

				$rate = ($item['discount'] > 100) ? 100 : $item['discount'];
				$price = $item['price'] - ($item['price'] * $rate / 100);

				if ($price <= 0) {
					$price = $item['price'];
				}

			}

			$data['transactions'][0]['amount']['total'] = $price;

			if ($price != $item['price']) {

				// Add Discount item
				$data['transactions'][0]['item_list']['items'][] = [
					'name' => 'Discount',
					'price' => ($price - $item['price']),
					'currency' => $item['currency'],
					'quantity' => 1
				];

			}

		}

	    if ($item['description']) {
		    $data['transactions'][0]['item_list']['items'][0]['description'] = $item['description'];
	    }

		$payment = new \PayPal\Api\Payment($data);

		try {
		    $payment->create($this->context);
		}
		catch (\PayPal\Exception\PayPalConnectionException $ex) {
		    return $this->error($ex->getData());
		}

		// Error handling
		if ($payment->getState() == 'failed') {
			return $this->error($payment->getFailureReason());
		}

		$this->log([
			'type' => 'created',
			'bid' => (int) $item['bid']
		]);

		return $payment;
	}

	public function executePayment()
	{
		global $mybb, $db, $lang;

		$paymentId = $mybb->get_input('paymentID');

	    $payment = \PayPal\Api\Payment::get($paymentId, $this->context);

	    $execution = new \PayPal\Api\PaymentExecution([
		    "payer_id" => $mybb->get_input('payerID')
	    ]);

	    try {
		    $payment = $payment->execute($execution, $this->context);
	    }
	    catch (Exception $ex) {
		    return $this->error($ex);
	    }

	    // Error handling
		if ($payment->getState() == 'failed') {
			return $this->error($payment->getFailureReason());
		}

		$state = $payment->getTransactions()[0]->getRelatedResources()[0]->getSale()->state;
		if ($state != 'completed') {

			if ($state == 'pending') {

				$input = unserialize(base64_decode($payment->getTransactions()[0]->custom));

				$this->log([
					'type' => 'pending',
					'bid' => (int) $input['bid'],
					'uid' => (int) $input['uid']
				]);

				return $this->error($lang->bankpipe_error_pending_payment);

			}
			else {
				return $this->error($lang->sprintf($lang->bankpipe_error_could_not_complete, $state)); // Not completed?
			}

		}

		// Finally process the payment by inserting it into the db and do what needs to be done
		$result = $this->processExecutedPayment($payment);

		if ($result['error']) {
			return $result;
		}

	    return $payment->toArray();
	}

	public function processExecutedPayment($payment)
	{
		global $mybb, $db, $lang;

		$transactions = $payment->getTransactions();
		$sale = $transactions[0]->getRelatedResources()[0]->getSale();

		$input = unserialize(base64_decode($transactions[0]->custom));

		$query = $db->simple_select('users', 'uid', "payee = '" . $db->escape_string($input['payee']) . "'");
		$payee = (int) $db->fetch_field($query, 'uid');

		// Add payment log
		$data = [
			'bid' => (int) $input['bid'],
			'uid' => (int) $input['uid'],
			'payment_id' => $db->escape_string($payment->id),
			'sale' => $db->escape_string($sale->getId()),
			'email' => $db->escape_string($payment->payer->payer_info->email),
			'payer_id' => $db->escape_string($payment->payer->payer_info->payer_id),
			'payee' => $payee,
			'invoice' => $db->escape_string($transactions[0]->invoice_number),
			'date' => TIME_NOW,
			'price' => $transactions[0]->amount->total
		];

		// Get purchased item data
		$query = $db->simple_select('bankpipe_items', '*', "bid = '" . (int) $input['bid'] . "'", ['limit' => 1]);
		$item = $db->fetch_array($query);

		// Expiry date
		if ($item['expires']) {
			$data['expires'] = (int) (TIME_NOW + (60*60*24*$item['expires']));
		}

		// Change usergroup
		if ($item['gid']) {

			if ($item['primarygroup']) {
				$update = [
					'usergroup' => (int) $item['gid'],
					'displaygroup' => (int) $item['gid']
				];
			}
			else {

				$additionalGroups = (array) explode(',', $mybb->user['additionalgroups']);

				// Check if the new gid is already present and eventually add it
				if (!in_array($item['gid'], $additionalGroups)) {
					$additionalGroups[] = $item['gid'];
				}

				$update = [
					'additionalgroups' => implode(',', $additionalGroups)
				];

			}

			$db->update_query('users', $update, "uid = '" . $data['uid'] . "'");

			// Add new and old gid to this payment
			$data['oldgid'] = ($item['expirygid']) ? (int) $item['expirygid'] : (int) $mybb->user['usergroup'];
			$data['newgid'] = (int) $item['gid'];

		}

		$db->insert_query('bankpipe_payments', $data);
		$pid = $db->insert_id();

		// If enabled, send out a reminder to a specified list of admins
		if ($mybb->settings['bankpipe_admin_notification'] or $payee['uid']) {

			$update_mailqueue = false;

			$uids = explode(',', $mybb->settings['bankpipe_admin_notification']);

			// Add the payee to the notification list, if available
			if ($payee['uid']) {
				$uids[] = $payee['uid'];
			}

			$uids = array_filter(array_unique(array_map('intval', $uids)));

			if (count($uids) > 0) {

				$mailqueue = $emails = $ugroups = [];

				$query = $db->simple_select('users', 'uid, username', 'uid = ' . $data['uid'], ['limit' => 1]);
				$buyer = $db->fetch_array($query);

				$query = $db->simple_select('usergroups', 'gid, title', "gid IN ('{$data['oldgid']}', '{$data['newgid']}')");
				while ($ugroup = $db->fetch_array($query)) {
					$ugroups[$ugroup['gid']] = $ugroup['title'];
				}

				// Prepare the message
				$buyer['link'] = $mybb->settings['bburl'] . '/' . get_profile_link($buyer['uid']);

				$moved = ($data['oldgid'] != $data['newgid']) ? $lang->sprintf($lang->bankpipe_notification_purchase_moved, $ugroups[$data['oldgid']], $ugroups[$data['newgid']]) : '';

				require_once MYBB_ROOT."inc/class_parser.php";
				$parser = new postParser;
				$parserOptions = [
					'allow_mycode' => 1,
					'allow_imgcode' => 1,
					'allow_videocode' => 1,
					'allow_smilies' => 1,
					'filter_badwords' => 1
				];

				$title = $lang->sprintf($lang->bankpipe_notification_purchase_title, $buyer['username'], $item['name'], $data['price'] . $mybb->settings['bankpipe_currency']);
				$message = $lang->sprintf($lang->bankpipe_notification_purchase, $buyer['username'], $buyer['link'], $item['name'], $data['price'] . $mybb->settings['bankpipe_currency'], $moved, $mybb->settings['bbname']);
				$message = $parser->parse_message($message, $parserOptions);

				// Cache user emails
				if ($mybb->settings['bankpipe_admin_notification_method'] != 'pm') {

					$query = $db->simple_select('users', 'uid, email', 'uid IN (' . implode(',', $uids) . ')');
					while ($user = $db->fetch_array($query)) {

						if ($user['email']) {
							$emails[$user['uid']] = $user['email'];
						}

					}

				}

				// Send notification
				foreach ($uids as $uid) {

					if ($mybb->settings['bankpipe_admin_notification_method'] == 'pm') {

						require_once MYBB_ROOT . "inc/datahandlers/pm.php";
						$pmhandler                 = new PMDataHandler();
						$pmhandler->admin_override = true;

						$pm = [
							"subject" => $title,
							"message" => $message,
							"fromid" => -1,
							"toid" => [
								$uid
							]
						];

						$pmhandler->set_data($pm);

						if ($pmhandler->validate_pm()) {
							$pmhandler->insert_pm();
						}

					}
					else if ($emails[$uid]) {

						$mailqueue[] = [
							"mailto" => $db->escape_string($emails[$uid]),
							"mailfrom" => '',
							"subject" => $db->escape_string($title),
							"message" => $db->escape_string($message),
							"headers" => ''
						];

						$update_mailqueue = true;

					}

				}

				if ($mailqueue) {
					$db->insert_query_multiple("mailqueue", $mailqueue);
				}

				if ($update_mailqueue) {
					$GLOBALS['cache']->update_mailqueue();
				}

			}

		}

		return $this->log([
			'type' => 'executed',
			'bid' => $data['bid'],
			'uid' => $data['uid'],
			'pid' => (int) $pid
		]);
	}

	public function getPaymentDetails($paymentId)
	{
		if (!$paymentId) {
			return false;
		}

		return (array) \PayPal\Api\Payment::get($paymentId, $this->context)->toArray();
	}

	public function refundPurchase($transaction, $amount)
	{
		if (!$transaction['sale']) {
			return false;
		}

		global $mybb, $db;

		// Set the amount. If blank, it's a total refund
		$refundAmount = [];

		if ($amount and $transaction['price'] and $amount < $transaction['price']) {
			$refundAmount = [
				'amount' => [
					'total' => $this->filterPrice($amount),
					'currency' => $mybb->settings['bankpipe_currency']
				]
			];
		}

		$sale = new \PayPal\Api\Sale([
			'id' => $transaction['sale']
		]);

		$refundRequest = new \PayPal\Api\RefundRequest($refundAmount);

		// Try to refund this sale
		try {
		    $refund = $sale->refundSale($refundRequest, $this->context);
	    }
	    catch (Exception $ex) {
		    return $this->error($ex);
	    }

	    if ($refund->state != 'completed') {
		    return $this->error($lang->sprintf($lang->bankpipe_error_could_not_complete_refund, $refund->state));
	    }

	    // Update payment status
	    $db->update_query('bankpipe_payments', [
		    'refund' => $db->escape_string($refund->id),
		    'active' => 0
	    ], "sale = '" . $db->escape_string($refund->sale_id) . "'");

		$this->log([
			'type' => 'refund',
			'bid' => (int) $transaction['bid'],
			'pid' => (int) $transaction['pid']
		]);

		return $refund->toArray();
	}

	public function send($data, $exit = false)
	{
		header('Content-Type: application/json');
		echo json_encode($data);
		if ($exit) {
			exit;
		}
	}

	public function error($data)
	{
		$this->log([
			'type' => 'error',
			'message' => $data
		]);

		return ['error' => $data];
	}

	public function filterPrice($price)
	{
		return filter_var(str_replace(',', '.', $price), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
	}

	public function log($data = [])
	{
		global $db, $mybb;

		if (!$data) {
			return false;
		}

		$arr = [
			'uid' => (int) $mybb->user['uid'],
			'date' => TIME_NOW
		];

		$arr = array_merge($arr, $data);

		$arr['message'] = $db->escape_string($arr['message']);

		return $db->insert_query('bankpipe_log', $arr);
	}
}