<?php
namespace Task;

/**
 * Worker_command class socket se data receive kar ke background tasks manage karti hai.
*/
class Worker_command
{
    private $register = []; // Command registry
    public $minThreads;
    public $key;
    private Pool $Pool;
    public $isclose = false;

    // Command registration
    function __construct()
    {
        $this->register("runTask", $this->runTask(...));
        $this->register("setMinThreads", $this->setMinThreads(...));
    }

    /**
     * Background thread ke andar koi task run karta hai
    */
    public function runTask($sock, $cls, $args)
    {
        $key = $this->key;
        $args  = json_encode($args);
        $path = config::$logPath;
        

        // Task run karne ke liye parallel thread me function
        $id = $this->Pool->run(function () use ($cls, $args, $key, $path) {
            if (!file_exists(__DIR__ . '/config.php')) 
            {
                $fp = fopen($path, 'a');
                if ($fp) {
                    fwrite($fp,  __DIR__ . '/config.php: ' . 'file not found in worker');
                    fclose($fp);
                }
                return;
            }

            require_once(__DIR__ . '/config.php');
           
            if (file_exists(config::$autoloadPath)) require_once(config::$autoloadPath);
            else config::log("Autoload file not found in worker: " . config::$autoloadPath);
            
            $fqcn = "Mscode\\Task\\" . $cls; 
            
            if (class_exists($fqcn)) {
                try {
                    $decodedArgs = json_decode($args, true);
                    $cls = new $fqcn(...$decodedArgs);
                    if (method_exists($cls, 'execute')) {
                        $cls->execute();
                    } else {
                        config::log("[WORKER] Method 'execute' not found in class {$fqcn}");
                    }

                } catch (Exception $e) {
                    config::log("[WORKER] Exception while instantiating {$fqcn}: " . $e->getMessage());
                }
            } else {
                config::log("[WORKER] Class not found: {$fqcn}");
            }

            // task free hone ka message wapas server ko bheja jata hai.
            $arr = [
                'command' => 'freeThread',
                'args' => [$key],
            ];
            $msg = json_encode($arr);
            $len = strlen($msg);
            $packet = pack("N", $len) . pack("a{$len}", $msg);
            $client = socket_create(AF_UNIX, SOCK_STREAM, 0);
            $socketFile = config::$sokPath;

            if (!socket_connect($client, $socketFile)) {
                config::log("[CLIENT] Failed to connect to socket: " . socket_strerror(socket_last_error($client)));
                socket_close($client);
                return false;
            }

            $write = [$client];
            $read = $except = [];

            $selectResult = socket_select($read, $write, $except, 10);

            if ($selectResult === false) {
                config::log("[CLIENT] socket_select() failed: " . socket_strerror(socket_last_error($client)));
                socket_close($client);
                return false;
            } elseif ($selectResult > 0) {
                $written = socket_write($client, $packet);
                if ($written === false) {
                    config::log("[CLIENT] socket_write() failed: " . socket_strerror(socket_last_error($client)));
                    socket_close($client);
                    return false;
                }
            } else {
                config::log("[CLIENT] socket_select() timed out after 10 seconds.");
            }

            socket_close($client);
        });
    }
    
    /**
     *  ye ek command hai jo server minThreads, key, isclose set karta hai.  
    */
    function setMinThreads($sock, $minThreads, $key, $isclose)
    {
        $this->minThreads = $minThreads;
        $this->key = $key;
        $this->Pool = new Pool($minThreads);
        $this->isclose = $isclose;
    }

    /**
     * Socket se aaya hua command find kar ke appropriate handler ko call karta hai.
    */
    public function faindCommand(array $data, $sock)
    {
        if (isset($data['command']) and isset($data['args']) and isset($this->register[$data['command']]) and is_array($data['args'])) {
            $this->register[$data['command']]($sock, ...$data['args']);
        }else
        {
            $this->removeClient($sock);
        }
    }

    // command ko register karta hai.
    public function register(string $command, callable $func)
    {
        $this->register[$command] = $func;
    }

    /**
     * server ko sahi command send karta hai.
    */
    function sendServerCommand($sock, $command, $args)
    {
        $arr = [
            'command' => $command,
            'args' => $args,
        ];

        $msg = json_encode($arr);
        $len = strlen($msg);
        try {
            $packed = pack("N", $len) . pack("a{$len}", $msg);
            $bytesWritten = socket_write($sock, $packed);

            if ($bytesWritten === false) {
                throw new Exception("Socket write failed: " . socket_strerror(socket_last_error($sock)));
            }

            return true;
        } catch (Exception $e) {
            config::log($e->getMessage());
            return false;
        }
    }
}