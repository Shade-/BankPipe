<?php

namespace BankPipe\Admin;

use BankPipe\Items\Items;

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

            if (!$this->mybb->settings['bankpipe_subscription_payee']) {
                flash_message($this->lang->bankpipe_error_missing_default_payee, 'error');
                admin_redirect(MAINURL);
            }

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
            else if (!$bid) {

                $message = $this->lang->bankpipe_success_subscription_added;
                $items->insert([$this->mybb->input]);

            }
            else {

                $message = $this->lang->bankpipe_success_subscription_edited;
                $this->db->update_query(Items::ITEMS_TABLE, $data, "bid = '" . $subscription['bid'] . "'");

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

        $container->output_row(
            $this->lang->bankpipe_subscriptions_email,
            $this->lang->bankpipe_subscriptions_email_desc,
            $form->generate_text_box('email', $this->mybb->input['email'], [
                'id' => 'email',
                'style' => '" autocomplete="off" placeholder="Default: ' . $this->mybb->settings['bankpipe_subscription_payee']
            ]),
            'email'
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
            $this->lang->bankpipe_subscriptions_price,
            $this->lang->bankpipe_subscriptions_price_desc,
            $form->generate_text_box('price', $this->mybb->input['price'], [
                'id' => 'price'
            ]),
            'price'
        );

        // Subscription usergroup
        $subusergroups = [];

        $groups_cache = $this->cache->read('usergroups');
        unset($groups_cache[1]); // 1 = guests. Exclude them

        foreach ($groups_cache as $group) {
            $subusergroups[$group['gid']] = $group['title'];
        }

        $container->output_row(
            $this->lang->bankpipe_subscriptions_usergroup,
            $this->lang->bankpipe_subscriptions_usergroup_desc,
            $form->generate_select_box('gid', $subusergroups, [$this->mybb->input['gid']], [
                'id' => 'gid'
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

        foreach ($groups_cache as $group) {
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
