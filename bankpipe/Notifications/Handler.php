<?php

namespace BankPipe\Notifications;

use BankPipe\Core;

class Handler
{
    use \BankPipe\Helper\MybbTrait;

    protected $parser;
    protected $parserOptions;
    protected $pm;
    protected $sendEmail;
    protected $queue = [];

    public function __construct()
    {
        $this->traitConstruct(['cache']);

        $this->sendEmail = ($this->mybb->settings['bankpipe_admin_notification_method'] != 'pm');

        if ($this->sendEmail) {

            require_once MYBB_ROOT . "inc/class_parser.php";
            $this->parser = new \postParser;
            $this->parserOptions = [
                'allow_mycode' => 1,
                'allow_imgcode' => 1,
                'allow_videocode' => 1,
                'allow_smilies' => 1,
                'filter_badwords' => 1
            ];

        }
        else {

            require_once MYBB_ROOT . "inc/datahandlers/pm.php";
            $this->pm = new \PMDataHandler;
            $this->pm->admin_override = true;

        }

        if (!session_id()) {
            session_start();
        }
    }

    public function set(array $receivers, string $title, string $message)
    {
        $receivers = Core::normalizeArray(array_map('intval', $receivers));

        if (!$receivers or !$title or !$message) {
            return false;
        }

        // Custom fields? Find and replace those fuckers
        $find = $replace = [];

        if ($_SESSION['BankPipe']) {

            foreach ($_SESSION['BankPipe'] as $key => $field) {

                $find[] = '{' . strtoupper($key) . '}';
                $replace[] = $field;

            }

            unset($_SESSION['BankPipe']);

        }

        if ($find and $replace) {
            $message = str_replace($find, $replace, $message);
        }

        $this->queue[] = [
            'title' => $title,
            'message' => $message,
            'receivers' => $receivers
        ];

        $this->plugins->run_hooks('bankpipe_notifications_set', $this);

        return $this->queue;
    }

    public function send()
    {
        if (!$this->queue) {
            return false;
        }

        $this->plugins->run_hooks('bankpipe_notifications_send', $this);

        // Send notification
        if (!$this->sendEmail) {

            $sender = ($this->mybb->settings['bankpipe_admin_notification_sender']) ?
                (int) $this->mybb->settings['bankpipe_admin_notification_sender'] :
                -1;

            foreach ($this->queue as $queuedElement) {

                $pm = [
                    "subject" => $queuedElement['title'],
                    "message" => $queuedElement['message'],
                    "fromid" => $sender,
                    "toid" => $queuedElement['receivers']
                ];

                $this->pm->set_data($pm);

                if ($this->pm->validate_pm()) {
                    $this->pm->insert_pm();
                }

            }

        }
        else {

            $queue = $emails = [];

            foreach ($this->queue as $queuedElement) {

                // Get user emails
                $query = $this->db->simple_select('users', 'uid, email', 'uid IN (' . implode(',', $queuedElement['receivers']) . ')');
                while ($user = $this->db->fetch_array($query)) {

                    if ($user['email']) {
                        $emails[$user['uid']] = $user['email'];
                    }

                }

                foreach ($queuedElement['receivers'] as $uid) {

                    if ($emails[$uid]) {

                        $queue[] = [
                            "mailto" => $this->db->escape_string($emails[$uid]),
                            "mailfrom" => '',
                            "subject" => $this->db->escape_string($queuedElement['title']),
                            "message" => $this->db->escape_string($queuedElement['message']),
                            "headers" => ''
                        ];

                    }

                }

            }

            if (!empty($queue)) {

                $this->db->insert_query_multiple("mailqueue", $queue);
                $this->cache->update_mailqueue();

            }

        }
    }
}
