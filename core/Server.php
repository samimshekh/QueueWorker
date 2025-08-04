<?php
namespace Task;
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/WorkerRunner.php";
require_once __DIR__ . "/Server_command.php";

use Task\config;
use Task\WorkerRunner;
use Task\Server_command;

/**
 * Server class socket server initialize karta hai aur command class extend karta hai.
*/
abstract class Server extends Server_command
{
    public $maxThreads;
    public $minThreads;
    public $isr = false;
    abstract public function start(): void;

    /** @var Socket */
    private $server;

    /** @var Socket[] */
    private array $clients = [];

    /**
     * Server initialize karta hai, socket create, bind, listen, non-block set, aur parent init.
    */
    public function __construct() 
    {
        $this->maxThreads = config::$parWorkermaxThreads;
        $this->minThreads = config::$parWorkerminThreads;
        $socketFile = config::$sokPath;

        if (file_exists($socketFile)) {
            unlink($socketFile);
        }
        try {
            $this->server = socket_create(AF_UNIX, SOCK_STREAM, 0);
            if ($this->server === false) {
                throw new Exception("Socket create failed: " . socket_strerror(socket_last_error()));
            }

            if (!socket_bind($this->server, $socketFile)) {
                throw new Exception("Socket bind failed: " . socket_strerror(socket_last_error($this->server)));
            }

            if (!socket_listen($this->server, 500)) {
                throw new Exception("Socket listen failed: " . socket_strerror(socket_last_error($this->server)));
            }

            if (!socket_set_nonblock($this->server)) {
                throw new Exception("Set non-blocking failed: " . socket_strerror(socket_last_error($this->server)));
            }

            parent::__construct();
        } catch (Exception $e) {
            config::log("Server initialization error: " . $e->getMessage());
            exit(1); // optional: stop execution
        }

    }

    /**
     * Koi client disconnect ho jaye to usko remove karta hai.
    */
    function removeClient($sock)
    {
        foreach ($this->clients as $key => $value) {
            if ($sock === $value) 
                unset($this->clients[$key]);
        }

        if ($this->Worker->contains($sock)) {
            $this->Worker->detach($sock);
            $this->crtWorker--;
        }
        socket_close($sock);
    }

    /**
     * Main event loop — continuously connections accept karta hai,
     * data read karta hai, aur tasks dispatch karta hai.
    */
    final public function run()
    {
        $this->lastTime = microtime(true);

        while (true) {
            if (($this->noneRegisterWorker != 0))
            {
                if (((microtime(true) - $this->lastTime) > 60)) {
                    $this->crtWorker -= $this->noneRegisterWorker;
                    $this->noneRegisterWorker = 0;
                }
            }

            if (!empty($this->runQubue)) {
                foreach ($this->runQubue as $key => $args) {
                    if (!$this->runTask(null, ...$args))
                    {
                        unset($this->runQubue[$key]);
                        break;
                    } 
                    unset($this->runQubue[$key]);
                }
            }

            // Socket events
            $read = [$this->server, ...$this->clients];
            $w = null;
            $e = null;
            if (socket_select($read, $w, $e, 0, 0))
            {
                foreach ($read as $key => $sock) {
                    if ($sock===$this->server) {
                        $new = socket_accept($this->server);
                        if ($new === false) continue;
                        socket_set_nonblock($new);
                        $this->clients[] = $new;
                    }else
                    {
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
                                    $this->faindCommand($sock, $arr);
                                }
                            }
                        }
                    }
                }
            }else
            {
                usleep(1000);
            }
        }
        socket_close($this->server);
    }
}