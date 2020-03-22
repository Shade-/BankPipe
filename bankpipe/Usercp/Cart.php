<?php

namespace BankPipe\Usercp;

use BankPipe\Core;
use BankPipe\Helper\Permissions;
use BankPipe\Helper\Cookies;
use BankPipe\Items\Items;
use BankPipe\Messages\Handler as Messages;

class Cart extends Usercp
{
    public function __construct(
        $errors = ''
    )
    {
        $this->traitConstruct();

        global $theme, $templates, $headerinclude, $header, $footer, $usercpnav;
        global $mybb, $lang; // Required for bankpipe_script

        $cookies = new Cookies;
        $itemsHandler = new Items;
        $permissions = new Permissions;
        $messages = new Messages;

        $gateways = [];
        $query = $this->db->simple_select('bankpipe_gateways', '*');
        while ($gateway = $this->db->fetch_array($query)) {
            $gateways[] = $gateway;
        }

        $itemsInCart = $cookies->read('items');

        // Errors
        if ($errors) {
            $errors = inline_error($errors);
        }

        if ($this->mybb->input['add']) {

            $errors = [];

            $bid = (int) $this->mybb->input['bid'];
            $type = (int) $this->mybb->input['type'];

            $this->plugins->run_hooks('bankpipe_ucp_cart_add_start', $this);

            // Does this item exist?
            if ($bid) {

                $exists = $itemsHandler->getItem($bid);

                if (!$exists['bid']) {
                    $errors[] = $this->lang->bankpipe_cart_item_unknown;
                }
                else {

                    // Get first item out of existing items
                    $firstItem = (int) reset($itemsInCart);
                    if ($firstItem and $bid and $bid != $firstItem) {

                        $infos = [];

                        // Merchant check – since some gateways (like PayPal) can't handle multiple merchants at once,
                        // we must block every attempt to stack items from different merchants. The first item is enough,
                        // as if there are more, this check will prevent stacked items to be part of a different merchant.
                        $query = $this->db->query('
                            SELECT i.bid, w.*
                            FROM ' . TABLE_PREFIX . 'bankpipe_items i
                            LEFT JOIN ' . TABLE_PREFIX . 'bankpipe_wallets w ON (w.uid = i.uid)
                            WHERE i.bid IN (\'' . $bid .  "','" . $firstItem . '\')
                        ');
                        while ($wallets = $this->db->fetch_array($query)) {
                            $infos[$wallets['bid']] = $wallets;
                        }

                        // Determine the first item wallet. If not set, create it.
                        $firstItemWallet = $infos[$firstItem];

                        if (!$firstItemWallet) {

                            foreach ($gateways as $gateway) {
                                $firstItemWallet[$gateway['name']] = $gateway['wallet'];
                            }

                        }

                        foreach ($infos as $wallets) {

                            unset($wallets['bid'], $wallets['uid']);

                            foreach ($wallets as $name => $wallet) {

                                if ($firstItemWallet[$name] != $wallet) {
                                    $errors[] = $this->lang->bankpipe_cart_merchant_different;
                                    break;
                                }

                            }

                        }

                    }

                }

            }

            $args = [&$this, &$errors];
            $this->plugins->run_hooks('bankpipe_ucp_cart_add_end', $args);

            if (!$errors) {

                // Add attachments
                if (!in_array($bid, $itemsInCart)) {
                    $itemsInCart[] = $bid;
                }

                $cookies->write('items', $itemsInCart);

                $messages->display([
                    'title' => $this->lang->bankpipe_cart_item_added,
                    'message' => $this->lang->bankpipe_cart_item_added_desc,
                    'action' => 'add'
                ]);

            }
            else {
                $messages->error($errors);
            }

        }

        if ($this->mybb->input['remove']) {

            $this->plugins->run_hooks('bankpipe_ucp_cart_remove', $this);

            $bid = (int) $this->mybb->input['bid'];

            if (($key = array_search($bid, $itemsInCart)) !== false) {
                unset($itemsInCart[$key]);
            }

            if (!array_filter($itemsInCart)) {
                $cookies->destroy('items');
            }
            else {
                $cookies->write('items', $itemsInCart);
            }

            $messages->display([
                'title' => $this->lang->bankpipe_cart_item_removed,
                'message' => $this->lang->bankpipe_cart_item_removed_desc,
                'action' => 'remove'
            ]);

        }

        $this->plugins->run_hooks('bankpipe_ucp_cart_start', $this);

        add_breadcrumb($this->lang->bankpipe_nav, 'usercp.php');
        add_breadcrumb($this->lang->bankpipe_nav_cart, 'usercp.php?action=cart');

        $errors = ($errors) ? inline_error($errors) : '';

        $items = $appliedDiscounts = $script = $discountArea = $giftToUser = '';

        $currency = Core::friendlyCurrency($this->mybb->settings['bankpipe_currency']);

        // Items
        if ($itemsInCart) {

            // Discounts
            $existingDiscounts = $cookies->read('discounts');

            if ($existingDiscounts) {

                $search = implode(',', array_map('intval', $existingDiscounts));

                $query = $this->db->simple_select('bankpipe_discounts', '*', 'did IN (' . $search . ')');
                while ($discount = $this->db->fetch_array($query)) {

                    $discount['suffix'] = ($discount['type'] == 1) ? '%' : ' ' . $this->mybb->settings['bankpipe_currency'];

                    $code = ($discount['name']) ? $discount['name'] : $discount['code'];

                    eval("\$appliedDiscounts .= \"".$templates->get("bankpipe_discounts_code")."\";");

                    $discounts[] = $discount;

                }

            }

            eval("\$discountArea = \"".$templates->get("bankpipe_discounts")."\";");

            $search = array_map('intval', $itemsInCart);
            $paidItems = $itemsHandler->getItems($search);
            $aids = Core::normalizeArray(array_column($paidItems, 'aid'));

            $tot = 0;
            if ($paidItems) {

                if ($aids) {

                    $query = $this->db->simple_select('attachments', 'aid, pid', 'aid IN (' . implode(',', $aids) . ')');
                    while ($attach = $this->db->fetch_array($query)) {
                        $pids[$attach['aid']] = $attach['pid'];
                    }

                }

                foreach ($paidItems as $item) {

                    $itemDiscounts = '';
                    $discountsList = [];
                    $item['price'] += 0;
                    $originalPrice = $item['price'];

                    // Apply discounts
                    if ($discounts) {

                        foreach ($discounts as $k => $discount) {

                            if (!$permissions->discountCheck($discount, $item)) {
                                continue;
                            }

                            $discountsList[$k] = '– ' . $discount['value'];

                            // Apply
                            if ($discount['type'] == 1) {

                                $discountsList[$k] .= '%';
                                $item['price'] = $item['price'] - ($item['price'] * $discount['value'] / 100);

                            }
                            else {

                                $item['price'] = $item['price'] - $discount['value'];

                            }

                        }

                        if ($discountsList) {

                            foreach ($discountsList as $singleDiscount) {
                                eval("\$itemDiscounts .= \"".$templates->get("bankpipe_cart_item_discounts")."\";");
                            }

                        }

                    }

                    $tot += $item['price'];

                    if ($pids[$item['aid']]) {
                        $item['postlink'] = get_post_link($pids[$item['aid']]);
                    }

                    eval("\$items .= \"".$templates->get("bankpipe_cart_item")."\";");

                }

            }

            if ($tot) {
                eval("\$total = \"".$templates->get("bankpipe_cart_total")."\";");
            }

        }

        if (!$items) {
            eval("\$items = \"".$templates->get("bankpipe_cart_no_items")."\";");
        }
        else {

            $buyButtons = '';
            foreach ($gateways as $gateway) {

                if ($gateway['enabled']) {
                    eval("\$buyButtons .= \"".$templates->get("bankpipe_cart_payment_method")."\";");
                }

            }

            eval("\$noItemsTemplate = \"".$templates->get("bankpipe_cart_no_items")."\";");
            $noItemsTemplate = json_encode($noItemsTemplate);

            eval("\$paymentArea = \"".$templates->get("bankpipe_cart_payment_area")."\";");

        }

        eval("\$script = \"".$templates->get("bankpipe_script")."\";");

        $this->plugins->run_hooks('bankpipe_ucp_cart_end', $this);

        eval("\$page = \"".$templates->get("bankpipe_cart")."\";");
        output_page($page);
    }
}
