<?php

namespace BankPipe\Usercp;

use BankPipe\Helper\Permissions;
use BankPipe\Core;

class Manage extends Usercp
{
	public function __construct()
	{
		$this->traitConstruct();

		global $theme, $templates, $headerinclude, $header, $footer, $usercpnav;

		$permissions = new Permissions;

		if (!$permissions->simpleCheck(['manage'])) {
    		throw new \Exception($this->lang->bankpipe_error_functionality_not_allowed);
		}

		$uid = (int) $this->mybb->user['uid'];

		$payee = $this->mybb->user['payee'];

		if ($this->mybb->request_method == 'post') {

			// Delete all items if no payee is specified
			// TO-DO: this is quite unreliable. gid = 0 should be replaced with "type" once it is implemented in the db
			// TO-DO: implement "type" in the db
			if ($payee and !$this->mybb->input['payee']) {
				$this->db->delete_query('bankpipe_items', 'gid = 0 AND uid = ' . $uid);
			}

			// Add payee
			if ($this->mybb->input['payee'] != $payee) {

				$this->db->update_query(
				    'users',
				    ['payee' => $this->db->escape_string($this->mybb->input['payee'])],
				    'uid = ' . $uid
				);

			}

			if ($this->mybb->input['items']) {

        		$this->plugins->run_hooks('bankpipe_ucp_manage_edit', $this);

				$items = (array) $this->mybb->input['items'];
				$olditems = (array) $this->mybb->input['olditems'];

				foreach ($items as $bid => $price) {

					$price = Core::filterPrice($price);
					$oldprice = Core::filterPrice($olditems[$bid]);

					if ($price == $oldprice) {
						continue;
					}

					// Delete if price has dropped to zero or below
					if (!$price or $price <= 0) {
    					$this->db->delete_query('bankpipe_items', 'bid = ' . (int) $bid . ' AND uid = ' . $uid);
					}
					else {

    					$update = [
    						'price' => $price
    					];

    					$this->db->update_query('bankpipe_items', $update, 'bid = ' . (int) $bid . ' AND uid = ' . $uid);

    				}

				}

			}

			redirect(
			    'usercp.php?action=manage',
			    $this->lang->bankpipe_success_settings_edited_desc,
			    $this->lang->bankpipe_success_settings_edited
            );

		}

		$this->plugins->run_hooks('bankpipe_ucp_manage_start', $this);

		add_breadcrumb($this->lang->bankpipe_nav, 'usercp.php');
		add_breadcrumb($this->lang->bankpipe_nav_purchases, 'usercp.php?action=manage');

		$query = $this->db->simple_select('bankpipe_items', 'COUNT(bid) AS total', 'uid = ' . $uid);
		$totalItems = $this->db->fetch_field($query, 'total');

		$perpage = 20;

		if ($this->mybb->input['page'] > 0) {

			$page = $this->mybb->input['page'];
			$start = ($page-1) * $perpage;
			$pages = $totalItems / $perpage;
			$pages = ceil($pages);

			if ($page > $pages || $page <= 0) {
				$start = 0;
				$page = 1;
			}

		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($totalItems, $perpage, $page, 'usercp.php?action=bankpipe-manage');

		$items = '';

		$query = $this->db->query('
			SELECT i.*, a.*, p.subject
			FROM ' . TABLE_PREFIX . 'bankpipe_items i
			LEFT JOIN ' . TABLE_PREFIX . 'attachments a ON (i.aid = a.aid)
			LEFT JOIN ' . TABLE_PREFIX . 'posts p ON (p.pid = a.pid)
			WHERE i.uid = ' . $uid . '
			ORDER BY a.dateuploaded DESC
			LIMIT ' . $start . ', ' . $perpage
		);
		if ($this->db->num_rows($query) > 0) {

			$orphaned = [];

			while ($item = $this->db->fetch_array($query)) {

				if (!$item['aid']) {
					$orphaned[] = (int) $item['bid'];
					continue;
				}

				$item['postlink'] = get_post_link($item['pid']);
				$size = get_friendly_size($item['filesize']);
				$ext = get_extension($item['filename']);
				$icon = get_attachment_icon($ext);

				eval("\$items .= \"".$templates->get("bankpipe_manage_items_item")."\";");

			}

			// Delete orphaned items
			if ($orphaned) {
				$this->db->delete_query('bankpipe_items', 'bid IN (' . implode(',', $orphaned) . ')');
			}

			eval("\$items = \"".$templates->get("bankpipe_manage_items")."\";");

		}
		else {
			eval("\$items = \"".$templates->get("bankpipe_manage_items_no_items")."\";");
		}

		$this->plugins->run_hooks('bankpipe_ucp_manage_end', $this);

		eval("\$page = \"".$templates->get("bankpipe_manage")."\";");
		output_page($page);
	}
}