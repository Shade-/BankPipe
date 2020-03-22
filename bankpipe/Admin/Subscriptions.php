<?php

namespace BankPipe\Admin;

use BankPipe\Items\Items;
use BankPipe\Core;

class Subscriptions
{
    use \BankPipe\Helper\MybbTrait;

    public function __construct()
    {
        $this->traitConstruct(['page', 'sub_tabs', 'cache']);

        // Get this subscription
        $bid = (int) $this->mybb->get_input('bid');

        if ($bid) {

            $query = $this->db->simple_select(Items::ITEMS_TABLE, '*', "bid = '" . $bid . "'", ['limit' => 1]);
            $subscription = $this->db->fetch_array($query);

            if (!$subscription['bid']) {
                flash_message($this->lang->bankpipe_error_invalid_item);
                admin_redirect(MAINURL);
            }

        }

        if ($this->mybb->request_method == 'post') {

            $price = $this->mybb->input['price'];

            if (!$this->mybb->input['delete'] and (!$price or $price <= 0)) {
                flash_message($this->lang->bankpipe_error_price_not_valid, 'error');
                admin_redirect(MAINURL);
            }

            $items = new Items;

            if ($this->mybb->input['delete']) {

                $message = $this->lang->bankpipe_success_subscription_deleted;
                $this->db->delete_query(Items::ITEMS_TABLE, "bid IN ('" . implode("','", (array) $this->mybb->input['delete']) . "')");

            }
            else {

                $message = (!$bid) ?
                    $this->lang->bankpipe_success_subscription_added :
                    $this->lang->bankpipe_success_subscription_edited;

                if ($bid) {
                    $this->mybb->input['bid'] = $bid;
                }

                $this->mybb->input['discount'] = (int) $this->mybb->input['discount'];
                $this->mybb->input['expires'] = (int) $this->mybb->input['expires'];
                $this->mybb->input['gid'] = $this->mybb->input['usergroups'];
                $this->mybb->input['permittedgroups'] = $this->mybb->input['permittedgroups']; // Not a mistake. If it's !isset(), it can't be changed.

                $items->insert([$this->mybb->input]);

            }

            // Redirect
            flash_message($message, 'success');
            admin_redirect(MAINURL);

        }

        // Default values
        if ($bid) {

            foreach ($subscription as $field => $value) {
                $this->mybb->input[$field] = $value;
            }

        }

        $currency = Core::friendlyCurrency($this->mybb->settings['bankpipe_currency']);

        $title = ($bid) ?
            $this->lang->sprintf($this->lang->bankpipe_edit_subscription, $subscription['name']) :
            $this->lang->bankpipe_add_subscription;

        $this->page->add_breadcrumb_item($title, MAINURL . '&action=subscriptions');
        $this->page->output_header($title);
        $this->page->output_nav_tabs($this->sub_tabs, 'subscriptions');

        // Determine the post request attributes
        $extraAction = ($bid) ? "&bid=" . $subscription['bid'] : '';

        $form = new \Form(MAINURL . "&action=subscriptions" . $extraAction, "post", "subscriptions");

        $container = new \FormContainer($title);

        $container->output_row(
            $this->lang->bankpipe_subscriptions_name,
            $this->lang->bankpipe_subscriptions_name_desc,
            $form->generate_text_box('name', $this->mybb->input['name'], [
                'id' => 'name',
                'maxlength' => 127
            ]),
            'name'
        );

        $container->output_row(
            $this->lang->bankpipe_subscriptions_description,
            $this->lang->bankpipe_subscriptions_description_desc,
            $form->generate_text_area('description', $this->mybb->input['description'], [
                'id' => 'description',
                'maxlength' => 127
            ]),
            'description'
        );

        // Custom wallet
        $html = '';

        $query = $this->db->simple_select('bankpipe_gateways', '*');
        while ($gateway = $this->db->fetch_array($query)) {

            $html .= $form->generate_text_box($gateway['name'], $this->mybb->input[$gateway['name']], [
                'id' => $gateway['name'],
                'style' => '" autocomplete="off" placeholder="' . $gateway['name']
            ]) . ' ';

        }

        $container->output_row(
            $this->lang->bankpipe_subscriptions_wallet,
            $this->lang->bankpipe_subscriptions_wallet_desc,
            $html,
            'wallet'
        );

        $container->output_row(
            $this->lang->bankpipe_subscriptions_htmldescription,
            $this->lang->bankpipe_subscriptions_htmldescription_desc,
            $form->generate_text_area('htmldescription', $this->mybb->input['htmldescription'], [
                'id' => 'htmldescription'
            ]),
            'htmldescription'
        );

        $container->output_row(
            $this->lang->sprintf($this->lang->bankpipe_subscriptions_price, $currency),
            $this->lang->bankpipe_subscriptions_price_desc,
            $form->generate_text_box('price', $this->mybb->input['price'], [
                'id' => 'price'
            ]),
            'price'
        );

        // Subscription usergroup
        $subusergroups = [];

        $permissionsGroupsCache = $groupsCache = $this->cache->read('usergroups');
        unset($groupsCache[1]); // 1 = guests. Exclude them

        foreach ($groupsCache as $group) {
            $subusergroups[$group['gid']] = $group['title'];
        }
        
        // Default value
        $selectedGroups = explode(',', $this->mybb->input['gid']);

        $container->output_row(
            $this->lang->bankpipe_subscriptions_usergroup,
            $this->lang->bankpipe_subscriptions_usergroup_desc,
            $form->generate_select_box('usergroups[]', $subusergroups, $selectedGroups, [
                'id' => 'usergroups',
                'multiple' => true
            ])
        );

        $container->output_row(
            $this->lang->bankpipe_subscriptions_change_primary,
            $this->lang->bankpipe_subscriptions_change_primary_desc,
            $form->generate_yes_no_radio('primarygroup', $this->mybb->input['primarygroup'], true)
        );

        $container->output_row(
            $this->lang->bankpipe_subscriptions_discount,
            $this->lang->bankpipe_subscriptions_discount_desc,
            $form->generate_text_box('discount', $this->mybb->input['discount'], [
                'id' => 'discount'
            ])
        );
        
        // Permissions
        $permissionsGroups = [];
        foreach ($permissionsGroupsCache as $group) {
            $permissionsGroups[$group['gid']] = $group['title'];
        }
        
        // Default value
        $selectedGroups = explode(',', $this->mybb->input['permittedgroups']);

        $container->output_row(
            $this->lang->bankpipe_subscriptions_permitted_usergroups,
            $this->lang->bankpipe_subscriptions_permitted_usergroups_desc,
            $form->generate_select_box('permittedgroups[]', $permissionsGroups, $selectedGroups, [
                'id' => 'permittedgroups',
                'multiple' => true
            ])
        );

        // Expiration date
        $container->output_row(
            $this->lang->bankpipe_subscriptions_expires,
            $this->lang->bankpipe_subscriptions_expires_desc,
            $form->generate_text_box('expires', $this->mybb->input['expires'], [
                'id' => 'expires'
            ])
        );

        // Expiry usergroup
        $expirygid = [
            $this->lang->bankpipe_subscriptions_use_default_usergroup
        ];

        foreach ($groupsCache as $group) {
            $expirygid[$group['gid']] = $group['title'];
        }

        $container->output_row(
            $this->lang->bankpipe_subscriptions_expiry_usergroup,
            $this->lang->bankpipe_subscriptions_expiry_usergroup_desc,
            $form->generate_select_box('expirygid', $expirygid, [$this->mybb->input['expirygid']], [
                'id' => 'expirygid'
            ])
        );

        $container->end();

        echo $form->generate_hidden_field('type', Items::SUBSCRIPTION);

        $buttons = [
            $form->generate_submit_button($this->lang->bankpipe_save)
        ];
        $form->output_submit_wrapper($buttons);
        $form->end();
    }
}
