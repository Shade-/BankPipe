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
		if (version_compare($this->old_version, 'beta 2', "<")) {

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

		// beta 3
		if (version_compare($this->old_version, 'beta 3', "<")) {

			if (!$db->table_exists('bankpipe_downloadlogs')) {

				$collation = $db->build_create_table_collation();

				$db->write_query("
				CREATE TABLE " . TABLE_PREFIX . "bankpipe_downloadlogs (
					lid int(8) NOT NULL AUTO_INCREMENT PRIMARY KEY,
					pid int(10) NOT NULL DEFAULT '0',
					uid int(10) NOT NULL DEFAULT '0',
					aid int(10) NOT NULL DEFAULT '0',
					title text,
					date int(10) NOT NULL DEFAULT '0'
		        ) ENGINE=MyISAM{$collation};
				");

			}
			if (!$db->table_exists('bankpipe_discounts')) {

				$collation = $db->build_create_table_collation();

				$db->write_query("
				CREATE TABLE " . TABLE_PREFIX . "bankpipe_discounts (
					did int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
					bids text,
					uids text,
					gids text,
					code text,
					value int(10) NOT NULL DEFAULT '0',
					type tinyint(1) NOT NULL DEFAULT '0',
					date int(10) NOT NULL DEFAULT '0',
					expires int(10) UNSIGNED NOT NULL DEFAULT '0',
					stackable tinyint(1) NOT NULL DEFAULT '0'
		        ) ENGINE=MyISAM{$collation};
				");

			}

			if (!$db->field_exists('country', 'bankpipe_payments')) {
				$db->add_column('bankpipe_payments', 'country', 'varchar(8) NOT NULL DEFAULT \'\' AFTER `payer_id`');
			}

			if ($db->field_exists('bid', 'bankpipe_log')) {
				$db->rename_column('bankpipe_log', 'bid', 'bids', 'text');
			}

			$new_settings[] = [
				"name" => "bankpipe_cart_mode",
				"title" => $db->escape_string($lang->setting_bankpipe_cart_mode),
				"description" => $db->escape_string($lang->setting_bankpipe_cart_mode_desc),
				"optionscode" => "yesno",
				"value" => 1,
				"disporder" => 13,
				"gid" => $gid
			];

			$new_settings[] = [
				"name" => "bankpipe_required_fields",
				"title" => $db->escape_string($lang->setting_bankpipe_required_fields),
				"description" => $db->escape_string($lang->setting_bankpipe_required_fields_desc),
				"optionscode" => "text",
				"value" => '',
				"disporder" => 14,
				"gid" => $gid
			];

			$updateTemplates = 1;

		}

		// beta 4
		if (version_compare($this->old_version, 'beta 4', "<")) {

			if (!$db->field_exists('name', 'bankpipe_discounts')) {
				$db->add_column('bankpipe_discounts', 'name', 'varchar(128) DEFAULT NULL AFTER `gids`');
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

			$PL->templates('bankpipe', 'BankPipe', $templates);

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