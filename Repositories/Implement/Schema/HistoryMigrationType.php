<?php
/**
 * Created by PhpStorm.
 * User: dell
 * Date: 3/11/20
 * Time: 2:59 PM
 */

namespace App\Repositories\Implement\Schema;


class HistoryMigrationType {

    public $field_change = null;
    public $previous_value = null;
    public $current_value= null;

    public function __construct($field_change, $previous_value, $current_value) {
        $this->field_change = $field_change;
        $this->previous_value = $previous_value;
        $this->current_value = $current_value;
    }
}
