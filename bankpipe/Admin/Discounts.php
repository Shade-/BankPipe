<?php

namespace BankPipe\Admin;

use BankPipe\Items\Items;
use BankPipe\Core;

class Discounts
{
	use \BankPipe\Helper\MybbTrait;

	public function __construct()
	{
		$this->traitConstruct(['page', 'sub_tabs', 'cache']);
		
		// Manage
		if (isset($this->mybb->input['manage'])) {
    		
    		// Get this notification
        	$did = (int) $this->mybb->get_input('manage');

        	if ($did) {
        
        		$query = $this->db->simple_select(Items::DISCOUNTS_TABLE, '*', "did = '" . $did . "'", ['limit' => 1]);
        		$discount = $this->db->fetch_array($query);
        
        		if (!$discount['did']) {
        			flash_message($this->lang->bankpipe_error_invalid_discount);
        			admin_redirect(MAINURL);
        		}
        
        	}
        
        	if ($this->mybb->request_method == 'post') {
        
        		$this->mybb->input['expires'] = get_formatted_date($this->mybb->input['expires']);
        
        		$data = [
        			'code' => $this->db->escape_string($this->mybb->input['code']),
        			'value' => Core::filterPrice($this->mybb->input['value']),
        			'expires' => (int) $this->mybb->input['expires'],
        			'type' => (int) $this->mybb->input['type'],
        			'stackable' => (int) $this->mybb->input['stackable'],
        			'gids' => implode(',', (array) $this->mybb->input['gids']),
        			'bids' => (string) $this->mybb->input['bids'],
        			'uids' => (string) $this->mybb->input['uids'],
        			'name' => (string) $this->mybb->input['name']
        		];
        
        		if ($this->mybb->input['delete']) {
        			$message = $this->lang->bankpipe_success_discount_deleted;
        			$this->db->delete_query(Items::DISCOUNTS_TABLE, "did IN ('" . implode("','", (array) $this->mybb->input['delete']) . "')");
        		}
        		else {
        
        			$error = false;
        
        			// Duplicate code check
        			if (!$did and $this->db->fetch_field(
        					$this->db->simple_select(Items::DISCOUNTS_TABLE, 'did', "code = '" . $this->db->escape_string($this->mybb->input['code']) . "'"),
        					'did'
        				)) {
        				$error = true;
        				flash_message($this->lang->bankpipe_error_duplicate_code, 'error');
        			}
        			else if (!$data['value']) {
        				$error = true;
        				flash_message($this->lang->bankpipe_error_no_value_provided, 'error');
        			}
        			else if ($data['value'] >= 100 and $data['type'] == 1) {
        				$error = true;
        				flash_message($this->lang->bankpipe_error_cannot_exceed_hundreds, 'error');
        			}
        			else if (!$did) {
        				$message = $this->lang->bankpipe_success_discount_added;
        				$this->db->insert_query(Items::DISCOUNTS_TABLE, $data);
        			}
        			else {
        				$message = $this->lang->bankpipe_success_discount_edited;
        				$this->db->update_query(Items::DISCOUNTS_TABLE, $data, "did = '" . (int) $discount['did'] . "'");
        			}
        
        		}
        
        		// Redirect
        		if (!$error) {
        			flash_message($message, 'success');
        			admin_redirect(MAINURL . '&action=discounts');
        		}
        
        	}
        
        	// Default values
        	if ($did) {
        
        		foreach ($discount as $field => $value) {
        			$this->mybb->input[$field] = $value;
        		}
        
        		if (!is_array($this->mybb->input['gids'])) {
        			$this->mybb->input['gids'] = explode(',', $this->mybb->input['gids']);
        		}
        
        		if ($this->mybb->input['bids']) {
        
        			$selectBids = [];
        
        			$query = $this->db->simple_select(Items::ITEMS_TABLE, 'bid, name', 'bid IN (' . $this->db->escape_string($this->mybb->input['bids']) . ')');
        			while ($item = $this->db->fetch_array($query)) {
        				$selectBids[] = [
        					'id' => $item['bid'],
        					'text' => $item['name']
        				];
        			}
        
        			$selectBids = json_encode($selectBids);
        
        		}
        
        		if ($this->mybb->input['uids']) {
        
        			$selectUids = [];
        
        			$query = $this->db->simple_select('users', 'username, uid', 'uid IN (' . $this->db->escape_string($this->mybb->input['uids']) . ')');
        			while ($user = $this->db->fetch_array($query)) {
        				$selectUids[] = [
        					'id' => $user['uid'],
        					'text' => $user['username']
        				];
        			}
        
        			$selectUids = json_encode($selectUids);
        
        		}
        
        	}
        
        	$title = ($did) ?
        	    $this->lang->sprintf($this->lang->bankpipe_manage_discount_editing, $discount['code']) :
        	    $this->lang->bankpipe_manage_discount;
        
        	$this->page->add_breadcrumb_item($title, MAINURL . '&action=discounts&manage');
        	$this->page->output_header($title);
        	$this->page->output_nav_tabs($this->sub_tabs, 'discounts');
        
        	$form = new \Form(MAINURL . "&action=discounts&manage=" . $discount['did'], "post", "manage");
        
        	$container = new \FormContainer($title);
        
        	// Name
        	$container->output_row(
        	    $this->lang->bankpipe_manage_discount_name,
        	    $this->lang->bankpipe_manage_discount_name_desc,
        	    $form->generate_text_box('name', $this->mybb->input['name'], [
            		'id' => 'name',
            		'style' => '" autocomplete="off'
            	]),
            	'name'
            );
        
        	// Code
        	$container->output_row(
        	    $this->lang->bankpipe_manage_discount_code,
        	    $this->lang->bankpipe_manage_discount_code_desc,
        	    $form->generate_text_box('code', $this->mybb->input['code'], [
            		'id' => 'code',
            		'style' => '" autocomplete="off'
            	]),
            	'code'
            );
        
        	// Value
        	$container->output_row(
        	    $this->lang->bankpipe_manage_discount_value,
        	    $this->lang->bankpipe_manage_discount_value_desc,
        	    $form->generate_text_box('value', $this->mybb->input['value'], [
            		'id' => 'value'
            	]) . ' ' .
            	$form->generate_select_box('type', [
            		'1' => '%',
            		'2' => Core::friendlyCurrency($this->mybb->settings['bankpipe_currency'])
            	], $this->mybb->input['type'], ['id' => 'type']),
                'value'
            );
        
        	// Permissions – usergroups
        	$groups_cache = $this->cache->read('usergroups');
        	unset($groups_cache[1]); // 1 = guests. Exclude them
        
        	$usergroups = [];
        
        	foreach ($groups_cache as $group) {
        		$usergroups[$group['gid']] = $group['title'];
        	}
        
        	$container->output_row(
        	    $this->lang->bankpipe_manage_discount_permissions_usergroups,
        	    $this->lang->bankpipe_manage_discount_permissions_usergroups_desc,
        	    $form->generate_select_box('gids[]', $usergroups, $this->mybb->input['gids'], [
            		'id' => 'gids',
            		'multiple' => true
            	])
            );
        
        	// Permissions - items
        	$container->output_row(
        	    $this->lang->bankpipe_manage_discount_permissions_items,
                $this->lang->bankpipe_manage_discount_permissions_items_desc,
                $form->generate_text_box('bids', $this->mybb->input['bids'], [
            		'id' => 'bids'
            	])
            );
        
        	// Permissions - users
        	$container->output_row(
        	    $this->lang->bankpipe_manage_discount_permissions_users,
        	    $this->lang->bankpipe_manage_discount_permissions_users_desc,
        	    $form->generate_text_box('uids', $this->mybb->input['uids'], [
            		'id' => 'uids'
            	])
            );
        
        	// Stackable
        	$container->output_row(
        	    $this->lang->bankpipe_manage_discount_stackable,
        	    $this->lang->bankpipe_manage_discount_stackable_desc,
        	    $form->generate_check_box('stackable', 1, $this->lang->bankpipe_manage_discount_stackable, [
            		'checked' => $this->mybb->input['stackable']
            	])
            );
        
        	// Expiration
        	$container->output_row(
        	    $this->lang->bankpipe_manage_discount_expires,
        	    $this->lang->bankpipe_manage_discount_expires_desc,
        	    $form->generate_text_box('expires', format_date($this->mybb->input['expires']), [
            		'id' => 'expires',
            		'style' => '" placeholder="' . $this->lang->bankpipe_filter_endingdate . '" autocomplete="off'
            	])
            );
        
        	$container->end();
        
        	$buttons = [
        		$form->generate_submit_button($this->lang->bankpipe_save)
        	];
        	$form->output_submit_wrapper($buttons);
        	$form->end();
        
        	echo <<<HTML
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.css" type="text/css" />
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.js"></script>
<link rel="stylesheet" href="../jscripts/select2/select2.css">
<script type="text/javascript" src="../jscripts/select2/select2.min.js"></script>
<script type="text/javascript">
<!--
// Date picking
var expiry = $("#expires").datepicker({
	autoHide: true,
	startDate: new Date(),
	format: 'dd/mm/yyyy'
});

// Random code generator
$('#random').on('click', (e) => {

	e.preventDefault();

	var random = Math.random().toString(36).toUpperCase().substr(2, 18);

	$('#code').val(random);

});
// Get items autocomplete
$("#bids").select2({
	placeholder: "{$this->lang->search_for_an_item}",
	minimumInputLength: 2,
	multiple: true,
	ajax: {
		url: "../xmlhttp.php?action=bankpipe_get_items",
		dataType: 'json',
		data: function (term, page) {
			return {
				query: term
			};
		},
		results: function (data, page) {
			return {results: data};
		}
	},
	initSelection: function(element, callback) {
        callback($selectBids);
	}
});
// Get users autocomplete
$("#uids").select2({
	placeholder: "{$this->lang->search_for_a_user}",
	minimumInputLength: 2,
	multiple: true,
	ajax: {
		url: "../xmlhttp.php?action=bankpipe_get_users",
		dataType: 'json',
		data: function (term, page) {
			return {
				query: term
			};
		},
		results: function (data, page) {
			return {results: data};
		}
	},
	initSelection: function(element, callback) {
        callback($selectUids);
	}
});
-->
</script>
HTML;
    		
        }
        // Overview
        else {
		
    		$this->page->add_breadcrumb_item($this->lang->bankpipe_discounts, MAINURL . '&action=discounts');
        	$this->page->output_header($this->lang->bankpipe_discounts);
        	$this->page->output_nav_tabs($this->sub_tabs, 'discounts');
        
        	$form = new \Form(MAINURL . "&action=discounts&delete=1&manage", "post", "manage_discount");
        
        	$table = new \Table;
        
        	$table->construct_header($this->lang->bankpipe_discounts_header_code);
        	$table->construct_header($this->lang->bankpipe_discounts_header_value, ['width' => '200px']);
        	$table->construct_header($this->lang->bankpipe_discounts_header_permissions);
        	$table->construct_header($this->lang->bankpipe_discounts_header_expires, ['width' => '200px']);
        	$table->construct_header($this->lang->bankpipe_delete, ['width' => '1px', 'style' => 'text-align: center']);
        
        	$query = $this->db->simple_select(Items::DISCOUNTS_TABLE, '*');
        	if ($this->db->num_rows($query) > 0) {
        
        		while ($discount = $this->db->fetch_array($query)) {
        
        			// Name/code
        			$name = ($discount['name']) ? $discount['name'] : $discount['code'];
        
        			$table->construct_cell("<a href='" . MAINURL . "&action=discounts&manage={$discount['did']}'>{$name}</a>");
        
        			// Value
        			$value = '-' . $discount['value'];
        
        			$value .= ($discount['type'] == 1) ? '%' : ' ' . Core::friendlyCurrency($this->mybb->settings['bankpipe_currency']);
        			$table->construct_cell($value, ['style' => 'text-align: center']);
        
        			// Permissions
        			$arr = [
        				'users' => $discount['uids'],
        				'usergroups' => $discount['gids'],
        				'items' => $discount['bids']
        			];
        
        			$text = [];
        			foreach ($arr as $t => $v) {
        
        				if ($v) {
        
        					$v = count(explode(',', $v));
        
        					$tempString = ($v == 1) ?
        					    'bankpipe_discounts_text_' . $t . '_singular' :
        					    'bankpipe_discounts_text_' . $t;
        
        					$text[] = $v . ' ' . $this->lang->$tempString;
        
        				}
        
        			}
        			
        			$text = implode(', ', $text);
        
        			$text = $text ?? $this->lang->bankpipe_discounts_no_restrictions;
        
        			$table->construct_cell($text, ['style' => 'text-align: center']);
        
        			// Expiry date
        			$expiryDate = ($discount['expires']) ? my_date('relative', $discount['expires']) : $this->lang->bankpipe_discounts_expires_never;
        			$table->construct_cell($expiryDate, ['style' => 'text-align: center']);
        
        			// Delete
        			$table->construct_cell($form->generate_check_box("delete[]", $discount['did']), ['style' => 'text-align: center']);
        			$table->construct_row();
        
        		}
        
        	}
        	else {
        		$table->construct_cell($this->lang->bankpipe_discounts_no_code, ['colspan' => 5, 'style' => 'text-align: center']);
        		$table->construct_row();
        	}
        
        	$table->output($this->lang->bankpipe_discounts . $this->lang->bankpipe_new_discount);
        
        	$buttons = [
        		$form->generate_submit_button($this->lang->bankpipe_discounts_delete)
        	];
        	$form->output_submit_wrapper($buttons);
        	
        	$form->end();
        
        }
    }
}