# PHP Socket-Based Worker Task System

Yeh project ek high-performance aur scalable task processing system hai jo PHP me banaya gaya hai. System UNIX socket ka use karta hai fast IPC ke liye, aur background me worker processes ko dynamically run karta hai jo tasks ko asynchronously execute karte hain.

---

## Features

- **UNIX Socket Server** – Secure aur efficient inter-process communication ke liye
- **Dynamic Worker Management** – Min/Max workers aur threads ka control
- **Asynchronous Task Dispatching** – Socket clients ke through task bhejna
- **Non-blocking Background Execution** – Linux system me workers ko silently run karna
- **Log System** – Har activity aur error ka log
- **Fully Configurable** – Sab path aur limits config file se control hoti hain
---
### Disclaimer

Yeh system **high-performance architecture** ke concepts dikhane ke liye design kiya gaya hai, jisme multi-threaded workers, UNIX socket server, task queue, aur dynamic class execution jaise advanced features ka istemal hota hai.

**Lekin:**

> **Yeh ek production-ready system nahi hai.**
> Iska primary purpose sirf **learning aur interview demonstration** ke liye hai.

Main **samim shekh**, is project ka original author hoon. Aap ise explore, study aur customize kar sakte hain — lekin production use ke liye testing, error handling aur security improvements karna zaroori hai.
---
## Requirements

- PHP >= 8.1 (ZTS build recommended for threads)
- Linux system (UNIX socket + nohup support)
- Required Extensions:
  - `sockets`
  - `pcntl`
  - `posix`
  - `parallel`
---
## Installation
```bash
# PHP dependencies install karein
sudo apt install php php-cli php-sockets php-pcntl php-posix

# parallel extension
pecl install parallel 
```
## setup 
```bash
https://github.com/samimshekh/QueueWorker.git
cd task-system
composer install
```
Agar composer nahi hai to pehle https://getcomposer.org se install karo.
```bash
run cli: php ./task_s.php #start server
alag tab me run cli:  php task_c.php #run Test task
```

## Configuration (core/config.php)

Is file me system ke sabhi important parameters define hote hain jo socket path, worker limits, thread settings, log file aur autoload path ke liye use hote hain.

Aap is file ko customize karke apne server environment ke hisaab se configure kar sakte hain.


## Run Task Example (task_c.php)

Is system me client side se ek ya zyada background tasks ko UNIX socket ke zariye server ko bhejne ke liye `Task` class ka use hota hai.

### File Include & Namespace

Sabse pehle aapko `Task` class ko include karna hoga aur `use` karna hoga:
```php
require_once "core/Task.php";
use Task\Task;
```

Jab aap `new Task('Test', ["test1"])` likhte hain, to iske do parameters hote hain:

```php
new Task('ClassName', [ConstructorArgs]);
```

Yeh worker task ka naam hota hai, jaise: "Test", "EmailSender", "BackupWorker" etc.
Yeh class dynamically load hoti hai path se jo config::$autoloadPath me define hota hai. PSR-4 internally usi path me given class name wali file (jaise Test.php) ko dhundhta hai aur load karta hai. 
---

### **Example Code**

```php
require_once "core/Task.php"; 
use Task\Task;

new Task('Test', ["test$i"]);
```
Bas itna likhne se hi task backend me run ho jata hai.
---
### **Run Server (task_s.php)**

```php
require_once "core/Server.php"; 
use Task\Server;

class task_s extends Server
{
    public function start(): void
    {
        echo "[*] run server.\n";
        $this->run(); // socket server start hota hai
    }
}

$sv = new task_s();
$sv->start();
```

**Note:**

* `Server` ek abstract class hai jisme `public function start(): void` method ko aapko implement karna padta hai.
* `start()` ke andar jab aap `$this->run();` call karte hain, to **ek UNIX socket server background me run ho jata hai**.
* `run()` method internally pura system manage karta hai: Task queueing, Worker management, Process spawning, Thread queuing, Worker communication

Aapko sirf `run()` call karna hota hai—baaki sab kuch backend me handle ho jata hai.
---
### **Make Task (Task/Test.php)**

