<?php
namespace Task;

/**
 * config class background task server ke liye configuration values define karti hai.
 * Is class ka use statically hota hai jahan se socket path, worker limits, logging path,
 * aur autoloading path jaise settings access ki ja sakti hain.
 */
class config
{
    /**
     * @var string
     * Unix domain socket ka path — is path par server socket create karega.
     */
    public static $sokPath = '/tmp/php_socket.sock';

    /**
     * @var string
     * Har worker ke andar minimum threads ka count (parallel execution ke liye).
     */
    public static $parWorkerminThreads = 50;

    /**
     * @var string
     * Har worker ke andar maximum threads ka limit.
     */
    public static $parWorkermaxThreads = 100;

    /**
     * @var string
     * Server ke andar chalne wale minimum worker process ka number.
     */
    public static $minWorker = 1;

    /**
     * @var string
     * Server ke andar allowed maximum worker process ka number.
     */
    public static $maxWorker = 3;

    /**
     * @var string
     * Socket backlog — kitne clients queue me wait kar sakte hain jab tak accept() na ho.
     */
    public static $backlog = 10000;

    /**
     * @var string
     * Log file ka path jahan system messages aur errors write honge.
     */
    public static $logPath = __DIR__ . '/../taskError.log';

    /**
     * @var string
     * Autoload path — jahan se PHP classes ko include/load kiya ja sakta hai.
     */
    public static $autoloadPath = __DIR__ . '/../vendor/autoload.php';

    /**
     * Diya gaya message log file me append karta hai.
     *
     * @param string $message — Log message jo file me likhna hai.
     */
    public static function log(string $message)
    {
        file_put_contents(config::$logPath, $message, FILE_APPEND);
    }
}
