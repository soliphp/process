<?php

use Soli\Process;

include __DIR__ . "/../vendor/autoload.php";

// 把 [10, 23] 这个数据范围平分到 4 个进程处理
$startUid = 10;
$endUid = 23;
$count = 4;

$proc = new Process();
$proc->name = 'soli process 03';
$proc->count = $count;
$proc->daemonize = 0;
// 进程退出不自动补充新的进程
$proc->refork = false;

$proc->setJob(function ($worker) use ($startUid, $endUid) {
    // 获取当前 worker 应处理的数据范围
    list($start, $end, $number) = data_segment($worker->id, $worker->count, $startUid, $endUid);

    $uids = [];

    for ($uid = $start; $uid <= $end; $uid++) {
        $uids[] = $uid;
    }

    echo "worker[{$worker->id}]: " . implode(',', $uids) . PHP_EOL;
});

$proc->start();
