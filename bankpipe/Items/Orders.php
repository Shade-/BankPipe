<?php

namespace BankPipe\Items;

use BankPipe\Core;
use BankPipe\Helper\Permissions;
use BankPipe\Helper\Utilities;

class Orders
{
    use \BankPipe\Helper\MybbTrait;

    // bankpipe.js needs to be updated as well if those values are changed!
    // TO-DO: add idempotency to bankpipe.js
    const ERROR = 0;
    const CREATE = 1;
    const SUCCESS = 2;
    const PENDING = 3;
    const FAIL = 4;
    const REFUND = 5;
    const MANUAL = 6;
    const CANCEL = 7;
    const UNRESOLVED = 8;
    const UNDERPAID = 9;
    const DELETE = 10;

    public static $orders = [];

    public function __construct()
    {
        $this->traitConstruct();

        $this->permissions = new Permissions;
        $this->utilities = new Utilities;
    }

    public function insert(array $orders, string $orderId, array $settings = [])
    {
        if (!$orders or !$orderId) {
            return false;
        }

        $validatedOrders = $bids = [];

        // Loop through this order
        foreach ($orders as $key => $order) {

            // We want only validated items, not garbage
            if (!$order['bid']) {
                continue;
            }

            $validatedDiscounts = [];

            $price = $order['price'];

            // Subtract discounts
            if ($settings['discounts'][$order['bid']]) {

                foreach ($settings['discounts'][$order['bid']] as $discount) {

                    $price += $discount['amount'];
                    $validatedDiscounts[] = $discount['id'];

                }

            }

            // Target user
            $uid = ($settings['uid']) ? (int) $settings['uid'] : (int) $this->mybb->user['uid'];

            $validatedOrders[$key] = [
                'invoice' => $this->db->escape_string($orderId),
                'price' => Core::sanitizePriceForDatabase($price),
                'uid' => $uid,
                'bid' => (int) $order['bid'],
                'oldgid' => (int) $order['oldgid'],
                'newgid' => (int) $order['newgid'],
                'expires' => (int) $order['expires'],
                'currency' => $this->db->escape_string($settings['currency']),
                'date' => TIME_NOW,
                'active' => 0
            ];

            if ($settings['type'] and is_int($settings['type'])) {
                $validatedOrders[$key]['type'] = (int) $settings['type'];
            }

            // Reference?
            if ($settings['reference']) {
                $validatedOrders[$key]['payment_id'] = $this->db->escape_string($settings['reference']);
            }

            // Associate merchant with this payment
            if ($settings['wallet']) {
                $validatedOrders[$key]['wallet'] = $this->db->escape_string($settings['wallet']);
            }

            if ($settings['merchant']) {
                $validatedOrders[$key]['merchant'] = (int) $settings['merchant'];
            }

            // Add discounts
            if ($validatedDiscounts) {
                $validatedOrders[$key]['discounts'] = implode('|', Core::normalizeArray($validatedDiscounts));
            }

            // Gift?
            if ($settings['donor']) {
                $validatedOrders[$key]['donor'] = (int) $settings['donor'];
            }

            // Gateway
            if ($settings['gateway']) {
                $validatedOrders[$key]['gateway'] = $this->db->escape_string($settings['gateway']);
            }

        }

        $args = [&$this, &$validatedOrders];
        $this->plugins->run_hooks('bankpipe_orders_insert', $args);

        if ($validatedOrders) {
            $this->db->insert_query_multiple(Items::PAYMENTS_TABLE, array_values($validatedOrders));
        }

        return Core::normalizeArray(array_column($validatedOrders, 'bid'));
    }

