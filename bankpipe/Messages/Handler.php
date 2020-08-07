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

    public function display($data, $type = 'normal')
    {
        return $this->outputAndDie($data, $type);
    }

    public function error($data)
    {
        // TO-DO: add logging
        if (is_string($data)) {
            $data = [$data];
        }

        return $this->outputAndDie(['errors' => $data]);
    }

    private function outputAndDie(array $data, $type = 'normal')
    {
        if ($type == 'popup') {

            $html = trim(ob_get_clean());
            header('Content-Type: text/html');

            $json = json_encode($data);

            echo <<<HTML
<script type="text/javascript">
window.opener.BankPipe.processPopupMessage($json);
window.opener.BankPipe.popupClosedAutomatically = true;
window.close();
</script>
HTML;
            exit;

        }

        echo json_encode((array) $data, JSON_PRETTY_PRINT);
        exit;
    }
}
