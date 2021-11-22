<?php
function _qsort_partition(\SplFixedArray $arr, int $bottom, int $top, callable $compare_func): int {
    $m = $arr[(int)(($bottom + $top) / 2)];
    $l = $bottom - 1;
    $r = $top + 1;

    while (true) {
        do {
            $l++;
        } while ($l <= $top && $compare_func($arr[$l], $m));

        do {
            $r--;
        } while ($r >= $bottom && $compare_func($m, $arr[$r]));

        if ($l >= $r) {
            return $r;
        }

        $tmp = $arr[$r];
        $arr[$r] = $arr[$l];
        $arr[$l] = $tmp;
    }
}

function _qsort(\SplFixedArray $arr, int $bottom, int $top, callable $compare_func): void {
    if ($bottom < $top) {
        $j = _qsort_partition($arr, $bottom, $top, $compare_func);
        _qsort($arr, $bottom, $j, $compare_func);
        _qsort($arr, $j + 1, $top, $compare_func);
    }
}

/**
 * 快排，默认从小到达排序。注意：会去掉数组的键值。能用sort等函数的话就不要调用本函数
 *
 * @param array|SplFixedArray $arr
 * @param callable $compare_func
 * @param bool $need_spl_fixed_array
 * @return number length of $arr
 */
function qsort(&$arr, ?callable $compare_func = null, bool $need_spl_fixed_array = false): int {
    if (is_array($arr)) {
        $array = \SplFixedArray::fromArray($arr, false);
    } else {
        $array = $arr;
        $need_spl_fixed_array = true;
    }
    $length = $array->getSize();
    if ($length == 0) {
        if ($need_spl_fixed_array) {
            $arr = $array;
        } else {
            $arr = [];
        }
        return 0;
    }
    if (! isset($compare_func)) {
        $compare_func = function ($a, $b) {
            return $a < $b;
        };
    }
    _qsort($array, 0, $length - 1, $compare_func);
    if ($need_spl_fixed_array) {
        $arr = $array;
    } else {
        $arr = $array->toArray();
    }
    return $length;
}