<?php
/**
 * @author ueaner <ueaner@gmail.com>
 */

if (!function_exists('data_segment')) {
    /**
     * 数据切分
     *
     * @example 将需要处理的数据平分到多个 worker 进程处理
     *
     *  // 1. 通过一个「数据范围」获取当前 worker 应处理的子数据范围
     *  // 如将 uid 从 1w 到 2w 的用户均分到多个 worker 处理
     *  $startUid = 10000;
     *  $endUid = 20000;
     *  list($start, $end, $number) = data_segment($worker->id, $worker->count, $startUid, $endUid);
     *
     *  // 2. 通过「总数」获取当前 worker 应处理的数据范围，是 [1, $total] 的简写
     *  // 如将 1w 个用户均分到多个 worker 处理
     *  $total = 10000;
     *  list($start, $end, $number) = data_segment($worker->id, $worker->count, $total);
     *
     *  // 处理当前 worker 领取到的 uid 范围：[$start, $end]
     *  for ($uid = $start; $uid <= $end; $uid++) {
     *      // do something ...
     *  }
     *
     * @param int $id           当前 worker id 范围是：[0, $worker->count - 1]
     * @param int $count        worker 进程总数
     * @param int $totalOrStart 需要处理的数据总条数，或数据的起始值
     * @param int $end          需要处理的数据结束值，大于 0 时，$totalOrStart 被认为是数据的起始值
     * @return array [$start 数据起始值, $end 数据结束值, $number 条数]
     */
    function data_segment($id, $count, $totalOrStart, $end = 0)
    {
        if ($end) {
            $fixedStart = $totalOrStart > 0 ? $totalOrStart - 1 : $totalOrStart;
            $offset = $fixedStart;
            $total = $end - $fixedStart;
        } else {
            $offset = 0;
            $total = $totalOrStart;
        }

        // 每个进程平均处理多少条数据
        $number = intval($total / $count);

        // 余数：是否以整数个均分到每个进程
        $remain = $total % $count;

        // 有余，将多余的数据分到前 [0, $remain - 1] 个 worker，$worker->id 从 0 开始
        if ($remain) {
            if ($id >= $remain) {
                $start = $id * $number + $remain;
            } else {
                $number++;
                $start = $id * $number;
            }
        } else {
            // 刚好可以平分在各个 worker 中
            $start = $id * $number;
        }

        $end = $start + $number;
        $start++;

        return [$start + $offset, $end + $offset, $number];
    }
}
