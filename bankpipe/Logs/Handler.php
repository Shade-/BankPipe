<?php

namespace BankPipe\Logs;

use BankPipe\Core;
use BankPipe\Items\Orders;

class Handler
{
    use \BankPipe\Helper\MybbTrait;

    protected $orderId;

    public function __construct(string $orderId = '')
    {
        $this->traitConstruct();

        if ($orderId) {
            $this->orderId = $orderId;
        }
    }

    public function save(array $data)
    {
        $insert = [
            'uid' => (int) $this->mybb->user['uid'],
            'date' => TIME_NOW
        ];

        if ($this->orderId) {
            $insert['invoice'] = $this->orderId;
        }

        $insert = array_merge($insert, $data);

        $toSanitize = ['message', 'invoice'];
        foreach ($toSanitize as $field) {

            if ($insert[$field]) {
                $insert[$field] = $this->db->escape_string($insert[$field]);
            }

        }

        // Convert arrays to strings
        $toString = ['bids', 'discounts'];
        foreach ($toString as $key) {

            if (is_array($insert[$key])) {
                $insert[$key] = implode('|', Core::normalizeArray($insert[$key]));
            }

        }

        $insert = $this->plugins->run_hooks('bankpipe_log', $insert);

        return $this->db->insert_query('bankpipe_log', $insert);
    }
}
