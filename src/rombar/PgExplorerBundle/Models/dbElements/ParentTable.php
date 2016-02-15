<?php

namespace rombar\PgExplorerBundle\Models\dbElements;

/**
 * Description of Index
 *
 * @author barbu
 */
class ParentTable extends PgElement {

    protected $table;

    protected $schema;

    public function __construct($schema, $table)
    {
        $this->schema = $schema;
        $this->table = $table;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function setTable($table)
    {
        $this->table = $table;
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function setSchema($schema)
    {
        $this->schema = $schema;
    }

}

?>
