<?php

namespace BankPipe\Admin;

class Main
{
    use \BankPipe\Helper\MybbTrait;

    public function __construct()
    {
        $this->traitConstruct(['page', 'sub_tabs']);

        $this->page->add_breadcrumb_item($this->lang->bankpipe, MAINURL);
        $this->page->output_header($this->lang->bankpipe);
        $this->page->output_nav_tabs($this->sub_tabs, 'general');

        $form = new \Form(MAINURL . "&action=subscriptions&delete=true", "post", "manage");

        $table = new \Table;

        $table->construct_header($this->lang->bankpipe_subscriptions_name);
        $table->construct_header($this->lang->bankpipe_subscriptions_price);
        $table->construct_header($this->lang->bankpipe_delete, ['width' => '1px', 'style' => 'text-align: center']);

        $query = $this->db->simple_select('bankpipe_items', '*', "gid <> 0", ['order_by' => 'price ASC']);
        while ($subscription = $this->db->fetch_array($query)) {

            $table->construct_cell("<a href='" . MAINURL . "&action=subscriptions&bid={$subscription['bid']}'>{$subscription['name']}</a>");
            $table->construct_cell($subscription['price']);
            $table->construct_cell($form->generate_check_box("delete[]", $subscription['bid']), ['style' => 'text-align: center']);
            $table->construct_row();

        }

        if ($this->db->num_rows($query) == 0) {
            $table->construct_cell($this->lang->bankpipe_subscriptions_no_subscription, ['colspan' => 3]);
            $table->construct_row();
        }

        $table->output($this->lang->bankpipe_overview_available_subscriptions . $this->lang->bankpipe_new_subscription);

        $buttons = [
            $form->generate_submit_button($this->lang->bankpipe_subscriptions_delete)
        ];
        $form->output_submit_wrapper($buttons);
        $form->end();
    }
}
