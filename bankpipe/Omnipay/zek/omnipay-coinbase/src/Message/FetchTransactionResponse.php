<?php

namespace Omnipay\Coinbase\Message;

use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * Coinbase FetchTransaction Response
 */
class FetchTransactionResponse extends AbstractResponse implements RedirectResponseInterface
{
    /**
     * {@inheritdoc}
     */
    public function isRedirect()
    {
        return isset($this->data['data']['hosted_url']);
    }

    /**
     * {@inheritdoc}
     */
    public function getRedirectUrl()
    {
        if ($this->isRedirect()) {
            return $this->data['data']['hosted_url'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRedirectMethod()
    {
        return 'GET';
    }

    /**
     * {@inheritdoc}
     */
    public function getRedirectData()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function isSuccessful()
    {
        return parent::isSuccessful();
    }

    /**
     * @return boolean
     */
    public function isOpen()
    {
        $status = $this->getStatus();
        return $status === 'NEW';
    }

    /**
     * @return boolean
     */
    public function isPending()
    {
        $status = $this->getStatus();
        return $status === 'PENDING';
    }

    /**
     * @return boolean
     */
    public function isCancelled()
    {
        $status = $this->getStatus();
        return $status === 'UNRESOLVED';
    }

    /**
     * @return boolean
     */
    public function isPaid()
    {
        $status = $this->getStatus();
        return $status === 'COMPLETED';
    }

    /**
     * @return boolean
     */
    public function isExpired()
    {
        $status = $this->getStatus();
        return $status === 'EXPIRED';
    }

    /**
     * @return boolean
     */
    public function isResolved()
    {
        $status = $this->getStatus();
        return $status === 'RESOLVED';
    }
    
    /**
     * @return mixed
     */
    public function getTransactionReference()
    {
        if (isset($this->data['data']['payments'][0]['transaction_id'])) {
            return $this->data['data']['payments'][0]['transaction_id'];
        }
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        if (isset($this->data['data']['timeline'])) {
            return end($this->data['data']['timeline'])['status'];
        }
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        if (isset($this->data['data']['pricing']['local']['amount'])) {
            return $this->data['data']['pricing']['local']['amount'];
        }
    }

    /**
     * @return mixed
     */
    public function getCurrency()
    {
        if (isset($this->data['data']['pricing']['local']['currency'])) {
            return $this->data['data']['pricing']['local']['currency'];
        }
    }

    /**
     * @return mixed
     */
    public function getMetadata()
    {
        if (isset($this->data['data']['metadata'])) {
            return $this->data['data']['metadata'];
        }
    }

    /**
     * @return mixed
     */
    public function getConfirmations()
    {
        if (isset($this->data['data']['payments'][0]['block']['confirmations'])) {
            return min(array_map(function ($payment) {
                return $payment['block']['confirmations'];
            }, $this->data['data']['payments']));
        }
    }


    public function getCode()
    {
        if (isset($this->data['data']['code'])) {
            return $this->data['data']['code'];
        }
    }
}
