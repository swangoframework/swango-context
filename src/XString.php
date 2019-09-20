<?php
namespace XString;
function GenerateRandomString(int $length = 16, string $type = 'lower&num'): string {
    $s = '';
    switch ($type) {
        case 'all' :
            $strPol = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
        case 'lower&num' :
            $strPol = '0123456789abcdefghijklmnopqrstuvwxyz';
            break;
        case 'num' :
            $strPol = '0123456789';
            break;
        case 'upper' :
            $strPol = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
    $max = strlen($strPol) - 1;
    for($i = 0; $i < $length; $i ++)
        $s .= $strPol[mt_rand(0, $max)];
    return $s;
}
/**
 * only work with utf-8
 */
function strRealLength(string $str): int {
    $json = json_encode($str);
    $json = substr($json, 1, strlen($json) - 2);
    $ret = 0;
    for($i = 0, $l = strlen($json); $i < $l; ++ $i) {
        if ($json{$i} != '\\')
            ++ $ret;
        else {
            if ($json{$i + 1} == 'u' && ($json{$i + 2} == 'd' || $json{$i + 2} == 'e')) {
                $offset_to_add = 11;
                $ret += 2;
            } else {
                $offset_to_add = 5;
                $ret += 2;
            }
            $i += $offset_to_add;
        }
    }
    return $ret;
}
function strRealSub(string $str, int $start, int $length = NULL, ?callable $if_sub_call_back = NULL): string {
    $json = json_encode($str);
    $json = substr($json, 1, strlen($json) - 2);
    $l = strlen($json);
    if ($l == 0)
        return '';
    $ret = [];
    for($i = 0; $i < $l; ++ $i) {
        if ($json{$i} != '\\')
            $ret[] = $json{$i};
        else {
            if ($json{$i + 1} == 'u' && ($json{$i + 2} == 'd' || $json{$i + 2} == 'e')) {
                $offset_to_add = 11;
                $s = '"' . substr($json, $i, 12) . '"';
            } else {
                $offset_to_add = 5;
                $s = '"' . substr($json, $i, 6) . '"';
            }
            // echo $s. ' '. json_decode($s).'<br>';
            $ret[] = json_decode($s);
            $ret[] = ''; // 非英文字符占两位
            $i += $offset_to_add;
        }
    }
    $retstr = implode('', array_slice($ret, $start, $length));
    if (isset($if_sub_call_back)) {
        if ($start > 0)
            $if_sub_call_back($retstr, FALSE);
        elseif ($start + $length < count($ret))
            $if_sub_call_back($retstr, TRUE);
    }
    return $retstr;
}
function removeEmoji(string $str): string {
    $json = json_encode($str);
    $json = substr($json, 1, strlen($json) - 2);
    $newstr = '';
    for($i = 0, $l = strlen($json); $i < $l; ++ $i) {
        if ($json{$i} != '\\')
            $newstr .= $json{$i};
        else {
            if ($json{$i + 1} == 'u' && ($json{$i + 2} == 'd' || $json{$i + 2} == 'e')) {
                $offset_to_add = 11;
                $newstr .= '?';
            } else {
                $offset_to_add = 5;
                $newstr .= substr($json, $i, 6);
            }
            $i += $offset_to_add;
        }
    }
    $str = json_decode("\"$newstr\"");
    return $str;
}