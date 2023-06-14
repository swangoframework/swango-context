<?php
use function Swlib\Http\stream_for;
class Xml {
    private static function intoObject(SimpleXMLElement $data, array &$need_array, string $key) {
        $s = $data->__toString();
        if (str_replace([' ', "\n", "\r", "\t"], '', $s) != '') {
            return $s;
        }
        $result = new stdClass();
        $empty = true;
        foreach ($data as $k => $v) {
            $empty = false;
            $result->{$k} ??= [];
            $result->{$k}[] = self::intoObject($v, $need_array, "$key.$k");
        }
        if ($empty) {
            return null;
        }

        foreach ($result as $k => &$v)
            if (1 === count($v) && ! in_array("$key.$k", $need_array)) {
                $v = current($v);
            }
        unset($v);
        return $result;
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
