<?php

namespace BankPipe\Admin;

use BankPipe\Items\Items;
use BankPipe\Core;

class Main
{
    use \BankPipe\Helper\MybbTrait;

    public function __construct()
    {
        $this->traitConstruct(['page', 'sub_tabs']);

        $gateways = [];
        $query = $this->db->simple_select('bankpipe_gateways', 'name');
        while ($name = $this->db->fetch_field($query, 'name')) {
            $gateways[] = $name;
        }

        if ($this->mybb->input['ajaxSave']) {

            foreach ($gateways as $gateway) {

                if ($this->mybb->input[$gateway]) {

                    foreach ($this->mybb->input[$gateway] as $key => $value) {

                        if (is_numeric($value)) {
                            $this->mybb->input[$gateway][$key] = (int) $value;
                        }

                        if (is_string($value)) {
                            $this->mybb->input[$gateway][$key] = $this->db->escape_string($value);
                        }

                    }

                    // Checkboxes
                    foreach (['enabled', 'sandbox'] as $checkbox) {

                        if (!$this->mybb->input[$gateway][$checkbox]) {
                            $this->mybb->input[$gateway][$checkbox] = 0;
                        }

                    }

                    $this->db->update_query('bankpipe_gateways', $this->mybb->input[$gateway], "name = '{$gateway}'");

                    // Add/delete db columns
                    if ((int) $this->mybb->input[$gateway]['enabled']) {

        				if (!$this->db->field_exists($gateway, 'bankpipe_wallets')) {
        					$this->db->add_column('bankpipe_wallets', $gateway, "varchar(255) NOT NULL DEFAULT ''");
        				}

        				if (!$this->db->field_exists($gateway, 'bankpipe_items')) {
        					$this->db->add_column('bankpipe_items', $gateway, "varchar(255) NOT NULL DEFAULT ''");
        				}

                    }
                    else {

                        if ($this->db->field_exists($gateway, 'bankpipe_wallets')) {
        					$this->db->drop_column('bankpipe_wallets', $gateway);
        				}

                        if ($this->db->field_exists($gateway, 'bankpipe_items')) {
        					$this->db->drop_column('bankpipe_items', $gateway);
        				}

                    }

                }

            }

            echo 1;
            exit;

        }

        $this->page->add_breadcrumb_item($this->lang->bankpipe, MAINURL);
        $this->page->output_header($this->lang->bankpipe);
        $this->page->output_nav_tabs($this->sub_tabs, 'general');

        $currency = Core::friendlyCurrency($this->mybb->settings['bankpipe_currency']);

        // Subscriptions
        $form = new \Form(MAINURL . "&action=subscriptions&delete=true", "post", "manage");

        $table = new \Table;

        $table->construct_header($this->lang->bankpipe_subscriptions_name);
        $table->construct_header($this->lang->sprintf($this->lang->bankpipe_subscriptions_price, $currency));
        $table->construct_header($this->lang->bankpipe_delete, ['width' => '1px', 'style' => 'text-align: center']);

        $query = $this->db->simple_select(Items::ITEMS_TABLE, '*', "type = " . Items::SUBSCRIPTION, ['order_by' => 'price ASC']);
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

        echo '<br>';
        echo '<br>';

        // Gateways
        $form = new \Form('index.php', 'post', 'gateways');
		echo $form->generate_hidden_field('module', 'config-bankpipe');
		echo $form->generate_hidden_field('ajaxSave', 1);

		$table = new \Table;

		$table->construct_header($this->lang->bankpipe_gateways_header_name);
		$table->construct_header($this->lang->bankpipe_gateways_header_enabled);
		$table->construct_header($this->lang->bankpipe_gateways_header_identifier);
		$table->construct_header($this->lang->bankpipe_gateways_header_secret);
		$table->construct_header($this->lang->bankpipe_gateways_header_walletid);
		$table->construct_header($this->lang->bankpipe_gateways_header_sandbox);

		$gatewaysCache = [];

        $query = $this->db->simple_select('bankpipe_gateways', '*');

        while ($gateway = $this->db->fetch_array($query)) {
            $gatewaysCache[$gateway['name']] = $gateway;
        }

        foreach ($gateways as $gateway) {

            $current = $gatewaysCache[$gateway];

            $table->construct_cell($gateway);

            $options = ($current['enabled'] == 1) ? ['checked' => 1] : [];
            $table->construct_cell(
                $form->generate_check_box($gateway . '[enabled]', 1, '', $options),
                ['style' => 'text-align: center']
            );

            $table->construct_cell(
                $form->generate_text_box($gateway . '[id]', $current['id'])
            );
            $table->construct_cell(
                $form->generate_text_box($gateway . '[secret]', $current['secret'])
            );
            $table->construct_cell(
                $form->generate_text_box($gateway . '[wallet]', $current['wallet'])
            );

            $options = ($current['sandbox'] == 1) ? ['checked' => 1] : [];
            $table->construct_cell(
                $form->generate_check_box($gateway . '[sandbox]', 1, '', $options),
                ['style' => 'text-align: center']
            );

            $table->construct_row();

        }

		$table->output($this->lang->bankpipe_gateways_title);

		$buttons = [
            $form->generate_submit_button($this->lang->bankpipe_save)
        ];
        $form->output_submit_wrapper($buttons);

		$form->end();

		echo <<<HTML
<script type="text/javascript">

$(document).ready(function() {

	$('#gateways').on('submit', function(e) {

        e.preventDefault();

		$.when(

			$.ajax('index.php?module=config-bankpipe', {
				data: $(this).serialize(),
				type: 'post'
			})

		).then((response) => {

			if (Number(response) === 1) {
				$.jGrowl('Gateways configuration has been saved successfully.', {theme: 'jgrowl_success'});
			}
			else {
				$.jGrowl('Gateways configuration could not be saved due to an unknown reason. Please retry.', {theme: 'jgrowl_error'});
			}

		});

		return false;

	});

});

</script>
HTML;

    }
}
