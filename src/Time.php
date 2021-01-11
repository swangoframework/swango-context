<?php
namespace Time;
function now(): int {
    return time();
}
function getToday(): int {
    [
        $year,
        $month,
        $day
    ] = explode('-', date('Y-n-j', now()));
    return mktime(0, 0, 0, $month, $day, $year);
}
function getTomorrow(): int {
    return getToday() + 86400;
}
function getDaysOfAMonth(int $year, int $month): int {
    if ($month === 2) {
        return (($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0) ? 29 : 28;
    }
    if ($month === 4 || $month === 6 || $month === 9 || $month === 11) {
        return 30;
    }
    return 31;
}
/**
 * 获取上月月初
 */
function getLastMonth(): int {
    [
        $year,
        $month
    ] = explode('-', date('Y-n', now()));
    if ($month > 1) {
        return mktime(0, 0, 0, $month - 1, 1, $year);
    }
    return mktime(0, 0, 0, 12, 1, $year - 1);
}
function getEndOfThisMonth(): int {
    [
        $year,
        $month
    ] = explode('-', date('Y-n', now()));
    if ($month == 12) {
        return mktime(0, 0, 0, 1, 1, $year + 1) - 1;
    } else {
        return mktime(0, 0, 0, $month + 1, 1, $year) - 1;
    }
}