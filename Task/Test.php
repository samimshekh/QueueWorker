<?php
namespace Mscode\Task;
use Mscode\Task\BaseTask;

class Test extends BaseTask
{
    public $arg;
    /**
     * task var initialize karta hai.
    */
    function __construct($arg)
    {
        $this->arg = $arg;
    }

    /**
     * task ko run karta hai. 
    */
    public function execute(): void
    {
        $this->echo("samim sk execute test 12 : {$this->arg}");
        sleep(15);
    }
}