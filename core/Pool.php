<?php
namespace Task;
use \parallel\Runtime;

/**
 * Pool class threads (Runtime objects) manage karta hai jo parallel tasks ko run karte hain.
*/
class Pool 
{
    private $minThreads;         // Minimum required threads
    public $crtThreads;          // Current active threads count
    private $runtimes = [];      // Runtime threads array
    private $futures = [];       // Futures holding async results

    function __construct(int $minThreads)
    {
        $this->minThreads = config::$parWorkerminThreads;
        $this->crtThreads = config::$parWorkerminThreads;
        for ($i = 0; $i < $this->minThreads; $i++) {
            $this->runtimes[$i] = new Runtime();
        }
    }

    // Check karta hai ki future complete ho gaya ya nahi
    function done(int $id): bool 
    {
        return isset($this->futures[$id]) && $this->futures[$id]->done();
    }

    /**
     * Naya task thread me run karta hai agar slot available ho
    */
    function run($func) : bool|int
    {
        $key = count($this->futures);
        if (($key < $this->minThreads)) {
            $this->futures[$key] = $this->runtimes[$key]->run($func);
            return $key;
        }

        foreach ($this->futures as $key => $value) {
            if ($this->done($key))
            {
                $this->futures[$key] = $this->runtimes[$key]->run($func);
                return $key;
            }
        }

        $key++;
        $this->runtimes[$key] = new Runtime();
        $this->futures[$key] = $this->runtimes[$key]->run($func);
        return $key;
    }

    // Task result return karta hai agar complete ho gaya ho
    function getResult(int $id): mixed
    {
        if (isset($this->futures[$id]) && $this->futures[$id]->done()) {
            return $this->futures[$id]->value();
        }
        return null;
    }

    // Wait kar ke result return karta hai
    function getResultForWait(int $id): mixed
    {
        if (isset($this->futures[$id])) {
            return $this->futures[$id]->value();
        }
        return null;
    }

    // Sare tasks ke result ek saath return karta hai
    function waitAll(): array
    {
        $results = [];
        foreach ($this->futures as $id => $future) {
            if ($future instanceof Future) {
                $results[$id] = $future->value();
            }
        }
        return $results;
    }
}