<?php
class FileLog {
    public static function logThrowable(Throwable $e, string $dir, string $title = '') {
        if (! is_dir($dir))
            mkdir($dir);
        $s = sprintf('[%s] %s: ', date('Y-m-d H:i:s', time()), $title);
        $s .= str_replace([
            \Swango\Environment::getDir()->base,
            "\n"
        ], [
            '',
            '<=='
        ], $e->getMessage() . ' ' . $e->getFile() . "({$e->getLine()}))\n{$e->getTraceAsString()}") . "\n";
        $fp = fopen($dir . date('Y-m-d') . '.log', 'a');
        fwrite($fp, $s);
        fclose($fp);
    }
}