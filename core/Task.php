<?php
namespace Task;
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/Task_command.php";
use Task\config;
use Task\Task_command;

/**
 * Task class ek wrapper hai jo high-level task runner ke roop me kaam karti hai.
 */
class Task extends Task_command
{

    /**
     *  ye this->run ko call karta hai. 
     * 
     * @param string $cls — Task class
     * @param mixed $args — Task args
    */
    public function __construct(string $cls, array $args = [])
    {
        $this->run($cls, $args);
    }

    /**
     * Server tak task bhejne ke liye simplified interface.
     * Har task ke liye naya command client create karta hai.
     *
     * @param string $cls — Task class
     * @param mixed $args — Task args
     */
    private function run($cls, $args) 
    {
        $command = new Task_command(); 
        if ($command->runTask($cls, $args))
            $command->close();
        else
            return false;

        return true;
    }
}