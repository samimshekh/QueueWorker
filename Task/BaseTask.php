<?php
namespace Mscode\Task;

abstract class BaseTask
{
    // Abstract method: subclass ko define karna hoga
    abstract public function execute() : void;

    // File path jahan data save hoga
    protected string $logFile = __DIR__ . '/task_output.log';

    // Log data in file (like echo)
    public function echo(string $data) : void
    {
        file_put_contents($this->logFile, $data, FILE_APPEND);
    }

    // Print_r output to file
    public function print_r(mixed $data) : void
    {
        $entry = print_r($data, true);
        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }

    // var_dump output to file
    public function var_dump(mixed $data) : void
    {
        ob_start();
        var_dump($data);
        $output = ob_get_clean();
        file_put_contents($this->logFile, $output, FILE_APPEND);
    }

     /**
     * Log file ko clear (empty) karta hai.
     * Ye method file me se purana data hata deta hai.
     */
    public function clear_Log() : void
    {
        file_put_contents($this->logFile, '');
    }
}
