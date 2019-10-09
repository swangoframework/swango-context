<?php
abstract class BaseClient {
    protected const HOST = null, PATH = null, PORT = null;
    protected const SCHEME = 'http', METHOD = 'POST', TIMEOUT = 20, VERIFY_SSL = false, KEEP_ALIVE = true, USE_POOL = true;
    protected const PARSE_MODE_URL_ENCODE_KV = 1, PARSE_MODE_JSON = 2, PARSE_MODE_RAW = 3; // 普通form格式，json格式，原文。 xml因为需要更多参数，请使用原文格式并在子类中解析
    protected const PARSE_POST = self::PARSE_MODE_URL_ENCODE_KV, PARSE_GET = self::PARSE_MODE_URL_ENCODE_KV;
    /**
     *
     * @var \Swlib\Saber\Request
     */
    protected $client;
    private static $exception_name_map = [];
    private $exception_name, $send_time;
    protected $request_sent = false, $request_no_need_send = false;
    protected function handleHttpErrorCode(int $code): bool {
        if (method_exists($this, 'handleErrorCode_' . $code))
            return $this->{'handleErrorCode_' . $code}();
        return false;
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
            if (static::HOST !== null)
                $uri->withHost(static::HOST);
            if (static::PORT !== null)
                $port = static::PORT;
            elseif (static::SCHEME === 'https')
                $port = 443;
            else
                $port = 80;
            $uri->withPort($port);
            if (static::PATH !== null)
                $uri->withPath(static::PATH);
        }

