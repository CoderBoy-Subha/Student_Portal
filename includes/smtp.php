<?php
// ============================================================
//  includes/smtp.php — Pure-PHP SMTP client
//  No external libraries. Supports TLS (587) and SSL (465).
//  Timeout uses SMTP_TIMEOUT constant to avoid page hangs.
// ============================================================

if (!class_exists('SimpleSMTP')) {

class SimpleSMTP
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $socket = null;
    private $timeout;
    public  $last_error = '';

    public function __construct($host, $port, $username, $password, $encryption = 'tls', $timeout = 10)
    {
        $this->host       = $host;
        $this->port       = $port;
        $this->username   = $username;
        $this->password   = $password;
        $this->encryption = strtolower($encryption);
        $this->timeout    = (int)$timeout;
    }

    private function connect()
    {
        $prefix = ($this->encryption === 'ssl') ? 'ssl://' : '';
        $remote = $prefix . $this->host . ':' . $this->port;
        $ctx    = stream_context_create(array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            )
        ));
        $errno  = 0;
        $errstr = '';
        $this->socket = @stream_socket_client(
            $remote, $errno, $errstr, $this->timeout,
            STREAM_CLIENT_CONNECT, $ctx
        );
        if (!$this->socket) {
            $this->last_error = 'Cannot connect to ' . $this->host . ':' . $this->port . ' — ' . $errstr . ' (' . $errno . ')';
            return false;
        }
        stream_set_timeout($this->socket, $this->timeout);
        $this->read(); // server greeting
        return true;
    }

    private function cmd($line)
    {
        fwrite($this->socket, $line . "\r\n");
        return $this->read();
    }

    private function read()
    {
        $data = '';
        while ($line = fgets($this->socket, 515)) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    }

    private function code($resp)
    {
        return (int)substr($resp, 0, 3);
    }

    public function send($from, $from_name, $to, $to_name, $subject, $body)
    {
        if (!$this->connect()) {
            return false;
        }

        $helo = isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] ? $_SERVER['SERVER_NAME'] : 'localhost';

        $r = $this->cmd('EHLO ' . $helo);
        if ($this->code($r) !== 250) {
            $this->last_error = 'EHLO failed: ' . trim($r);
            fclose($this->socket);
            return false;
        }

        if ($this->encryption === 'tls') {
            $r = $this->cmd('STARTTLS');
            if ($this->code($r) !== 220) {
                $this->last_error = 'STARTTLS failed: ' . trim($r);
                fclose($this->socket);
                return false;
            }
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->last_error = 'TLS crypto negotiation failed';
                fclose($this->socket);
                return false;
            }
            $r = $this->cmd('EHLO ' . $helo);
            if ($this->code($r) !== 250) {
                $this->last_error = 'EHLO after STARTTLS failed: ' . trim($r);
                fclose($this->socket);
                return false;
            }
        }

        $r = $this->cmd('AUTH LOGIN');
        if ($this->code($r) !== 334) {
            $this->last_error = 'AUTH LOGIN not accepted: ' . trim($r);
            fclose($this->socket);
            return false;
        }
        $r = $this->cmd(base64_encode($this->username));
        if ($this->code($r) !== 334) {
            $this->last_error = 'Username rejected: ' . trim($r);
            fclose($this->socket);
            return false;
        }
        $r = $this->cmd(base64_encode($this->password));
        if ($this->code($r) !== 235) {
            $this->last_error = 'Authentication failed — check SMTP credentials or use an App Password: ' . trim($r);
            fclose($this->socket);
            return false;
        }

        $r = $this->cmd('MAIL FROM:<' . $from . '>');
        if ($this->code($r) !== 250) {
            $this->last_error = 'MAIL FROM rejected: ' . trim($r);
            fclose($this->socket);
            return false;
        }

        $r = $this->cmd('RCPT TO:<' . $to . '>');
        if ($this->code($r) !== 250 && $this->code($r) !== 251) {
            $this->last_error = 'RCPT TO rejected: ' . trim($r);
            fclose($this->socket);
            return false;
        }

        $r = $this->cmd('DATA');
        if ($this->code($r) !== 354) {
            $this->last_error = 'DATA rejected: ' . trim($r);
            fclose($this->socket);
            return false;
        }

        $msg  = 'Date: '    . date('r') . "\r\n";
        $msg .= 'From: '    . $this->rfc_name($from_name) . ' <' . $from . '>' . "\r\n";
        $msg .= 'To: '      . $this->rfc_name($to_name)   . ' <' . $to   . '>' . "\r\n";
        $msg .= 'Subject: ' . $this->rfc_name($subject)   . "\r\n";
        $msg .= 'MIME-Version: 1.0' . "\r\n";
        $msg .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $msg .= 'Content-Transfer-Encoding: 8bit' . "\r\n";
        $msg .= 'X-Mailer: StudentPortal-PHP' . "\r\n";
        $msg .= "\r\n";
        $msg .= preg_replace('/^\./m', '..', $body); // SMTP dot-stuffing
        $msg .= "\r\n.";

        $r = $this->cmd($msg);
        if ($this->code($r) !== 250) {
            $this->last_error = 'Message not accepted: ' . trim($r);
            fclose($this->socket);
            return false;
        }

        $this->cmd('QUIT');
        fclose($this->socket);
        return true;
    }

    private function rfc_name($text)
    {
        if (preg_match('/[^\x20-\x7E]/', $text)) {
            return '=?UTF-8?B?' . base64_encode($text) . '?=';
        }
        return $text;
    }
}

} // end if !class_exists
