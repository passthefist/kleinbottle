<?php
namespace Kleinbottle\Filter;

require __DIR__.'/../validation/jsv4.php';

class Json extends Filter {
    private $schema;

    public function __construct(array $schema) {
        $this->schema = json_decode(json_encode($schema));
    }

    public function call(\Kleinbottle\Context $context) {
        $request = $context->get('request');

        $data = $request->paramsNamed();

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
}

