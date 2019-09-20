<?php
class Context {
    protected static $pool = [], $clear_func;
    public static function &get(string $key) {
        $uid = \Swoole\Coroutine::getCid();
        if (array_key_exists($uid, static::$pool)) {
            $ob = static::$pool[$uid];
            if (property_exists($ob, $key))
                return $ob->{$key};
        }
        $t = null;
        return $t;
    }
    public static function getAndDelete(string $key) {
        $uid = \Swoole\Coroutine::getCid();
        if (array_key_exists($uid, static::$pool)) {
            $ob = static::$pool[$uid];
            if (property_exists($ob, $key)) {
                $ret = $ob->{$key};
                unset($ob->{$key});
                return $ret;
            }
        }
        return null;
    }
    public static function hGet(string $key, string $hash_key) {
        $uid = \Swoole\Coroutine::getCid();
        if (! array_key_exists($uid, static::$pool))
            return null;
        $ob = static::$pool[$uid];
        if (! property_exists($ob, $key))
            return null;
        $arr = $ob->{$key};
        if (! is_array($arr) || ! array_key_exists($hash_key, $arr))
            return null;
        return $arr[$hash_key];
    }
    public static function has(string $key): bool {
        $uid = \Swoole\Coroutine::getCid();
        return array_key_exists($uid, static::$pool) && property_exists(static::$pool[$uid], $key);
    }
    public static function hHas(string $key, string $hash_key): bool {
        $uid = \Swoole\Coroutine::getCid();
        if (! array_key_exists($uid, static::$pool))
            return false;
        $ob = static::$pool[$uid];
        if (! property_exists($ob, $key))
            return false;
        $arr = $ob->{$key};
        return is_array($arr) && array_key_exists($hash_key, $arr);
    }
    public static function set(string $key, $value): void {
        $uid = \Swoole\Coroutine::getCid();
        if (! array_key_exists($uid, static::$pool)) {
            $ob = new \stdClass();
            $ob->{$key} = $value;
            static::$pool[$uid] = $ob;
            \Swoole\Coroutine::defer(static::class . '::clear');
            return;
        }
        static::$pool[$uid]->{$key} = $value;
    }
    public static function hSet(string $key, string $hash_key, $value): void {
        $uid = \Swoole\Coroutine::getCid();
        if (! array_key_exists($uid, static::$pool)) {
            $ob = new \stdClass();
            $ob->{$key} = [
                $hash_key => $value
            ];
            static::$pool[$uid] = $ob;
            \Swoole\Coroutine::defer(static::class . '::clear');
            return;
        }
        $ob = static::$pool[$uid];
        if (! property_exists($ob, $key)) {
            $ob->{$key} = [
                $hash_key => $value
            ];
            return;
        }
        $ob->{$key}[$hash_key] = $value;
    }
    public static function push(string $key, $value): void {
        $uid = \Swoole\Coroutine::getCid();
        if (! array_key_exists($uid, static::$pool)) {
            $ob = new \stdClass();
            $ob->{$key} = [
                $value
            ];
            static::$pool[$uid] = $ob;
            \Swoole\Coroutine::defer(static::class . '::clear');
            return;
        }
        $ob = static::$pool[$uid];
        if (! property_exists($ob, $key)) {
            $ob->{$key} = [
                $value
            ];
            return;
        }
        $ob->{$key}[] = $value;
    }
    public static function del(string $key): bool {
        $uid = \Swoole\Coroutine::getCid();
        if (array_key_exists($uid, static::$pool) && property_exists(static::$pool[$uid], $key)) {
            unset(static::$pool[$uid]->{$key});
            return true;
        }
        return false;
    }
    public static function hDel(string $key, string $hash_key): bool {
        $uid = \Swoole\Coroutine::getCid();
        if (! array_key_exists($uid, static::$pool))
            return false;
        $ob = static::$pool[$uid];
        if (! property_exists($ob, $key))
            return false;
        $arr = $ob->{$key};
        if (is_array($arr) && array_key_exists($hash_key, $arr)) {
            unset($ob->{$key}[$hash_key]);
            return true;
        }
        return false;
    }
    public static function clear(?int $uid = null): bool {
        if (! isset($uid))
            $uid = \Swoole\Coroutine::getCid();
        if (array_key_exists($uid, static::$pool)) {
            if (static::$clear_func === null) {
                unset(static::$pool[$uid]);
            } else {
                $ob = static::$pool[$uid];
                unset(static::$pool[$uid]);
                (static::$clear_func)($ob);
            }
            return true;
        }
        return false;
    }
    public static function setClearFunc(callable $clear_func): void {
        static::$clear_func = $clear_func;
    }
    public static function hasSomething(): bool {
        return array_key_exists(\Swoole\Coroutine::getCid(), static::$pool);
    }
    public static function getSize(): int {
        return count(static::$pool);
    }
}