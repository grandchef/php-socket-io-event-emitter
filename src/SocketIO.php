<?php


declare(strict_types = 1);

namespace GrandChef\SocketIO;

use Exception;

class SocketIO
{

    const  SSL_PROTOCOL = 'ssl://';
    const  TLS_PROTOCOL = 'tls://';
    const  NO_SECURE_PROTOCOL = '';

    private $objSocket;

    /**
     * @var string
     */
    private $port;

    /**
     * @var string|int
     */
    private $host;

    /**
     * @var mixed
     */
    private $socket_context;

    /**
     * @var string
     */
    private $protocol = SocketIO::NO_SECURE_PROTOCOL;

    /**
     * @var ?string
     */
    private $namespace;

    /**
     * @var string
     */
    private $event;

    /**
     * @var array|string
     */
    private $data = [];

    /**
     * @var string
     */
    private $path;

    /**
     * @var int
     */
    private $maxRetry = 5;

    /**
     * @var int
     */
    private $retryInterval = 200;


    private $queryParams = [];


    /**
     * SocketIO constructor.
     * @param string null $host
     * @param string|int null $port
     * @param string $path
     * @param ?resource $socket_context
     */
    public function __construct($host = null, $port = null, $path = "/socket.io/?EIO=4", $socket_context = null)
    {
        $this->host = $host;
        $this->port = (int)$port;
        $this->path = $path;
        $this->socket_context = $socket_context;

        $protocol = $this->port == 443
            ? self::TLS_PROTOCOL
            : self::NO_SECURE_PROTOCOL;
        $this->protocol = $protocol;
    }

    /**
     * @return string
     */
    public function getQueryParams()
    {
        $query = '';
        if(count($this->queryParams) > 0)
        {
            $query =  http_build_query($this->queryParams);
        }


        return $query;
    }

    /**
     * @param array $queryParams
     */
    public function setQueryParams($queryParams)
    {
        $this->queryParams = $queryParams;
    }

    public function setNamespace()
    {
        fwrite($this->objSocket, $this->hybi10Encode("40/{$this->namespace}"));
        fflush($this->objSocket);
        \usleep(100000);
    }

    public function connect()
    {
        $hostname = "{$this->protocol}{$this->host}:{$this->port}";
        $this->objSocket = stream_socket_client($hostname, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $this->socket_context);

        if (! $this->objSocket) {
            throw new Exception("Error: {$errstr}", $errno);
        }

        $strKey = $this->generateKey();
        $strSend = "GET {$this->path}&{$this->getQueryParams()}&transport=websocket HTTP/1.1\r\n";
        $strSend.= "Host: {$this->host}:{$this->port}\r\n";
        $strSend.= "x-forwarded-port: {$this->port}\r\n";
        $strSend.= "Upgrade: WebSocket\r\n";
        $strSend.= "Connection: Upgrade\r\n";
        $strSend.= "Sec-WebSocket-Key: $strKey\r\n";
        $strSend.= "Sec-WebSocket-Version: 13\r\n";
        $strSend.= "Origin: *\r\n\r\n";

        fwrite($this->objSocket, $strSend);
        fflush($this->objSocket);
        // 101 switching protocols, see if echoes key
        $result = fread($this->objSocket, 10000);
        preg_match('#Sec-WebSocket-Accept:\s(.*)$#mU', $result, $matches);
        $keyAccept = trim($matches[1]);
        $expectedResonse = base64_encode(pack('H*', sha1($strKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $handshaked = ($keyAccept === $expectedResonse) ? true : false;

        if ($handshaked)
        {
            // connect in namespace
            $this->setNamespace();

            return true;
        }
        fclose($this->objSocket);
        throw new Exception("SocketIO Client not handshaked!");
    }

    private function send()
    {
        fwrite($this->objSocket, $this->hybi10Encode('42/' . $this->namespace. ',["' . $this->event . '",'. json_encode($this->data).']'));
        fflush($this->objSocket);
        \usleep(200000);

        return true;
    }

    private function read()
    {
        $result = '';
        while(($data = fread($this->objSocket, 4096)) !== false) {
            echo $data . "\n";
            $result .= $data;
        }
        return $result;
    }

    public function close()
    {
        fclose($this->objSocket);
    }


    public function emit($event, $data = [])
    {

        $this->event = $event;
        $this->data = $data;
        $success = false;

        while(true) {
            try {
                if ($this->send()) {
                    $success = true;
                } else {
                    do
                    {
                        usleep($this->retryInterval * 1000);
                        $this->maxRetry--;
                        $success = $this->send();
    
                    } while($this->maxRetry > 0 && !$success);
    
                }
            } catch (Exception $e) {
                if($this->maxRetry > 0)
                {
                    continue;
                }
            }
            break;
        }

        return $success;

    }

    private function generateKey($length = 16)
    {
        return base64_encode(openssl_random_pseudo_bytes($length));
    }

    private function hybi10Encode($payload, $type = 'text', $masked = true)
    {
        $frameHead = array();
        $payloadLength = strlen($payload);
        switch ($type) {
            case 'text':
                $frameHead[0] = 129;
                break;
            case 'close':
                $frameHead[0] = 136;
                break;
            case 'ping':
                $frameHead[0] = 137;
                break;
            case 'pong':
                $frameHead[0] = 138;
                break;
        }
        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            if ($frameHead[2] > 127) {
                return false;
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        } else {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }

        $mask = null;
        if ($masked === true) {
            $mask = array();
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = chr(rand(0, 255));
            }
            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }
        return $frame;
    }
}
