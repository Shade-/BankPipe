<?php

namespace BankPipe\Usercp;

use BankPipe\Helper\Permissions;

class Usercp
{
	use \BankPipe\Helper\MybbTrait;

	public function __construct()
	{
		$this->traitConstruct();

		$allowedPages = ['subscriptions', 'cart', 'purchases', 'manage', 'discounts'];
		
		$args = [&$this, &$allowedPages];
		$this->plugins->run_hooks('bankpipe_ucp_main', $args);
		
		$permissions = new Permissions;

		if (in_array($this->mybb->input['action'], $allowedPages) and $permissions->simpleCheck(['view'])) {

			$className = 'BankPipe\Usercp\\' . ucfirst($this->mybb->input['action']);

			try {
    			
    			if (!class_exists($className)) {
        			new \BankPipe\Usercp\Subscriptions($this->lang->bankpipe_error_module_not_exists);
        			exit;
    			}
    			
				new $className;
			}
			catch (\Exception $e) {
				new $className($e->getMessage());
			}

		}

	}

}