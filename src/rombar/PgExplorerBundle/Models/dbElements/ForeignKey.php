<?php

namespace rombar\PgExplorerBundle\Models\dbElements;


/**
 * Description of Index
 *
 * @author barbu
 */
class ForeignKey extends PgElement {

    protected $table;

    protected $schema;

    protected $parentTable;

    protected $updateType;

    protected $deleteType;

    protected $matchType;

    protected $cols;

    protected $refCols;

    protected $creationQuery;

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

    /**
     * @return mixed
     */
    public function getParentTable()
    {
        return $this->parentTable;
    }

    /**
     * @param mixed $parentTable
     */
    public function setParentTable($parentTable)
    {
        $this->parentTable = $parentTable;
    }

    /**
     * @return mixed
     */
    public function getUpdateType()
    {
        return $this->updateType;
    }

    /**
     * @param mixed $updateType
     */
    public function setUpdateType($updateType)
    {
        $this->updateType = $updateType;
    }

    /**
     * @return mixed
     */
    public function getDeleteType()
    {
        return $this->deleteType;
    }

    /**
     * @param mixed $deleteType
     */
    public function setDeleteType($deleteType)
    {
        $this->deleteType = $deleteType;
    }

    /**
     * @return mixed
     */
    public function getMatchType()
    {
        return $this->matchType;
    }

    /**
     * @param mixed $matchType
     */
    public function setMatchType($matchType)
    {
        $this->matchType = $matchType;
    }

    /**
     * @return mixed
     */
    public function getCols()
    {
        return $this->cols;
    }

    /**
     * @param mixed $cols
     */
    public function setCols($cols)
    {
        $this->cols = $cols;
    }

    /**
     * @return mixed
     */
    public function getRefCols()
    {
        return $this->refCols;
    }

    /**
     * @param mixed $refCols
     */
    public function setRefCols($refCols)
    {
        $this->refCols = $refCols;
    }

    /**
     * @return mixed
     */
    public function getCreationQuery()
    {
        return $this->creationQuery;
    }

    /**
     * @param mixed $creationQuery
     */
    public function setCreationQuery($creationQuery)
    {
        $this->creationQuery = $creationQuery;
    }

}

?>
