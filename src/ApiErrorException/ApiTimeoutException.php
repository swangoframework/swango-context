<?php
namespace ApiErrorException;
/**
 * 接口超时
 *
 * @author fdream
 */
class ApiTimeoutException extends UnknownResultException {
    public function __construct(string $message = 'Api timeout') {
        parent::__construct($message);
    }
}