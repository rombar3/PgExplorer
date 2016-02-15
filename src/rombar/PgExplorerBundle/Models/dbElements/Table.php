<?php

namespace rombar\PgExplorerBundle\Models\dbElements;

/**
 * Description of Table
 *
 * @author barbu
 */
class Table extends PgElement{

    /**
     *
     * @var array 
     */
    protected $columns = [];
    
    /**
     *
     * @var array 
     */
    protected $indexs = [];
    
    /**
     *
     * @var Index 
     */
    protected $primaryKey;

    /**
     *
     * @var array 
     */
    protected $foreignKeys = [];
    
    /**
     *
     * @var array 
     */
    protected $rules = [];
    
    /**
     *
     * @var array 
     */
    protected $childTables = [];
    
    /**
     *
     * @var array 
     */
    protected $parentTables = [];
    
    /**
     *
     * @var array 
     */
    protected $referencedInTables = [];

    /**
     * @var array
     */
    protected $linkedSequences = [];

    protected $schema;
    
    /**
     *
     * @var array 
     */
    protected $triggers = [];
    
    public function getColumns()
    {
        return $this->columns;
    }

    public function setColumns(array $columns)
    {
        $this->columns = $columns;
    }

    /**
     * @return array
     */
    public function getIndexs()
    {
        return $this->indexs;
    }

    public function setIndexs(array $indexs)
    {
        $this->indexs = $indexs;
    }

    /**
     * @return Index
     */
    public function getPrimaryKey()
    {
        if(empty($this->primaryKey) && !empty($this->indexs)){
            foreach($this->indexs as $index){
                if ($index->getIsPrimary()) {
                    $this->setPrimaryKey($index);
                }
            }
        }
        return $this->primaryKey;
    }

    /**
     * @param Index $primaryKey
     */
    public function setPrimaryKey(Index $primaryKey)
    {
        $this->primaryKey = $primaryKey;
    }

    /**
     * @return array
     */
    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }

    public function setForeignKeys(array $foreignKeys)
    {
        $this->foreignKeys = $foreignKeys;
    }
    
    public function getRules()
    {
        return $this->rules;
    }

    public function setRules(array $rules)
    {
        $this->rules = $rules;
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function setSchema($schema)
    {
        $this->schema = $schema;
    }

    public function getTriggers()
    {
        return $this->triggers;
    }

    /**
     * @return bool
     */
    public function hasTriggers()
    {
        return (count($this->triggers)) ? true : false;
    }

    public function setTriggers(array $triggers)
    {
        $this->triggers = $triggers;
    }

    public function getReferencedInTables()
    {
        return $this->referencedInTables;
    }

    public function setReferencedInTables(array $referencedInTables)
    {
        $this->referencedInTables = $referencedInTables;
    }

    public function getChildTables()
    {
        return $this->childTables;
    }

    public function setChildTables(array $childTables)
    {
        $this->childTables = $childTables;
    }

    public function getParentTables()
    {
        return $this->parentTables;
    }

    public function setParentTables(array $parentTables)
    {
        $this->parentTables = $parentTables;
    }

    /**
     * @return array
     */
    public function getLinkedSequences()
    {
        return $this->linkedSequences;
    }

    /**
     * @param array $linkedSequences
     */
    public function setLinkedSequences($linkedSequences)
    {
        $this->linkedSequences = $linkedSequences;
    }

}

?>
