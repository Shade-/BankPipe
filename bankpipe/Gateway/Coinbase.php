<?php

/**
 * Coinbase adapter for BankPipe
 */

namespace BankPipe\Gateway;

use Omnipay\Omnipay;
use BankPipe\Core;
use BankPipe\Items\Orders;
use BankPipe\Helper\Permissions;

class Coinbase extends Core
{
    protected $gatewayName = 'Coinbase';

    public function __construct()
    {
        parent::__construct();

        $this->gateway = Omnipay::create('Coinbase');

        $this->gateway->setApiKey($this->gateways[$this->gatewayName]['wallet']);

        $GLOBALS['plugins']->run_hooks('bankpipe_coinbase_construct', $this);
    }

    public function purchase(array $parameters = [], array $items = [])
    {
        // Custom wallet
        if ($parameters['wallet']) {
            $this->gateway->setApiKey($parameters['wallet']);
        }

        $parameters = array_merge($parameters, [
            'pricingType' => 'fixed_price',
            'redirect_url' => $this->getReturnUrl(),
            'metadata' => [
                'invoice' => $this->orderId
            ]
        ]);

        // Add name and description
        if (count($items) == 1) {

            $first = reset($items);

            $parameters['name'] = $first['name'];
            $parameters['description'] = $first['description'];

        }
        else {

            $parameters['name'] = 'Multiple items';
            $parameters['description'] = implode(', ', array_column($items, 'name'));

        }

        $parameters['description'] = substr($parameters['description'], 0, 199);

        // Directly set this order as pending, as Coinbase does not redirect back to our site
        $parameters['type'] = Orders::PENDING;

        $response = parent::purchase($parameters, $items);

        $data = $response->getData()['data'];

        // Add the payment id
        if ($data['code']) {

            $this->orders->update([
                'payment_id' => $data['code'],
            ], $this->orderId);

        }

        return $response;
    }

    public function complete(array $parameters = [])
    {   
        // Log this as pending, given cryptos need several confirmations before being approved.
        // This should be never called, but leaving it here just in case
        $this->orders->update([
            'type' => Orders::PENDING,
        ], $this->orderId);

        $order = reset($this->orders->get([
            'invoice' => $this->orderId
        ], [
            'includeItemsInfo' => true
        ]));

        $bids = self::normalizeArray(array_column($order['items'], 'bid'));

        $this->log->save([
            'type' => Orders::PENDING,
            'bids' => $bids
        ]);

        return [
            'status' => Orders::PENDING,
            'invoice' => $this->orderId
        ];
    }

