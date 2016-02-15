<?php

namespace rombar\PgExplorerBundle\Models\dbElements;


/**
 * Description of Index
 *
 * @author barbu
 */
class Index extends PgElement {

    protected $table;

    protected $schema;

    protected $isPrimary;

    protected $isUnique;

    protected $isClustered;

    protected $isValid;

    protected $creationQuery;

    protected $constraintDef;

    protected $contype;

    protected $condeferrable;

    protected $condeferred;

    protected $reltablespace;

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
    public function getIsPrimary()
    {
        return $this->isPrimary;
    }

    /**
     * @param mixed $isPrimary
     */
    public function setIsPrimary($isPrimary)
    {
        $this->isPrimary = $isPrimary;
    }

    /**
     * @return mixed
     */
    public function getIsUnique()
    {
        return $this->isUnique;
    }

    /**
     * @param mixed $isUnique
     */
    public function setIsUnique($isUnique)
    {
        $this->isUnique = $isUnique;
    }

    /**
     * @return mixed
     */
    public function getIsClustered()
    {
        return $this->isClustered;
    }

    /**
     * @param mixed $isClustered
     */
    public function setIsClustered($isClustered)
    {
        $this->isClustered = $isClustered;
    }

    /**
     * @return mixed
     */
    public function getIsValid()
    {
        return $this->isValid;
    }

    /**
     * @param mixed $isValid
     */
    public function setIsValid($isValid)
    {
        $this->isValid = $isValid;
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

    /**
     * @return mixed
     */
    public function getConstraintDef()
    {
        return $this->constraintDef;
    }

    /**
     * @param mixed $constraintDef
     */
    public function setConstraintDef($constraintDef)
    {
        $this->constraintDef = $constraintDef;
    }

    /**
     * @return mixed
     */
    public function getContype()
    {
        return $this->contype;
    }

    /**
     * @param mixed $contype
     */
    public function setContype($contype)
    {
        $this->contype = $contype;
    }

    /**
     * @return mixed
     */
    public function getCondeferrable()
    {
        return $this->condeferrable;
    }

    /**
     * @param mixed $condeferrable
     */
    public function setCondeferrable($condeferrable)
    {
        $this->condeferrable = $condeferrable;
    }

    /**
     * @return mixed
     */
    public function getCondeferred()
    {
        return $this->condeferred;
    }

    /**
     * @param mixed $condeferred
     */
    public function setCondeferred($condeferred)
    {
        $this->condeferred = $condeferred;
    }

    /**
     * @return mixed
     */
    public function getReltablespace()
    {
        return $this->reltablespace;
    }

    /**
     * @param mixed $reltablespace
     */
    public function setReltablespace($reltablespace)
    {
        $this->reltablespace = $reltablespace;
    }

}

