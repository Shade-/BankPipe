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

        $appliedDiscounts = '';
        $existingDiscounts = $cookies->read('discounts');

        if ($existingDiscounts) {

            $search = implode(',', array_map('intval', $existingDiscounts));

            $query = $this->db->simple_select(Items::DISCOUNTS_TABLE, '*', 'did IN (' . $search . ')');
            while ($discount = $this->db->fetch_array($query)) {

                $discount['suffix'] = ($discount['type'] == 1) ? '%' : ' ' . $this->mybb->settings['bankpipe_currency'];
                $code = ($discount['name']) ? $discount['name'] : $discount['code'];

                eval("\$appliedDiscounts .= \"".$templates->get("bankpipe_discounts_code")."\";");

                $discounts[] = $discount;

            }

        }

        $currency = Core::friendlyCurrency($this->mybb->settings['bankpipe_currency']);

        eval("\$script = \"".$templates->get("bankpipe_script")."\";");
        eval("\$discountArea = \"".$templates->get("bankpipe_discounts")."\";");

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

                if ($discounts) {

                    foreach ($discounts as $discount) {

                        if ($discount['bids']) {

                            $bids = explode(',', $discount['bids']);

                            if (!in_array($subscription['bid'], $bids)) {
                                $skip = true;
                            }

                        }

                    }

                }

                // Discounts?
                if ((
                        $subscription['discount']
                        and $highestPurchased['price']
                        and $subscription['price'] >= $highestPurchased['price']
                    )
                    or ($discounts and !$skip)
                    ) {

                    $subscription['discounted'] = $subscription['price'];
                    $discount = 0;

                    if ($subscription['discount']) {
                        $discount = ($highestPurchased['price'] * $subscription['discount'] / 100);
                    }

                    if ($discounts) {

                        foreach ($discounts as $discount) {

                            // Percentage
                            if ($discount['type'] == 1) {
                                $discount = (($subscription['discounted'] / 100) * $discount['value']);
                            }
                            // Absolute value
                            else {
                                $discount = $discount['value'];
                            }

                        }

                    }

                    // Scrape off the discount
                    if ($discount) {

                        $subscription['discounted'] -= $discount;

                        if ($subscription['discounted'] <= 0) {
                            $subscription['discounted'] = 0;
                        }

                    }

                    eval("\$price = \"".$templates->get("bankpipe_subscriptions_subscription_price_discounted")."\";");

                }
                else {
                    eval("\$price = \"".$templates->get("bankpipe_subscriptions_subscription_price")."\";");
                }

                // Bought or not?
                if ($purchases[$subscription['bid']] and $highestPurchased['bid'] == $subscription['bid']) {

                    $paymentId = $purchases[$subscription['bid']];
                    eval("\$subscriptions .= \"".$templates->get("bankpipe_subscriptions_subscription_purchased")."\";");

                }
                else {
                    eval("\$subscriptions .= \"".$templates->get("bankpipe_subscriptions_subscription")."\";");
                }

            }

        }
        else {
            eval("\$subscriptions = \"".$templates->get("bankpipe_subscriptions_no_subscription")."\";");
        }

        $this->plugins->run_hooks('bankpipe_ucp_subscriptions_end', $this);

        eval("\$page = \"".$templates->get("bankpipe_subscriptions")."\";");
        output_page($page);

    }
}
