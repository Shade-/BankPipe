<?php

namespace Omnipay\Coinbase\Message;

/**
 * Coinbase Purchase Response
 */
class PurchaseResponse extends FetchTransactionResponse
{
    public function isSuccessful()
    {
        return false;
    }
}