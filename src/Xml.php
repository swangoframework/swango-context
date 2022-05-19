<?php
use function Swlib\Http\stream_for;
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
        foreach ($data as $k => $v) {
            $child_need_to_be_array = in_array("$key.$k", $need_array);
            if ($child_need_to_be_array && ! isset($result->{$k})) {
                $result->{$k} = [];
            }

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
        if ($xml === false) {
            throw new XmlDecodeFailException();
        }
        $name = $xml->getName();
        $ret = new stdClass();
        $ret->{$name} = self::intoObject($xml, $need_array, $name);
        return $ret;
    }
    private static function toXml(&$data,
                                  \Psr\Http\Message\StreamInterface $result,
                                  string $outer_key,
                                  ?int $level): void {
        $is_fixed_array = is_array($data) && array_is_list($data);

        if (! $is_fixed_array && '' !== $outer_key) {
            if ($level) {
                $result->write(str_repeat('  ', $level));
            }
            $result->write("<$outer_key>");
            if (isset($level)) {
                $result->write("\n");
            }
        }
        foreach ($data as $key => &$val) {
            if ($val instanceof \BackedEnum) {
                $val = $val->value;
            }
            if (is_scalar($val)) {
                if (isset($level)) {
                    $result->write(str_repeat('  ', $is_fixed_array ? $level : $level + 1));
                }
                if ($is_fixed_array) {
                    $result->write("<$outer_key><![CDATA[");
                    $result->write($val);
                    $result->write("]]></$outer_key>");
                } else {
                    $result->write("<$key><![CDATA[");
                    $result->write($val);
                    $result->write("]]></$key>");
                }
                if (isset($level)) {
                    $result->write("\n");
                }
            } else {
                self::toXml($val,
                    $result,
                    $is_fixed_array ? $outer_key : $key,
                    isset($level) ? ($is_fixed_array ? $level : $level + 1) : null
                );
            }
        }
        if (! $is_fixed_array && '' !== $outer_key) {
            if ($level) {
                $result->write(str_repeat('  ', $level));
            }
            $result->write("</$outer_key>");
            if (isset($level)) {
                $result->write("\n");
            }
        }
        unset($val);
    }
    public static function encode($valueToEncode, bool $pretty_print = false): string {
        if (is_scalar($valueToEncode)) {
            throw new \Exception('Cannot xml encode scalar');
        }
        $res = stream_for('');
        self::toXml($valueToEncode, $res, '', $pretty_print ? -1 : null);
        return $res->__toString();
    }
}
