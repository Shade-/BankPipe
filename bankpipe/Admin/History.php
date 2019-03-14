<?php

namespace BankPipe\Admin;

use BankPipe\Items\Items;
use BankPipe\Items\Orders;
use BankPipe\Core;

class History
{
	use \BankPipe\Helper\MybbTrait;

	public function __construct()
	{
		$this->traitConstruct(['page', 'sub_tabs']);
		
		$this->page->add_breadcrumb_item($this->lang->bankpipe_history, MAINURL);
    	$this->page->output_header($this->lang->bankpipe_history);
    	$this->page->output_nav_tabs($this->sub_tabs, 'history');
    
    	// Sorting
    	$form = new \Form(MAINURL . "&action=history&sort=1", "post", "history");
    	$container = new \FormContainer();
    
    	$container->output_row('', '', $form->generate_text_box('username', $this->mybb->input['username'], [
    		'id' => 'username',
    		'style' => '" autocomplete="off" placeholder="' . $this->lang->bankpipe_filter_username
    	]) . ' ' . $form->generate_text_box('startingdate', $this->mybb->input['startingdate'], [
    		'id' => 'startingdate',
    		'style' => 'width: 150px" autocomplete="off" placeholder="' . $this->lang->bankpipe_filter_startingdate
    	]) . ' ' . $form->generate_text_box('endingdate', $this->mybb->input['endingdate'], [
    		'id' => 'endingdate',
    		'style' => 'width: 150px" autocomplete="off" placeholder="' . $this->lang->bankpipe_filter_endingdate
    	]) . ' ' . $form->generate_submit_button($this->lang->bankpipe_filter), 'sort');
    
    	$container->end();
    	$form->end();
    
        $exclude = [Orders::CREATE, Orders::ERROR];
    	$where = [
        	'type NOT IN (' . implode(',', $exclude) . ')'
    	];
    	if ($this->mybb->input['sort']) {
    
    		if ($this->mybb->input['username']) {
        		
        		$uids = [];
        		$query = $this->db->simple_select('users', 'uid', "username LIKE ('" . $this->db->escape_string($this->mybb->input['username']) . "')");
        		while ($uid = $this->db->fetch_field($query, 'uid')) {
            		$uids[] = $uid;
        		}
        		
    			$where[] = "uid IN ('" . implode("','", $uids) . "')";
    			
    		}
    
    		if ($this->mybb->input['startingdate']) {
    			$where[] = "date >= " . get_formatted_date($this->mybb->input['startingdate']);
    		}
    
    		if ($this->mybb->input['endingdate']) {
    			$where[] = "date <= " . get_formatted_date($this->mybb->input['endingdate']);
    		}
    
    	}
    
    	// Paging
    	$perpage = 20;
    	$sortingOptions = ['username', 'startingdate', 'endingdate'];
    	$sortingString = '';
    	foreach ($sortingOptions as $opt) {
    		if ($this->mybb->input[$opt]) {
    			$sortingString .= '&' . $opt . '=' . $this->mybb->input[$opt];
    		}
    	}
    
    	if ($sortingString) {
    		$sortingString .= '&sort=true';
    	}
    
    	$this->mybb->input['page'] = (int) $this->mybb->input['page'] ?? 1;
    
    	$start = 0;
    	if ($this->mybb->input['page']) {
    		$start = ($this->mybb->input['page'] - 1) * $perpage;
    	}
    	else {
    		$this->mybb->input['page'] = 1;
    	}
    
    	$query = $this->db->simple_select(Items::PAYMENTS_TABLE, 'COUNT(DISTINCT(invoice)) as num_results', implode(' AND ', $where));
    	$num_results = (int) $this->db->fetch_field($query, 'num_results');
    
    	if ($num_results > $perpage) {
    		echo draw_admin_pagination($this->mybb->input['page'], $perpage, $num_results, MAINURL . "&action=history" . $sortingString);
    	}
    
    	// Main view
    	$table = new \Table;
    
    	$table->construct_header($this->lang->bankpipe_history_header_user, ['width' => '15%']);
    	$table->construct_header($this->lang->bankpipe_history_header_merchant, ['width' => '15%']);
    	$table->construct_header($this->lang->bankpipe_history_header_items);
    	$table->construct_header($this->lang->bankpipe_history_header_revenue, ['width' => '10%']);
    	$table->construct_header($this->lang->bankpipe_history_header_date, ['width' => '10%']);
    	$table->construct_header($this->lang->bankpipe_history_header_expires, ['width' => '10%']);
    	$table->construct_header($this->lang->bankpipe_history_header_options, ['width' => '10%']);
    
    	$orders = (new Orders)->get($where, [
        	'limit_start' => (int) $start,
        	'limit' => (int) $perpage,
        	'includeItemsInfo' => true
    	]);
    	
    	$revenue = 0;
    	
    	// Cache users
    	$users = [];
    	$uids = Core::normalizeArray(array_merge(
    	    array_column($orders, 'buyer'),
    	    array_column($orders, 'merchant')
        ));
    	$query = $this->db->simple_select('users', 'username, usergroup, displaygroup, avatar, uid', "uid IN ('" . implode("','", $uids) . "')");
    	while ($user = $this->db->fetch_array($query)) {
        	$users[$user['uid']] = $user;
    	}
    	
        foreach ($orders as $invoice => $order) {
            
            // Revenue
            $totalPaid = ($order['total'] - $order['fee']);
            $revenue += $totalPaid;
    
    		// Buyer
    		$user = $users[$order['buyer']];
    		$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
    		$username = build_profile_link($username, $user['uid']);
    
    		$avatar = format_avatar($user['avatar']);
    
    		$table->construct_cell('<img src="' . $avatar['image'] . '" style="height: 20px; width: 20px; vertical-align: middle" /> ' . $username);
    
    		// Merchant
    		if ($order['merchant']) {
        		
        		$user = $users[$order['merchant']];
        		$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
        		$username = build_profile_link($username, $user['uid']);
        
            }
            else {
                $username = $this->mybb->settings['bbname'];
            }
        
            $table->construct_cell($username);
    
    		// Expires
    		$class = $extra = '';
    
    		$expires = ($order['expires']) ? 
    		    my_date('relative', $order['expires']) :
    		    $this->lang->bankpipe_history_expires_never;
    		    		
    		if ($order['type'] == Orders::REFUND) {
    
    			$extra = $this->lang->bankpipe_history_refunded;
    			$class = 'refunded';
    			$revenue -= $totalPaid;
    
    		}
    		else if ($order['type'] == Orders::PENDING) {
    
    			$extra = $this->lang->bankpipe_history_pending;
    			$class = 'pending';
    
    		}
    		else if ($order['expires'] and $order['expires'] < TIME_NOW) {
    
    			$expires = $this->lang->bankpipe_history_expires_expired;
    			$class = 'expired';
    
    		}
    		else if (!$order['active']) {
    
    			$extra = $this->lang->bankpipe_history_inactive;
    			$class = 'inactive';
    
    		}
    		
    		// Items
    		$table->construct_cell(implode(', ', array_column($order['items'], 'name')) . $extra);
    
    		// Price
    		$price = $totalPaid;
    		
    		if ($order['fee'] > 0) {
        		$price .= ' (' . $order['fee'] . ')';
    		}
    		
    		$price .= ' ' . $order['currency'];
    		
    		$table->construct_cell($price);
    
    		// Date
    		$table->construct_cell(my_date('relative', $order['date']));
    
    		// Expires
    		$table->construct_cell($expires);
    
    		// Options
    		$popup = new \PopupMenu("options_" . $order['invoice'], $this->lang->bankpipe_options);
    		$popup->add_item($this->lang->bankpipe_history_edit, MAINURL . '&action=purchases&sub=edit&invoice=' . $invoice);
    
    		if ($order['sale'] and !$order['refund']) {
    			$popup->add_item($this->lang->bankpipe_history_refund, MAINURL . '&action=purchases&sub=refund&invoice=' . $invoice);
    		}
    
    		$popup->add_item($this->lang->bankpipe_history_revoke, MAINURL . '&action=purchases&sub=revoke&invoice=' . $invoice);
    		$table->construct_cell($popup->fetch());
    		$table->construct_row(['class' => $class]);
    
    	}
    
    	if ($this->db->num_rows($query) == 0) {
    		$table->construct_cell(
    		    $this->lang->bankpipe_history_no_payments,
    		    ['colspan' => 5, 'style' => 'text-align: center']
            );
    		$table->construct_row();
    	}
    
    	if ($revenue > 0) {
        	
    		$table->construct_cell(
    		    $this->lang->sprintf(
    		        $this->lang->bankpipe_history_revenue,
    		        $revenue,
    		        Core::friendlyCurrency($this->mybb->settings['bankpipe_currency'])
                ),
                ['colspan' => 7, 'style' => 'text-align: center']
            );
    		$table->construct_row();
    		
    	}
    
    	$table->output($this->lang->bankpipe_history);
    
    	echo <<<HTML
<style type="text/css">
	.expired td {
		background: #e0e0e0!important;
		text-decoration: line-through
	}
	.inactive td {
		background: #a1a1a1!important
	}
	.refunded td {
		background: lightblue!important
	}
	.pending td {
		background: #e6df86!important
	}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.css" type="text/css" />
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.js"></script>
<script type="text/javascript">
<!--
// Date picking
var expiry = $("#startingdate, #endingdate").datepicker({
	autoHide: true,
	format: 'dd/mm/yyyy'
})
-->
</script>
HTML;
    }
}