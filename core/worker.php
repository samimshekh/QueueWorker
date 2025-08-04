<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/Pool.php";
require_once __DIR__ . "/Worker_command.php";
use Task\config;
use Task\Pool;
use Task\Worker_command;

/**
 * Worker class ek socket client banata hai aur server se continuously instructions receive karta hai.
*/
class Worker extends Worker_command
{
    /** @var Socket[] */
    public Socket $client;

    // Worker initialize karta hai aur server se connect karta hai. 
    public function __construct() 
    {
        $this->client = socket_create(AF_UNIX, SOCK_STREAM, 0);
        $socketFile = config::$sokPath;

        if (!socket_connect($this->client, $socketFile)) {
            $errorCode = socket_last_error($this->client);
            $errorMessage = socket_strerror($errorCode);
            config::log("Worker connection failed: [{$errorCode}] {$errorMessage}");
            exit;
        }
        
        socket_set_nonblock($this->client);
        parent::__construct();
    }

    /**
     * server disconnect ho jaye to usko socket close karta hai aur exit karta hai.  
    */
    function removeClient($sock) : void
    {
        socket_close($sock);
        exit;
    }
    
    /**
     * Worker main loop — server se commands receive karta hai aur process karta hai.
    */
    final public function run() : void
    {
        $this->sendServerCommand($this->client, 'registerWorker', []);
        $limitInSeconds = 60;
        $executionTimeCheck = false;
        $executionTime = 0;
        $start = microtime(true);
        while (true) {
            if ($this->isclose == true)
            {
                if (($executionTime > $limitInSeconds) and ($executionTimeCheck)) 
                {
                    socket_close($this->client);
                    exit;
                }
            }

            $executionTimeCheck = true;
            $read = [$this->client];
            $w = null;
            $e = null;
            if (socket_select($read, $w, $e, 0, 0))
            {
                $executionTimeCheck = false;
                foreach ($read as $key => $sock) {
                    $buffer = @socket_read($sock, 4);
                    if ($buffer === '' or $buffer === false)  {
                        $this->removeClient($sock);
                    }else{
                        if (strlen($buffer) !== 4 )
                        {
                            $this->removeClient($sock);
                        }else{
                            $len = unpack("Nlen", $buffer)['len'] ;
                            $data = socket_read($sock, $len);
                            $arr = json_decode($data, true);
                            if ($arr === null) {
                                $this->removeClient($sock);
                            }else
                            {
                                $this->faindCommand($arr, $sock);
                            }
                        }
                    }
                }
            }else usleep(100);
            if ($executionTimeCheck) $executionTime = microtime(true) - $start;
            else $start = microtime(true);
        }
        socket_close($this->client);
    }
}

// Start worker
$server = new Worker();
$server->run();