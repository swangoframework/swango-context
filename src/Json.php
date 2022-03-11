<?php
class Json {
    public static function encode($valueToEncode, ?int $encodeOptions = null): string {
        return json_encode($valueToEncode,
            $encodeOptions ?? (JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
    }
    public static function decodeAsArray($encodedValue, int $flags = 0): array {
        $decoded = json_decode($encodedValue, associative: true, flags: $flags);
        switch (json_last_error()) {
            case JSON_ERROR_NONE :
                return $decoded;
            case JSON_ERROR_DEPTH :
                throw new JsonDecodeFailException('Decoding failed: Maximum stack depth exceeded');
            case JSON_ERROR_CTRL_CHAR :
                throw new JsonDecodeFailException('Decoding failed: Unexpected control character found');
            case JSON_ERROR_SYNTAX :
                throw new JsonDecodeFailException('Decoding failed: Syntax error');
            default :
                throw new JsonDecodeFailException('Decoding failed');
        }
    }
    public static function decodeAsObject($encodedValue, int $flags = 0) {
        $decoded = json_decode($encodedValue, associative: false, flags: $flags);
        switch (json_last_error()) {
            case JSON_ERROR_NONE :
                return $decoded;
            case JSON_ERROR_DEPTH :
                throw new JsonDecodeFailException('Decoding failed: Maximum stack depth exceeded');
            case JSON_ERROR_CTRL_CHAR :
                throw new JsonDecodeFailException('Decoding failed: Unexpected control character found');
            case JSON_ERROR_SYNTAX :
                throw new JsonDecodeFailException('Decoding failed: Syntax error');
            default :
                throw new JsonDecodeFailException('Decoding failed');
        }
    }
}
