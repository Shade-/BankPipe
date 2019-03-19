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

class Core implements GatewayInterface
{
    use Helper\MybbTrait;

    protected $gatewayName;
    protected $orderId;

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
        try {

            $response = $this->gateway
                ->purchase($parameters)
                ->setItems($items)
                ->send();

        }
        catch (\Exception $e) {
            return $this->messages->error($e->getMessage());
        }

        $parameters['type'] = Orders::CREATE;

        // If preparation is successful, insert the preliminary order (will be authorized or destroyed in the complete() method)
        $bids = $this->orders->insert($items, $this->orderId, $parameters);

        $this->log->save([
            'type' => Orders::CREATE,
            'bids' => $bids
        ]);

        $args = [&$this, &$items];
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
            $update = [];
            $additionalGroups = (array) explode(',', $this->mybb->user['additionalgroups']);

            // TO-DO: change getItems($bids) to something already available?
            foreach ($this->items->getItems($bids) as $item) {

                if ($item['gid']) {

                    if ($item['primarygroup']) {

                        // Move the current primary group to the additional groups array
                        $additionalGroups[] = $this->mybb->user['usergroup'];

                        $update['usergroup'] = (int) $item['gid'];
                        $update['displaygroup'] = 0; // Use primary

                    }
                    else {

                        // Check if the new gid is already present and eventually add it
                        if (!in_array($item['gid'], $additionalGroups)) {
                            $additionalGroups[] = $item['gid'];
                        }

                    }

                    $update['additionalgroups'] = $additionalGroups;

                }

            }

            if ($update['additionalgroups']) {
                $update['additionalgroups'] = implode(',', self::normalizeArray($additionalGroups));
            }

            if ($update) {
                $this->db->update_query('users', $update, "uid = '" . (int) $this->mybb->user['uid'] . "'");
            }

            // Wipe cart cookies
            if ($this->mybb->settings['bankpipe_cart_mode'] and $this->mybb->cookies['bankpipe-items']) {
                $this->cookies->destroy('items');
            }

            // Wipe discount cookies
            if ($this->mybb->cookies['bankpipe-discounts']) {
                $this->cookies->destroy('discounts');
            }

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
            'invoice' => $this->orderId
        ];
    }

    public function refund(array $parameters = [])
    {
        return $this->gateway->refund($parameters);
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
        return $this->mybb->settings['bburl'] . '/bankpipe.php?action=notify&gateway=' . $this->gatewayName . '&orderId=' . $this->orderId;
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
