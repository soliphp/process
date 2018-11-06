Soli Process
------------

基于 pcntl 和 posix 扩展，简单的 PHP 多进程管理类库。

## 依赖

- Unix-like
- PHP 5.0+
- ext-pcntl
- ext-posix

`注：信号处理部分需要 PHP 7.1+`


## 安装

使用 `composer` 进行安装：

    composer require soliphp/process


## 快速使用

    <?php

    use Soli\Process;

    include __DIR__ . "/vendor/autoload.php";

    $job = function ($worker) {
        while (1) {
            echo "Hello world, master pid: {$worker->masterPid}, worker pid: {$worker->workerPid}, worker id: {$worker->id}\n";
            sleep(1);
        }
    };

    $proc = new Process();
    $proc->name = 'soli process test';
    $proc->count = 2;
    $proc->daemonize = 0;
    $proc->logFile = '/tmp/soli_process.log';

    $proc->setJob($job);

    $proc->start();


## 进程结构

`Soli\Process` 使用 Master-Worker 进程模型：

                [ master ]
                /   |   \
             /      v      \
    [Worker1]   [Worker2]   [WorkerN]

master 进程 fork worker 进程，在 PHP 7.1+ 下 master 进程还会接收终止信号传递给 worker 进程执行退出。

## 自动补充退出的进程

如程序出现异常或其他情况导致进程退出，默认会自动补充进程，且保持对应的 $worker->id 不变。

如果不想自动补充退出的进程，可以设置 `$process->refork = false;`


## 函数列表

### setJob

设置 worker 进程需要执行的任务。

    Process->setJob(callable $job)

关于 callable 类型参见[官方文档]。

如这里使用匿名函数定义 job，输出一条信息退出进程：

    $proc = new Process();

    $proc->setJob(function ($worker) {
        echo "Hello world, master pid: {$worker->masterPid}, worker pid: {$worker->workerPid}, worker id: {$worker->id}\n";
    });

`在 job 回调参数中会返回当前 worker 进程的 $worker 实例`，通过这个 $worker 实例可以获取以下[属性列表]中的中的属性。

### start

启动进程，将根据设置的 `count` 启动相应个 worker 进程。

    Process->start()


## 属性列表

### id

当前 worker 进程的编号，编号范围：[1, $worker->count]。

`可在 job 回调中使用`。

### masterPid

当前 master 进程 PID。

`可在 job 回调中使用`。

### workerPid

当前 worker 进程 PID。

`可在 job 回调中使用`。

### name

当前 master/worker 进程的名称，只支持 Linux。

执行设置进程名称时：

master 进程会自动在其后追加 master 文本，最终的进程名称为 `$name master`；

worker 进程会自动在其后追加 worker 文本，最终的进程名称为 `$name worker`。

### count

设置启动的 worker 进程数，默认为 1

### daemonize

是否将程序作为守护进程运行。

启用守护进程后，标准输出和错误会被重定向到 `logFile`；

如果未设置 `logFile`，将重定向到 `/dev/null`，所有打印到屏幕的信息都会被丢弃。

### logFile

运行期发生的异常信息、进程的终止信息等会记录到这个文件；

启用守护进程后，所有打印到屏幕的信息也都会写入到这个文件。

### refork

worker 进程退出后是否自动补充新的进程，`默认为 true，自动补充新的进程`。

如果不想自动补充退出的进程，请可以设置 `refork` 为 `false;`


## 信号处理

信号处理部分使用了 [pcntl_async_signals] 需要 `PHP 7.1+` 支持。

master 进程支持接收 `SIGINT`, `SIGTERM` 终止信号，master 进程接收到终止信号后向所有 worker
进程发送相应的终止信号，worker 进程接收到终止信号后执行退出操作。

我们可以通过 `kill` 命令发送信号：

    kill -SIGINT <master pid>
    kill -SIGTERM <master pid>

也可以直接通过信号所对应的数字参数：

    kill -2 <master pid>
    kill -15 <master pid>

`SIGINT` 即为在终端按下的 `CTRL + C`。

注：如果使用 `kill -9` 即 `kill -SIGKILL` 对 master
进程发送终止信号，master 进程会立即终止，并不会再向 worker
进程发送终止信号，所以如果是要通过 master 进程终止所有 worker 进程，建议使用
`kill -15` 终止所有 worker 进程。


低于 PHP 7.1 可以使用以下命令终止所有 worker 进程，注意替换为你的 "process name"：

    ps aux | grep "process name" | grep -v grep | awk '{ print $2 }' | xargs kill -9


## License

MIT Public License


[pcntl_async_signals]: http://php.net/pcntl_async_signals
[官方文档]: http://php.net/callable
[属性列表]: #属性列表
