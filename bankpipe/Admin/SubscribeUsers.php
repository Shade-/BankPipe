<?php

namespace BankPipe\Admin;

use BankPipe\Items\Items;
use BankPipe\Items\Orders;
use BankPipe\Logs\Handler as Logs;

class SubscribeUsers
{
    use \BankPipe\Helper\MybbTrait;

    public function __construct()
    {
        $this->traitConstruct(['page', 'sub_tabs', 'cache']);

        $subscriptions = $users = $uids = [];

        $query = $this->db->simple_select(
            'bankpipe_items',
            'bid, name, gid, primarygroup, expirygid',
            'type = ' . Items::SUBSCRIPTION,
            ['order_by' => 'price ASC']
        );
        while ($subscription = $this->db->fetch_array($query)) {
            $subscriptions[$subscription['bid']] = $subscription;
        }

        if (!array_filter($subscriptions)) {
            flash_message($this->lang->bankpipe_error_missing_subscriptions, 'error');
            admin_redirect(MAINURL);
        }

        if ($this->mybb->request_method == 'post') {

            $startDate = get_formatted_date($this->mybb->input['startdate']);
            $endDate = ($this->mybb->input['enddate']) ?
                get_formatted_date($this->mybb->input['enddate']) + 60*60*24 : // get_formatted_date returns dates adjusted to midnight, add a day to the ending one
                0;

            // Get users
            $where = explode(',', (string) $this->mybb->input['users']);

            if ($where) {

                $query = $this->db->simple_select(
                    'users',
                    'uid, usergroup, additionalgroups',
                    "username IN ('" . implode("','", $where) . "')"
                );
                while ($user = $this->db->fetch_array($query)) {
                    $uids[] = (int) $user['uid'];
                    $users[$user['uid']] = $user;
                }

            }

            // Get users in selected usergroups
            $usergroups = (array) $this->mybb->input['usergroups'];

            if ($usergroups) {

                $query = $this->db->simple_select(
                    'users',
                    'uid, usergroup, additionalgroups',
                    "usergroup IN ('" . implode("','", $usergroups) . "')"
                );
                while ($user = $this->db->fetch_array($query)) {
                    $uids[] = (int) $user['uid'];
                    $users[$user['uid']] = $user;
                }

            }

            // Normalize uids array
            if ($uids) {
                $uids = array_filter($uids);
            }

            if (empty($uids)) {
                admin_redirect(MAINURL . '&action=subscribeusers');
            }

            if ($startDate > TIME_NOW or ($endDate and $endDate < TIME_NOW)) {
                flash_message($this->lang->bankpipe_error_incorrect_dates, 'error');
                admin_redirect(MAINURL . '&action=subscribeusers');
            }

            // For multiple users, sale ID is disabled
            if (count($uids) > 1) {
                $this->mybb->input['sale'] = '';
            }

            $bid = (int) $this->mybb->input['subscription'];

            // Check if not existing already
            $activeSubs = $data = [];

            // TO-DO: this part is built with shit logic. The following should be adapted using the new Orders and Items classes
            $query = $this->db->simple_select(
                Items::PAYMENTS_TABLE,
                'uid',
                'uid IN (' . implode(',', $uids) . ') AND active = 1'
            );
            while ($activeSub = $this->db->fetch_field($query, 'uid')) {
                $activeSubs[] = $activeSub;
            }

            $log = new Logs;

            foreach ($uids as $uid) {

                if (!$uid or in_array($uid, $activeSubs)) {
                    continue;
                }

                // Change usergroup
                if ($subscriptions[$bid]['primarygroup']) {
                    $update = [
                        'usergroup' => (int) $subscriptions[$bid]['gid'],
                        'displaygroup' => (int) $subscriptions[$bid]['gid']
                    ];
                }
                else {

                    $additionalGroups = (array) explode(',', $users[$uid]['additionalgroups']);

                    // Check if the new gid is already present and eventually add it
                    if (!in_array($subscriptions[$bid]['gid'], $additionalGroups)) {
                        $additionalGroups[] = $subscriptions[$bid]['gid'];
                    }

                    $update = [
                        'additionalgroups' => implode(',', $additionalGroups)
                    ];

                }

                $this->db->update_query('users', $update, "uid = '" . (int) $uid . "'");

                $arr = [
                    'bid' => $bid,
                    'uid' => (int) $uid,
                    'currency' => $this->db->escape_string($this->mybb->settings['bankpipe_currency']),
                    'sale' => $this->db->escape_string($this->mybb->input['sale']),
                    'date' => $startDate,
                    'expires' => $endDate,
                    'newgid' => (int) $subscriptions[$bid]['gid'],
                    'invoice' => uniqid(),
                    'type' => Orders::MANUAL
                ];

                $arr['oldgid'] = ($subscriptions[$bid]['expirygid']) ?
                    (int) $subscriptions[$bid]['expirygid'] :
                    (int) $users[$uid]['usergroup'];

                $data[] = $arr;

            }

            $data = array_filter($data);

            if ($data) {

                $this->db->insert_query_multiple(Items::PAYMENTS_TABLE, $data);

                // Log this
                $bids = array_column($data, 'bid');
                if ($bids) {

                    $uids = array_column($data, 'uid');

                    $message = [];

                    if ($uids) {

                        $query = $this->db->simple_select('users', 'uid, username', 'uid IN (' . implode(',', $uids) . ')');
                        while ($user = $this->db->fetch_array($query)) {
                            $message[] = build_profile_link($user['username'], $user['uid']);
                        }

                    }

                    $log->save([
                        'message' => implode(', ', $message),
                        'uid' => $this->mybb->user['uid'],
                        'type' => Orders::MANUAL,
                        'bids' => $bids
                    ]);

                }

            }

            // Redirect
            flash_message($this->lang->bankpipe_success_users_added, 'success');
            admin_redirect(MAINURL);

        }

        // Generate sub list
        $displaySubscriptions = [];
        foreach ($subscriptions as $sub) {
            $displaySubscriptions[$sub['bid']] = $sub['name'];
        }

        $this->page->add_breadcrumb_item($this->lang->bankpipe_manual_add, MAINURL . '&action=subscribeusers');
        $this->page->output_header($this->lang->bankpipe_manual_add);
        $this->page->output_nav_tabs($this->sub_tabs, 'subscribeusers');

        // Determine the post request attributes
        $form = new \Form(MAINURL . "&action=subscribeusers", "post", "subscribeusers");

        $container = new \FormContainer($this->lang->bankpipe_manual_add);

        $container->output_row(
            $this->lang->bankpipe_manual_add_user,
            $this->lang->bankpipe_manual_add_user_desc,
            $form->generate_text_box('users', $this->mybb->input['users'], [
                'id' => 'users'
            ]),
            'users'
        );

        $usergroups = [];

        $groups_cache = $this->cache->read('usergroups');
        foreach ($groups_cache as $group) {
            $usergroups[$group['gid']] = $group['title'];
        }

        $container->output_row(
            $this->lang->bankpipe_manual_add_usergroup,
            $this->lang->bankpipe_manual_add_usergroup_desc,
            $form->generate_select_box('usergroups[]', $usergroups, (array) $this->mybb->input['usergroups'], [
                'id' => 'usergroup',
                'multiple' => true
            ])
        );

        $container->output_row(
            $this->lang->bankpipe_manual_add_subscription,
            $this->lang->bankpipe_manual_add_subscription_desc,
            $form->generate_select_box('subscription', $displaySubscriptions, [$this->mybb->input['subscription']], [
                'id' => 'subscription'
            ])
        );

        $container->output_row(
            $this->lang->bankpipe_manual_add_start_date,
            $this->lang->bankpipe_manual_add_start_date_desc,
            $form->generate_text_box('startdate', $this->mybb->input['startdate'], [
                'id' => 'startdate'
            ]),
            'startdate'
        );

        $container->output_row(
            $this->lang->bankpipe_manual_add_end_date,
            $this->lang->bankpipe_manual_add_end_date_desc,
            $form->generate_text_box('enddate', $this->mybb->input['enddate'], [
                'id' => 'enddate'
            ]),
            'enddate'
        );

        $container->output_row(
            $this->lang->bankpipe_manual_add_sale_id,
            $this->lang->bankpipe_manual_add_sale_id_desc,
            $form->generate_text_box('sale', $this->mybb->input['sale'], [
                'id' => 'sale'
            ]),
            'sale'
        );

        $container->end();

        $buttons = [
            $form->generate_submit_button($this->lang->bankpipe_save)
        ];
        $form->output_submit_wrapper($buttons);

        $form->end();

        // JS routines
        echo '
<link rel="stylesheet" href="../jscripts/select2/select2.css" type="text/css" />
<script type="text/javascript" src="../jscripts/select2/select2.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.css" type="text/css" />
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/datepicker/0.6.5/datepicker.min.js"></script>
<script type="text/javascript">
<!--
// Date picking
var start = $("#startdate").datepicker({
    autoPick: true,
    endDate: new Date(),
    autoHide: true,
    format: \'dd/mm/yyyy\'
});

var end = $("#enddate").datepicker({
    autoPick: true,
    autoHide: true,
    format: \'dd/mm/yyyy\'
});

start.on("pick.datepicker", (e) => {
    return end.datepicker("show");
});

// Autocomplete
$("#users").select2({
    placeholder: "'.$this->lang->search_for_a_user.'",
    minimumInputLength: 2,
    multiple: true,
    ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
        url: "../xmlhttp.php?action=get_users",
        dataType: \'json\',
        data: function (term, page) {
            return {
                query: term // search term
            };
        },
        results: function (data, page) { // parse the results into the format expected by Select2.
            // since we are using custom formatting functions we do not need to alter remote JSON data
            return {results: data};
        }
    },
    initSelection: function(element, callback) {
        var query = $(element).val();
        if (query !== "") {
            $.ajax("../xmlhttp.php?action=get_users&getone=1", {
                data: {
                    query: query
                },
                dataType: "json"
            }).done(function(data) { callback(data); });
        }
    }
});
// -->
</script>';
    }
}
