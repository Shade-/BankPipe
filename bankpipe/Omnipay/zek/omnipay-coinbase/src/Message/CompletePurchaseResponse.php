<?php

namespace Omnipay\Coinbase\Message;

/**
 * Coinbase CompletePurchase Response
 */
class CompletePurchaseResponse extends FetchTransactionResponse
{
    /**
     * {@inheritdoc}
     */
    public function isSuccessful()
    {
        return $this->isPaid() || $this->isResolved();
    }
}
