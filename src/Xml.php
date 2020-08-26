<?php
class Xml {
    private static function intoObject(SimpleXMLElement $data, array &$need_array, string $key) {
        $s = $data->__toString();
        if (str_replace([
            ' ',
            "\n",
            "\r",
            "\t"
        ], '', $s) != '') {
            return $s;
        }
        $result = new stdClass();
        $flag = 0; // 0表示没有儿子 1表示有多个同键名儿子 2表示有儿子
        $map = [];
        foreach ($data as $k=>$v) {
            $child_need_to_be_array = in_array("$key.$k", $need_array);
            if ($child_need_to_be_array && ! isset($result->{$k}))
                $result->{$k} = [];

            // 有两项相同健值，说明这里需要转换为数组
            if (! $child_need_to_be_array && $flag !== 1 && array_key_exists($k, $map)) {
                $flag = 1;
                $ob = $result->{$k};
                $result = new stdClass();
                $result->{$k} = [
                    $ob
                ];
            }
            if ($flag === 0) {
                $flag = 2;
            }
            $map[$k] = null;
            if ($child_need_to_be_array || $flag === 1) {
                $result->{$k}[] = self::intoObject($v, $need_array, "$key.$k");
            } else {
                $result->{$k} = self::intoObject($v, $need_array, "$key.$k");
            }
        }
        unset($v);
        // 一个儿子都没有，说明本项为空
        if ($flag === 0) {
            return null;
        } else {
            return $result;
        }
    }
    public static function decodeAsObject(string $data, array $need_array = []): stdClass {
        $xml = simplexml_load_string($data);
        if ($xml === false)
            throw new XmlDecodeFailException();
        $name = $xml->getName();
        $ret = new stdClass();
        $ret->{$name} = self::intoObject($xml, $need_array, $name);
        return $ret;
    }
}