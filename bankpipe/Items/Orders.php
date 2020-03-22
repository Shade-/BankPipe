<?php

namespace BankPipe\Items;

use BankPipe\Core;
use BankPipe\Helper\Permissions;

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

    public $orders = [];

    public function __construct()
    {
        $this->traitConstruct();

        $this->permissions = new Permissions;
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
                'price' => Core::filterPrice($price),
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
        $return = $bids = [];

        $options['group_by'] = $options['group_by'] ?? 'invoice';
        $options['group_by'] .= ', pid, price, bid';

        switch ($this->db->type) {
            case 'pgsql':
                $query = $this->getQuery($search, '*, STRING_AGG(bid || \'|\' || price || \'|\' || pid, \',\') AS concat', $options);
                break;
            default:
                $query = $this->getQuery($search, '*, GROUP_CONCAT(bid, \'|\', price, \'|\', pid) concat', $options);
                break;
        }
        while ($item = $this->db->fetch_array($query)) {

            $item = $this->plugins->run_hooks('bankpipe_orders_get_item', $item);

            $item['discounts'] = explode('|', $item['discounts']);
            $specificFeatures = explode(',', $item['concat']);

            unset($item['concat'], $item['bid'], $item['pid'], $item['price']);

            $item['currency_code'] = $item['currency'];
            $item['currency'] = Core::friendlyCurrency($item['currency']);

            $return[$item['invoice']] = $item;

            foreach ($specificFeatures as $info) {

                $info = explode('|', $info);

                $return[$item['invoice']]['total'] += $info[1];

                $bids[] = $info[0];

                $return[$item['invoice']]['items'][] = [
                    'bid' => $info[0],
                    'price' => $info[1],
                    'pid' => $info[2]
                ];

            }

        }

        if ($options['includeItemsInfo']) {

            // Associate items
            $items = (new Items)->getItems(Core::normalizeArray($bids));
            foreach ($return as $k => $order) {

                foreach ($order['items'] as $key => $item) {

                    $return[$k]['items'][$key]['originalPrice'] = $items[$item['bid']]['price'];

                    if (is_array($items[$item['bid']])) {
                        $return[$k]['items'][$key] += $items[$item['bid']];
                    }

                }

            }

        }

        $args = [&$this, &$return];
        $this->plugins->run_hooks('bankpipe_orders_get', $args);

        $this->orders = array_merge($this->orders, $return);

        return $return;
    }

    public function update(array $update, string $orderId, array $where = [])
    {
        $where['invoice'] = $orderId;

        $update = $this->plugins->run_hooks('bankpipe_orders_update', $update);

        // Get order
        if (!$this->orders[$orderId]) {
            $this->get(['invoice' => $orderId]);
        }

        // Update discounts usage
        if ($update['active'] and $update['type'] == self::SUCCESS and $this->orders[$orderId] and $this->orders[$orderId]['discounts']) {

            // Delete previous subs special "p" value
            if (($key = array_search('p', $this->orders[$orderId]['discounts'])) !== false) {
                unset($this->orders[$orderId]['discounts'][$key]);
            }

            $discounts = Core::normalizeArray($this->orders[$orderId]['discounts']);

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
