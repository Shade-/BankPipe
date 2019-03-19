<?php

namespace BankPipe\Admin;

use BankPipe\Items\Orders;
use BankPipe\Items\Items;
use BankPipe\Core;

class Logs
{
    use \BankPipe\Helper\MybbTrait;

    public function __construct()
    {
        $this->traitConstruct(['page', 'sub_tabs']);

        if ($this->mybb->input['delete'] and $this->mybb->request_method == 'post') {

            if ($this->mybb->input['delete']) {
                $this->db->delete_query('bankpipe_log', "invoice IN ('" . implode("','", (array) $this->mybb->input['delete']) . "')");
            }

            // Redirect
            flash_message($this->lang->bankpipe_success_deleted_selected_logs, 'success');
            admin_redirect(MAINURL . '&action=logs');

        }

        $query = $this->db->simple_select('bankpipe_log', 'COUNT(DISTINCT(invoice)) as num_results');
        $num_results = $this->db->fetch_field($query, 'num_results');

        $this->page->add_breadcrumb_item($this->lang->bankpipe_logs, MAINURL);
        $this->page->output_header($this->lang->bankpipe_logs);
        $this->page->output_nav_tabs($this->sub_tabs, 'logs');

        if ($num_results > 0) {
            $form = new \Form(MAINURL . '&action=logs', 'post', 'logs');
        }

        $perpage = 20;

        $this->mybb->input['page'] = (int) $this->mybb->input['page'] ?? 1;

        $start = 0;
        if ($this->mybb->input['page']) {
            $start = ($this->mybb->input['page'] - 1) * $perpage;
        }
        else {
            $this->mybb->input['page'] = 1;
        }

        if ($num_results > $perpage) {
            echo draw_admin_pagination($this->mybb->input['page'], $perpage, $num_results, MAINURL . "&action=logs");
        }

        $table = new \Table;

        $table->construct_header($this->lang->bankpipe_logs_header_user, ['width' => '15%']);
        $table->construct_header($this->lang->bankpipe_logs_header_action);
        $table->construct_header($this->lang->bankpipe_logs_header_items, ['width' => '40%']);
        $table->construct_header($this->lang->bankpipe_delete, ['width' => '1px', 'style' => 'text-align: center']);

        $logs = $search = $toDelete = [];

        $query = $this->db->query('
            SELECT l.*, u.username, u.usergroup, u.displaygroup, u.avatar, GROUP_CONCAT(l.type, \'|\', l.date ORDER BY l.date DESC) types
            FROM ' . TABLE_PREFIX . 'bankpipe_log l
            LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid = l.uid)
            GROUP BY l.invoice
            ORDER BY l.date DESC
            LIMIT ' . (int) $start . ', ' . (int) $perpage . '
        ');
        while ($log = $this->db->fetch_array($query)) {

            if (!$log['invoice']) {
                continue;
            }

            $log['bids'] = explode('|', $log['bids']);
            $search = array_merge($search, $log['bids']);

            $logs[] = $log;

        }

        $search = Core::normalizeArray($search);

        // Cache items
        if ($search) {
            $items = (new Items)->getItems($search);
        }

        // Loop through logs and display them
        foreach ($logs as $log) {

            // User
            $username = format_name($log['username'], $log['usergroup'], $log['displaygroup']);
            $username = build_profile_link($username, $log['uid']);

            $avatar = format_avatar($log['avatar']);

            $table->construct_cell('<img src="' . $avatar['image'] . '" style="height: 20px; width: 20px; vertical-align: middle" /> ' . $username);

            // Action
            $success = false;
            $action = '';
            $subtable = new \Table;

            $actions = explode(',', $log['types']);
            foreach ($actions as $key => $type) {

                $type = explode('|', $type); // 0 = action, 1 = date

                switch ($type[0]) {

                    case Orders::ERROR:
                    default:
                        $action = $this->lang->bankpipe_logs_error;
                        break;

                    case Orders::CREATE:
                        $action = $this->lang->bankpipe_logs_created;
                        break;

                    case Orders::REFUND:
                        $action = $this->lang->bankpipe_logs_refunded;
                        break;

                    case Orders::PENDING:
                        $action = $this->lang->bankpipe_logs_pending;
                        break;

                    case Orders::MANUAL:
                        $action = $this->lang->bankpipe_logs_manual_subscription;
                        break;

                    case Orders::CANCEL:
                        $action = $this->lang->bankpipe_logs_cancel;
                        break;

                    case Orders::SUCCESS:
                        $action = $this->lang->bankpipe_logs_success;
                        $success = true;
                        break;

                }

                if ($log['message']) {
                    $action .= '<br><span class="smalltext">' . $log['message'] . '</span>';
                }

                $subtable->construct_cell($action);
                $subtable->construct_cell(my_date('relative', $type[1]), [
                    'width' => '40%'
                ]);

                $subtable->construct_row();

            }

            $table->construct_cell($subtable->output('', 0, '', true));

            // Items
            $names = [];

            foreach ($log['bids'] as $bid) {

                $asset = $items[$bid];

                $names[] = $asset['name'];

            }

            $names = Core::normalizeArray($names);
            $names = ($names) ? $names : [$this->lang->bankpipe_logs_items_deleted];
            $names = implode(', ', Core::normalizeArray($names));

            if ($success) {
                $item = '<a href="' . MAINURL . '&action=purchases&sub=edit&invoice=' . $log['invoice'] . '">' . $names . '</a>';
            }
            else {
                $item = $names;
            }

            $table->construct_cell($item);

            // Delete
            $table->construct_cell($form->generate_check_box("delete[]", $log['invoice']), ['style' => 'text-align: center']);
            $table->construct_row();

        }

        if ($this->db->num_rows($query) == 0) {
            $table->construct_cell($this->lang->bankpipe_logs_no_logs, ['colspan' => 5, 'style' => 'text-align: center']);
            $table->construct_row();
        }

        $table->output($this->lang->bankpipe_logs);

        if ($num_results > 0) {

            $buttons = [
                $form->generate_submit_button($this->lang->bankpipe_logs_delete)
            ];
            $form->output_submit_wrapper($buttons);

            $form->end();

        }
    }
}
