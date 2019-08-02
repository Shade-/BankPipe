<?php

// Bankpipe Cleanup
// TO-DO: implement Orders class

function task_bankpipe($task)
{
    global $db, $lang, $mybb, $cache;

    $lang->load('bankpipe');

    // Clean up "CREATE"-type orders. TO-DO: use Orders class. CREATE = 1
    $db->delete_query('bankpipe_payments', 'type = 1');

    $now = TIME_NOW;
    $updateMailqueue = false;
    $deadline = ($mybb->settings['bankpipe_pending_payments_cleanup'])
        ? (int) $mybb->settings['bankpipe_pending_payments_cleanup']
        : 7;

    // Delete pending payments older than X days
    $limit = $now - (60*60*24*$deadline);
    $toDelete = $uids = $users = $pendingPaymentsToDelete = [];

    $query = $db->simple_select('bankpipe_payments', '*', 'type = 3 AND date < ' . $limit);
    while ($payment = $db->fetch_array($query)) {
        $pendingPaymentsToDelete[] = $payment;
        $uids[] = $payment['uid'];
    }

    if ($uids) {

        $query = $db->simple_select('users', 'uid, email, username, usergroup, displaygroup', 'uid IN (' . implode(',', $uids) . ')');
        while ($user = $db->fetch_array($query)) {
            $users[$user['uid']] = $user;
        }

    }

    foreach ($pendingPaymentsToDelete as $payment) {

        $toDelete[] = $payment['pid'];

        $user = $users[$payment['uid']];

        $username = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);
        $username = build_profile_link($username, $user['uid']);

        // Notify
        $message = $lang->sprintf(
            $lang->bankpipe_notification_pending_payment_cancelled,
            $username,
            $payment['invoice'],
            $deadline,
            $mybb->settings['bbname']
        );

        $emails[] = [
            "mailto" => $db->escape_string($user['email']),
            "mailfrom" => '',
            "subject" => $db->escape_string($lang->bankpipe_notification_pending_payment_cancelled_title),
            "message" => $db->escape_string($message),
            "headers" => ''
        ];

        $updateMailqueue = true;

    }

    if ($toDelete) {
        $db->delete_query('bankpipe_payments', 'pid IN (' . implode(',', array_unique($toDelete)) . ')');
    }

    // Get notifications info
    $notifications = $expiryDates = [];

    $query = $db->simple_select('bankpipe_notifications', '*', '', ['order_by' => 'daysbefore DESC']);
    while ($notification = $db->fetch_array($query)) {

        // Calculate the last dateline to include in the range – the others are too far away to be processed
        $dateline = ($now + ($notification['daysbefore']*60*60*24));

        $expiryDates[] = $dateline;

        $notifications[$dateline] = $notification;

    }

    function closest($search, $arr) {
        $closest = null;
        foreach ($arr as $item) {
            if ($closest === null || abs($search - $closest) > abs($item - $search)) {
                $closest = $item;
            }
        }
        return $closest;
    }

    $where = ($expiryDates and max($expiryDates)) ? ' AND expires < ' . max($expiryDates) : '';

    require_once MYBB_ROOT . "inc/datahandlers/pm.php";
    $pmhandler                 = new PMDataHandler();
    $pmhandler->admin_override = true;

    $subscriptions = $uids = [];

    // Process expiring subscriptions
    $query = $db->simple_select('bankpipe_payments', '*', 'active = 1 AND expires > 0' . $where, ['order_by' => 'expires ASC']);
    while ($subscription = $db->fetch_array($query)) {

        $subscriptions[] = $subscription;
        $uids[] = $subscription['uid'];

    }

    if ($subscriptions) {

        // Get associated items
        $items = $emails = $users = [];

        $query = $db->simple_select('bankpipe_items', 'name, primarygroup, price, bid', 'bid IN (' . implode(',', array_column($subscriptions, 'bid')) . ')');
        while($item = $db->fetch_array($query)) {
            $items[$item['bid']] = $item;
        }

        // Get users data
        $query = $db->simple_select('users', 'uid, username, usergroup, additionalgroups, email, displaygroup', 'uid IN (' . implode(',', $uids) . ')');
        while($user = $db->fetch_array($query)) {
            $users[$user['uid']] = $user;
        }

        // Process
        foreach ($subscriptions as $subscription) {

            // This subscription has expired
            if ($subscription['expires'] < $now) {

                // Revert usergroup
                $oldGroup = (int) $subscription['oldgid'];

                if ($oldGroup) {

                    if ($items[$subscription['bid']]['primarygroup']) {

                        $data = [
                            'usergroup' => $oldGroup
                        ];

                        // Change display group only if set differently from "use primary"
                        if ($users[$subscription['uid']]['displaygroup'] != 0) {
                            $data['displaygroup'] = 0;
                        }

                    }
                    else {

                        $additionalGroups = (array) explode(',', $users[$subscription['uid']]['additionalgroups']);

                        // Check if the old gid is already present and eventually add it
                        if (!in_array($oldGroup, $additionalGroups)) {
                            $additionalGroups[] = $oldGroup;
                        }

                        // Remove the new gid
                        if (($key = array_search($subscription['newgid'], $additionalGroups)) !== false) {
                            unset($additionalGroups[$key]);
                        }

                        $data = [
                            'additionalgroups' => implode(',', $additionalGroups)
                        ];

                    }

                    $db->update_query('users', $data, "uid = '" . (int) $subscription['uid'] . "'");

                }

                // Mark payment as expired
                $db->update_query('bankpipe_payments', ['active' => 0], "pid = '" . (int) $subscription['pid'] . "'");

            }

            // Send reminder
            $closest = closest($subscription['expires'], $expiryDates);
            $notification = $notifications[$closest];

            // Send only when expired if daysbefore = 0
            if ($notification['daysbefore'] == 0 and $subscription['expires'] > $now) {
                $keys = array_keys($notifications);
                $notification = $notifications[$keys[array_search($closest, $keys)-1]];
            }

            if ($notification) {

                // Already sent this notification
                if ($subscription['sentnotification'] == $notification['nid']) {
                    continue;
                }

                $daysleft = (int) (($subscription['expires'] - $now) / (60*60*24));

                if ($daysleft <= 0) {
                    $daysleft = 0;
                }

                $title = $notification['title'];
                $message = $notification['description'];

                $find = [
                    "{user}",
                    "{price}",
                    "{days}",
                    "{name}"
                ];

                $replace = [
                    $users[$subscription['uid']]['username'],
                    $items[$subscription['bid']]['price'],
                    $daysleft,
                    $items[$subscription['bid']]['name']
                ];

                $title = str_replace($find, $replace, $title);
                $message = str_replace($find, $replace, $message);

                if ($notification['method'] == 'pm') {

                    $fromid = ($mybb->settings['bankpipe_notification_uid']) ?
                        (int) $mybb->settings['bankpipe_notification_uid'] :
                        -1;

                    $pm = [
                        "subject" => $title,
                        "message" => $message,
                        "fromid" => $fromid,
                        "toid" => [
                            $subscription['uid']
                        ]
                    ];

                    $pm['bccid'] = ($mybb->settings['bankpipe_notification_cc']) ?
                        explode(',', $mybb->settings['bankpipe_notification_cc']) :
                        [];

                    $pmhandler->set_data($pm);

                    // Let the PM handler do all the hard work
                    if ($pmhandler->validate_pm()) {
                        $pmhandler->insert_pm();
                    }

                }
                else if ($notification['method'] == 'email' and $users[$subscription['uid']]['email']) {

                    $emails[] = [
                        "mailto" => $db->escape_string($users[$subscription['uid']]['email']),
                        "mailfrom" => '',
                        "subject" => $db->escape_string($title),
                        "message" => $db->escape_string($message),
                        "headers" => ''
                    ];

                    $updateMailqueue = true;

                }

                $db->update_query('bankpipe_payments', ['sentnotification' => $notification['nid']], 'pid = ' . $subscription['pid']);

            }

        }

    }

    if ($emails) {
        $db->insert_query_multiple("mailqueue", $emails);
    }

    if ($updateMailqueue) {
        $cache->update_mailqueue();
    }

    add_task_log($task, $lang->task_bankpipe_ran);
}
