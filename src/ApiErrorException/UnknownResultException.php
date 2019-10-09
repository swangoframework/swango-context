<?php
namespace ApiErrorException;
class UnknownResultException extends \ApiErrorException {
    public function __construct(string $message = 'Unknown result') {
        parent::__construct($message);
    }
}