<?php

namespace BankPipe\Items;

use BankPipe\Core;
use BankPipe\Helper\Permissions;

class Orders
{
	use \BankPipe\Helper\MybbTrait;

    // bankpipe.js needs to be updated as well if those values are changed!
    // TO-DO: add idempotency to bankpipe.js
    const ERROR = 0;
    const CREATE = 1;
    const SUCCESS = 2;
    const PENDING = 3;
    const FAIL = 4;
    const REFUND = 5;
    const MANUAL = 6;
    const CANCEL = 7;

	public function __construct()
	{
		$this->traitConstruct();

		$this->permissions = new Permissions;
	}

	public function insert(array $items, string $orderId, array $settings = [])
	{
    	if (!$items or !$orderId) {
        	return false;
    	}

    	$discounts = $order = $bids = $merchants = [];

    	// Extract discounts
    	$dids = Core::normalizeArray(array_column($items, 'did'));
    	$query = $this->db->simple_select('bankpipe_discounts', '*', "did IN ('" . implode("','", $dids) . "')");
    	while ($discount = $this->db->fetch_array($query)) {
        	$discounts[] = $discount;
    	}

    	// Loop through this order items
    	foreach ($items as $key => $item) {

        	// We want only registered items, not discounts or other garbage
        	if (!$item['bid']) {
            	continue;
        	}

        	$price = $item['price'];
        	$dids = [];

        	// Check and apply discounts
        	if ($discounts) {

            	foreach ($discounts as $discount) {

                	if (!$this->permissions->discountCheck($discount, $item)) {
						continue;
					}

					// Percentage
					if ($discount['type'] == 1) {
						$price = $price - ($price * $discount['value'] / 100);
					}
					// Absolute
					else {
						$price = $price - $discount['value'];
					}

					$dids[] = $discount['did'];

            	}

            }

        	$order[$key] = [
    			'invoice' => $this->db->escape_string($orderId),
    			'price' => Core::filterPrice($price),
    			'uid' => $this->mybb->user['uid'],
    			'bid' => (int) $item['bid'],
    			'oldgid' => (int) $item['oldgid'],
    			'newgid' => (int) $item['newgid'],
    			'expires' => (int) $item['expires'],
    			'date' => TIME_NOW,
    			'active' => 0,
    			'discounts' => implode('|', Core::normalizeArray($dids))
    		];

    		if ($settings['type'] and is_int($settings['type'])) {
        		$order[$key]['type'] = (int) $settings['type'];
    		}

    		// Reference?
    		if ($settings['reference']) {
        		$order[$key]['payment_id'] = $this->db->escape_string($settings['reference']);
    		}

    		// Look for a third party merchant and associate it with this payment
    		if ($settings['merchant']) {

        		$merchant = $this->db->escape_string($settings['merchant']);

        		// TO-DO: cache merchants properly
        		if (!$merchants[$merchant]) {

            		$query = $this->db->simple_select(
        				'users',
        				'uid',
        				"payee = '" . $merchant . "'"
        			);
        			$uid = $this->db->fetch_field($query, 'uid');

            		if ($uid) {
                		$merchants[$merchant] = $uid;
            		}

                }

        		$order[$key]['payee'] = $merchants[$merchant];
        		$order[$key]['payee_email'] = $merchant;

    		}

        }

        $args = [&$this, &$order];
        $this->plugins->run_hooks('bankpipe_orders_insert', $args);

        if ($order) {
            $this->db->insert_query_multiple(Items::PAYMENTS_TABLE, $order);
        }

        return Core::normalizeArray(array_column($order, 'bid'));
	}

	public function get(array $search, array $options = [])
	{	
    	$return = $bids = [];

    	$options['group_by'] = $options['group_by'] ?? 'invoice';

    	$query = $this->getQuery($search, '*, GROUP_CONCAT(bid, \'|\', price, \'|\', pid) concat', $options);
	    while ($item = $this->db->fetch_array($query)) {

    	    $item = $this->plugins->run_hooks('bankpipe_orders_get_item', $item);

    	    $item['merchant'] = $item['payee'];
    	    $item['buyer'] = $item['uid'];
    	    $item['discounts'] = explode('|', $item['discounts']);
    	    $specificFeatures = explode(',', $item['concat']);

    	    unset($item['payee'], $item['uid'], $item['concat'], $item['bid'], $item['pid'], $item['price']);

    	    $item['currency'] = Core::friendlyCurrency($item['currency']);

			$return[$item['invoice']] = $item;

            foreach ($specificFeatures as $info) {

                $info = explode('|', $info);

                $return[$item['invoice']]['total'] += $info[1];

                $bids[] = $info[0];

                $return[$item['invoice']]['items'][] = [
                    'bid' => $info[0],
                    'price' => $info[1],
                    'pid' => $info[2]
                ];

            }

	    }

	    if ($options['includeItemsInfo']) {

    	    // Associate items
    	    $items = (new Items)->getItems(Core::normalizeArray($bids));
    	    foreach ($return as $k => $order) {

        	    foreach ($order['items'] as $key => $item) {

            	    $return[$k]['items'][$key]['originalPrice'] = $items[$item['bid']]['price'];

            	    if (is_array($items[$item['bid']])) {
            	        $return[$k]['items'][$key] += $items[$item['bid']];
                    }

        	    }

    	    }

	    }

        $args = [&$this, &$return];
        $this->plugins->run_hooks('bankpipe_orders_get', $args);

	    return $return;
	}

	public function update(array $update, string $orderId, array $where = [])
	{
    	$where['invoice'] = $orderId;

        $update = $this->plugins->run_hooks('bankpipe_orders_update', $update);

    	return ($update) ? $this->db->update_query(Items::PAYMENTS_TABLE, $update, $this->buildWhereStatement($where)) : false;
	}

	public function destroy(string $orderId)
	{
        $this->plugins->run_hooks('bankpipe_orders_destroy');

    	return ($orderId) ? $this->db->delete_query(Items::PAYMENTS_TABLE, $this->buildWhereStatement(['invoice' => $orderId])) : false;
	}

	private function getQuery(array $where, string $columns = '*', array $options = [])
	{
        $options['order_by'] = $options['order_by'] ?? 'date DESC';

    	return $this->db->simple_select(Items::PAYMENTS_TABLE, $columns, $this->buildWhereStatement($where), $options);
	}

	private function buildWhereStatement(array $fields)
	{
    	$search = [];

    	foreach ($fields as $column => $value) {

        	if (is_int($column)) {
            	$search[] = $value;
        	}
        	else if (is_string($value)) {
        	    $search[] = $column . " = '" . $this->db->escape_string($value) . "'";
            }
            else if (is_array($value)) {
        	    $search[] = $column . " IN ('" . implode("','", $value) . "')";
            }
            else if (is_int($value)) {
        	    $search[] = $column . " = '" . $value . "'";
            }

    	}

    	return implode(' AND ', $search);
	}
}