<?php
namespace Kleinbottle\Schema;
use Kleinbottle\Schema;

class DateRange extends Schema{
    function __construct(array $config){
        $this->_schema = array(
            "type"=> "object",
            "description"=> "Specify the sort type and direction.",
            "additionalProperties"=>false,
            "properties"=> array(
                "startDate"=> DateTime::build(),
                "endDate"=> DateTime::build(),
            ),

        );
    }
}
