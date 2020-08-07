<?php

namespace BankPipe\Admin;

use BankPipe\Items\Orders;
use BankPipe\Core;
use BankPipe\Logs\Handler as Logs;

class Purchases
{
    use \BankPipe\Helper\MybbTrait;

    public function __construct()
    {
        $this->traitConstruct(['page', 'sub_tabs', 'cache']);

        $ordersHandler = new Orders;

        // Get this purchase
        $invoice = $this->mybb->input['invoice'];

        if ($invoice) {

            $order = reset($ordersHandler->get([
                'invoice' => $invoice
            ], [
                'includeItemsInfo' => true
            ]));

            $buyer = get_user($order['uid']);
            $merchant = ($order['merchant']) ? get_user($order['merchant']) : [];

            if (!$order['invoice']) {
                flash_message($this->lang->bankpipe_error_invalid_purchase, 'error');
                admin_redirect(MAINURL);
            }

        }

        // Reactivate
        if ($this->mybb->input['sub'] == 'reactivate') {

            $ordersHandler->update([
                'active' => 1
            ], $invoice);

            // Upgrade user if previously inactive
            if (!$order['active']) {
                $ordersHandler->utilities->upgradeUser($buyer['uid'], $invoice);
            }

            flash_message($this->lang->bankpipe_success_purchase_reactivated, 'success');
            admin_redirect(MAINURL . '&action=history');

        }

        if ($this->mybb->request_method == 'post') {

            // Revoke
            if ($this->mybb->input['sub'] == 'revoke') {

                if (!$this->mybb->input['no']) {

                    $ordersHandler->update([
                        'active' => 0
                    ], $invoice);

                    // Demote user if previously active
                    if ($order['active']) {
                        $ordersHandler->utilities->demoteUser($buyer['uid'], $invoice);
                    }

                    flash_message($this->lang->bankpipe_success_purchase_revoked, 'success');

                }

                admin_redirect(MAINURL . '&action=history');

            }

            // Refund
            if ($this->mybb->input['sub'] == 'refund') {

                $defaultGateway = ($mybb->input['gateway']) ? $mybb->input['gateway'] : 'PayPal';

                try {

                    $className = 'BankPipe\Gateway\\' . $defaultGateway;
                    $gateway = new $className();

                }
                catch (Throwable $t) {
                    echo "<pre>";
                    print_r($t);
                    exit;
                }

                $refund = ($this->mybb->input['amount']) ?
                    [
                        'amount' => Core::filterPrice($this->mybb->input['amount']),
                        'currency' => Core::friendlyCurrency($order['currency'])
                    ]
                    : [];

                $response = $gateway
                    ->refund($refund)
                    ->setTransactionReference($order['sale'])
                    ->send();

                $data = $response->getData();

                if ($response->isSuccessful()) {

                    // Deactivate this order
                    $ordersHandler->update([
                        'active' => 0,
                        'refund' => $data['id'],
                        'type' => Orders::REFUND
                    ], $order['invoice']);

                    // Log
                    (new Logs($order['invoice']))->save([
                        'type' => Orders::REFUND,
                        'bids' => array_column($order['items'], 'bid')
                    ]);

                    flash_message(
                        $this->lang->sprintf(
                            $this->lang->bankpipe_success_purchase_refunded,
                            $data['amount']['total'] . ' ' . Core::friendlyCurrency($data['amount']['currency']),
                            $data['refund_from_transaction_fee']['value'] . ' ' . Core::friendlyCurrency($data['refund_from_transaction_fee']['currency'])
                        ),
                        'success'
                    );
                }
                else {
                    flash_message($data['message'], 'error');
                }

                admin_redirect(MAINURL . '&action=history');

            }

            $this->mybb->input['expires'] = get_formatted_date($this->mybb->input['expires']);

            $data = [
                'oldgid' => (int) $this->mybb->input['oldgid'],
                'active' => (int) $this->mybb->input['active'],
                'expires' => (int) $this->mybb->input['expires']
            ];

            $ordersHandler->update($data, $invoice);

            // Demote user if previously active
            if ($data['active'] === 0 and $order['active']) {
                $ordersHandler->utilities->demoteUser($buyer['uid'], $invoice);
            }
            // Upgrade user if previously inactive
            else if ($data['active'] === 1 and !$order['active']) {
                $ordersHandler->utilities->upgradeUser($buyer['uid'], $invoice);
            }

            // Redirect
            flash_message($this->lang->bankpipe_success_purchase_edited, 'success');
            admin_redirect(MAINURL . '&action=history');

        }

        // Default values
        if ($invoice) {

            foreach ($order as $field => $value) {
                $this->mybb->input[$field] = $value;
            }

        }

        // Revoke
        if ($this->mybb->input['sub'] == 'revoke') {
            $this->page->output_confirm_action(MAINURL . "&action=purchases&sub=revoke&invoice=" . $invoice, $this->lang->bankpipe_revoke_purchase, $this->lang->bankpipe_revoke_purchase_title);
        }

        $this->page->add_breadcrumb_item($this->lang->bankpipe_manage_purchase, MAINURL . '&action=purchases&sub=' . $this->mybb->input['sub']);
        $this->page->output_header($this->lang->bankpipe_manage_purchase);
        $this->page->output_nav_tabs($this->sub_tabs, 'purchases');

        $form = new \Form(MAINURL . "&action=purchases&sub=" . $this->mybb->input['sub'] . "&invoice=" . $invoice, "post", $this->mybb->input['sub']);

        $main = new \Table;
        $table = new \Table;

        // Name
        $html = implode(', ', array_column($order['items'], 'name'));
        $table->construct_cell($this->lang->bankpipe_edit_purchase_name, [
            'width' => '30%', // This rules the width of all subsequent rows
            'style' => 'font-weight: 700'
        ]);
        $table->construct_cell(<<<HTML
<div class="form_row">
    {$html}
</div>
HTML
);
        $table->construct_row();

        // Price
        $html = $order['total'] . ' ' . $order['currency'];
        $table->construct_cell($this->lang->bankpipe_edit_purchase_total, [
            'style' => 'font-weight: 700'
        ]);
        $table->construct_cell(<<<HTML
<div class="form_row">
    {$html}
</div>
HTML
);
        $table->construct_row();

        // Fee
        if ($order['fee']) {

            $html = $order['fee'] . ' ' . $order['currency'];
            $table->construct_cell($this->lang->bankpipe_edit_purchase_fee, [
                'style' => 'font-weight: 700'
            ]);
            $table->construct_cell(<<<HTML
    <div class="form_row">
        {$html}
    </div>
HTML
);
            $table->construct_row();

        }

        // Buyer
        $text = build_profile_link(format_name($buyer['username'], $buyer['usergroup'], $buyer['displaygroup']), $buyer['uid']);
        if ($order['email'] or $order['payer_id']) {
            $text = implode(', ', [$text, $order['email'], $order['payer_id']]);
        }

        $table->construct_cell($this->lang->bankpipe_edit_purchase_bought_by, [
            'style' => 'font-weight: 700'
        ]);
        $table->construct_cell(<<<HTML
<div class="form_row">
    {$text}
</div>
HTML
);
        $table->construct_row();

        // Merchant
        if ($merchant) {

            $html = build_profile_link(format_name($merchant['username'], $merchant['usergroup'], $merchant['displaygroup']), $merchant['uid']);
            $table->construct_cell($this->lang->bankpipe_edit_purchase_merchant, [
                'style' => 'font-weight: 700'
            ]);
            $table->construct_cell(<<<HTML
    <div class="form_row">
        {$html}
    </div>
HTML
);
            $table->construct_row();

        }

        // Date of purchase
        $html = my_date('relative', $order['date']);
        $table->construct_cell($this->lang->bankpipe_edit_purchase_date, [
            'style' => 'font-weight: 700'
        ]);
        $table->construct_cell(<<<HTML
<div class="form_row">
    {$html}
</div>
HTML
);
        $table->construct_row();

        // Sale ID
        if ($order['sale']) {

            $table->construct_cell($this->lang->bankpipe_edit_purchase_sale_id, [
                'style' => 'font-weight: 700'
            ]);
            $table->construct_cell(<<<HTML
<div class="form_row">
    {$order['sale']}
</div>
HTML
);
            $table->construct_row();

        }

        // Payment ID
        if ($order['payment_id']) {

            $table->construct_cell($this->lang->bankpipe_edit_purchase_payment_id, [
                'style' => 'font-weight: 700'
            ]);
            $table->construct_cell(<<<HTML
<div class="form_row">
    {$order['payment_id']}
</div>
HTML
);
            $table->construct_row();

        }

        // Status
        switch ($order['type']) {

            case Orders::PENDING:
                $html = $this->lang->bankpipe_edit_purchase_pending;
                break;

            case Orders::SUCCESS:
                $html = $this->lang->bankpipe_edit_purchase_success;
                break;

            case Orders::REFUND:
                $html = $this->lang->bankpipe_edit_purchase_refund;
                break;

            case Orders::FAIL:
                $html = $this->lang->bankpipe_edit_purchase_fail;
                break;

            case Orders::MANUAL:
                $html = $this->lang->bankpipe_edit_purchase_manual;
                break;

        }
        $table->construct_cell($this->lang->bankpipe_edit_purchase_status, [
            'style' => 'font-weight: 700'
        ]);
        $table->construct_cell(<<<HTML
<div class="form_row">
    {$html}
</div>
HTML
);
        $table->construct_row();

        if ($order['refund']) {

            $query = $this->db->simple_select('bankpipe_log', 'date', "type = " . Orders::REFUND . " AND pid = " . (int) $invoice);
            $date = $this->db->fetch_field($query, 'date');

            if ($date > 0) {

                $html = my_date('relative', $date);
                $table->construct_cell($this->lang->bankpipe_edit_purchase_refund_date, [
                    'style' => 'font-weight: 700'
                ]);
                $table->construct_cell(<<<HTML
<div class="form_row">
{$html}
</div>
HTML
);
                $table->construct_row();

            }

        }

        $main->construct_cell($table->output('', 0, '', true), [
            'width' => '50%'
        ]);

        $table = new \Table;

        // Edit
        if ($this->mybb->input['sub'] == 'edit') {

            $oldgid = [
                $this->lang->bankpipe_edit_purchase_no_group
            ];

            $groups_cache = $this->cache->read('usergroups');
            foreach ($groups_cache as $group) {
                $oldgid[$group['gid']] = $group['title'];
            }

            $html = $form->generate_select_box('oldgid', $oldgid, [$this->mybb->input['oldgid']], [
                'id' => 'oldgid'
            ]);
            $table->construct_cell(<<<HTML
<label>{$this->lang->bankpipe_edit_purchase_oldgid}</label>
<div class="description">
    {$this->lang->bankpipe_edit_purchase_oldgid_desc}
</div>
<div class="form_row">
    {$html}
</div>
HTML
);
            $table->construct_row();

            $html = $form->generate_text_box('expires', format_date($this->mybb->input['expires']), [
                'id' => 'expires'
            ]);
            $table->construct_cell(<<<HTML
<label>{$this->lang->bankpipe_edit_purchase_expires}</label>
<div class="description">
    {$this->lang->bankpipe_edit_purchase_expires_desc}
</div>
<div class="form_row">
    {$html}
</div>
HTML
);
            $table->construct_row();

            $html = $form->generate_check_box('active', 1, $this->lang->bankpipe_edit_purchase_active, [
                'checked' => $this->mybb->input['active']
            ]);
            $table->construct_cell(<<<HTML
<label>{$this->lang->bankpipe_edit_purchase_active}</label>
<div class="description">
    {$this->lang->bankpipe_edit_purchase_active_desc}
</div>
<div class="form_row">
    {$html}
</div>
HTML
);
            $table->construct_row();

        }
        // Refund
        else if ($this->mybb->input['sub'] == 'refund') {

            $html = $form->generate_text_box('amount', $this->mybb->input['amount'], [
                'id' => 'amount'
            ]);
            $table->construct_cell(<<<HTML
<label>{$this->lang->bankpipe_refund_purchase_amount}</label>
<div class="description">
    {$this->lang->bankpipe_refund_purchase_amount_desc}
</div>
<div class="form_row">
    {$html}
</div>
HTML
);
            $table->construct_row();

        }

        $main->construct_cell($table->output('', 0, '', true));
        $main->construct_row();

        $main->output($this->lang->bankpipe_manage_purchase, 1, "general form_container");

        $buttons = [
            $form->generate_submit_button($this->lang->bankpipe_save)
        ];
        $form->output_submit_wrapper($buttons);

        $form->end();

        $format = get_datepicker_format();

        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.css" type="text/css" />
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.js"></script>
<script type="text/javascript">
<!--
// Date picking
var expiry = $("#expires").datepicker({
    autoHide: true,
    format: \'' . $format . '\'
})
-->
</script>';
    }
}
