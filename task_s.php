<?php
require_once "core/Server.php";
use Task\Server;

class task_s extends Server
{
    function start() : void
    {
        echo "[*] run server.\n";
        $this->run();
    }
}

$sv  = new task_s();
$sv->start();