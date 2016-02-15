<?php

namespace rombar\PgExplorerBundle\Models\dbElements;

/**
 * Description of Sequence
 *
 * @author barbu
 */
class Sequence extends PgElement{
   
    protected $schema;
    protected $currentValue;

    public function getSchema() {
        return $this->schema;
    }

    public function setSchema($schema) {
        $this->schema = $schema;
    }

    public function getCurrentValue() {
        return $this->currentValue;
    }

    public function setCurrentValue($currentValue) {
        $this->currentValue = $currentValue;
    }



}

?>
