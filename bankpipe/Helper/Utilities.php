<?php

namespace BankPipe\Helper;

use BankPipe\Core;
use BankPipe\Items\Orders;

class Utilities
{
    use \BankPipe\Helper\MybbTrait;

    public function __construct() {
        $this->traitConstruct();
    }

    // Upgrades an user
    public function upgradeUser(int $uid, string $orderId)
    {
        // Get order info
        $order = reset((new Orders)->get([
            'invoice' => $orderId
        ], [
            'includeItemsInfo' => true
        ]));

        $user = get_user($uid);

        // Update usergroup
        $update = [];
        $additionalGroups = (array) explode(',', $user['additionalgroups']);

        if ($order['items']) {

            foreach ($order['items'] as $item) {

                if ($item['gid']) {

                    if ($item['primarygroup'] and strpos($item['gid'], ',') === false) {

                        // Move the current primary group to the additional groups array
                        $additionalGroups[] = $user['usergroup'];

                        $update['usergroup'] = (int) $item['gid'];
                        $update['displaygroup'] = 0; // Use primary

                    }
                    else {

                        $groups = explode(',', $item['gid']);

                        foreach ($groups as $gid) {

                            // Check if the new gid is already present and eventually add it
                            if (!in_array($gid, $additionalGroups)) {
                                $additionalGroups[] = $gid;
                            }

                        }

                    }

                    $update['additionalgroups'] = $additionalGroups;

                }

            }

        }

        if ($update['additionalgroups']) {
            $update['additionalgroups'] = implode(',', Core::normalizeArray($additionalGroups));
        }

        if ($update) {
            return $this->db->update_query('users', $update, "uid = '" . $uid . "'");
        }
    }

    // Demotes an user to the pre-purchase usergroup
    public function demoteUser(int $uid, string $orderId)
    {
        // Get order info
        $order = reset((new Orders)->get([
            'invoice' => $orderId
        ], [
            'includeItemsInfo' => true
        ]));

        $user = get_user($uid);

        if ($order['items'] and $order['invoice'] and $uid == $order['uid']) {

            $oldGroup = (int) $order['oldgid'];

            if ($oldGroup) {

                // If the item is available
                foreach ($order['items'] as $item) {

                    if ($item['primarygroup'] and strpos($order['newgid'], ',') === false) {
                        $update['usergroup'] = $oldGroup;
                        $update['displaygroup'] = 0;
                    }
                    else {

                        $additionalGroups = (array) explode(',', $user['additionalgroups']);

                        // Check if the old gid is already present and eventually add it
                        if (!in_array($oldGroup, $additionalGroups)) {
                            $additionalGroups[] = $oldGroup;
                        }

                        // Remove the new gid(s)
                        $groups = (array) explode(',', $order['newgid']);

                        foreach ($groups as $gid) {

                            if (($key = array_search($gid, $additionalGroups)) !== false) {
                                unset($additionalGroups[$key]);
                            }

                        }

                        $data['additionalgroups'] = implode(',', Core::normalizeArray($additionalGroups));

                    }

                }

            }

            if ($update) {
                $this->db->update_query('users', $update, "uid = '" . $uid . "'");
            }
        }
    }
}
