<?php

namespace BankPipe\Helper;

use BankPipe\Core;

class Permissions
{
	use MybbTrait;
	
	public function __construct()
	{
		$this->traitConstruct('forumpermissions');
	}
	
	public function simpleCheck(array $type, int $fid = 0)
	{
		// Disable for guests
		if ($this->mybb->user['uid'] == 0) {
			return false;
		}
	
		$type = $type ?? ['view', 'manage', 'forums'];
	
		$permissions = [
			'view' => Core::normalizeArray(explode(',', $this->mybb->settings['bankpipe_usergroups_view'])),
			'manage' => Core::normalizeArray(explode(',', $this->mybb->settings['bankpipe_usergroups_manage'])),
			'forums' => Core::normalizeArray(explode(',', $this->mybb->settings['bankpipe_forums']))
		];
		
        $permissions = $this->plugins->run_hooks('bankpipe_permissions_simple', $permissions);
	
		// Check if available in this forum
		if ($fid
			and !empty($permissions['forums'])
			and in_array('forums', $type)
			and !in_array($fid, $permissions['forums'])) {
			return false;
		}
	
		if ($this->forumpermissions and $this->forumpermissions['candownloadpaidattachments']) {
			return true;
		}
	
		unset($type['forums']);
	
		// Not allowed if the main settings is disabled
		if (in_array('manage', $type)
			and !$this->mybb->settings['bankpipe_third_party']
			and !$this->mybb->usergroup['cancp']) {
			return false;
		}
	
		// Check if available for this user's usergroup
		$usergroups = Core::normalizeArray(
			array_merge(
				[$this->mybb->user['usergroup']],
				(array) explode(',', $this->mybb->user['additionalgroups'])
			)
		);
	
		foreach ($type as $permission) {
	
			if (!empty($permissions[$permission]) and !array_intersect($usergroups, $permissions[$permission])) {
				return false;
			}
	
		}
	
		return true;
	}
	
	public function discountCheck(array $code, array $item = [])
	{
		$permissions = [
			'codes' => $code['bids'],
			'users' => $code['uids'],
			'usergroups' => $code['gids']
		];
		
        $permissions = $this->plugins->run_hooks('bankpipe_permissions_discount', $permissions);
	
		foreach ($permissions as $permission => $value) {
	
			$value = Core::normalizeArray(explode(',', $value));
	
			if ($value) {
	
				if ($permission == 'codes' and $item and !in_array($item['bid'], $value)) {
					return false;
				}
	
				if ($permission == 'users' and !in_array($this->mybb->user['uid'], $value)) {
					return false;
				}
	
				if ($permission == 'usergroups') {
	
					// Count additional groups in
					$usergroups = [$this->mybb->user['usergroup']];
					$usergroups += explode(',', $this->mybb->user['additionalgroups']);
	
					if (count(array_intersect($value, $usergroups)) == 0) {
						return false;
					}
	
				}
	
			}
	
		}
	
		return true;
	}
}