```php
namespace Mscode\Task;
use Mscode\Task\BaseTask;

class Test extends BaseTask
{
    public $arg;

    /**
     * Task ka argument initialize karta hai. Jo arguments aap `Task` constructor me pass karte hain,
     * woh isi constructor me $arg ke roop me milte hain — jaise: new Task("Test", [1, 2]) ya multiple values to Test me public function __construct($arg1, $arg2) is tarah se array ke index ya name ke through.
    */
    public function __construct($arg)
    {
        $this->arg = $arg;
    }

    /**
     * Ye method task ko execute karta hai.
    */
    public function execute(): void
    {
        $this->echo("samim sk execute test 12 : {$this->arg}");
        sleep(15); // simulate heavy task
    }
}
```

**Important Points:**

* Har task ko `BaseTask` class extend karna zaruri hai.
* `execute()` method ko implement karna mandatory hai—worker isi method ko background me call karta hai.
* Agar `execute()` define nahi hua, ya fir class `autoload` path se nahi mili to error aayega.
* Runtime errors, task class missing errors, ya koi bhi worker/server ke internal exceptions ko log kiya jata hai is file me:

```php
public static $logPath = __DIR__ . '/../taskError.log';
```

* Saare errors handle karne ke liye `config::log(string $message)` method use hota hai—ye system-wide error logging mechanism hai.

---
### Debug & Logging System (Task/BaseTask.php)

Task environment me aap **CLI output (echo, print\_r, var\_dump)** directly use nahi kar sakte, kyunki ye background threads/processes me chalte hain. Isliye, debugging ke liye aapko data **log file** me likhna hota hai.

Is purpose ke liye `BaseTask` class me kuch useful helper methods diye gaye hain:

#### Available Logging Methods:  
#### **echo($data)**: Text message ko log file me likhta hai (like `echo`)
#### **print_r($data)**: `print_r()` ka output log file me save karta hai 
#### **var_dump($data)**: `var_dump()` ka output log file me save karta hai  
#### **clear_log($data)**: Log file ko empty kar deta hai (purana data hata deta hai)

#### Default Log File:

```php
protected string $logFile = __DIR__ . '/task_output.log';
```

Aap har ek task ke liye **alag log file** bhi set kar sakte ho — is property ko subclass me override karke.

#### Best Practice:

* Har Task class me `try { ... } catch()` block ka use karein aur error ko log karein.
* Task-specific log file use karna long-term debugging me madad karta hai.
* `BaseTask` ke methods ka use karna memory-safe aur efficient hai.
---

Jo log PSR-4 ke bare me jante hain, unko ye batana zaroori nahi hota ki Task classes ko aap kisi bhi folder structure me rakh sakte hain — bas aapko `config::$autoloadPath` me correct `vendor/autoload.php` ka path set karna hota hai.

---

### Worker Thread & Process Configuration

`config::parWorkerminThreads` aur `config::parWorkermaxThreads` ko aapko apne project aur system ke resource ke hisaab se set karna hota hai.

* Agar aapke task **CPU par load kam daalte hain**, lekin **execution time zyada lete hain** (jaise background APIs, network jobs), to threads ka number zyada rakhna behtar hota hai. Isse tasks queue me wait nahi karenge.

* `config::minWorker` ko **bahut dhyan se** set karna chahiye, kyunki:

  * Ye itne worker processes define karta hai jo **system me hamesha active rahenge** — chahe task ho ya nahi.
  * Agar minWorker se jada worker run horaha hai to 1 minute tak idle rehta hai (task nahi milta), to **auto-exit** ho jata hai jo minWorker se jada run horaha tha **system me minWorker hamesha active rahenge**.
  * Isliye, `minWorker = 1` rakhna ek achha default hai.

* `config::maxWorker` aapke **target client load** ke hisaab se set hota hai.

  * Jitne concurrent tasks aap handle karna chahte ho, aur
  * Jitna system ka capacity allow karta hai,
  * Uske hisaab se maximum worker limit define karein.


Yeh configuration system ko efficient aur scalable banata hai — bina kisi unnecessary resource waste ke.

