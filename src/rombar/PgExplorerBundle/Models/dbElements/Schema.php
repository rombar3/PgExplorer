<?php

namespace rombar\PgExplorerBundle\Models\dbElements;

/**
 * Description of Schema
 *
 * @author barbu
 */
class Schema extends PgElement{
    
    /**
     *
     * @var array 
     */
    protected $tables = [];

    /**
     *
     * @var array
     */
    protected $tablesByName = [];

    /**
     *
     * @var array 
     */
    protected $views = [];

    /**
     *
     * @var array
     */
    protected $viewsByName = [];

    /**
     *
     * @var array 
     */
    protected $sequences = [];

    /**
     *
     * @var array
     */
    protected $sequencesByName = [];
    
    /**
     *
     * @var array 
     */
    protected $functions = [];

    /**
     *
     * @var array
     */
    protected $functionsByName = [];
    
    /**
     *
     * @var array 
     */
    protected $types = [];

    /**
     *
     * @var array
     */
    protected $typesByName = [];

    public function getTables()
    {
        return $this->tables;
    }

    public function setTables(array $tables)
    {
        $this->tables = $tables;
    }

    public function getFunctions()
    {
        return $this->functions;
    }

    public function setFunctions(array $functions)
    {
        $this->functions = $functions;
    }

    public function getTypes()
    {
        return $this->types;
    }

    public function setTypes(array $types)
    {
        $this->types = $types;
    }

    public function getViews()
    {
        return $this->views;
    }

    public function setViews(array $views)
    {
        $this->views = $views;
    }

    public function getSequences()
    {
        return $this->sequences;
    }

    public function setSequences(array $sequences)
    {
        $this->sequences = $sequences;
    }

    /**
     * Replace keys oid->name
     */
    public function indexByName()
    {

        if(count($this->tablesByName) == 0) {
            foreach ($this->tables as $table) {
                $this->tablesByName[$table->getName()] = $table->getOid();
            }
        }

        if(count($this->sequencesByName) == 0) {
            foreach ($this->sequences as $sequence) {
                $this->sequencesByName[$sequence->getName()] = $sequence->getOid();
            }
        }

        if(count($this->typesByName) == 0) {
            foreach ($this->types as $type) {
                $this->typesByName[$type->getName()] = $type->getOid();
            }
        }

        if(count($this->viewsByName) == 0) {
            foreach ($this->views as $view) {
                $this->viewsByName[$view->getName()] = $view->getOid();
            }
        }

        if(count($this->functionsByName) == 0) {
            foreach ($this->functions as $function) {
                $this->functionsByName[$function->getName()] = $function->getOid();
            }
        }

    }

    /**
     * @param $tableIndex
     * @param Column $column
     */
    public function addTableColumn($tableIndex, Column $column)
    {
        $this->tables[$tableIndex]->addColumn($column);
    }

    /**
     * @param $tableIndex
     * @param ForeignKey $fk
     */
    public function addTableFk($tableIndex, ForeignKey $fk)
    {
        $this->tables[$tableIndex]->addForeignKey($fk);
    }

    /**
     * @param $tableIndex
     * @param ParentTable $parent
     */
    public function addTableParentTable($tableIndex, ParentTable $parent)
    {
        $this->tables[$tableIndex]->addParentTable($parent);
    }

    /**
     * @param $tableIndex
     * @param ChildTable $parent
     */
    public function addTableChildTable($tableIndex, ChildTable $parent)
    {
        $this->tables[$tableIndex]->addChildTable($parent);
    }

    /**
     * @param $tableIndex
     * @param Rule $rule
     */
    public function addTableRule($tableIndex, Rule $rule)
    {
        $this->tables[$tableIndex]->addRule($rule);
    }

    /**
     * @param $tableIndex
     * @param Trigger $trigger
     */
    public function addTableTrigger($tableIndex, Trigger $trigger)
    {
        $this->tables[$tableIndex]->addTrigger($trigger);
    }

    /**
     * @param $tableIndex
     * @param int $ref
     */
    public function addTableReferenced($tableIndex, $ref)
    {
        $this->tables[$tableIndex]->addReferencedInTables($ref);
    }

    /**
     * @param $tableIndex
     * @param Index $index
     */
    public function addTableIndex($tableIndex, Index $index)
    {
        $this->tables[$tableIndex]->addIndex($index);
    }

}

