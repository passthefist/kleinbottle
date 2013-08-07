<?php
namespace Kleinbottle\Middleware;

require __DIR__.'/../validation/jsv4.php';

class ValidateJson implements \ehough_chaingang_api_Command {
    private $schema;
    private $paramName;

    public function __construct($paramName, $schema = null) {
        if ($schema === null) {
            $this->schema = json_decode(json_encode($paramName));
            $this->paramName = null;
        } else {
            $this->schema = json_decode(json_encode($schema));
            $this->paramName = $paramName;
        }
    }

    public function execute(\ehough_chaingang_api_Context $context) {
        $request = $context->get('request');

        $data = null;

        if ($this->paramName != null) {
            $data = $this->parseFromParam($request);
        } else {
            $data = $this->parseFromBody($request);
        }

        $result = \Jsv4::coerce($data, $this->schema);

        if (!$result->valid) {
            $context->get('response')->code(400);
            $context->get('response')->json(
                $result->errors
            );

            return true;
        }

        $request->{$this->paramName} = $result->value;

        return false;
    }

    public function parseFromParam($request) {
        $data = $request->param($this->paramName);
        return json_decode($data);
    }

    public function parseFromBody($request) {
        return json_decode($request->body());
    }
}
