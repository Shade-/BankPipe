<?php

/**
 * PayPal REST adapter for BankPipe
 */

namespace BankPipe\Gateway;

use Omnipay\Omnipay;
use BankPipe\Core;
use BankPipe\Items\Orders;
use BankPipe\Helper\Permissions;

class PayPal extends Core
{
	protected $gatewayName = 'PayPal';

	public function __construct()
    {
        parent::__construct();

        $this->gateway = Omnipay::create('PayPal_Rest');

		if (!$this->mybb->settings['bankpipe_client_id'] or !$this->mybb->settings['bankpipe_client_secret']) {
			error($this->lang->bankpipe_error_missing_tokens);
		}

        $this->gateway->setClientId($this->mybb->settings['bankpipe_client_id']);
        $this->gateway->setSecret($this->mybb->settings['bankpipe_client_secret']);

 		if ($this->mybb->settings['bankpipe_sandbox']) {
			$this->gateway->setTestMode(true);
 		}

		$GLOBALS['plugins']->run_hooks('bankpipe_paypal_construct', $this);
    }

    public function purchase(array $parameters = [], array $items = [])
    {
	 	$settings = [
	        'amount' => 0,
	        'currency' => $parameters['currency'],
	        'returnUrl' => $this->getReturnUrl(),
	        'cancelUrl' => $this->getCancelUrl()
	    ];

	    // Third party merchant
	    if ($parameters['merchant']) {
		    $settings['merchant'] = $parameters['merchant'];
	    }

	    $finalItems = $bids = $discounts = [];
	    $finalPrice = 0;

	    // Cache discounts
	    $appliedDiscounts = $this->cookies->read('discounts');
		if ($appliedDiscounts) {

			$search = implode(',', array_map('intval', $appliedDiscounts));

			$permissions = new Permissions;

			$query = $this->db->simple_select('bankpipe_discounts', '*', 'did IN (' . $search . ')');
			while ($discount = $this->db->fetch_array($query)) {
    			$discounts[] = $discount;
            }

        }

	    // Loop through all the items
	    foreach ($items as $item) {

		    $finalPrice = $price = self::filterPrice($item['price']);

		    $itemData = [
				'name' => $item['name'],
				'price' => $price,
				'quantity' => 1,
				'bid' => $item['bid']
			];

			// Expiration date
			if ($item['expires']) {
				$itemData['expires'] = (TIME_NOW + (60*60*24*$item['expires']));
			}

            // Description
		    if ($item['description']) {
			    $itemData['description'] = $item['description'];
		    }

            // Destination usergroup
    		if ($item['gid']) {

    			$itemData['oldgid'] = ($item['expirygid']) ? $item['expirygid'] : $this->mybb->user['usergroup'];
    			$itemData['newgid'] = $item['gid'];

    		}

			// Add this item to the final array
		    $finalItems[] = $itemData;

			// Apply discounts
			// Previous subscriptions
			$item['discount'] = (int) $item['discount'];

			if ($item['discount'] > 0) {

				// Search for the highest previous subscription of this user
				$query = $this->db->query('
					SELECT MAX(i.price) AS price, p.payment_id
					FROM ' . TABLE_PREFIX . 'bankpipe_items i
					LEFT JOIN ' . TABLE_PREFIX . 'bankpipe_payments p ON (p.uid = ' . (int) $this->mybb->user['uid'] . ' AND p.bid = i.bid AND p.active = 1)
					WHERE i.gid <> 0 AND p.payment_id IS NOT NULL AND i.bid <> ' . (int) $item['bid'] . '
					LIMIT 1
				');
				$oldSubscription = $this->db->fetch_array($query);

				// If found, calculate relative discount
				if ($oldSubscription['price']) {
					$price = ($item['price'] - ($oldSubscription['price'] * $item['discount'] / 100));
				}

				$price = ($price > 0) ? $price : 0;

				if ($price != $finalPrice) {

					// Set new price
					$finalPrice = $price;

					// Add discount
					$finalItems[] = [
						'name' => $this->lang->bankpipe_discount_previous_item,
						'description' => $this->lang->bankpipe_discount_previous_item_desc,
						'price' => self::filterPrice($price - $item['price']),
						'quantity' => 1
					];

				}

			}

			// Apply discounts
			if ($discounts and ($this->mybb->settings['bankpipe_cart_mode'] or $item['gid'])) {

				foreach ($discounts as $discount) {

					if (!$permissions->discountCheck($discount, $item)) {
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
							'description' => $this->lang->bankpipe_discount_code_desc,
							'price' => self::filterPrice($price - $finalPrice),
							'quantity' => 1,
							'did' => $discount['did']
						];

						// Set the new final price
						$finalPrice = $price;

					}

				}

			}

			$settings['amount'] += $finalPrice; // Add this item to the total

		}

		$settings['amount'] = self::filterPrice($settings['amount']);

	    return parent::purchase($settings, $finalItems);
    }

