<?php

namespace BankPipe\Usercp;

use BankPipe\Helper\Cookies;
use BankPipe\Helper\Permissions;
use BankPipe\Messages\Handler as Messages;

class Discounts extends Usercp
{
    public function __construct()
    {
        $this->traitConstruct();

        global $templates;

        $cookies = new Cookies;
        $permissions = new Permissions;
        $messages = new Messages;

        $existingDiscounts = $cookies->read('discounts');

        if ($this->mybb->input['add']) {

            $this->plugins->run_hooks('bankpipe_ucp_discounts_add_start', $this);

            $errors = [];
            $newCode = (string) $this->mybb->input['code'];

            $query = $this->db->simple_select('bankpipe_discounts', '*', "code = '" . $this->db->escape_string($newCode) . "'", ['limit' => 1]);
            $discount = $this->db->fetch_array($query);

            // Already there buddy! We got ya
            if ($discount['did'] and in_array($discount['did'], $existingDiscounts)) {
                $errors[] = $this->lang->bankpipe_error_code_already_applied;
            }

            if (!$discount['did']) {
                $errors[] = $this->lang->bankpipe_error_code_not_found;
            }
            else if ($discount['expires'] and $discount['expires'] < TIME_NOW) {
                $errors[] = $this->lang->bankpipe_error_code_expired;
            }

            // Check for permissions
            if (!$errors and !$permissions->discountCheck($discount)) {
                $errors[] = $this->lang->bankpipe_error_code_not_allowed;
            }

            // Is THIS CODE stackable?
            if (!$errors and !$discount['stackable'] and count($existingDiscounts) > 0) {
                $errors[] = $this->lang->bankpipe_error_code_not_allowed_stackable;
            }

            // Is THE CURRENT APPLIED CODE stackable? Account for the first value, as if there are many, they are all stackable by design
            if (!$errors and count($existingDiscounts) > 0) {

                $query = $this->db->simple_select('bankpipe_discounts', 'did, stackable', "did = '" . (int) reset($existingDiscounts) . "'");
                $existingCode = $this->db->fetch_array($query);

                if ($existingCode['did'] and !$existingCode['stackable']) {
                    $errors[] = $this->lang->bankpipe_error_other_codes_not_allowed_stackable;
                }

            }

            $args = [&$this, &$errors, &$discount];
            $this->plugins->run_hooks('bankpipe_ucp_discounts_add_end', $args);

            if (!$errors) {

                $existingDiscounts[] = $discount['did'];

                $cookies->write('discounts', $existingDiscounts);

                $discount['aids'] = [];

                // Look up for the allowed aids
                if ($discount['bids']) {

                    $query = $this->db->simple_select('bankpipe_items', 'aid', "bid IN (" . $this->db->escape_string($discount['bids']) . ")");
                    while ($aid = $this->db->fetch_field($query, 'aid')) {
                        $discount['aids'][] = $aid;
                    }

                }

                if (!$discount['aids']) {
                    $discount['aids'] = 'all';
                }

                $discount['suffix'] = ($discount['type'] == 1) ? '%' : ' ' . $this->mybb->settings['bankpipe_currency'];

                $code = ($discount['name']) ? $discount['name'] : $discount['code'];

                eval("\$template = \"".$templates->get("bankpipe_discounts_code")."\";");

                $messages->display([
                    'message' => $this->lang->bankpipe_discount_applied,
                    'data' => $discount,
                    'template' => $template
                ]);

            }
            else {
                $messages->error($errors);
            }

        }
        else if ($this->mybb->input['delete']) {

            $this->plugins->run_hooks('bankpipe_ucp_discounts_delete', $this);

            $discountId = (int) $this->mybb->input['did'];

            if (($key = array_search($discountId, $existingDiscounts)) !== false) {
                unset($existingDiscounts[$key]);
            }

            if ($existingDiscounts) {
                $cookies->write('discounts', $existingDiscounts);
            }
            else {
                $cookies->destroy('discounts');
            }

            $messages->display([
                'message' => $this->lang->bankpipe_discounts_removed
            ]);

        }
    }
}
