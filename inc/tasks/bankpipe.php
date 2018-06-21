<?php

// Bankpipe Cleanup

function task_bankpipe($task)
{
	global $db, $lang, $mybb, $cache;

	$lang->load('bankpipe');

	// Get notifications info
	$notifications = $expiryDates = [];
	$now = TIME_NOW;

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

	$where = (max($expiryDates)) ? ' AND expires < ' . max($expiryDates) : '';

	require_once MYBB_ROOT . "inc/datahandlers/pm.php";
	$pmhandler                 = new PMDataHandler();
	$pmhandler->admin_override = true;

	$subscriptions = $uids = [];

	// Process expiring subscriptions
	$query = $db->simple_select('bankpipe_payments', '*', 'active = 1 AND expires > 0' . $where, ['order_by' => 'expires ASC']);
	while ($subscription = $db->fetch_array($query)) {

		$subscriptions[$subscription['bid']] = $subscription;
		$uids[] = $subscription['uid'];

	}

	if ($subscriptions) {

		// Get associated items
		$items = $emails = $users = [];

		$query = $db->simple_select('bankpipe_items', 'name, primarygroup, price, bid', 'bid IN (' . implode(',', array_keys($subscriptions)) . ')');
		while($item = $db->fetch_array($query)) {
			$items[$item['bid']] = $item;
		}

		// Get users usernames
		$query = $db->simple_select('users', 'uid, username, usergroup, additionalgroups, email', 'uid IN (' . implode(',', $uids) . ')');
		while($user = $db->fetch_array($query)) {
			$users[$user['uid']] = $user;
		}

		$update_mailqueue = false;

		// Process
		foreach ($subscriptions as $subscription) {

			// This subscription has expired
			if ($subscription['expires'] < $now) {

				// Revert usergroup
				$oldGroup = (int) $subscription['oldgid'];

				if ($oldGroup) {

					if ($items[$subscription['bid']]['primarygroup']) {
						$data = [
							'usergroup' => $oldGroup,
							'displaygroup' => $oldGroup
						];
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

					// Make sure admins haven't done something bad
					$fromid = ($mybb->settings['bankpipe_notification_uid']) ? (int) $mybb->settings['bankpipe_notification_uid'] : -1;

					$pm = [
						"subject" => $title,
						"message" => $message,
						"fromid" => $fromid,
						"toid" => [
							$subscription['uid']
						]
					];

					$pmhandler->set_data($pm);

					// Now let the PM handler do all the hard work
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

					$update_mailqueue = true;

				}

				$db->update_query('bankpipe_payments', ['sentnotification' => $notification['nid']], 'pid = ' . $subscription['pid']);

			}

		}

		if ($emails) {
			$db->insert_query_multiple("mailqueue", $emails);
		}

		if ($update_mailqueue) {
			$cache->update_mailqueue();
		}

	}

	add_task_log($task, $lang->task_bankpipe_ran);
}