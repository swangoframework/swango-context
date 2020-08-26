<?php
class XmlDecodeFailException extends \Exception {
    public function __constrcut() {
        parent::__construct('Xml decode fail');
    }
}