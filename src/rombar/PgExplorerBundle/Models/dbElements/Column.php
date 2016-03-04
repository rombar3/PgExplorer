<?php

namespace rombar\PgExplorerBundle\Models\dbElements;

/**
 * Description of Columns
 *
 * @author barbu
 */
class Column extends PgElement{

    protected $table;

    protected $schema;

    protected $type;

    protected $default;

    protected $nullable;

    protected $position;

    protected $attcollation;

    protected $tableName;

    protected $realPosition;

    public function __construct($schema)
    {
        $this->schema = $schema;
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
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @param mixed $default
     */
    public function setDefault($default)
    {
        $this->default = $default;
    }

    /**
     * @return mixed
     */
    public function getNullable()
    {
        return $this->nullable;
    }

    /**
     * @param mixed $nullable
     */
    public function setNullable($nullable)
    {
        $this->nullable = $nullable;
    }

    /**
     * @return mixed
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param mixed $position
     */
    public function setPosition($position)
    {
        $this->position = $position;
    }

    /**
     * @return mixed
     */
    public function getAttcollation()
    {
        return $this->attcollation;
    }

    /**
     * @param mixed $attcollation
     */
    public function setAttcollation($attcollation)
    {
        $this->attcollation = $attcollation;
    }

    /**
     * @return mixed
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @param mixed $tableName
     */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * @return mixed
     */
    public function getRealPosition()
    {
        return $this->realPosition;
    }

    /**
     * @param mixed $realPosition
     */
    public function setRealPosition($realPosition)
    {
        $this->realPosition = $realPosition;
    }

}

