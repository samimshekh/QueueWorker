<?php
namespace Task;
/**
 * command class ek UNIX socket client create karti hai jo server ko task commands bhejta hai.
 * Iska use background task ko remote socket server tak bhejne ke liye hota hai.
 */

class Task_command
{
    private $client;        // Socket client instance
    private $ct;             // Connection status flag
    public $error = false;   // Error state flag

    /**
     * Constructor UNIX socket client create karta hai aur server se connect karta hai.
     */
    public function __construct() 
    {
        $this->client = socket_create(AF_UNIX, SOCK_STREAM, 0);
        $socketFile = config::$sokPath;

        // Server se connect karne ki koshish
        if (!($this->ct = socket_connect($this->client, $socketFile))) {
            config::log("[CLIENT] Failed to connect to socket: " . socket_strerror(socket_last_error($client)));
            socket_close($client);
            $this->error = true;
        }
    }

    /**
     * Server ko koi command aur arguments ke saath bhejne ke liye high-level method.
     *
     * @param string $command — Command name jaise "runTask"
     * @param array $args — Arguments list
     */
    public function sendServerCommand($command, $args)
    {
        $data = [
            'command' => $command,
            'args' => $args,
        ];
        return $this->sendCommand($data);
    }

    /**
     * Low-level socket write function jo JSON command ko socket ke through bhejta hai.
     * Retry mechanism 5 baar tak try karta hai agar write fail ho jaye.
     */
    private function sendCommand(array $arr)
    {
        $msg = json_encode($arr);
        $len = strlen($msg);

        for ($i = 0; $i < 5; $i++) {
            $we = @socket_write($this->client, pack("N", $len) . pack("a{$len}", $msg));
            if ($we) break;
            usleep(10000); // Retry delay
        }

        return $we;
    }

    /**
     * Server ko "runTask" command bhejta hai kisi class aur arguments ke saath.
     *
     * @param string $cls — Task class name
     * @param mixed $args — Task arguments
     */
    function runTask($cls, $args) 
    {
        if (!$this->ct) return false;
        return $this->sendServerCommand("runTask", [$cls, $args]);
    }

    /**
     * Client socket ko close karta hai.
     */
    function close() 
    {
        socket_close($this->client);
    }
}