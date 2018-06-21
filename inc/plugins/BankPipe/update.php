<?php

/**
 * Upgrading routines
 */

class BankPipe_Update
{

	private $version;

	private $old_version;

	private $plugins;

	private $info;

	public function __construct()
	{

		global $mybb, $db, $cache, $lang;

		if (!$lang->bankpipe) {
			$lang->load("bankpipe");
		}

		$this->loadVersion();

		$check = $this->checkUpdate();

		if ($mybb->input['update'] == 'bankpipe' and $check) {
			$this->update();
		}

	}

	private function loadVersion()
	{
		global $cache;

		$this->info        = bankpipe_info();
		$this->plugins     = $cache->read('shade_plugins');
		$this->old_version = $this->plugins[$this->info['name']]['version'];
		$this->version     = $this->info['version'];

	}

	private function checkUpdate()
	{
		global $lang, $mybb;

		if (version_compare($this->old_version, $this->version, "<")) {

			if ($mybb->input['update']) {
				return true;
			} else {
				flash_message($lang->bankpipe_error_needtoupdate, "error");
			}

		}

		return false;

	}

	private function update()
	{
		global $db, $mybb, $cache, $lang, $PL;

		if (!$lang->bankpipe) {
			$lang->load('bankpipe');
		}

		$new_settings = $drop_settings = [];
		$updateTemplates = 0;

		// Get the gid
		$query = $db->simple_select("settinggroups", "gid", "name='bankpipe'");
		$gid   = (int) $db->fetch_field($query, "gid");

		// beta 2
		if (version_compare($this->old_version, '1.2', "<")) {

			$new_settings[] = [
				"name" => "bankpipe_admin_notification",
				"title" => $db->escape_string($lang->setting_bankpipe_admin_notification),
				"description" => $db->escape_string($lang->setting_bankpipe_admin_notification_desc),
				"optionscode" => "text",
				"value" => '',
				"disporder" => 11,
				"gid" => $gid
			];

			$new_settings[] = [
				"name" => "bankpipe_admin_notification_method",
				"title" => $db->escape_string($lang->setting_bankpipe_admin_notification_method),
				"description" => $db->escape_string($lang->setting_bankpipe_admin_notification_method_desc),
				"optionscode" => "select
pm=Private message
email=Email",
				"value" => 'pm',
				"disporder" => 12,
				"gid" => $gid
			];

			if (!$db->field_exists('price', 'bankpipe_payments')) {
				$db->add_column('bankpipe_payments', 'price', 'decimal(6,2) NOT NULL AFTER `email`');
			}

			// Update the price table to hold the current prices
			$query = $db->simple_select('bankpipe_items', 'bid, price');
			while ($item = $db->fetch_array($query)) {
				$db->update_query('bankpipe_payments', ['price' => $item['price']], 'bid = ' . (int) $item['bid']);
			}

		}

		if ($new_settings) {
			$db->insert_query_multiple('settings', $new_settings);
		}

		if ($drop_settings) {
			$db->delete_query('settings', "name IN ('bankpipe_". implode("','bankpipe_", $drop_settings) ."')");
		}

		rebuild_settings();

		if ($updateTemplates) {

			$PL or require_once PLUGINLIBRARY;

			// Update templates	   
			$dir       = new DirectoryIterator(dirname(__FILE__) . '/templates');
			$templates = [];
			foreach ($dir as $file) {
				if (!$file->isDot() and !$file->isDir() and pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'html') {
					$templates[$file->getBasename('.html')] = file_get_contents($file->getPathName());
				}
			}

			$PL->templates('bankpipe', 'bankpipe', $templates);

		}

		// Update the current version number and redirect
		$this->plugins[$this->info['name']]['version'] = $this->version;

		$cache->update('shade_plugins', $this->plugins);

		flash_message($lang->sprintf($lang->bankpipe_success_updated, $this->old_version, $this->version), "success");
		admin_redirect('index.php');

	}

}

// Direct init on call
$BankPipeUpdate = new BankPipe_Update();