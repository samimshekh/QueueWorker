<?php
namespace Task;
use \SplObjectStorage;

/**
 * Server_command class workers, threads, aur task execution logic handle karti hai.
*/
class Server_command
{
    protected $register = [];                     // Registered commands list
    protected SplObjectStorage $Worker;           // Worker socket => metadata storage
    protected $maxWorker = 4;                     // Max allowed workers
    protected $noneRegisterWorker = 0;            // Spawned but not yet registered workers
    protected $runQubue = [];                     // Tasks waiting for free thread
    protected $minWorker = 1;                     // Minimum workers required
    protected WorkerRunner $Process;              // WorkerRunner instance
    protected $crtWorker = 0;                     // Currently active workers
    protected $lastTime = 0;                      // Last time worker spawned
    protected $maxThreads = 100;                  // Per worker thread cap
    protected $minThreads = 50;                   // Per worker base threads

    function __construct()
    {
        $this->Worker = new SplObjectStorage();
        $this->Process = new WorkerRunner();
        
        $this->register("registerWorker", $this->registerWorker(...));
        $this->register("freeThread", $this->freeThread(...));
        $this->register("runTask", $this->runTask(...));
    }

    /**
     * Worker thread free hone par uska counter update karta hai.
    */
    public function freeThread($_sock, $key)
    {
        foreach ($this->Worker as $sock) {
            if ($this->Worker[$sock]['key'] == $key) {
                $data = $this->Worker[$sock];
                $data['freeThreads']++;
                $this->Worker[$sock] = $data;
            }
        }
    }
    
    /**
     * Koi bhi task kisi worker ko run karne ke liye assign karta hai.
     * Agar koi worker free nahi hai, to task ko queue me daal deta hai aur naye worker spawn karta hai (agar allowed hai).
    */
    public function runTask($sock, $cls, $args)
    {
        $isrun = false;
        foreach ($this->Worker as $sock) {
            $meta = $this->Worker[$sock];

            if (($this->maxThreads >= $meta['crtThreads']) or ($meta['freeThreads'] != 0)) {
                $this->sendWorkerCommand($sock, 'runTask', [$cls, $args]);
                $this->Worker->detach($sock);

                if ($meta['freeThreads'] != 0) {
                    $meta['freeThreads']--;
                }else{
                    $meta['crtThreads']++;
                }

                $this->Worker->attach($sock, $meta);
                $isrun = true;
                break;
            }
        }
        
        if (!$isrun) {
            $this->runQubue[] = [$cls, $args]; 

            if (($this->maxWorker > $this->crtWorker) and ($this->noneRegisterWorker == 0)) { 
                do {
                    $this->Process->run('php ' . escapeshellarg(__DIR__ . '/worker.php'));
                    $this->lastTime = microtime(true);
                    $this->crtWorker++;
                    $this->noneRegisterWorker++;
                } while (($this->crtWorker < $this->minWorker));
            }
        }

        return $isrun; 
    }
     
    /**
     * Socket se aaya hua command find kar ke appropriate handler ko call karta hai.
    */
    public function faindCommand($sock, array $data)
    {
        if (isset($data['command']) and isset($data['args']) and isset($this->register[$data['command']]) and is_array($data['args'])) {
            $this->register[$data['command']]($sock, ...$data['args']);
        }else config::log("Invalid command structure: " . json_encode($data));
    }

    /**
     * Command register karne ke liye use hota hai (function bindings).
    */
    public function register(string $command, callable $func)
    {
        $this->register[$command] = $func;
    }

    /**
     * Jab naya worker connect hota hai, to uska metadata register kiya jata hai.
    */
    public function registerWorker($sock, ...$args)
    {
        if (!((count($this->Worker) + 1) <= $this->crtWorker)) 
            $this->crtWorker++;

        $key = count($this->Worker);
        $this->Worker[$sock]  = [                                       
            'freeThreads' => $this->minThreads,
            'crtThreads' => $this->minThreads,
            'key' => $key,
        ];
        if ($this->noneRegisterWorker != 0) $this->noneRegisterWorker--;
        $this->lastTime = microtime(true);

        if (($this->minWorker >= $this->crtWorker)) {
            $isClose = false;
        }else{
            $isClose = true;
        }
        while (!$this->sendWorkerCommand($sock, 'setMinThreads', [$this->minThreads, $key, $isClose]))  break;
    }

    /**
     * Worker ko command bhejne ka wrapper.
    */
    public function sendWorkerCommand($sock, $command, $args) : bool
    {
        $data = [
            'command' => $command,
            'args' => $args,
        ];
        return $this->sendCommand($sock, $data);
    }

    /**
     * Socket pe JSON command send karta hai length prefix ke saath.
    */
    function sendCommand($sock, array $arr) : bool
    {
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