<?php

if (!defined("IN_MYBB")) {
	header("HTTP/1.0 404 Not Found");
	exit;
}

define('MODULE', "bankpipe");
define('MAINURL', "index.php?module=config-bankpipe");

include BANKPIPE;

$lang->load(MODULE);

$sub_tabs['general']  = [
	'title' => $lang->bankpipe_overview,
	'link' => MAINURL,
	'description' => $lang->bankpipe_overview_desc
];
$sub_tabs['logs'] = [
	'title' => $lang->bankpipe_logs,
	'link' => MAINURL . '&action=logs',
	'description' => $lang->bankpipe_logs_desc
];
$sub_tabs['history'] = [
	'title' => $lang->bankpipe_history,
	'link' => MAINURL . '&action=history',
	'description' => $lang->bankpipe_history_desc
];
$sub_tabs['downloadlogs'] = [
	'title' => $lang->bankpipe_downloadlogs,
	'link' => MAINURL . '&action=downloadlogs',
	'description' => $lang->bankpipe_downloadlogs_desc
];
$sub_tabs['notifications'] = [
	'title' => $lang->bankpipe_notifications,
	'link' => MAINURL . '&action=notifications',
	'description' => $lang->bankpipe_notifications_desc
];
$sub_tabs['subscribeusers'] = [
	'title' => $lang->bankpipe_manual_add,
	'link' => MAINURL . '&action=subscribeusers',
	'description' => $lang->bankpipe_manual_add_desc
];
$sub_tabs['discounts'] = [
	'title' => $lang->bankpipe_discounts,
	'link' => MAINURL . '&action=discounts',
	'description' => $lang->bankpipe_discounts_desc
];

if ($mybb->input['action'] == 'subscriptions') {

	$sub_tabs['subscriptions'] = [
		'title' => $lang->bankpipe_subscriptions,
		'link' => MAINURL . '&action=subscriptions',
		'description' => $lang->bankpipe_subscriptions_desc
	];

}

if ($mybb->input['action'] == 'purchases' and in_array($mybb->input['sub'], ['edit', 'refund'])) {

	$sub_tabs['purchases'] = [
		'title' => $lang->bankpipe_manage_purchase,
		'link' => MAINURL,
		'description' => $lang->bankpipe_manage_purchase_desc
	];

}

function dd($data)
{
    echo "<pre>";
    print_r($data);
    exit;
}

$className = ($mybb->input['action'])
	? 'BankPipe\Admin\\' . ucfirst($mybb->input['action'])
	: 'BankPipe\Admin\Main';

try {
	new $className;
}
catch (\Exception $e) {
	new \BankPipe\Admin\Main($e->getMessage());
}
catch (\Error $e) {
    echo "<pre>";
    print_r($e);
    exit;
	//admin_redirect(MAINURL);
}

$page->output_footer();

function get_formatted_date($string)
{
	return (int) strtotime(str_replace('/', '-', (string) $string));
}

function format_date($date)
{	
	return ($date) ? date('d/m/Y', $date) : $date;
}

function array_column_recursive(array $haystack, $needle)
{
    $found = [];
    
    array_walk_recursive($haystack, function($value, $key) use (&$found, $needle) {
        if ($key == $needle)
            $found[] = $value;
    });
    
    return $found;
}