    public function get(array $search, array $options = [])
    {
        $ordersToReturn = $bids = [];

        // Found in cache
        if ($search['invoice'] and is_string($search['invoice']) and  self::$orders[$search['invoice']]) {

            $ordersToReturn = [
                $search['invoice'] => self::$orders[$search['invoice']]
            ];

            if (!$options['includeItemsInfo'] or self::$orders[$search['invoice']]['itemsAdded']) {
                return $ordersToReturn;
            }

            // Used to grab items
            $bids = array_column(self::$orders[$search['invoice']]['items'], 'bid');

        }
        // Fetch from db
        else {

            $options['group_by'] = $options['group_by'] ?? 'invoice';
            $options['group_by'] .= ', pid, price, bid';

            $query = $this->getQuery($search, '*', $options);
            while ($item = $this->db->fetch_array($query)) {

                // Common sanitization stuff
                $item['discounts'] = explode('|', $item['discounts']);
                $item['currency_code'] = $item['currency'];
                $item['currency'] = Core::friendlyCurrency($item['currency']);

                $item = $this->plugins->run_hooks('bankpipe_orders_get_item', $item);

                // Not yet in the array
                if (!$ordersToReturn[$item['invoice']]) {
                    $ordersToReturn[$item['invoice']] = $item;

                    // Unset specific stuff
                    unset (
                        $ordersToReturn[$item['invoice']]['pid'],
                        $ordersToReturn[$item['invoice']]['price'],
                        $ordersToReturn[$item['invoice']]['bid']
                    );
                }

                // Add the specific item info to a separate subgroup
                $ordersToReturn[$item['invoice']]['items'][] = [
                    'bid' => $item['bid'],
                    'price' => $item['price'],
                    'pid' => $item['pid'],
                    'fee' => $item['fee']
                ];
                $ordersToReturn[$item['invoice']]['total'] += $item['price'];

                // Cache bids for items
                $bids[] = $item['bid'];

            }

        }

        if ($options['includeItemsInfo']) {

            // Associate items
            $items = (new Items)->getItems(Core::normalizeArray($bids));

            if ($items) {

                foreach ($ordersToReturn as $k => $order) {

                    foreach ($order['items'] as $key => $item) {

                        $ordersToReturn[$k]['items'][$key]['originalPrice'] = $items[$item['bid']]['price'];

                        if (is_array($items[$item['bid']])) {
                            $ordersToReturn[$k]['items'][$key] += $items[$item['bid']];
                        }

                    }

                    $ordersToReturn[$k]['itemsAdded'] = true;

                }

            }

        }

        $args = [&$this, &$ordersToReturn];
        $this->plugins->run_hooks('bankpipe_orders_get', $args);

        self::$orders = array_merge(self::$orders, $ordersToReturn);

        return $ordersToReturn;
    }

    public function update(array $update, string $orderId, array $where = [])
    {
        $where['invoice'] = $orderId;

        $update = $this->plugins->run_hooks('bankpipe_orders_update', $update);

        // Get order
        if (!self::$orders[$orderId]) {
            $this->get(['invoice' => $orderId]);
        }

        // Update discounts usage
        if ($update['active'] and $update['type'] == self::SUCCESS and self::$orders[$orderId] and self::$orders[$orderId]['discounts']) {

            // Delete previous subs special "p" value
            if (($key = array_search('p', self::$orders[$orderId]['discounts'])) !== false) {
                unset(self::$orders[$orderId]['discounts'][$key]);
            }

            $discounts = Core::normalizeArray(self::$orders[$orderId]['discounts']);

            if ($discounts) {

                $table = TABLE_PREFIX . Items::DISCOUNTS_TABLE;
                $discounts = implode(',', $discounts);

                $this->db->query(<<<SQL
                    UPDATE $table
                    SET counter = counter + 1
                    WHERE did IN ($discounts)
SQL
);

            }

        }

        // Update the internal cache
        self::$orders[$orderId] = array_merge(self::$orders[$orderId], $update);

        return ($update) ? $this->db->update_query(Items::PAYMENTS_TABLE, $update, $this->buildWhereStatement($where)) : false;
    }

    public function destroy(string $orderId)
    {
        $this->plugins->run_hooks('bankpipe_orders_destroy');

        return ($orderId) ? $this->db->delete_query(Items::PAYMENTS_TABLE, $this->buildWhereStatement(['invoice' => $orderId])) : false;
    }

    private function getQuery(array $where, string $columns = '*', array $options = [])
    {
        $options['order_by'] = $options['order_by'] ?? 'date DESC';

        return $this->db->simple_select(Items::PAYMENTS_TABLE, $columns, $this->buildWhereStatement($where), $options);
    }

    private function buildWhereStatement(array $fields)
    {
        $final = '';

        if ($fields['OR']) {

            $or = $fields['OR'];
            unset($fields['OR']);

        }

        $and = $this->sanitizeFields($fields);
        $final = implode(' AND ', $and);

        if ($or) {

            if ($or = $this->sanitizeFields($or)) {
                $final .= ' OR (' . implode(' AND ', $or) . ')';
            }

        }

        return $final;
    }

    private function sanitizeFields(array $fields)
    {
        $array = [];

        foreach ($fields as $column => $value) {

            if (is_int($column)) {
                $array[] = $value;
            }
            else if (is_string($value)) {
                $array[] = $column . " = '" . $this->db->escape_string($value) . "'";
            }
            else if (is_array($value)) {
                $array[] = $column . " IN ('" . implode("','", $value) . "')";
            }
            else {
                $array[] = $column . " = '" . $value . "'";
            }

        }

        return $array;
    }
}