    public function webhookListener()
    {
        // Verify signature
        $headerName = 'X-Cc-Webhook-Signature';
        $headers = getallheaders();
        $signatureHeader = isset($headers[$headerName]) ? $headers[$headerName] : null;
        $notdecoded = trim(file_get_contents('php://input'));
        $payload = json_decode($notdecoded);

        $computedSignature = hash_hmac('sha256', $notdecoded, $this->gateways[$this->gatewayName]['secret']);
        if (!self::hashEqual($signatureHeader, $computedSignature)) {

            return $this->log->save([
                'message' => $this->lang->bankpipe_error_webhook_signature_check_failed
            ]);

        }

        $event = $payload->event;

        // Signature verified. Send status code 200 so Coinbase stops sending multiple identical notifications
        http_response_code(200);

        // Proceed with checking the payment status
        $order = reset($this->orders->get([
            'invoice' => $event->data->metadata->invoice
        ], [
            'includeItemsInfo' => true
        ]));

        if (!$order['invoice']) {

            // Coinbase might send multiple attempts of the same notification.
            // This is to prevent logging an error since the order has been deleted from our db
            if ($payload->attempt_number > 1) {
                return false;
            }

            return $this->log->save([
                'message' => json_encode($payload)
            ]);

        }

        $lastEvent = end($event->data->timeline);
        $status = $lastEvent->status;

        $bids = array_column($order['items'], 'bid');

        $donation = ($order['donor']) ? true : false;
        $buyerUid = ($donation) ? (int) $order['donor'] : (int) $order['uid'];

        // Status: confirmed
        if (in_array($event->type, ['charge:confirmed', 'charge:resolved']) and in_array($status, ['COMPLETED', 'RESOLVED'])) {

            $update = [
                'type' => Orders::SUCCESS,
                'active' => 1,
                'payment_id' => $this->db->escape_string($event->data->code),
                'price' => $this->db->escape_string($event->data->payments[0]->value->local->amount), // Real amount paid
                'currency' => $this->db->escape_string($event->data->payments[0]->value->local->currency),
                'crypto_price' => $this->db->escape_string((float) $event->data->payments[0]->value->crypto->amount),
                'crypto_currency' => $this->db->escape_string($event->data->payments[0]->value->crypto->currency)
            ];

            $this->orders->update($update, $order['invoice']);

            // Update usergroup
            $this->updateUsergroup($order['items'], $order['uid']);

            // Success notifications
            $order = array_merge($order, $update);

            $names = array_column($order['items'], 'name');
            $currency = Core::friendlyCurrency($order['currency']);

            $buyer = get_user($buyerUid);

            // Merchants and admins
            if ($this->mybb->settings['bankpipe_admin_notification']) {

                $receivers = explode(',', $this->mybb->settings['bankpipe_admin_notification']);

                if ($order['merchant']) {
                    $receivers[] = $order['merchant'];
                }

                $title = $this->lang->sprintf(
                    $this->lang->bankpipe_notification_merchant_purchase_title,
                    $buyer['username'],
                    $order['total'],
                    $currency
                );
                $message = $this->lang->sprintf(
                    $this->lang->bankpipe_notification_merchant_crypto_purchase,
                    $buyer['username'],
                    $this->mybb->settings['bburl'] . '/' . get_profile_link($buyer['uid']),
                    '[*]' . implode("\n[*]", $names),
                    $order['total'],
                    $currency,
                    $update['crypto_price'],
                    $update['crypto_currency'],
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
                $this->lang->bankpipe_notification_buyer_crypto_purchase,
                $buyer['username'],
                $order['invoice'],
                '[*]' . implode("\n[*]", $names),
                $order['total'],
                $currency,
                $update['crypto_price'],
                $update['crypto_currency'],
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

            $this->log->save([
                'type' => Orders::SUCCESS,
                'bids' => $bids,
                'invoice' => $order['invoice'],
                'uid' => $order['uid']
            ]);

        }
        // Unresolved
        else if ($status == 'UNRESOLVED') {

            // Underpaid = revert order and notify. In all other cases (MULTIPLE, OVERPAID), we log it but the order should be left as is
            if ($lastEvent->context == 'UNDERPAID') {

                $this->orders->update([
                    'type' => Orders::UNDERPAID,
                    'active' => 0,
                    'payment_id' => $this->db->escape_string($event->data->code),
                    'price' => $this->db->escape_string($event->data->payments[0]->value->local->amount), // Real amount paid
                    'currency' => $this->db->escape_string($event->data->payments[0]->value->local->currency),
                    'crypto_price' => $this->db->escape_string((float) $event->data->payments[0]->value->crypto->amount),
                    'crypto_currency' => $this->db->escape_string($event->data->payments[0]->value->crypto->currency)
                ], $order['invoice']);

                // Send notifications
                $buyer = get_user($buyerUid);

                // Merchants and admins
                if ($this->mybb->settings['bankpipe_admin_notification']) {

                    $receivers = explode(',', $this->mybb->settings['bankpipe_admin_notification']);

                    if ($order['merchant']) {
                        $receivers[] = $order['merchant'];
                    }

                    $title = $this->lang->sprintf(
                        $this->lang->bankpipe_notification_merchant_underpaid_purchase_title,
                        $buyer['username']
                    );
                    $message = $this->lang->sprintf(
                        $this->lang->bankpipe_notification_merchant_underpaid_purchase,
                        $buyer['username'],
                        $this->mybb->settings['bburl'] . '/' . get_profile_link($buyer['uid']),
                        $event->data->payments[0]->value->crypto->amount,
                        $event->data->payments[0]->value->crypto->currency,
                        $event->data->code,
                        $this->mybb->settings['bbname']
                    );

                    $this->notifications->set($receivers, $title, $message);

                }

                // Buyer
                $receivers = [$buyerUid];

                $title = $this->lang->sprintf(
                    $this->lang->bankpipe_notification_buyer_underpaid_purchase_title,
                    $order['invoice']
                );
                $message = $this->lang->sprintf(
                    $this->lang->bankpipe_notification_buyer_underpaid_purchase,
                    $buyer['username'],
                    $order['invoice'],
                    $this->mybb->settings['bburl'] . '/usercp.php?action=purchases&env=bankpipe&invoice=' . $order['invoice'],
                    $this->mybb->settings['bbname']
                );

                $this->notifications->set($receivers, $title, $message);

            }

            $this->log->save([
                'type' => Orders::UNRESOLVED,
                'bids' => $bids,
                'invoice' => $order['invoice'],
                'uid' => $order['uid'],
                'message' => $this->lang->sprintf($this->lang->bankpipe_payment_marked_as_unresolved, $lastEvent->context)
            ]);

        }
        // Failed
        else if ($event->type == 'charge:failed' and $status == 'EXPIRED') {

            $this->orders->destroy($order['invoice']);

            $buyer = get_user($buyerUid);

            // Send out cancel confirmation to the buyer
            $title = $this->lang->bankpipe_notification_pending_payment_cancelled_webhooks_title;
            $message = $this->lang->sprintf(
                $this->lang->bankpipe_notification_pending_payment_cancelled_webhooks,
                $buyer['username'],
                $order['invoice'],
                $this->mybb->settings['bbname']
            );

            $this->notifications->set([$buyerUid], $title, $message);

            $this->log->save([
                'type' => Orders::CANCEL,
                'bids' => $bids,
                'invoice' => $order['invoice'],
                'uid' => $order['uid']
            ]);

        }
        // Pending
        else if (in_array($event->type, ['charge:pending', 'charge:created'])) {

            $this->orders->update([
                'type' => Orders::PENDING,
                'active' => 0
            ], $order['invoice']);

            $query = $this->db->simple_select(
                'bankpipe_log',
                'lid',
                'invoice = \'' . $order['invoice'] . '\' AND type = ' . Orders::PENDING
            );
            $id = $this->db->fetch_field($query, 'lid');

            if (!$id) {

                $this->log->save([
                    'type' => Orders::PENDING,
                    'invoice' => $order['invoice'],
                    'bids' => $bids
                ]);

            }
            else {
                $this->db->update_query('bankpipe_log', ['date' => TIME_NOW], 'lid = ' . (int) $id);
            }

        }
        else {

            $this->log->save([
                'message' => json_encode($payload),
                'invoice' => $order['invoice'],
                'uid' => $order['uid']
            ]);

        }

        $this->notifications->send();

    }

    public static function hashEqual($str1, $str2)
    {
        if (function_exists('hash_equals')) {
            return \hash_equals($str1, $str2);
        }
        if (strlen($str1) != strlen($str2)) {
            return false;
        } else {
            $res = $str1 ^ $str2;
            $ret = 0;
            for ($i = strlen($res) - 1; $i >= 0; $i--) {
                $ret |= ord($res[$i]);
            }
            return !$ret;
        }
    }
}