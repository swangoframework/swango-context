<?php
class FinishFunc {
    public static function register(callable $callback, ...$parameter): void {
        if (defined('WORKING_MODE') && defined('WORKING_MODE_SWOOLE_COR') && WORKING_MODE === WORKING_MODE_SWOOLE_COR)
            \Swoole\Coroutine::defer(
                [
                    new self($callback, ...$parameter),
                    'exec'
                ]);
        else
            register_shutdown_function($callback, ...$parameter);
    }
    private $callback, $parameter;
    private function __construct(callable $callback, ...$parameter) {
        $this->callback = $callback;
        $this->parameter = $parameter;
    }
    public function exec() {
        try {
            ($this->callback)(...$this->parameter);
        } catch(\Throwable $e) {
            $this->callback = null;
            $this->parameter = null;
            FileLog::logThrowable($e, LOGDIR . 'error/', 'FinishFunc');
        }
    }
}