        $client = \Swlib\SaberGM::psr(
            [
                'use_pool' => static::USE_POOL,
                'method' => static::METHOD,
                'useragent' => 'MangoHttpClient/3.0.0 (CentOS 7; Cor)',
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
            $dir = LIBDIR . str_replace('\\', '/', $class_name);
            $timeout = $unknown = $error = null;
            do {
                if (! isset($timeout) && file_exists($dir . '/Exception/ApiTimeoutException.php'))
                    $timeout = $class_name . '\\Exception\\ApiTimeoutException';

                if (! isset($unknown) && file_exists($dir . '/Exception/UnknownResultException.php'))
                    $unknown = $class_name . '\\Exception\\UnknownResultException';

                if (! isset($error) && file_exists($dir . '/Exception/ApiErrorException.php'))
                    $error = $class_name . '\\Exception\\ApiErrorException';

                if ((isset($timeout) && isset($error)) && isset($unknown))
                    break;
                $pos = strrpos($class_name, '\\');
                if ($pos === false)
                    break;
                $class_name = substr($class_name, 0, $pos);
                $dir = substr($dir, 0, strrpos($dir, '/'));
            } while ( $class_name && $class_name != '\\' );
            if (! isset($timeout))
                $timeout = 'ApiErrorException\\ApiTimeoutException';
            if (! isset($unknown))
                $unknown = 'ApiErrorException\\UnknownResultException';
            if (! isset($error))
                $error = 'ApiErrorException';
            $this->exception_name = [
                'timeout' => '\\' . $timeout,
                'unknown' => '\\' . $unknown,
                'error' => '\\' . $error
            ];
            self::$exception_name_map[static::class] = $this->exception_name;
        }
        return $this;
    }
    /**
     * 发出http包体，该方法为public，可以用来在外层组织协程并发时使用
     *
     * @return self
     */
    public function sendHttpRequest(): self {
        if ($this->request_no_need_send)
            return $this;
        if ($this->request_sent)
            throw new \Exception('Request already sent');
        if (! isset($this->client))
            $this->makeClient();
        $this->send_time = \Time\now();
        $this->client->exec();
        $this->request_sent = true;
        return $this;
    }
    public function requestSent(): bool {
        return $this->request_sent;
    }
    protected function recv(): \Swlib\Saber\Response {
        if (! $this->request_sent)
            throw new \Exception('Request not sent');
        $client = $this->client;
        $log_string = date('[Y-m-d H:i:s] ', $this->send_time) . "----------Request----------\n";
        $log_string .= $client->__toString() . "\n";
        try {
            $response = $client->recv();
            $code = $response->statusCode;

            $log_string .= date('[Y-m-d H:i:s] ', \Time\now()) . "----------Response---------\n";
            $log_string .= $response->__toString() . "\n\n";
            $this->writeLog($log_string);

            if ($code !== 200 && ! $this->handleHttpErrorCode($code)) {
                $name = $this->exception_name['error'];
                if (! isset($name) || $name === '')
                    throw new \ApiErrorException(static::class . ' api code error :' . $code);
                throw new $name(static::class . ' api code error :' . $code);
            }
            return $response;
        } catch(\Swlib\Http\Exception\ConnectException $e) {
            $code = $e->getCode();
            $error = $e->getMessage();
            $log_string .= "----Response:$error----\n\n";
            $this->writeLog($log_string);
            if ($code === - 1 || $code === - 2) {
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
        if (strpos($class_name, 'class@anonymous') !== false)
            $dir = LOGDIR . 'http_client/anonymous/' . $this->client->getUri()->getHost();
        else
            $dir = LOGDIR . 'http_client/' . str_replace('\\', '/', $class_name);
        return $dir;
    }
    protected function writeLog(string &$log_string): void {
        if (WORKING_MODE === WORKING_MODE_CLI) {
            if (strlen($log_string) > 4096)
                echo substr($log_string, 0, 4096) . "\n\n";
            else
                echo $log_string;
        }
        $dir = $this->getLogDir();
        if (! is_dir($dir))
            mkdir($dir, 0777, true);
        $fp = fopen($dir . '/' . date('Y-m-d') . '.log', 'a');
        fwrite($fp, $log_string);
        fclose($fp);
    }
    protected function parseRequest(&$post, &$get, &$header, ?string $request_string = null) {
        if (defined('WORKING_MODE') && defined('WORKING_MODE_SWOOLE_COR') && WORKING_MODE === WORKING_MODE_SWOOLE_COR) {
            $swoole_request = \Controller::getInstance()->getSwooleHttpRequest();
            if (\HttpServer\Router::getInstance()->getMethod() === 'POST') {
                if (static::PARSE_POST === self::PARSE_MODE_URL_ENCODE_KV)
                    $post = $swoole_request->post;
                elseif (static::PARSE_POST === self::PARSE_MODE_JSON)
                    $post = \Json::decodeAsArray($swoole_request->rawContent());
                else
                    $post = $swoole_request->rawContent();
            } else
                $post = null;

            if (static::PARSE_GET === self::PARSE_MODE_URL_ENCODE_KV)
                $get = $swoole_request->get;
            else
                $get = $swoole_request->server['query_string'];

            $header = [];
            foreach ($swoole_request->header as $key=>&$value) {
                $key_parts = explode('-', $key);
                foreach ($key_parts as &$p)
                    $p = ucfirst($p);
                unset($p);
                $header[implode('-', $key_parts)] = $value;
            }
            unset($value);
            $log_string = date('[Y-m-d H:i:s] ', \Time\now()) . "----------Webhook----------\n";
            $log_string .= $swoole_request->getData() . "\n";
            $this->writeLog($log_string);
        } else {
            if (! isset($request_string))
                throw new \Exception('Need request string when not in fgi mode');
            $request = \Zend\Http\Request::fromString(trim($request_string), false);
            if ($request->getMethod() === 'POST') {
                if (static::PARSE_POST === self::PARSE_MODE_URL_ENCODE_KV) {
                    $post = null;
                    parse_str($request->getContent(), $post);
                } elseif (static::PARSE_POST === self::PARSE_MODE_JSON)
                    $post = \Json::decodeAsArray($request->getContent());
                else
                    $post = $request->getContent();
            } else
                $post = null;

            if (static::PARSE_GET === self::PARSE_MODE_URL_ENCODE_KV)
                $get = $request->getUri()->getQueryAsArray();
            else
                $get = $request->getUri()->getQuery();

            $header = $request->getHeaders()->toArray();
        }
    }
}