<?php
/**
 * @author ueaner <ueaner@gmail.com>
 */

if (!interface_exists('Throwable') && !class_exists('Throwable')) {
    class Throwable extends Exception
    {
    }
}

if (!function_exists('data_segment')) {
    /**
     * 数据切分
     *
     * @example 将需要处理的总数据条数平分到多个 worker 进程处理
     *
     *  // 获取当前 worker 应处理的数据范围
     *  list($start, $end, $number) = data_segment($worker->id, $worker->count, $total);
     *
     *  for ($uid = $start; $uid <= $end; $uid++) {
     *      // do something ...
     *  }
     *
     * @param int $id 当前 worker id
     * @param int $count worker 进程总数
     * @param int $total 需要处理的数据总条数
     * @return array [$start, $end, $number]
     */
    function data_segment($id, $count, $total)
    {
        // 每个进程平均处理多少条数据
        $number = intval($total / $count);

        // 是否以整数个均分到每个进程
        $remain = $total % $count;

        // 有余，将多余的数据分到前 [1, $remain] 个 worker，$worker->id 从 1 开始
        if ($remain) {
            if ($id > $remain) {
                // worker id 大于余数
                $start = ($id - 1) * $number + $remain;
            } else {
                // worker id 在余数之内
                $number++;
                $start = ($id - 1) * $number;
            }
        } else {
            // 刚好可以平分在各个 worker 中
            $start = ($id - 1) * $number;
        }

        $end = $start + $number;
        $start++;

        return [$start, $end, $number];
    }
}
