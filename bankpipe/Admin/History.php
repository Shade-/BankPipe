<?php

namespace BankPipe\Admin;

use BankPipe\Items\Items;
use BankPipe\Items\Orders;
use BankPipe\Core;
use BankPipe\Logs\Handler as Logs;

class History
{
    use \BankPipe\Helper\MybbTrait;

    public function __construct()
    {
        $this->traitConstruct(['page', 'sub_tabs']);

        $ordersHandler = new Orders;
        $logs = new Logs;

        if ($this->mybb->input['delete'] and $this->mybb->request_method == 'post') {

            $toDelete = (array) Core::normalizeArray($this->mybb->input['delete']);
            $orders = $ordersHandler->get([
                'invoice' => $toDelete
            ]);

            foreach ($toDelete as $invoice) {

                // Demote user
                if ($orders[$invoice] and $orders[$invoice]['active']) {
                    $ordersHandler->utilities->demoteUser($orders[$invoice]['uid'], $invoice);
                }

                // Logging
                $logs->save([
                    'uid' => $this->mybb->user['uid'],
                    'type' => Orders::DELETE,
                    'invoice' => $invoice
                ]);

                // Destroy order
                $ordersHandler->destroy($invoice);

            }

            // Redirect
            flash_message($this->lang->bankpipe_success_deleted_selected_payments, 'success');
            admin_redirect(MAINURL . '&action=history');

        }

        $this->page->add_breadcrumb_item($this->lang->bankpipe_history, MAINURL);
        $this->page->output_header($this->lang->bankpipe_history);
        $this->page->output_nav_tabs($this->sub_tabs, 'history');

        // Sorting
        $form = new \Form(MAINURL . "&action=history&sort=1", "post", "history");
        $container = new \FormContainer();

        $container->output_row('', '', $form->generate_text_box('username', $this->mybb->input['username'], [
            'id' => 'username',
            'style' => '" autocomplete="off" placeholder="' . $this->lang->bankpipe_filter_username
        ]) . ' ' . $form->generate_text_box('payment_id', $this->mybb->input['payment_id'], [
            'id' => 'payment_id',
            'style' => '" autocomplete="off" placeholder="' . $this->lang->bankpipe_filter_payment_id
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
                $query = $this->db->simple_select('users', 'uid', "username LIKE '%" . $this->db->escape_string($this->mybb->input['username']) . "%'");
                while ($uid = $this->db->fetch_field($query, 'uid')) {
                    $uids[] = $uid;
                }

                $where[] = "(uid IN ('" . implode("','", $uids) . "') OR donor IN ('" . implode("','", $uids) . "'))";

            }

            if ($this->mybb->input['payment_id']) {
                $where[] = "(payment_id LIKE '%" . $this->db->escape_string($this->mybb->input['payment_id']) . "%' OR sale LIKE '%" . $this->db->escape_string($this->mybb->input['payment_id']) . "%')";
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
        $sortingOptions = ['username', 'payment_id', 'startingdate', 'endingdate'];
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
        $numResults = (int) $this->db->fetch_field($query, 'num_results');

        if ($numResults > $perpage) {
            echo draw_admin_pagination($this->mybb->input['page'], $perpage, $numResults, MAINURL . "&action=history" . $sortingString);
        }

        // Main view
        if ($numResults > 0) {
            $form = new \Form(MAINURL . '&action=history', 'post', 'history');
        }

        $table = new \Table;

        $table->construct_header($this->lang->bankpipe_history_header_user, ['width' => '15%']);
        $table->construct_header($this->lang->bankpipe_history_header_gateway, ['width' => '10%']);
        $table->construct_header($this->lang->bankpipe_history_header_merchant, ['width' => '15%']);
        $table->construct_header($this->lang->bankpipe_history_header_items);
        $table->construct_header($this->lang->bankpipe_history_header_revenue, ['width' => '10%']);
        $table->construct_header($this->lang->bankpipe_history_header_date, ['width' => '10%']);
        $table->construct_header($this->lang->bankpipe_history_header_expires, ['width' => '10%']);
        $table->construct_header($this->lang->bankpipe_history_header_options, ['width' => '1px']);
        $table->construct_header($this->lang->bankpipe_delete, ['width' => '1px', 'style' => 'text-align: center']);

        $orders = $ordersHandler->get($where, [
            'limit_start' => (int) $start,
            'limit' => (int) $perpage,
            'includeItemsInfo' => true
        ]);

        $revenue = [];

        // Cache users
        $users = [];
        $uids = Core::normalizeArray(array_merge(
            array_column($orders, 'uid'),
            array_column($orders, 'merchant'),
            array_column($orders, 'donor')
        ));
        $query = $this->db->simple_select('users', 'username, usergroup, displaygroup, avatar, uid', "uid IN ('" . implode("','", $uids) . "')");
        while ($user = $this->db->fetch_array($query)) {
            $users[$user['uid']] = $user;
        }

        foreach ($orders as $invoice => $order) {

            // Revenue
            $totalPaid = ($order['total'] - $order['fee']);
            $revenue[$order['currency_code']] += $totalPaid;

            // Buyer
            $user = ($order['donor']) ? $users[$order['donor']] : $users[$order['uid']];
            $username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
            $username = build_profile_link($username, $user['uid']);

            $avatar = format_avatar($user['avatar']);

            // Add donation info
            $donation = '';
            if ($order['donor']) {

                $gifted = $users[$order['uid']];
                $giftedUsername = format_name($gifted['username'], $gifted['usergroup'], $gifted['displaygroup']);
                $giftedUsername = build_profile_link($giftedUsername, $gifted['uid']);

                $donation = $this->lang->bankpipe_history_gifted_to . $giftedUsername;

            }

            $table->construct_cell('<img src="' . $avatar['image'] . '" style="height: 20px; width: 20px; vertical-align: middle" /> ' . $username . $donation);

            // Gateway
            $table->construct_cell($order['gateway']);

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
                $revenue[$order['currency_code']] -= $totalPaid;

            }
            else if ($order['type'] == Orders::PENDING) {

                $extra = $this->lang->bankpipe_history_pending;
                $class = 'pending';
                $revenue[$order['currency_code']] -= $totalPaid;

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

            if ($order['active']) {
                $popup->add_item($this->lang->bankpipe_history_revoke, MAINURL . '&action=purchases&sub=revoke&invoice=' . $invoice);
            }
            else {
                $popup->add_item($this->lang->bankpipe_history_reactivate, MAINURL . '&action=purchases&sub=reactivate&invoice=' . $invoice);
            }
            $table->construct_cell($popup->fetch());

            // Delete
            $table->construct_cell($form->generate_check_box("delete[]", $order['invoice']), ['style' => 'text-align: center']);

            $table->construct_row(['class' => $class]);

        }

        if (count($orders) == 0) {
            $table->construct_cell(
                $this->lang->bankpipe_history_no_payments,
                ['colspan' => 9, 'style' => 'text-align: center']
            );
            $table->construct_row();
        }

        if ($revenue) {

            $html = [];
            foreach ($revenue as $curr => $amount) {
                $html[] = $amount . ' ' . Core::friendlyCurrency($curr);
            }

            $table->construct_cell(
                $this->lang->sprintf(
                    $this->lang->bankpipe_history_revenue,
                    implode(', ', $html)
                ),
                ['colspan' => 9, 'style' => 'text-align: center']
            );
            $table->construct_row();

        }

        // Total revenue
        if ($numResults > $perpage) {

            $revenue = [];
            $query = $this->db->simple_select(
              Items::PAYMENTS_TABLE, 'SUM(price) AS total, SUM(fee) AS fees, currency',
              implode(' AND ', $where),
              ['group_by' => 'currency']
            );
            while ($rev = $this->db->fetch_array($query)) {
                $revenue[$rev['currency']] = $rev['total'] - $rev['fees'];
            }

            if ($revenue) {

                $html = [];
                foreach ($revenue as $curr => $amount) {
                    $html[] = $amount . ' ' . Core::friendlyCurrency($curr);
                }

                $table->construct_cell(
                    $this->lang->sprintf(
                        $this->lang->bankpipe_history_total_revenue,
                        implode(', ', $html)
                    ),
                    ['colspan' => 9, 'style' => 'text-align: center']
                );
                $table->construct_row();
            }

        }

        $table->output($this->lang->bankpipe_history);

        if ($numResults > 0) {

            $buttons = [
                $form->generate_submit_button($this->lang->bankpipe_history_delete)
            ];
            $form->output_submit_wrapper($buttons);

            $form->end();

        }

        // Adjust date format to the board's one
        $format = get_datepicker_format();

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
    format: '$format'
})
-->
</script>
HTML;
    }
}
