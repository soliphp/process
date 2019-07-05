<?php
/**
 * @author ueaner <ueaner@gmail.com>
 */
namespace Soli;

class Process
{
    public $id = 0;

    public $masterPid = null;

    public $workerPid = null;

    public $name = null;

    public $count = 1;

    public $daemonize = false;

    public $logFile = null;

    // worker 进程退出后是否自动补充新的进程
    public $refork = true;

    protected $job = null;

    protected static $isMaster = true;

    protected static $workers = [];

    protected static $shutdown = false;

    public function setJob(callable $job)
    {
        $this->job = $job;
    }

    public function start()
    {
        if ($this->daemonize) {
            $this->daemonize();
        }

        $this->setupSignalHandlers();

        $this->masterPid = posix_getpid();
        $this->setProcName();

        $this->log("\"{$this->name}\" started");

        for ($id = 0; $id < $this->count; $id++) {
            $this->forkWorker($id);
        }

        $this->waitAll();

        $this->log("\"{$this->name}\" stopped");
    }

    protected function forkWorker($id)
    {
        $pid = $this->fork();

        if ($pid > 0) {
            static::$workers[$id] = $pid;
        } else {
            static::$isMaster = false;
            $this->id = $id;
            $this->workerPid = posix_getpid();
            $this->setProcName();

            // Run worker.
            $this->run();
            exit(0);
        }
    }

    protected function daemonize()
    {
        umask(0);

        if ($this->fork()) {
            exit(0);
        }

        $this->setsid();

        if ($this->fork()) {
            exit(0);
        }

        chdir('/');

        $this->redirectStd();
    }

    protected function fork()
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException('fork() failed');
        }
        return $pid;
    }

    protected function setsid()
    {
        $sid = posix_setsid();
        if ($sid === -1) {
            throw new \RuntimeException('setsid() failed');
        }
        return $sid;
    }

    protected function redirectStd()
    {
        $logFile = $this->logFile ?: '/dev/null';

        // redirect stdin to /dev/null
        // redirect stdout, stderr to $logFile
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        global $stdin, $stdout, $stderr;
        $stdin = fopen('/dev/null', 'r');
        $stdout = fopen($logFile, 'a');
        $stderr = fopen($logFile, 'a');
    }

    protected function waitAll()
    {
        while (1) {
            $pid = pcntl_wait($status, WUNTRACED);
            if ($pid > 0) {
                $id = array_search($pid, static::$workers);
                if ($id === false) {
                    continue;
                }
                $this->log("[worker:$id $pid] process stopped with status $status");
                unset(static::$workers[$id]);

                if ($this->refork && !static::$shutdown) {
                    // refork
                    $this->forkWorker($id);
                }
            }

            if (empty(static::$workers) && (static::$shutdown || $this->refork == false)) {
                break;
            }
        }
    }

    protected function setProcName()
    {
        if ($this->name && PHP_OS === 'Linux') {
            if (static::$isMaster) {
                cli_set_process_title($this->name . ' master');
            } else {
                cli_set_process_title($this->name . ' worker');
            }
        }
    }

    protected function run()
    {
        srand();
        mt_srand();

        register_shutdown_function([$this, 'handleFatalError']);

        try {
            $this->log("process started");
            // Run job.
            call_user_func($this->job, $this);
        } catch (\Exception $e) {
            $this->log($e);
            exit(250);
        } catch (\Throwable $e) {
            $this->log($e);
            exit(250);
        }
    }

    public function handleFatalError()
    {
        $errmsg = "process terminated";
        // Handle last error.
        $error = error_get_last();
        if ($error) {
            $errmsg .= " with error: {$error['type']} \"{$error['message']} in {$error['file']} on line {$error['line']}\"";
        }

        $this->log($errmsg);
    }

    public function log($contents)
    {
        $time = gettimeofday();
        $usec = str_pad($time['usec'], 6, '0');

        $workerInfo = static::$isMaster ? '' : "[worker:{$this->id} {$this->workerPid}] ";
        $contents = sprintf("[%s.%s] [master %s] %s%s\n", date('Y-m-d H:i:s', $time['sec']), $usec, $this->masterPid, $workerInfo, $contents);

        if (!$this->daemonize) {
            echo $contents;
        }

        if ($this->logFile) {
            file_put_contents($this->logFile, $contents, FILE_APPEND | LOCK_EX);
        }
    }

    protected function setupSignalHandlers()
    {
        if (!function_exists('pcntl_async_signals')) {
            return false;
        }

        // PHP 7.1+
        pcntl_async_signals(true);

        pcntl_signal(SIGINT, [$this, 'signalShutdownHandler'], false);
        pcntl_signal(SIGTERM, [$this, 'signalShutdownHandler'], false);
    }

    public function signalShutdownHandler($signo)
    {
        switch ($signo) {
            case SIGINT:
                $msg = "Received SIGINT scheduling shutdown...";
                break;
            case SIGTERM:
                $msg = "Received SIGTERM scheduling shutdown...";
                break;
            default:
                $msg = "Received shutdown signal, scheduling shutdown...";
                break;
        };

        if (static::$isMaster) {
            static::$shutdown = true;
            $this->log($msg);

            // Send stop signal to all worker processes.
            foreach (static::$workers as $id => $workerPid) {
                posix_kill($workerPid, $signo);
            }
        } else {
            $this->log($msg);

            // Worker process exit.
            exit(0);
        }
    }
}
