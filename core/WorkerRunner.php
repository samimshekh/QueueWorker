<?php
namespace Task;

/**
 * WorkerRunner class: Linux system me non-blocking background process run karne ke liye.
 * Command nohup ke sath silently aur asynchronously run hoti hai.
 */
class WorkerRunner
{
    /**
     * Non-blocking background process run karta hai (fire-and-forget).
     *
     * @param string $command Jo bhi system command aapko run karna hai (e.g. php worker.php)
     */
    public static function run(string $command): void
    {
        try {
            // nohup: detach terminal
            // > /dev/null 2>&1 &: silent background
            $cmd = "nohup {$command} > /dev/null 2>&1 &";

            // popen: fire-and-forget non-blocking
            pclose(popen($cmd, 'r'));
        } catch (\Throwable $e) {
            config::log("Command execution error: " . $e->getMessage());
        }
    }
}