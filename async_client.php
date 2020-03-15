<?php
class Client
{
    private $client;
    public function __construct()
    {
        $this->client = new swoole_client(SWOOLE_SOCK_TCP);
    }

    public function connect()
    {
        if (!$this->client->connect("127.0.0.1", 9501, 1)) {
            throw new Exception(sprintf('Swoole Error: %s', $this->client->errCode));
        }
    }
//传输数据
    public function send($data)
    {
        if ($this->client->isConnected()) {

            return $this->client->send(json_encode($data));
        } else {
            throw new Exception('Swoole Server does not connected.');
        }
    }

    public function close()
    {
        $this->client->close();
    }
}

$data = array(
    "params"=>"aaaaaa"
);
$client = new Client();
$client->connect();
if ($client->send($data)) {
    echo 'ok';
} else {
    echo 'fail';
}
$client->close();