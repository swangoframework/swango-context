<?php
abstract class BaseClient {
    protected const HOST = null, PATH = null, PORT = null;
    protected const SCHEME = 'http', METHOD = 'POST', TIMEOUT = 20, VERIFY_SSL = false, KEEP_ALIVE = true, USE_POOL = true;
    protected const PARSE_MODE_URL_ENCODE_KV = 1, PARSE_MODE_JSON = 2, PARSE_MODE_RAW = 3; // 普通form格式，json格式，原文。 xml因为需要更多参数，请使用原文格式并在子类中解析
    protected const PARSE_POST = self::PARSE_MODE_URL_ENCODE_KV, PARSE_GET = self::PARSE_MODE_URL_ENCODE_KV;
    private static $_echo_log_on = true;
    /**
     * BaseClient在cli模式下默认会输出请求响应内容到标准输出
     * @param bool $on
     */
    public static function setEchoLog(bool $on = false): void {
        self::$_echo_log_on = $on;
    }
    /**
     *
     * @var \Swlib\Saber\Request
     */
    protected $client;
    private static $exception_name_map = [];
    private $exception_name, $send_time;
    protected $request_sent = false, $request_no_need_send = false;
    protected function handleHttpErrorCode(int $code): bool {
        if (method_exists($this, 'handleErrorCode_' . $code)) {
            return $this->{'handleErrorCode_' . $code}();
        }
        return false;
    }
    protected function getExceptionName(): array {
        $class_name = static::class;
        $dir = \Swango\Environment::getDir()->library . str_replace('\\', '/', $class_name);
        $timeout = $unknown = $error = null;
        do {
            if (! isset($timeout) && file_exists($dir . '/Exception/ApiTimeoutException.php')) {
                $timeout = $class_name . '\\Exception\\ApiTimeoutException';
            }
            if (! isset($unknown) && file_exists($dir . '/Exception/UnknownResultException.php')) {
                $unknown = $class_name . '\\Exception\\UnknownResultException';
            }
            if (! isset($error) && file_exists($dir . '/Exception/ApiErrorException.php')) {
                $error = $class_name . '\\Exception\\ApiErrorException';
            }
            if ((isset($timeout) && isset($error)) && isset($unknown)) {
                break;
            }
            $pos = strrpos($class_name, '\\');
            if ($pos === false) {
                break;
            }
            $class_name = substr($class_name, 0, $pos);
            $dir = substr($dir, 0, strrpos($dir, '/'));
        } while ($class_name && $class_name != '\\');
        if (! isset($timeout)) {
            $timeout = 'ApiErrorException\\ApiTimeoutException';
        }
        if (! isset($unknown)) {
            $unknown = 'ApiErrorException\\UnknownResultException';
        }
        if (! isset($error)) {
            $error = 'ApiErrorException';
        }
        return [
            'timeout' => '\\' . $timeout,
            'unknown' => '\\' . $unknown,
            'error' => '\\' . $error
        ];
    }
    /**
     * 发包之前的准备，在调用此方法后setData addHeader之类的方法将不生效
     *
     * @param string $host
     *            HOST常量优先此项生效
     * @param string $path
     *            PATH常量优先此项生效
     * @param int $port
     *            PORT常量优先此项生效
     * @return self
     */
    protected function makeClient(?\Swlib\Http\Uri $uri = null): self {
        if (! isset($uri)) {
            $uri = new \Swlib\Http\Uri();
            $uri->withScheme(static::SCHEME);
            if (static::HOST !== null) {
                $uri->withHost(static::HOST);
            }
            if (static::PORT !== null) {
                $port = static::PORT;
            } elseif (static::SCHEME === 'https') {
                $port = 443;
            } else {
                $port = 80;
            }
            $uri->withPort($port);
            if (static::PATH !== null) {
                $uri->withPath(static::PATH);
            }
        }
        $client = \Swlib\SaberGM::psr([
            'use_pool' => static::USE_POOL,
            'method' => static::METHOD,
            'useragent' => 'Swango/1.2',
            'timeout' => static::TIMEOUT,
            'keep_alive' => static::KEEP_ALIVE,
            'uri' => $uri,
            'exception_report' => Swlib\Http\Exception\HttpExceptionMask::E_CONNECT
        ]);
        $this->client = $client;
        // 构建对应的exception_name，并注册
        $class_name = static::class;
        if (strpos($class_name, 'class@anonymous') !== false) {
            $this->exception_name = [
                'timeout' => '\\ApiErrorException\\ApiTimeoutException',
                'unknown' => '\\ApiErrorException\\UnknownResultException',
                'error' => '\\ApiErrorException'
            ];
        } elseif (array_key_exists($class_name, self::$exception_name_map)) {
            $this->exception_name = self::$exception_name_map[$class_name];
        } else {
            self::$exception_name_map[static::class] = $this->exception_name = $this->getExceptionName();
        }
        return $this;
    }
    /**
     * 发出http包体，该方法为public，可以用来在外层组织协程并发时使用
     *
     * @return self
     */
    public function sendHttpRequest(): self {
        if ($this->request_no_need_send) {
            return $this;
        }
        if ($this->request_sent) {
            throw new \Exception('Request already sent');
        }
        if (! isset($this->client)) {
            $this->makeClient();
        }
        $this->send_time = \Time\now();
        $this->client->exec();
        $this->request_sent = true;
        return $this;
    }
    public function requestSent(): bool {
        return $this->request_sent;
    }
    protected function getRequestLogContent(): string {
        return $this->client->__toString();
    }
    protected function recv(): \Swlib\Saber\Response {
        if (! $this->request_sent) {
            throw new \Exception('Request not sent');
        }
        $client = $this->client;
        $log_string = date('[Y-m-d H:i:s] ', $this->send_time) . "----------Request----------\n";
        $log_string .= $this->getRequestLogContent() . "\n";
        try {
            $response = $client->recv();
            $code = $response->statusCode;
            $log_string .= date('[Y-m-d H:i:s] ', \Time\now()) . "----------Response---------\n";
            $log_string .= $response->__toString() . "\n\n";
            $this->writeLog($log_string);
            if ($code !== 200 && ! $this->handleHttpErrorCode($code)) {
                $name = $this->exception_name['error'];
                if (! isset($name) || $name === '') {
                    throw new \ApiErrorException(static::class . ' api code error :' . $code);
                }
                throw new $name(static::class . ' api code error :' . $code);
            }
            return $response;
        } catch (\Swlib\Http\Exception\ConnectException $e) {
            $code = $e->getCode();
            $error = $e->getMessage();
            $log_string .= "----Response:$error----\n\n";
            $this->writeLog($log_string);
            if ($code === -1 || $code === -2) {
                $name = $this->exception_name['timeout'];
                if (isset($name)) {
                    throw new $name(static::class . $error);
                } else {
                    throw new \ApiErrorException\ApiTimeoutException(static::class);
                }
            } else {
                $name = $this->exception_name['unknown'];
                if (isset($name)) {
                    throw new $name(static::class . $error);
                } else {
                    throw new \ApiErrorException\UnknownResultException(static::class);
                }
            }
        }
    }
    protected function getLogDir(): string {
        $class_name = static::class;
        if (strpos($class_name, 'class@anonymous') !== false) {
            $dir = \Swango\Environment::getDir()->log . 'http_client/anonymous/' . $this->client->getUri()->getHost();
        } else {
            $dir = \Swango\Environment::getDir()->log . 'http_client/' . str_replace('\\', '/', $class_name);
        }
        return $dir;
    }
    protected function writeLog(string &$log_string): void {
        if (self::$_echo_log_on && \Swango\Environment::getWorkingMode()->isInCliScript()) {
            if (strlen($log_string) > 4096) {
                echo substr($log_string, 0, 4096) . "\n\n";
            } else {
                echo $log_string;
            }
        }
        $dir = $this->getLogDir();
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $fp = fopen($dir . '/' . date('Y-m-d') . '.log', 'a');
        fwrite($fp, $log_string);
        fclose($fp);
    }
    protected function parseRequest(&$post, &$get, &$header, ?string $request_string = null) {
        if (\Swango\Environment::getWorkingMode()->isInSwooleWorker() && class_exists('\\Swango\\HttpServer\\Router')) {
            $swoole_request = \Swango\HttpServer\Controller::getInstance()->getSwooleHttpRequest();
            if (\Swango\HttpServer\Router::getInstance()->getMethod() === 'POST') {
                if (static::PARSE_POST === self::PARSE_MODE_URL_ENCODE_KV) {
                    $post = null;
                    parse_str($swoole_request->rawContent(), $post);
                } elseif (static::PARSE_POST === self::PARSE_MODE_JSON) {
                    $post = \Json::decodeAsArray($swoole_request->rawContent());
                } else {
                    $post = $swoole_request->rawContent();
                }
            } else {
                $post = null;
            }
            if (static::PARSE_GET === self::PARSE_MODE_URL_ENCODE_KV) {
                $get = \SysContext::get('request_get');
            } else {
                $get = $swoole_request->server['query_string'];
            }
            $header = [];
            foreach ($swoole_request->header as $key => &$value) {
                $key_parts = explode('-', $key);
                foreach ($key_parts as &$p)
                    $p = ucfirst($p);
                unset($p);
                $header[implode('-', $key_parts)] = $value;
            }
            unset($value);
            $log_string = date('[Y-m-d H:i:s] ', \Time\now()) . "----------Webhook----------\n";
            $log_string .= $swoole_request->getData() . "\n\n";
            $this->writeLog($log_string);
        } else {
            if (! isset($request_string)) {
                throw new \Exception('Need request string when not in fgi mode');
            }
            /**
             *
             * @var Swlib\Http\Request $request
             */
            $request = self::parseRequestString(trim($request_string));
            if ($request->getMethod() === 'POST') {
                if (static::PARSE_POST === self::PARSE_MODE_URL_ENCODE_KV) {
                    $post = null;
                    parse_str($request->getBody()->__toString(), $post);
                } elseif (static::PARSE_POST === self::PARSE_MODE_JSON) {
                    $post = Json::decodeAsArray($request->getBody()->__toString());
                } else {
                    $post = $request->getBody()->__toString();
                }
            } else {
                $post = null;
            }
            if (static::PARSE_GET === self::PARSE_MODE_URL_ENCODE_KV) {
                $get = null;
                parse_str($request->getUri()->getQuery(), $get);
            } else {
                $get = $request->getUri()->getQuery();
            }
            $header = $request->getHeaders(true, true);
        }
    }
    public static function parseRequestString(string $string): Swlib\Http\Request {
        $lines = explode("\r\n", $string);
        // first line must be Method/Uri/Version string
        $matches = null;
        $regex = '#^(?P<method>[\w-]+)\s(?P<uri>[^ ]*)(?:\sHTTP\/(?P<version>\d+\.\d+)){0,1}#';
        $firstLine = array_shift($lines);
        if (! preg_match($regex, $firstLine, $matches)) {
            throw new Exception('A valid request line was not found in the provided string');
        }
        $request = new Swlib\Http\Request($matches['method'], $matches['uri'], [], null, $matches['version']);
        if (! empty($lines)) {
            $isHeader = true;
            $rawBody = [];
            while ($lines) {
                $nextLine = array_shift($lines);
                if ($nextLine == '') {
                    $isHeader = false;
                    continue;
                }
                if ($isHeader) {
                    if (preg_match("/[\r\n]/", $nextLine)) {
                        throw new Exception('CRLF injection detected');
                    }
                    $pos = strpos($nextLine, ':');
                    $raw_name = trim(substr($nextLine, 0, $pos));
                    $value = trim(substr($nextLine, $pos + 1));
                    $request->withHeader($raw_name, $value);
                    continue;
                }
                if (empty($rawBody) && preg_match('/^[a-z0-9!#$%&\'*+.^_`|~-]+:$/i', $nextLine)) {
                    throw new Exception('CRLF injection detected');
                }
                $rawBody[] = $nextLine;
            }
            if (! empty($rawBody)) {
                $request->withBody(Swlib\Http\stream_for(implode("\r\n", $rawBody)));
            }
        }
        return $request;
    }
}
