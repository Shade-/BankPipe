<?php

namespace BankPipe\Messages;

class Handler
{
    public function __construct()
    {
        header('Content-Type: application/json');
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Credentials: true");
    }

    public function display($data)
    {   
        return $this->outputAndDie($data);
    }

    public function error($data)
    {
        // TO-DO: add logging

        return $this->outputAndDie(['errors' => $data]);
    }

    private function outputAndDie(array $data)
    {
        if (is_string($data)) {
            $data = [$data];
        }

        echo json_encode((array) $data, JSON_PRETTY_PRINT);
        exit;
    }
}
