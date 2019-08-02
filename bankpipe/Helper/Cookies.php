<?php

namespace BankPipe\Helper;

class Cookies
{
    use MybbTrait;

    public function __construct()
    {
        $this->traitConstruct();
    }

    public function read(string $name)
    {
        return ($this->mybb->cookies['bankpipe-' . $name])
            ? (array) json_decode($this->mybb->cookies['bankpipe-' . $name])
            : [];
    }

    public function write(string $name, array $data = [])
    {
        return my_setcookie('bankpipe-' . $name, json_encode(array_unique($data)));
    }

    public function destroy(string $name)
    {
        return my_unsetcookie('bankpipe-' . $name);
    }
}
