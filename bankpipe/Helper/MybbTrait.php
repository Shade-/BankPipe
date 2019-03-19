<?php

namespace BankPipe\Helper;

trait MybbTrait
{
    public $mybb;
    public $lang;
    public $db;
    public $session;
    public $plugins;

    public function traitConstruct($extra = [])
    {
        $toLoad = array_merge(['mybb', 'db', 'lang', 'session', 'plugins'], (array) $extra);

        foreach ($toLoad as $variable) {

            $name = strtolower($variable);

            if (!empty($this->$name)) {
                continue;
            }

            if (!empty($GLOBALS[$variable])) {
                $this->$name = $GLOBALS[$variable];
            }

        }
    }
}
