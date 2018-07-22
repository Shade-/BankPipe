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

	public function createPayment($items = [])
	{
		global $mybb, $db, $lang;

		$payee = ($items['payee']) ? $items['payee'] : $mybb->settings['bankpipe_subscription_payee'];
		$currency = $items['currency'];

		$custom = [
			'uid' => (int) $mybb->user['uid'],
			'payee' => $payee
		];
		
		// The final data sent to PayPal
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
	                    "total" => 0,
	                    "currency" => $currency
	                ],
					"invoice_number" => uniqid(),
					"payee" => [
						"email" => $payee,
					],
					"item_list" => [
						"items" => []
					]
				]
	        ]
	    ];
	    
	    $finalItems = $bids = $appliedDiscounts = [];
	    
	    // Loop through all the items
	    foreach ((array) $items['customs'] as $item) {
		    
		    $itemData = [
				"name" => $item['name'],
				"price" => $item['price'],
				"currency" => $currency,
				"quantity" => 1,
				"sku" => $item['bid']
			];
	
		    if ($item['description']) {
			    $itemData['description'] = $item['description'];
		    }
		    
		    $finalItems[] = $itemData;
		    $finalPrice = $item['price'];

			// Apply discounts
			// Previous subscriptions
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
	
				// If found, calculate relative discount
				if ($oldSubscription['price']) {
					$price = ($item['price'] - ($oldSubscription['price'] * $item['discount'] / 100));
				}
				// Not found, apply discount relative to the current price
				else {
	
					$rate = ($item['discount'] > 100) ? 100 : $item['discount'];
					$price = $item['price'] - ($item['price'] * $rate / 100);
	
				}
				
				$price = ($price > 0) ? $price : 0;
	
				if ($price != $finalPrice) {
					
					// Set new price
					$finalPrice = $price;
		
					// Add discount
					$finalItems[] = [
						'name' => $lang->bankpipe_discount_previous_item,
						'description' => $lang->bankpipe_discount_previous_item_desc,
						'price' => ($price - $item['price']),
						'currency' => $currency,
						'quantity' => 1,
						'sku' => $item['bid']
					];
	
				}
	
			}
			
			// Promo codes
			$existingDiscounts = bankpipe_read_cookie('discounts');
			
			if ($existingDiscounts and ($mybb->settings['bankpipe_cart_mode'] or $item['gid'])) {
				
				$search = implode(',', array_map('intval', $existingDiscounts));
					
				$query = $db->simple_select('bankpipe_discounts', 'value, code, type, bids, gids, uids', 'did IN (' . $search . ')');
				while ($discount = $db->fetch_array($query)) {
					
					if (!bankpipe_check_discount_permissions($discount, $item)) {
						continue;
					}
					
					// Finally apply the discount
					// Percentage
					if ($discount['type'] == 1) {
						$price = $finalPrice - ($finalPrice * $discount['value'] / 100);
					}
					// Absolute
					else {
						$price = $finalPrice - $discount['value'];
					}
					
					$price = ($price > 0) ? $price : 0;
					
					if ($price != $finalPrice) {
		
						// Add discount
						$finalItems[] = [
							'name' => $discount['code'],
							'description' => $lang->bankpipe_discount_code_desc,
							'price' => ($price - $finalPrice),
							'currency' => $currency,
							'quantity' => 1,
							'sku' => $item['bid']
						];
	
						// Set new price
						$finalPrice = $price;
						
					}
					
					$appliedDiscounts[] = $discount['code'];
					
				}
				
			}
			
			$bids[] = $item['bid'];
			$data['transactions'][0]['amount']['total'] += $finalPrice; // Add this item to the total
		   
		}
		
		if ($finalItems) {
			$data['transactions'][0]['item_list']['items'] = $finalItems;
		}
		else {
			// Error? No items?
		}
		
		$data['transactions'][0]['custom'] = base64_encode(serialize($custom));

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
		
		$log = [
			'type' => 'created',
			'bids' => implode('|', array_unique($bids))
		];
		
		if ($appliedDiscounts) {
			$log['message'] = $lang->sprintf($lang->bankpipe_discounts_applied, implode(', ', array_unique($appliedDiscounts)));
		}

		$this->log($log);

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
		
		$transaction = $payment->getTransactions()[0];

		$state = $transaction->getRelatedResources()[0]->getSale()->state;
		if ($state != 'completed') {

			if ($state == 'pending') {

				$input = unserialize(base64_decode($transaction->custom));
				
				$bids = [];
				foreach ((array) $transaction->item_list->getItems() as $item) {
					$bids[] = $item->getSku();
				}

				$this->log([
					'type' => 'pending',
					'bids' => implode('|', array_unique($bids)),
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

		$transaction = $payment->getTransactions()[0];
		$sale = $transaction->getRelatedResources()[0]->getSale();

		$input = unserialize(base64_decode($transaction->custom));
		$buyer = (int) $input['uid'];

		$query = $db->simple_select('users', 'uid', "payee = '" . $db->escape_string($input['payee']) . "'");
		$payee = (int) $db->fetch_field($query, 'uid');
		
		$totalPrice = $sku = 0;
		
		// Loop through each purchased item
		foreach ((array) $transaction->item_list->getItems() as $purchasedItem) {
			
			$sku = (int) $purchasedItem->getSku();
			
			$bids[] = $sku;
			
			$totalPrice += $purchasedItem->getPrice();

			// Add payment log
			// First time loop – the real item
			if (!$data[$sku]) {

				$data[$sku] = [
					'bid' => $sku,
					'uid' => $buyer,
					'payment_id' => $db->escape_string($payment->id),
					'sale' => $db->escape_string($sale->getId()),
					'email' => $db->escape_string($payment->payer->payer_info->email),
					'payer_id' => $db->escape_string($payment->payer->payer_info->payer_id),
					'country' => $db->escape_string($payment->payer->payer_info->country_code), // Fixes https://www.mybboost.com/thread-storing-buyer-s-country-in-database
					'payee' => $payee,
					'invoice' => $db->escape_string($transaction->invoice_number),
					'date' => TIME_NOW,
					'price' => $purchasedItem->getPrice()
				];
			
			}
			// N-time loop – discounts
			else {
				
				$data[$sku]['price'] += $purchasedItem->getPrice();
				$data[$sku]['discounts'][] = $purchasedItem->getName();
				
			}
		
		}
		
		$search = implode("','", array_map('intval', array_unique($bids)));
		
		$query = $db->simple_select('bankpipe_items', '*', "bid IN ('" . $search . "')");
		while($item = $db->fetch_array($query)) {
			$items[$item['bid']] = $item;
		}
		
		$itemNames = [];
		
		// Insert in db
		foreach ($data as $bid => $insert) {
			
			// Final retouches
			$item = $items[$bid];
	
			// Expiry date
			if ($item['expires']) {
				$insert['expires'] = (int) (TIME_NOW + (60*60*24*$item['expires']));
			}
			
			// Add name to the array of names – used for notifications
			$itemNames[] = $item['name'];
	
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
	
				$db->update_query('users', $update, "uid = '" . $buyer . "'");
	
				// Add new and old gid to this payment
				$insert['oldgid'] = ($item['expirygid']) ? (int) $item['expirygid'] : (int) $mybb->user['usergroup'];
				$insert['newgid'] = (int) $item['gid'];
	
			}
			
			if ($insert['discounts']) {
				
				$discounts = $insert['discounts'];
				unset($insert['discounts']);
				
			}
	
			// Insert in the db and log
			$db->insert_query('bankpipe_payments', $insert);
			$pid = $db->insert_id();
			
			$log = [
				'type' => 'executed',
				'bids' => $insert['bid'],
				'uid' => $insert['uid'],
				'pid' => (int) $pid
			];
			
			if ($discounts) {
				$log['message'] = $lang->sprintf($lang->bankpipe_discounts_applied, implode(', ', $discounts));
			}
			
			$this->log($log);
		
		}

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

				$mailqueue = $emails = [];

				$query = $db->simple_select('users', 'uid, username', 'uid = ' . (int) $input['uid'], ['limit' => 1]);
				$buyer = $db->fetch_array($query);

				// Prepare the message
				$buyer['link'] = $mybb->settings['bburl'] . '/' . get_profile_link($buyer['uid']);

				$names = '[*]' . implode("\n[*]", $itemNames);

				$title = $lang->sprintf($lang->bankpipe_notification_purchase_title, $buyer['username'], $totalPrice . $mybb->settings['bankpipe_currency']);
				$message = $lang->sprintf($lang->bankpipe_notification_purchase, $buyer['username'], $buyer['link'], $names, $totalPrice . $mybb->settings['bankpipe_currency'], $mybb->settings['bbname']);
				
				// Parse for emails
				if ($mybb->settings['bankpipe_admin_notification_method'] != 'pm') {
					
					require_once MYBB_ROOT."inc/class_parser.php";
					$parser = new postParser;
					$parserOptions = [
						'allow_mycode' => 1,
						'allow_imgcode' => 1,
						'allow_videocode' => 1,
						'allow_smilies' => 1,
						'filter_badwords' => 1
					];
					
					$message = $parser->parse_message($message, $parserOptions);
					
					// Cache user emails
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

		return true;
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