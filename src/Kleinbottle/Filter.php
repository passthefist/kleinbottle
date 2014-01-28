<?php
namespace Kleinbottle\Filter;

abstract class Filter extends ehough_chaingang_api_Command {
    public function execute(\ehough_chaingang_api_Context $context) {
        $this->call($context);
    }

    protected abstract function call($context);
}
