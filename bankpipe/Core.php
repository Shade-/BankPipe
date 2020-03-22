<?php

namespace BankPipe;

include 'Omnipay/autoload.php';

use Omnipay\Omnipay;
use BankPipe\Gateway\GatewayInterface;
use BankPipe\Items\Orders;
use BankPipe\Items\Items;
use BankPipe\Logs\Handler as Logs;
use BankPipe\Helper\Cookies;
use BankPipe\Notifications\Handler as Notifications;
use BankPipe\Messages\Handler as Messages;
use BankPipe\Helper\Permissions;

class Core implements GatewayInterface
{
    use Helper\MybbTrait;

    protected $gatewayName;
    protected $orderId;
    public $gateways;

    public function __construct()
    {
        $this->traitConstruct();

        $this->orderId = $this->mybb->input['orderId'] ?? uniqid();
        $this->orders = new Orders;
        $this->items = new Items;
        $this->log = new Logs($this->orderId);
        $this->cookies = new Cookies;
        $this->notifications = new Notifications;
        $this->messages = new Messages;

        $query = $this->db->simple_select('bankpipe_gateways', '*');
        while ($gateway = $this->db->fetch_array($query)) {
            $this->gateways[$gateway['name']] = $gateway;
        }
    }

    /**
     * Prepare a payment
     *
     * @param $parameters   An array of settings including the buyer's ID, amount charged, redirect URIs and
     *                      whatever is needed for any particular gateway
     * @param $items        An array of items to purchase
     *
     * @return $response    The response from the gateway
     */
    public function purchase(array $parameters, array $items)
    {
        $settings = array_merge($parameters, [
            'amount' => 0,
            'currency' => $parameters['currency'],
            'returnUrl' => $this->getReturnUrl(),
            'cancelUrl' => $this->getCancelUrl()
        ]);

        $finalItems = $bids = $discounts = $validatedDiscounts = [];
        $finalPrice = 0;

        // Cache discounts
        $discountsInCart = $this->cookies->read('discounts');
        if ($discountsInCart) {

            $search = implode(',', array_map('intval', $discountsInCart));

            $permissions = new Permissions;

            $query = $this->db->simple_select('bankpipe_discounts', '*', 'did IN (' . $search . ')');
            while ($discount = $this->db->fetch_array($query)) {
                $discounts[] = $discount;
            }

        }

        // Loop through all the items
        foreach ($items as $item) {

            $finalPrice = $price = self::filterPrice($item['price']);

            $itemDiscounts = [];

            $itemData = [
                'name' => $item['name'],
                'price' => $price,
                'quantity' => 1,
                'bid' => $item['bid']
            ];

            // Expiration date
            if ($item['expires']) {
                $itemData['expires'] = (TIME_NOW + (60*60*24*$item['expires']));
            }

            // Description
            if ($item['description']) {
                $itemData['description'] = $item['description'];
            }

            // Destination usergroup
            if ($item['gid']) {

                $itemData['oldgid'] = ($item['expirygid']) ? $item['expirygid'] : $this->mybb->user['usergroup'];
                $itemData['newgid'] = $item['gid'];

            }

            // Add this item to the final array
            $finalItems[] = $itemData;

            // Apply discounts
            // Previous subscriptions
            $item['discount'] = (int) $item['discount'];

            if (!$parameters['gift'] and $item['discount'] > 0) {

                // Search for the highest paid previous subscription of this user
                $query = $this->db->query('
                    SELECT MAX(i.price) AS price, p.payment_id
                    FROM ' . TABLE_PREFIX . 'bankpipe_items i
                    LEFT JOIN ' . TABLE_PREFIX . 'bankpipe_payments p ON (p.uid = ' . (int) $this->mybb->user['uid'] . ' AND p.bid = i.bid AND p.active = 1)
                    WHERE i.type = ' . Items::SUBSCRIPTION . ' AND p.payment_id IS NOT NULL AND i.bid <> ' . (int) $item['bid'] . '
                    LIMIT 1
                ');
                $oldSubscription = $this->db->fetch_array($query);

                // If found, calculate relative discount
                if ($oldSubscription['price']) {
                    $price = ($item['price'] - ($oldSubscription['price'] * $item['discount'] / 100));
                }

                $price = ($price > 0) ? $price : 0;

                if ($price != $finalPrice) {

                    // Set new price
                    $finalPrice = $price;

                    $discountAmount = self::filterPrice($price - $item['price']);

                    // Add discount
                    $finalItems[] = [
                        'name' => $this->lang->bankpipe_discount_previous_item,
                        'description' => $this->lang->bankpipe_discount_previous_item_desc,
                        'price' => $discountAmount,
                        'quantity' => 1
                    ];

                    $itemDiscounts[] = [
                        'id' => 'p',
                        'amount' => $discountAmount
                    ];

                }

            }

            // Code discounts
            if ($discounts) {

                foreach ($discounts as $discount) {

                    if (!$permissions->discountCheck($discount, $item)
                        or ($discount['cap'] and $discount['cap'] <= $discount['counter'])) {
                        continue;
                    }

                    // Finally apply the discount
                    // Percentage
                    if ($discount['type'] == 1) {
                        $price = $finalPrice - ($finalPrice * $discount['value'] / 100);
                    }
                    // Absolute
                    else {
                        $price = $finalPrice - $discount['value'];
                    }

                    $price = ($price > 0) ? $price : 0;

                    if ($price != $finalPrice) {

                        $discountAmount = self::filterPrice($price - $finalPrice);

                        // Add discount
                        $finalItems[] = [
                            'name' => $discount['code'],
                            'description' => $this->lang->bankpipe_discount_code_desc,
                            'price' => $discountAmount,
                            'quantity' => 1,
                            'did' => $discount['did']
                        ];

                        // Set the new final price
                        $finalPrice = $price;

                        $itemDiscounts[] = [
                            'id' => $discount['did'],
                            'amount' => $discountAmount
                        ];

                    }

                }

            }

            $settings['amount'] += $finalPrice; // Add this item to the total
            $validatedDiscounts[$item['bid']] = $itemDiscounts;

        }

        $settings['amount'] = self::filterPrice($settings['amount']);

        try {

            $response = $this->gateway
                ->purchase($settings)
                ->setItems($finalItems)
                ->send();

        }
        catch (\Exception $e) {
            return $this->messages->error($e->getMessage());
        }

        $settings['type'] = $settings['type'] ?? Orders::CREATE;
        $settings['discounts'] = $validatedDiscounts;

        // Gift to user?
        if ($parameters['gift']) {

            $settings['uid'] = $parameters['gift'];
            $settings['donor'] = $this->mybb->user['uid'];

        }

        // If preparation is successful, insert the preliminary order (will be authorized or destroyed in the complete() method)
        $bids = $this->orders->insert($finalItems, $this->orderId, $settings);

        $this->log->save([
            'type' => $settings['type'],
            'bids' => $bids
        ]);

        $args = [&$this, &$finalItems];
        $this->plugins->run_hooks('bankpipe_core_purchase', $args);

        return $response;
    }

    /**
     * Complete the authorized payment and charge the user
     *
     * @param $parameters   An array of extra parameters to finish the authorization
     *
     * @return $response    The response from the gateway
     */
    public function complete(array $parameters)
    {
        if (!$this->orderId) {
            $this->messages->error($this->lang->bankpipe_error_order_not_found);
        }

        $order = reset($this->orders->get([
            'invoice' => $this->orderId
        ], [
            'includeItemsInfo' => true
        ]));

        if (!$order) {
            $this->messages->error($this->lang->bankpipe_error_order_not_found);
        }

        $parameters = array_merge([
            'amount' => $order['total'],
            'returnUrl' => $this->getReturnUrl(),
            'cancelUrl' => $this->getCancelUrl()
        ], $parameters);

        // Complete purchase on gateway's side
        $response = $this->gateway
            ->completePurchase($parameters)
            ->send();

        $bids = self::normalizeArray(array_column($order['items'], 'bid'));

        // Update payment details
        if ($response->isSuccessful()) {

            $this->orders->update([
                'active' => 1,
                'date' => TIME_NOW,
                'type' => Orders::SUCCESS
            ], $this->orderId);

            // Update usergroup
            $this->updateUsergroup($order['items'], $order['uid']);

            $this->log->save([
                'type' => Orders::SUCCESS,
                'bids' => $bids
            ]);

        }
        // Unsuccessful, delete
        else {

            $this->orders->destroy($this->orderId);

            $this->log->save([
                'type' => Orders::CANCEL
            ]);

        }

        $args = [&$this, &$order];
        $this->plugins->run_hooks('bankpipe_core_complete', $args);

        return [
            'response' => $response,
            'status' => $response->isSuccessful(),
            'invoice' => $this->orderId
        ];
    }

    public function refund(array $parameters = [])
    {
        return $this->gateway->refund($parameters);
    }

    public function updateUsergroup(array $items = [], int $uid = 0)
    {
        if (!$uid) {
            return false;
        }

        $user = get_user($uid);

        // Update usergroup
        $update = [];
        $additionalGroups = (array) explode(',', $user['additionalgroups']);

        foreach ($items as $item) {

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

        if ($update['additionalgroups']) {
            $update['additionalgroups'] = implode(',', self::normalizeArray($additionalGroups));
        }

        if ($update) {
            return $this->db->update_query('users', $update, "uid = '" . $uid . "'");
        }
    }

    public function verifyWebhookSignature(array $parameters = [])
    {
        return $this->gateway->verifyWebhookSignature($parameters);
    }

    public function webhookListener()
    {
        return $this->verifyWebhookSignature();
    }

    public function getCancelUrl()
    {
        return $this->mybb->settings['bburl'] . '/bankpipe.php?action=cancel&gateway=' . $this->gatewayName . '&orderId=' . $this->orderId;
    }

    public function getReturnUrl()
    {
        return $this->mybb->settings['bburl'] . '/bankpipe.php?action=complete&gateway=' . $this->gatewayName . '&orderId=' . $this->orderId;
    }

    public function getNotifyUrl()
    {
        return $this->mybb->settings['bburl'] . '/bankpipe.php?action=webhooks&gateway=' . $this->gatewayName . '&orderId=' . $this->orderId;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

    static public function filterPrice(string $price)
    {
        return number_format((float) filter_var(str_replace(',', '.', $price), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), 2);
    }

    static public function normalizeArray(array $array)
    {
        return array_filter(array_unique($array));
    }

    static public function friendlyCurrency(string $currency)
    {
        $currencies = [
            'AUD' => 'AU&#36;',
            'BRL' => 'R&#36;',
            'CAD' => '&#36;',
            'CZK' => 'Kč',
            'DKK' => 'kr.',
            'EUR' => '&#8364;',
            'HKD' => '&#36;',
            'HUF' => 'Ft',
            'INR' => 'Rupees',
            'ILS' => '&#8362;',
            'JPY' => '&#165;',
            'MYR' => 'RM',
            'MXN' => '&#36;',
            'TWD' => 'NT&#36;',
            'NZD' => '&#36;',
            'NOK' => 'kr',
            'PHP' => '&#8369;',
            'PLN' => 'zł',
            'GBP' => '&#163;',
            'RUB' => '&#8381;',
            'SGD' => '&#36;',
            'SEK' => 'kr',
            'CHF' => 'CHF',
            'THB' => '&#3647;',
            'USD' => '&#36;'
        ];

        return ($currencies[$currency]) ? $currencies[$currency] : false;
    }
}
