<?php

namespace Omnipay\Coinbase\Message;
/**
 * Coinbase Abstract Request
 *
 * @method \Omnipay\Coinbase\Message\Response send()
 */
abstract class AbstractRequest extends \Omnipay\Common\Message\AbstractRequest
{
    const API_VERSION = '2018-03-22';

    protected $liveEndpoint = 'https://api.commerce.coinbase.com';

    public function getApiKey()
    {
        return $this->getParameter('apiKey');
    }

    public function setApiKey($value)
    {
        return $this->setParameter('apiKey', $value);
    }

    public function getSecret()
    {
        return $this->getParameter('secret');
    }

    public function setSecret($value)
    {
        return $this->setParameter('secret', $value);
    }

    public function getAccountId()
    {
        return $this->getParameter('accountId');
    }

    public function setAccountId($value)
    {
        return $this->setParameter('accountId', $value);
    }

    public function getCode()
    {
        return $this->getParameter('code');
    }

    public function setCode($value)
    {
        return $this->setParameter('code', $value);
    }
    
    /**
     * Get the request description.
     *
     * @return string
     */
    public function getPricingType()
    {
        return $this->getParameter('pricing_type');
    }

    /**
     * Sets the request description.
     *
     * @param string $value
     * @return $this
     */
    public function setPricingType($value)
    {
        return $this->setParameter('pricing_type', $value);
    }
    
    public function sendRequest($method, $action, $data = null)
    {
        $url = $this->getEndpoint() . $action;
        $body = $data ? http_build_query($data) : null;

        $response = $this->httpClient->request(
            $method,
            $url,
            [
                'X-CC-Api-Key' => $this->getApiKey(),
                'X-CC-Version' => self::API_VERSION,
            ],
            $body
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    public function generateSignature($url, $body, $nonce)
    {
        $message = $nonce . $url . $body;
        return hash_hmac('sha256', $message, $this->getSecret());
    }

    protected function getEndpoint()
    {
        return $this->liveEndpoint;
    }
}
