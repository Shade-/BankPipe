<?php

namespace BankPipe\Admin;

class Downloadlogs
{
    use \BankPipe\Helper\MybbTrait;

    public function __construct()
    {
        $this->traitConstruct(['page', 'sub_tabs']);

        if ($this->mybb->input['delete'] and $this->mybb->request_method == 'post') {

            if ($this->mybb->input['delete']) {
                $this->db->delete_query('bankpipe_downloadlogs', "lid IN ('" . implode("','", (array) $this->mybb->input['delete']) . "')");
            }

            // Redirect
            flash_message($this->lang->bankpipe_success_deleted_selected_logs, 'success');
            admin_redirect(MAINURL . "&action=downloadlogs");

        }

        $this->page->add_breadcrumb_item($this->lang->bankpipe_downloadlogs, MAINURL);
        $this->page->output_header($this->lang->bankpipe_downloadlogs);
        $this->page->output_nav_tabs($this->sub_tabs, 'downloadlogs');

        // Sorting
        $form = new \Form(MAINURL . "&action=downloadlogs&sort=true", "post", "downloadlogs");
        $container = new \FormContainer();

        $container->output_row('', '', $form->generate_text_box('username', $this->mybb->input['username'], [
            'id' => 'username',
            'style' => '" autocomplete="off" placeholder="' . $this->lang->bankpipe_filter_username
        ]) . ' ' . $form->generate_text_box('item', $this->mybb->input['item'], [
            'id' => 'item',
            'style' => '" autocomplete="off" placeholder="' . $this->lang->bankpipe_filter_item
        ]) . ' ' . $form->generate_text_box('startingdate', $this->mybb->input['startingdate'], [
            'id' => 'startingdate',
            'style' => 'width: 150px" autocomplete="off" placeholder="' . $this->lang->bankpipe_filter_startingdate
        ]) . ' ' . $form->generate_text_box('endingdate', $this->mybb->input['endingdate'], [
            'id' => 'endingdate',
            'style' => 'width: 150px" autocomplete="off" placeholder="' . $this->lang->bankpipe_filter_endingdate
        ]) . ' ' . $form->generate_submit_button($this->lang->bankpipe_filter), 'sort');

        $container->end();
        $form->end();

        $where = [];
        if ($this->mybb->input['sort']) {

            if ($this->mybb->input['username']) {
                $where[] = "u.username LIKE '%" . $this->db->escape_string($this->mybb->input['username']) . "%'";
            }

            if ($this->mybb->input['item']) {
                $where[] = "l.title LIKE '%" . $this->db->escape_string($this->mybb->input['item']) . "%'";
            }

            if ($this->mybb->input['startingdate']) {
                $where[] = "l.date >= " . get_formatted_date($this->mybb->input['startingdate']);
            }

            if ($this->mybb->input['endingdate']) {
                $where[] = "l.date <= " . get_formatted_date($this->mybb->input['endingdate']);
            }

        }

        $whereStatement = ($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $perpage = 20;
        $sortingOptions = ['username', 'startingdate', 'endingdate', 'item'];
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

        $query = $this->db->query('
            SELECT COUNT(l.lid) AS num_results
            FROM ' . TABLE_PREFIX . 'bankpipe_downloadlogs l
            LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid = l.uid)
            ' . $whereStatement);
        $num_results = $this->db->fetch_field($query, 'num_results');

        if ($num_results > 0) {
            $form = new \Form(MAINURL . "&action=downloadlogs", "post", "downloadlogs");
        }

        if ($num_results > $perpage) {
            echo draw_admin_pagination($this->mybb->input['page'], $perpage, $num_results, MAINURL . "&action=downloadlogs" . $sortingString);
        }

        $table = new \Table;

        $table->construct_header($this->lang->bankpipe_downloadlogs_header_user, ['width' => '15%']);
        $table->construct_header($this->lang->bankpipe_downloadlogs_header_item);
        $table->construct_header($this->lang->bankpipe_downloadlogs_header_handling_method);
        $table->construct_header($this->lang->bankpipe_downloadlogs_header_date, ['width' => '10%']);
        $table->construct_header($this->lang->bankpipe_delete, ['width' => '1px', 'style' => 'text-align: center']);

        $query = $this->db->query('
            SELECT l.*, i.name, u.username, u.usergroup, u.displaygroup, u.avatar, p.invoice
            FROM ' . TABLE_PREFIX . 'bankpipe_downloadlogs l
            LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid = l.uid)
            LEFT JOIN ' . TABLE_PREFIX . 'bankpipe_payments p ON (p.pid = l.pid)
            LEFT JOIN ' . TABLE_PREFIX . 'bankpipe_items i ON (i.bid = p.bid)
            ' . $whereStatement . '
            ORDER BY l.date DESC
            LIMIT ' . (int) $start . ', ' . (int) $perpage . '
        ');
        if ($this->db->num_rows($query) > 0) {

            while ($log = $this->db->fetch_array($query)) {

                // User
                $username = format_name($log['username'], $log['usergroup'], $log['displaygroup']);
                $username = build_profile_link($username, $log['uid']);

                $avatar = format_avatar($log['avatar']);

                $table->construct_cell('<img src="' . $avatar['image'] . '" style="height: 20px; width: 20px; vertical-align: middle" /> ' . $username);

                // Title
                $table->construct_cell(
                    $this->lang->sprintf(
                        $this->lang->bankpipe_downloadlogs_attachment_name,
                        $this->mybb->settings['bburl'] . '/attachment.php?aid=' . $log['aid'],
                        htmlspecialchars_uni($log['title'])
                    )
                );

                // Downloaded through a single-item purchase
                if ($log['pid'] > 0) {

                    if ($log['name']) {

                        $item = $this->lang->sprintf(
                            $this->lang->bankpipe_downloadlogs_single_item_purchase,
                            MAINURL . "&action=purchases&sub=edit&invoice=" . $log['invoice']
                        );

                    }
                    else {
                        $item = $this->lang->bankpipe_downloadlogs_cannot_fetch_item;
                    }

                }
                // Downloaded through usergroup permissions (eg. subscription)
                else if ($log['pid'] == -1) {
                    $item = $this->lang->bankpipe_downloadlogs_usergroup_access;
                }
                // Access without a subscription. This should not be possible, but leaving this here to display
                // just in case an user bypasses our internal checks
                else if (!$log['pid']) {
                    $item = $this->lang->bankpipe_downloadlogs_access_not_granted;
                }

                $table->construct_cell($item);

                // Date
                $table->construct_cell(my_date('relative', $log['date']));

                // Delete
                $table->construct_cell(
                    $form->generate_check_box("delete[]", $log['lid']),
                    ['style' => 'text-align: center']
                );
                $table->construct_row();

            }
        }
        else {
            $table->construct_cell($this->lang->bankpipe_downloadlogs_no_logs, ['colspan' => 5, 'style' => 'text-align: center']);
            $table->construct_row();
        }

        $table->output($this->lang->bankpipe_downloadlogs);

        if ($num_results > 0) {

            $buttons = [
                $form->generate_submit_button($this->lang->bankpipe_logs_delete)
            ];
            $form->output_submit_wrapper($buttons);

            $form->end();

        }

        $format = get_datepicker_format();

        echo <<<HTML
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