    public function complete(array $parameters = [])
    {
        $parameters['transactionReference'] = $this->mybb->input['paymentId'];
        $parameters['payerId'] = $this->mybb->input['PayerID'];

        $response = parent::complete($parameters);
        $response = $response['response'];

        $data = $response->getData();

        $transaction = $data['transactions'][0];
        $sale = $transaction['related_resources'][0]['sale'];
        $buyer = $data['payer']['payer_info'];
        $merchant = $transaction['payee'];
        $items = $transaction['item_list']['items'];

        $update = [
	        'payment_id' => $this->db->escape_string($data['id']),
	        'sale' => $this->db->escape_string($sale['id']),
			'email' => $this->db->escape_string($buyer['email']),
			'payer_id' => $this->db->escape_string($buyer['payer_id']),
			'country' => $this->db->escape_string($buyer['country_code']), // Fixes https://www.mybboost.com/thread-storing-buyer-s-country-in-database
			'currency' => $this->db->escape_string($transaction['amount']['currency'])
        ];

        // Add fee
        if ($sale['transaction_fee']['value']) {
	        $update['fee'] = self::filterPrice($sale['transaction_fee']['value']);
        }

        // Add merchant informations
        if ($merchant['email']) {

            $update['payee_email'] = $merchant['email'];

            $query = $this->db->simple_select('users', 'uid', "payee = '" . $this->db->escape_string($merchant['email']) . "'");
            $uid = (int) $this->db->fetch_field($query, 'uid');

            if ($uid) {
                $update['payee'] = $uid;
            }

        }

        $order = reset($this->orders->get([
            'invoice' => $this->orderId
        ], [
            'includeItemsInfo' => true
        ]));

        // Deactivate the purchase if not really completed. This might happen eg. if payment status is "pending"
        // Webhooks will handle this
        if ($sale['state'] != 'completed') {

	        $update['active'] = 0;
	        $status = $update['type'] = Orders::PENDING;

            $bids = self::normalizeArray(array_column($order['items'], 'bid'));

    		$this->log->save([
        		'type' => $status,
        		'bids' => $bids
    		]);

        }
        else {

            // Success notifications to merchants/admins
            if ($this->mybb->settings['bankpipe_admin_notification'] or $order['merchant']) {

                $receivers = explode(',', $this->mybb->settings['bankpipe_admin_notification']);

                if ($order['merchant']) {
                    $receivers[] = $order['merchant'];
                }

                $buyer = ($order['buyer'] != $this->mybb->user['uid']) ? get_user($order['buyer']) : $this->mybb->user;
                $names = array_column($order['items'], 'name');

                $netRevenue = $order['total'] - $update['fee'];
                $currency = Core::friendlyCurrency($update['currency']);

                $title = $this->lang->sprintf(
                    $this->lang->bankpipe_notification_purchase_title,
                    $buyer['username'],
                    $order['total'],
                    $currency
                );
                $message = $this->lang->sprintf(
                    $this->lang->bankpipe_notification_purchase,
                    $buyer['username'],
                    $this->mybb->settings['bburl'] . '/' . get_profile_link($buyer['uid']),
                    '[*]' . implode("\n[*]", $names),
                    $order['total'],
                    $update['fee'],
                    $netRevenue,
                    $currency,
                    $merchant['email'],
                    $this->mybb->settings['bbname']
                );

                // Will be sent out in bankpipe.php
                $this->notifications->set($receivers, $title, $message);

            }

	        $status = Orders::SUCCESS;

        }

        $this->orders->update($update, $this->orderId);

	    return [
	        'status' => $status,
	        'response' => $response,
	        'invoice' => $this->orderId
        ];
    }

    public function webhookListener()
    {
        if (!$this->verifyWebhookSignature()) {

        	return $this->log->save([
        	    'message' => $this->lang->bankpipe_error_webhook_signature_check_failed
            ]);

    	}
    	else {

        	$data = json_decode(trim(file_get_contents('php://input')));

            $order = reset($this->orders->get([
                'sale' => $data->resource->id
            ]));

            if (!$order['invoice']) {
                return $this->log->save([
                    'message' => $this->lang->sprintf(
                        $this->lang->bankpipe_error_webhook_no_matching_items, $data->resource->id
                    )
                ]);
            }

            if ($data->event_type == 'PAYMENT.SALE.COMPLETED') {

                $this->orders->update([
                    'type' => Orders::SUCCESS,
                    'active' => 1,
                    'fee' => self::filterPrice($data->resource->transaction_fee->value)
                ], $order['invoice']);

                // Add to logs if there is no success record for this order
                $query = $this->db->simple_select(
                    'bankpipe_log',
                    'lid',
                    'invoice = \'' . $order['invoice'] . '\' AND type <> ' . Orders::SUCCESS
                );
                if (!$this->db->fetch_field($query, 'lid')) {

                    $this->log->save([
                        'type' => Orders::SUCCESS,
                        'bids' => array_column($order['items'], 'bid'),
                        'invoice' => $order['invoice']
                    ]);

                }

            }
            else if ($data->event_type == 'PAYMENT.SALE.DENIED') {

                $this->orders->destroy($order['invoice']);
                $this->log->save([
                    'type' => Orders::CANCEL,
                    'invoice' => $order['invoice']
                ]);

            }
            else {

                $this->log->save([
                    'message' => $data->event_type,
                    'invoice' => $order['invoice']
                ]);

            }

        }

    }
}