<?php

namespace BankPipe\Usercp;

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
		
		$existingItems = $cookies->read('items');
		
		// Errors
		if ($errors) {
			$errors = inline_error($errors);
		}

		if ($this->mybb->input['add']) {

			$errors = [];

			$aid = (int) $this->mybb->input['aid'];
			
			$this->plugins->run_hooks('bankpipe_ucp_cart_add_start', $this);
			
			// Does this item exist?
			if ($aid) {
    			
    			$exists = $itemsHandler->getAttachment($aid);
    			
    			if (!$exists['bid']) {
        			$errors[] = $this->lang->bankpipe_cart_item_unknown;
    			}
    			else {
        			
        			// Get first item out of existing items
        			$firstItem = (int) reset($existingItems);
        			if ($firstItem and $aid) {
        
        				$infos = [];
        
        				// Merchant check – since some gateways (like PayPal) can't handle multiple payees at once,
        				// we must block every attempt to stack items from different merchants. The first item is enough,
        				// as if there are more, this check will prevent stacked items to be part of a different merchant.
        				$query = $this->db->query('
        					SELECT a.aid, u.payee
        					FROM ' . TABLE_PREFIX . 'attachments a
        					LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid = a.uid)
        					WHERE a.aid IN (\'' . $aid .  "','" . $firstItem . '\')
        				');
        				while ($info = $this->db->fetch_array($query)) {
        					$infos[$info['aid']] = $info['payee'];
        				}
        
        				if ($infos[$firstItem] and $infos[$firstItem] != $infos[$aid]) {
        					$errors[] = $this->lang->bankpipe_cart_payee_different;
        				}
        
        			}
        			
    			}
    			
			}
			
			$args = [&$this, &$errors];
			$this->plugins->run_hooks('bankpipe_ucp_cart_add_end', $args);

			if (!$errors) {

				if (!in_array($aid, $existingItems)) {
					$existingItems[] = $aid;
				}

				$cookies->write('items', $existingItems);

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

			$aid = (int) $this->mybb->input['aid'];

			if (($key = array_search($aid, $existingItems)) !== false) {
				unset($existingItems[$key]);
			}

			if (!array_filter($existingItems)) {
				$cookies->destroy('items');
			}
			else {
				$cookies->write('items', $existingItems);
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

		$items = $appliedDiscounts = $script = $discountArea = '';

		// Items
		if ($existingItems) {

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

			$environment = ($this->mybb->settings['bankpipe_sandbox']) ? 'sandbox' : 'production';

			eval("\$discountArea = \"".$templates->get("bankpipe_discounts")."\";");

			$search = array_map('intval', $existingItems);
			$paidItems = $itemsHandler->getAttachments($search);

			$tot = 0;
			if ($paidItems) {

				$query = $this->db->simple_select('attachments', 'aid, pid', 'aid IN (' . implode(',', $search) . ')');
				while ($attach = $this->db->fetch_array($query)) {
					$pids[$attach['aid']] = $attach['pid'];
				}

				if ($paidItems['bid']) {
					$paidItems = [$paidItems];
				}

				foreach ($paidItems as $item) {

					if (!$item['aid']) {
						continue;
					}

					$itemDiscounts = '';
					$discountsList = [];
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

					$item['postlink'] = get_post_link($pids[$item['aid']]);

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
    		
    		eval("\$noItemsTemplate = \"".$templates->get("bankpipe_cart_no_items")."\";");
    		$noItemsTemplate = json_encode($noItemsTemplate);
		}
        
        eval("\$script = \"".$templates->get("bankpipe_script")."\";");

		$this->plugins->run_hooks('bankpipe_ucp_cart_end', $this);

		eval("\$page = \"".$templates->get("bankpipe_cart")."\";");
		output_page($page);
	}
}