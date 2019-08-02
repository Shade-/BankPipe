<?php

namespace BankPipe\Usercp;

use BankPipe\Core;
use BankPipe\Helper\Cookies;
use BankPipe\Items\Items;

class Subscriptions extends Usercp
{
    public function __construct(
        $errors = ''
    )
    {
        $this->traitConstruct();

        $gateways = [];
        $query = $this->db->simple_select('bankpipe_gateways', '*');
        while ($gateway = $this->db->fetch_array($query)) {
            $gateways[] = $gateway;
        }

        global $theme, $templates, $headerinclude, $header, $footer, $usercpnav;
        global $mybb, $lang; // Required for bankpipe_script

        $this->plugins->run_hooks('bankpipe_ucp_subscriptions_start', $this);

        add_breadcrumb($this->lang->bankpipe_nav, 'usercp.php');
        add_breadcrumb($this->lang->bankpipe_nav_subscriptions, 'usercp.php?action=subscriptions&env=bankpipe');

        $cookies = new Cookies;

        // Errors
        if ($errors) {
            $errors = inline_error($errors);
        }

        $currency = Core::friendlyCurrency($this->mybb->settings['bankpipe_currency']);

        eval("\$script = \"".$templates->get("bankpipe_script")."\";");

        $highestPurchased = $subs = $purchases = [];

        $query = $this->db->simple_select(Items::PAYMENTS_TABLE, 'bid, invoice', 'active = 1 AND uid = ' . (int) $this->mybb->user['uid']);
        while ($purchase = $this->db->fetch_array($query)) {
            $purchases[$purchase['bid']] = $purchase['invoice'];
        }

        $query = $this->db->simple_select(Items::ITEMS_TABLE, '*', 'gid <> 0', ['order_by' => 'price ASC']);
        while ($subscription = $this->db->fetch_array($query)) {

            $subscription['price'] += 0;

            // Determine highest purchased subscription
            if ($purchases[$subscription['bid']]) {
                $highestPurchased = $subscription;
            }

            $subs[] = $subscription;

        }

        if ($subs) {

            foreach ($subs as $subscription) {

                $skip = false;

                // Bought or not?
                if ($purchases[$subscription['bid']] and $highestPurchased['bid'] == $subscription['bid']) {

                    $paymentId = $purchases[$subscription['bid']];
                    eval("\$subscriptions .= \"".$templates->get("bankpipe_subscriptions_subscription_purchased")."\";");

                }
                else {

                    $itemsInCart = $cookies->read('items');

                    if (in_array($subscription['bid'], $itemsInCart)) {
                        eval("\$subscriptions .= \"".$templates->get("bankpipe_subscriptions_subscription_added")."\";");
                    }
                    else {
                        eval("\$subscriptions .= \"".$templates->get("bankpipe_subscriptions_subscription")."\";");
                    }

                }

            }

        }
        else {
            eval("\$subscriptions = \"".$templates->get("bankpipe_subscriptions_no_subscription")."\";");
        }

        $this->plugins->run_hooks('bankpipe_ucp_subscriptions_end', $this);

        eval("\$page = \"".$templates->get("bankpipe_subscriptions")."\";");
        output_page($page);
        exit;
    }
}
