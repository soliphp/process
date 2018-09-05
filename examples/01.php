<?php

use Soli\Process;

include __DIR__ . "/../vendor/autoload.php";

$job = function ($worker) {
    while (1) {
        echo "Hello world, master pid: {$worker->masterPid}, worker pid: {$worker->workerPid}, worker id: {$worker->id}\n";
        sleep(1);
    }
};

$proc = new Process();
$proc->name = 'soli process 01';
$proc->count = 2;
$proc->daemonize = 0;
// 启用守护进程后，所有打印到屏幕的信息将会写入到 logFile 文件
$proc->logFile = '/tmp/soli_process.log';

$proc->setJob($job);

$proc->start();
