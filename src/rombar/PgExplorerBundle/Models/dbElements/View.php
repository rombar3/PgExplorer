<?php

namespace rombar\PgExplorerBundle\Models\dbElements;

/**
 * Description of View
 *
 * @author barbu
 */
class View extends PgElement{
    
    
    protected $schema;
    protected $query;
    

    public function getSchema() {
        return $this->schema;
    }

    public function setSchema($schema) {
        $this->schema = $schema;
    }

    public function getQuery() {
        return $this->query;
    }

    public function setQuery($query) {
        $this->query = $query;
    }


}

?>
