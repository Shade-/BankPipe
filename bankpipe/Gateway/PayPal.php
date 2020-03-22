<?php

/**
 * PayPal REST adapter for BankPipe
 */

namespace BankPipe\Gateway;

use Omnipay\Omnipay;
use BankPipe\Core;
use BankPipe\Items\Orders;

class PayPal extends Core
{
    protected $gatewayName = 'PayPal';

    public function __construct()
    {
        parent::__construct();

        $this->gateway = Omnipay::create('PayPal_Rest');

        $this->gateway->setClientId($this->gateways[$this->gatewayName]['id']);
        $this->gateway->setSecret($this->gateways[$this->gatewayName]['secret']);

        if ($this->gateways[$this->gatewayName]['sandbox']) {
            $this->gateway->setTestMode(true);
        }

        $GLOBALS['plugins']->run_hooks('bankpipe_paypal_construct', $this);
    }

    public function purchase(array $parameters = [], array $items = [])
    {   
        return parent::purchase($parameters, $items);
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

            $update['wallet'] = $this->db->escape_string($merchant['email']);

            $query = $this->db->simple_select('bankpipe_wallets', 'uid', $this->gatewayName . " = '" . $update['wallet'] . "'");
            $uid = (int) $this->db->fetch_field($query, 'uid');

            if ($uid) {
                $update['merchant'] = $uid;
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

            $bids = ($order['items'])
                ? self::normalizeArray(array_column($order['items'], 'bid'))
                : [];

            $this->log->save([
                'type' => $status,
                'bids' => $bids
            ]);

        }
        else {

            $order = array_merge($order, $update);
            $this->createNotifications($order);

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
                'payment_id' => $data->resource->id,
                'OR' => [
                    'sale' => $data->resource->id
                ]
            ], [
                'includeItemsInfo' => true
            ]));

            if (!$order['invoice']) {

                return $this->log->save([
                    'message' => $this->lang->sprintf(
                        $this->lang->bankpipe_error_webhook_no_matching_items, $data->resource->id
                    )
                ]);

            }

            $bids = array_column($order['items'], 'bid');
            $date = strtotime($data->create_time);

            http_response_code(200);

            if ($data->event_type == 'PAYMENT.SALE.COMPLETED') {

                // Perform operations if there is no success record for this order
                $query = $this->db->simple_select(
                    'bankpipe_log',
                    'lid',
                    'invoice = \'' . $order['invoice'] . '\' AND type = ' . Orders::SUCCESS
                );
                $id = $this->db->fetch_field($query, 'lid');

                if (!$id) {

                    $this->orders->update([
                        'type' => Orders::SUCCESS,
                        'active' => 1,
                        'fee' => self::filterPrice($data->resource->transaction_fee->value)
                    ], $order['invoice']);

                    $this->updateUsergroup($order['items'], $order['uid']);

                    $order['fee'] = self::filterPrice($data->resource->transaction_fee->value);
                    $this->createNotifications($order);
                    $this->notifications->send();

                    $this->log->save([
                        'type' => Orders::SUCCESS,
                        'bids' => $bids,
                        'invoice' => $order['invoice'],
                        'uid' => $order['uid'],
                        'date' => $date
                    ]);

                }
                else {
                    $this->db->update_query('bankpipe_log', ['date' => $date], 'lid = ' . (int) $id);
                }

            }
            else if ($data->event_type == 'PAYMENT.SALE.DENIED') {

                $this->orders->destroy($order['invoice']);
                $this->log->save([
                    'type' => Orders::CANCEL,
                    'bids' => $bids,
                    'invoice' => $order['invoice'],
                    'uid' => $order['uid'],
                    'date' => $date
                ]);

            }
            else if (in_array($data->event_type, ['PAYMENT.SALE.CREATED', 'PAYMENTS.PAYMENT.CREATED'])) {

                // Just update the log's date
                $this->db->update_query(
                    'bankpipe_log',
                    [
                        'date' => $date
                    ],
                    'invoice = \'' . $order['invoice'] . '\' AND type = ' . Orders::CREATE
                );

            }
            else {

                $this->log->save([
                    'message' => json_encode($data),
                    'bids' => $bids,
                    'invoice' => $order['invoice'],
                    'uid' => $order['uid'],
                    'date' => $date
                ]);

            }

            $args = [&$this, &$order, &$data];
            $GLOBALS['plugins']->run_hooks('bankpipe_paypal_webhooks_end', $args);

        }

    }

    protected function createNotifications(array $order = [])
    {
        // Success notifications
        $names = array_column($order['items'], 'name');
        $currency = Core::friendlyCurrency($order['currency']);

        // Gifted?
        $donation = ($order['donor']) ? true : false;

        $buyerUid = ($donation) ? (int) $order['donor'] : (int) $order['uid'];
        $buyer = ($buyerUid != $this->mybb->user['uid']) ? get_user($buyerUid) : $this->mybb->user;

        // Merchants and admins
        if ($this->mybb->settings['bankpipe_admin_notification']) {

            $receivers = explode(',', $this->mybb->settings['bankpipe_admin_notification']);

            if ($order['merchant']) {
                $receivers[] = $order['merchant'];
            }

            $netRevenue = $order['total'] - $order['fee'];

            $title = $this->lang->sprintf(
                $this->lang->bankpipe_notification_merchant_purchase_title,
                $buyer['username'],
                $order['total'],
                $currency
            );
            $message = $this->lang->sprintf(
                $this->lang->bankpipe_notification_merchant_purchase,
                $buyer['username'],
                $this->mybb->settings['bburl'] . '/' . get_profile_link($buyer['uid']),
                '[*]' . implode("\n[*]", $names),
                $order['total'],
                $order['fee'],
                $netRevenue,
                $currency,
                $order['wallet'],
                $this->mybb->settings['bbname']
            );

            $this->notifications->set($receivers, $title, $message);

        }

        // Buyer
        $receivers = [$buyerUid];

        $user = ($donation) ? get_user($order['uid']) : [];
        $donationLabel = ($donation) ? $this->lang->sprintf($this->lang->bankpipe_notification_donation_label, $user['username']) : '';

        $title = $this->lang->sprintf(
            $this->lang->bankpipe_notification_buyer_purchase_title,
            $order['invoice']
        );
        $message = $this->lang->sprintf(
            $this->lang->bankpipe_notification_buyer_purchase,
            $buyer['username'],
            $order['invoice'],
            '[*]' . implode("\n[*]", $names),
            $order['total'],
            $currency,
            $order['wallet'],
            $this->mybb->settings['bburl'] . '/usercp.php?action=purchases&env=bankpipe&invoice=' . $order['invoice'],
            $this->mybb->settings['bbname'],
            $donationLabel
        );

        $this->notifications->set($receivers, $title, $message);

        // Eventual donation, send to gifted user
        if ($donation) {

            $receivers = [$user['uid']];

            $title = $this->lang->sprintf(
                $this->lang->bankpipe_notification_donor_purchase_title,
                $buyer['username']
            );
            $message = $this->lang->sprintf(
                $this->lang->bankpipe_notification_donor_purchase,
                $user['username'],
                $buyer['username'],
                '[*]' . implode("\n[*]", $names),
                $this->mybb->settings['bbname']
            );

            $this->notifications->set($receivers, $title, $message);

        }
    }
}
