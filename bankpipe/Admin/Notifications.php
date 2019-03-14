<?php

namespace BankPipe\Admin;

class Notifications
{
	use \BankPipe\Helper\MybbTrait;

	public function __construct()
	{
		$this->traitConstruct(['page', 'sub_tabs']);
		
		// Manage
		if (isset($this->mybb->input['manage'])) {
    		
    		// Get this notification
        	$nid = (int) $this->mybb->get_input('manage');
        
        	if ($nid) {
        
        		$query = $this->db->simple_select('bankpipe_notifications', '*', "nid = '" . $nid . "'", ['limit' => 1]);
        		$notification = $this->db->fetch_array($query);
        
        		if (!$notification['nid']) {
        			flash_message($this->lang->bankpipe_error_invalid_notification);
        			admin_redirect(MAINURL);
        		}
        
        	}
        
        	if ($this->mybb->request_method == 'post') {
        
        		$data = [
        			'title' => $this->db->escape_string($this->mybb->input['title']),
        			'description' => $this->db->escape_string($this->mybb->input['description']),
        			'daysbefore' => (int) $this->mybb->input['daysbefore'],
        			'method' => $this->db->escape_string($this->mybb->input['method'])
        		];
        
        		if ($this->mybb->input['delete']) {
        			$message = $this->lang->bankpipe_success_notification_deleted;
        			$this->db->delete_query('bankpipe_notifications', "nid IN ('" . implode("','", (array) $this->mybb->input['delete']) . "')");
        		}
        		else if (!$nid) {
        			$message = $this->lang->bankpipe_success_notification_added;
        			$this->db->insert_query('bankpipe_notifications', $data);
        		}
        		else {
        			$message = $this->lang->bankpipe_success_notification_edited;
        			$this->db->update_query('bankpipe_notifications', $data, "nid = '" . $notification['nid'] . "'");
        		}
        
        		// Redirect
        		flash_message($message, 'success');
        		admin_redirect(MAINURL . '&action=notifications');
        
        	}
        
        	// Default values
        	if ($nid) {
        
        		foreach ($notification as $field => $value) {
        			$this->mybb->input[$field] = $value;
        		}
        
        	}
        
        	$title = ($nid) ?
        	    $this->lang->sprintf($this->lang->bankpipe_edit_notification, $notification['title']) :
        	    $this->lang->bankpipe_add_notification;
        
        	$this->page->add_breadcrumb_item($title, MAINURL . '&action=notifications');
        	$this->page->output_header($title);
        	$this->page->output_nav_tabs($this->sub_tabs, 'notifications');
        
        	$form = new \Form(MAINURL . "&action=notifications&manage=" . $notification['nid'], "post", "manage");
        	$container = new \FormContainer($title);
        
        	$container->output_row(
        	    $this->lang->bankpipe_manage_notification_title,
        	    $this->lang->bankpipe_manage_notification_title_desc,
        	    $form->generate_text_box('title', $this->mybb->input['title'], [
        		    'id' => 'title'
                ])
                , 'title'
            );
        
        	$container->output_row(
        	    $this->lang->bankpipe_manage_notification_description,
        	    $this->lang->bankpipe_manage_notification_description_desc,
        	    $form->generate_text_area('description', $this->mybb->input['description'], [
            		'id' => 'description'
            	]),
            	'description'
            );
        
        	$container->output_row(
        	    $this->lang->bankpipe_manage_notification_method,
        	    $this->lang->bankpipe_manage_notification_method_desc,
        	    $form->generate_select_box('method', [
            		'pm' => 'Private message',
            		'email' => 'Email'
            	], $this->mybb->input['method'], [
            		'id' => 'method'
            	])
            );
        
        	$container->output_row(
        	    $this->lang->bankpipe_manage_notification_daysbefore,
        	    $this->lang->bankpipe_manage_notification_daysbefore_desc,
        	    $form->generate_text_box('daysbefore', $this->mybb->input['daysbefore'], [
            		'id' => 'daysbefore'
            	])
            );
        
        	$container->end();
        
        	$buttons = [
        		$form->generate_submit_button($this->lang->bankpipe_save)
        	];
        	$form->output_submit_wrapper($buttons);
        	$form->end();
    		
		}
		// Overview
		else {
    		
    		$this->page->add_breadcrumb_item($this->lang->bankpipe_notifications, MAINURL);
        	$this->page->output_header($this->lang->bankpipe_notifications);
        	$this->page->output_nav_tabs($this->sub_tabs, 'notifications');
        
        	$form = new \Form(MAINURL . "&action=notifications&delete=1&manage", "post", "manage");
        
        	$table = new \Table;
        
        	$table->construct_header($this->lang->bankpipe_notifications_header_notification);
        	$table->construct_header($this->lang->bankpipe_notifications_header_days_before, ['width' => '200px']);
        	$table->construct_header($this->lang->bankpipe_delete, ['width' => '1px', 'style' => 'text-align: center']);
        
        	$query = $this->db->simple_select('bankpipe_notifications', '*');
        	if ($this->db->num_rows($query) > 0) {
        
        		while ($notification = $this->db->fetch_array($query)) {
        
        			$table->construct_cell("<a href='" . MAINURL . "&action=notifications&manage={$notification['nid']}'>{$notification['title']}</a>");
        			$table->construct_cell($notification['daysbefore'], ['style' => 'text-align: center']);
        			$table->construct_cell($form->generate_check_box("delete[]", $notification['nid']), ['style' => 'text-align: center']);
        			$table->construct_row();
        
        		}
        
        	}
        	else {
        		$table->construct_cell($this->lang->bankpipe_notifications_no_notification, ['colspan' => 3, 'style' => 'text-align: center']);
        		$table->construct_row();
        	}
        
        	$table->output($this->lang->bankpipe_notifications . $this->lang->bankpipe_new_notification);
        
        	$buttons = [
        		$form->generate_submit_button($this->lang->bankpipe_notifications_delete)
        	];
        	$form->output_submit_wrapper($buttons);
        	
        	$form->end();
        	
        }
    }
}