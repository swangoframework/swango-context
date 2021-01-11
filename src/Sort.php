<?php
function _qsort(SplFixedArray $arr, int $l, int $r, callable $compare) {
    $i = $l;
    $j = $r;
    $x = $arr[(int)(($i + $j) / 2)];
    $t = null;
    do {
        while ($compare($arr[$i], $x))
            ++$i;
        while ($compare($x, $arr[$j]))
            --$j;
        if ($i <= $j) {
            $t = $arr[$i];
            $arr[$i] = $arr[$j];
            $arr[$j] = $t;
            ++$i;
            --$j;
        }
    } while ($i <= $j);
    unset($t);
    unset($x);
    if ($i < $r) {
        _qsort($arr, $i, $r, $compare);
    }
    if ($l < $j) {
        _qsort($arr, $l, $j, $compare);
    }
}
;
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