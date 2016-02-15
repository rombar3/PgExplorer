<?php

namespace rombar\PgExplorerBundle\Models\dbElements;


/**
 * Description of Index
 *
 * @author barbu
 */
class Fonction extends PgElement {

    protected $schema;

    public function __construct($schema) {
        $this->schema = $schema;
    }

    public function getSchema() {
        return $this->schema;
    }

    public function setSchema($schema) {
        $this->schema = $schema;
    }

}

?>
