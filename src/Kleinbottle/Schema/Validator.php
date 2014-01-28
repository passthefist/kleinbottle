<?php
namespace Kleinbottle\Schema;

//require_once __DIR__.'/../validation/jsv4.php';

class Validator {
    private $schema;
    private $schemaObj;

    public function __construct(array $schema) {
        $this->schema = $schema;
        $this->schemaObj = json_decode(json_encode($schema));
    }

    public function validate($data) {

        $result = \geraintluff\Jsv4::coerce($data, $this->schemaObj);

        print_r(json_encode($result));

        if (!$result->valid) {
            throw new Exception($result->errors);
        }

        return $result->value;
    }

    public function getSchema() {
        return $this->schema;
    }
}

