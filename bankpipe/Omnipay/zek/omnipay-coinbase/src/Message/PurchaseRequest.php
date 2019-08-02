<?php

namespace Omnipay\Coinbase\Message;

/**
 * Coinbase Purchase Request
 *
 * @method \Omnipay\Coinbase\Message\PurchaseResponse send()
 */
class PurchaseRequest extends AbstractRequest
{
    public function setMetaData($value)
    {
        return $this->setParameter('metadata', $value);
    }

    public function getMetaData()
    {
        return $this->getParameter('metadata');
    }

    public function setRedirectUrl($value)
    {
        return $this->setParameter('redirect_url', $value);
    }

    public function getRedirectUrl()
    {
        return $this->getParameter('redirect_url');
    }

    public function setName($value)
    {
        return $this->setParameter('name', $value);
    }

    public function getName()
    {
        return $this->getParameter('name');
    }

    public function getData()
    {
        $this->validate('name', 'description', 'amount', 'currency', 'redirect_url', 'metadata', 'pricing_type');
        $data = [];
        $data['amount'] = $this->getAmount();
        $data['name'] = $this->getName();
        $data['description'] = $this->getDescription();
        $data['pricing_type'] = $this->getParameter('pricing_type');
        $data['redirect_url'] = $this->getRedirectUrl();
        $data['metadata'] = $this->getMetaData();
        if ($this->getParameter('pricing_type') == 'fixed_price') {
            $data['local_price'] = [
                'amount' => $this->getAmount(),
                'currency' => $this->getCurrency(),
            ];
        }
        return $data;
    }

    public function sendData($data)
    {
        $response = $this->sendRequest('POST', '/charges', $data);
        return $this->response = new PurchaseResponse($this, $response);
    }
}
