<?php

namespace BankPipe\Gateway;

interface GatewayInterface
{
    public function purchase(array $parameters, array $items);
    public function complete(array $parameters);
    public function webhookListener();
}
