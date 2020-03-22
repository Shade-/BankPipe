<?php

namespace BankPipe\Usercp;

use BankPipe\Items\Orders;
use BankPipe\Core;

class Purchases extends Usercp
{
    public function __construct()
    {
        $this->traitConstruct();

        global $theme, $templates, $headerinclude, $header, $footer, $usercpnav, $lang;

        $this->plugins->run_hooks('bankpipe_ucp_purchases_start', $this);

        add_breadcrumb($this->lang->bankpipe_nav, 'usercp.php');
        add_breadcrumb($this->lang->bankpipe_nav_purchases, 'usercp.php?action=purchases');

        $ordersHandler = new Orders;

        // Informations
        $invoice = $this->mybb->input['invoice'];

        if ($invoice) {

            $order = reset($ordersHandler->get([
                'invoice' => $invoice,
                'uid' => $this->mybb->user['uid'],
                'donor' => 0,
                'OR' => [
                    'invoice' => $invoice,
                    'donor' => $this->mybb->user['uid']
                ]
            ], [
                'includeItemsInfo' => true
            ]));

            if ($order) {

                $args = [&$this, &$order];
                $this->plugins->run_hooks('bankpipe_ucp_purchases_payment_start', $args);

                $items = $appliedDiscounts = $pending = '';

                // If no payment id is supplied, ignore this
                if (in_array($order['type'], [Orders::PENDING, Orders::SUCCESS])) {

                    // Set the merchant
                    if ($order['merchant']) {

                        $user = get_user($order['merchant']);
                        $merchant = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
                        $merchant = build_profile_link($merchant, $user['uid']);

                    }
                    else {
                        $merchant = $this->mybb->settings['bbname'];
                    }

                    // Format date
                    $date = my_date('relative', $order['date']);

                    $discounts = $names = [];
                    $originalTotal = 0;

                    // Loop through this order's items and display them in an ordered fashion
                    foreach ($order['items'] as $item) {

                        $item['price'] += 0;
                        $originalTotal += $item['originalPrice'];

                        eval("\$items .= \"".$templates->get("bankpipe_purchases_payment_item")."\";");

                    }

                    // Show savings
                    $discounts = Core::normalizeArray($order['discounts']);
                    if ($discounts) {

                        $savings = ($originalTotal - $order['total']);

                        $query = $this->db->simple_select('bankpipe_discounts', 'code, name', "did IN ('" . implode("','", $discounts) . "')");
                        while ($item = $this->db->fetch_array($query)) {
                            $names[] = ($item['name']) ? $item['name'] : $item['code'];
                        }

                        // Previous subscription discount?
                        if (in_array('p', $discounts)) {
                            $names[] = $this->lang->bankpipe_purchases_previous_subscription_discount;
                        }

                        $names = implode(', ', $names);

                        eval("\$appliedDiscounts = \"".$templates->get("bankpipe_purchases_payment_discounts")."\";");

                    }

                    // Show eventual gift
                    if ($order['donor']) {

                        $gifted = get_user($order['uid']);

                        eval("\$giftTo = \"".$templates->get("bankpipe_purchases_payment_gift_to")."\";");

                    }

                    $args = [&$this, &$order, &$discounts];
                    $this->plugins->run_hooks('bankpipe_ucp_purchases_payment_end', $args);

                    $title = $this->lang->bankpipe_purchases_payment_transaction;
                    if ($order['type'] == Orders::PENDING) {

                        $title = $this->lang->bankpipe_purchases_payment_transaction_pending;
                        eval("\$pending = \"".$templates->get("bankpipe_purchases_payment_pending")."\";");

                    }

                    eval("\$page = \"".$templates->get("bankpipe_purchases_payment")."\";");
                    output_page($page);
                    exit;

                }

            }

        }

        $purchases = $inactive = $refunded = $expired = $pending = '';

        $exclude = [Orders::CREATE, Orders::ERROR, Orders::MANUAL];
        $orders = $ordersHandler->get([
            'type NOT IN (' . implode(',', $exclude) . ')',
            'uid' => $this->mybb->user['uid'],
            'donor' => 0,
            'OR' => [
                'donor' => $this->mybb->user['uid']
            ]
        ], [
            'includeItemsInfo' => true
        ]);

        if ($orders) {

            foreach ($orders as $order) {

                $names = implode(', ', array_column($order['items'], 'name'));

                $order['date'] = my_date('relative', $order['date']);

                if ($order['refund']) {
                    eval("\$refunded .= \"".$templates->get("bankpipe_purchases_purchase_refunded")."\";");
                }
                else if ($order['type'] == Orders::PENDING) {
                    eval("\$pending .= \"".$templates->get("bankpipe_purchases_purchase_pending")."\";");
                }
                else if ($order['expires'] and $order['expires'] < TIME_NOW and !$order['active']) {
                    eval("\$expired .= \"".$templates->get("bankpipe_purchases_purchase_expired")."\";");
                }
                else if (!$order['active']) {
                    eval("\$inactive .= \"".$templates->get("bankpipe_purchases_purchase_inactive")."\";");
                }
                else {
                    eval("\$purchases .= \"".$templates->get("bankpipe_purchases_purchase")."\";");
                }

            }

        }
        else {
            eval("\$purchases = \"".$templates->get("bankpipe_purchases_no_purchases")."\";");
        }

        $args = [&$this, &$orders];
        $this->plugins->run_hooks('bankpipe_ucp_purchases_end', $args);

        eval("\$page = \"".$templates->get("bankpipe_purchases")."\";");
        output_page($page);
    }
}
