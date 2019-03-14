<?php

namespace BankPipe\Items;

use BankPipe\Core;

class Items
{
	use \BankPipe\Helper\MybbTrait;
	
	public $items = [];
	public $payments = [];
	
	const ITEMS_TABLE = 'bankpipe_items';
	const PAYMENTS_TABLE = 'bankpipe_payments';
	const DISCOUNTS_TABLE = 'bankpipe_discounts';
	
	const SUBSCRIPTION = 1;
	const ATTACHMENT = 2;
	
	public function __construct()
	{
		$this->traitConstruct();
		
		$this->orders = new Orders;
	}
	
	public function getAttachments(array $aids)
	{
    	if (!$aids) {
        	return [];
    	}
    	
		$search = $bids = $return = [];
		$aids = Core::normalizeArray($aids);
		$existingAids = array_column($this->items, 'aid');
		
		foreach ($aids as $aid) {
			
			$aid = (int) $aid;
	
			if (!in_array($aid, $existingAids)) {
				$search[] = $aid;
			}
	
		}

		if ($search) {
	
			// Get items
			$query = $this->db->simple_select(self::ITEMS_TABLE, '*, uid AS itemuid', 'aid IN (' . implode(',', $search) . ')');
			while ($item = $this->db->fetch_array($query)) {
				$this->items[$item['bid']] = $item;
			}
	
			// Get the logged user purchases and match them on top of available items
			$bids = Core::normalizeArray(array_keys($this->items));
			if ($bids) {
	
				$purchases = $this->orders->get([
				    'bid' => $bids,
				    'uid' => $this->mybb->user['uid']
				]);
				
				foreach ($purchases as $purchase) {
    				
    				$bid = $purchase['bid'];
    				
    				if ($this->items[$bid]) {
				        $this->items[$bid] = array_merge($this->items[$bid], $purchase);
				    }
				
				}
	
			}
	
		}
        
        foreach ($aids as $aid) {
            
            $key = array_search($aid, array_column($this->items, 'aid', 'bid'));
            
            if ($key !== false) {
                $return[$key] = $this->items[$key];
            }

        }
	    
        $args = [&$this, &$return];
        $this->plugins->run_hooks('bankpipe_items_get_attachments', $args);
	
		return $return;
	}
	
	public function getItems(array $bids, int $uid = 0)
	{
		if (!$bids) {
			return [];
		}
		
		$search = $return = [];
		$bids = Core::normalizeArray($bids);
	
		$extra = ($uid) ? " AND uid = $uid" : '';
		
		foreach ($bids as $bid) {
			
			$bid = (int) $bid;
	
			if (!$this->items[$bid]) {
				$search[] = $bid;
			}
	
		}
		
		if ($search) {
            
            $query = $this->db->simple_select(self::ITEMS_TABLE, '*', 'bid IN (' . implode(',', $search) . ')' . $extra);
    		while ($item = $this->db->fetch_array($query)) {
    			$this->items[$item['bid']] = $item;
    		}
    
        }
        
        foreach ($bids as $bid) {
            
            if ($this->items[$bid]) {
                $return[$bid] = $this->items[$bid];
            }
            
        }
	    
        $args = [&$this, &$return];
        $this->plugins->run_hooks('bankpipe_items_get_items', $args);
        
        return $return;
	}
	
	public function getAttachment(int $aid)
	{
		return reset($this->getAttachments([$aid]));
	}
	
	public function getItem(int $bid, int $uid = 0)
	{	
		return reset($this->getItems([$bid], $uid));
	}
	
	public function insert(array $items)
	{
    	$insert = $update = $toUpdate = [];
    	$allowed = [
			'uid',
			'price',
			'gid',
			'aid',
			'email',
			'name',
			'description',
			'htmldescription',
			'discount',
			'expires',
			'primarygroup',
			'expirygid',
			'type'
    	];
    	
    	$aids = Core::normalizeArray(array_column($items, 'aid'));
    	
    	if ($aids) {
        	
        	$query = $this->db->simple_select(
			    self::ITEMS_TABLE,
			    'aid',
			    "aid IN ('" . implode("','", array_map('intval', $aids)) . "')"
            );
			while ($aid = $this->db->fetch_field($query, 'aid')) {
                $toUpdate[] = $aid;
			}
        	
    	}
    	
        $args = [&$this, &$items, &$allowed, &$toUpdate];
        $this->plugins->run_hooks('bankpipe_items_insert_start', $args);

		foreach ($items as $item) {
    		
    		// Sanitize
    		foreach ($item as $key => $value) {
        		
        		if (!in_array($key, $allowed)) {
            		
            		unset($item[$key]);
            		continue;
            		
        		}
        		
        		if ($key == 'price') {
            		$item[$key] = Core::filterPrice($value);
        		}
        		else if (is_string($value)) {
            	    $item[$key] = $this->db->escape_string($value);
                }
                else if (is_array($value)) {
            	    $item[$key] = implode(',', $value);
                }
                else if (is_int($value)) {
            	    $item[$key] = (int) $value;
                }
        		
    		}
    		
    		// Update or insert?
    		if ($item['aid'] and in_array($item['aid'], $toUpdate)) {
        		$this->db->update_query(self::ITEMS_TABLE, $item, "aid = '" . $item['aid'] . "'");
    		}
    		else {
    		    $insert[] = $item;
            }

		}
	    
        $args = [&$this, &$items];
        $this->plugins->run_hooks('bankpipe_items_insert_end', $args);

		if ($insert) {
			return $this->db->insert_query_multiple(self::ITEMS_TABLE, $insert);
		}
	}
}