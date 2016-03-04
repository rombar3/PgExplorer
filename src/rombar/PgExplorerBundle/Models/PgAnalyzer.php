<?php

namespace rombar\PgExplorerBundle\Models;

use rombar\PgExplorerBundle\Exceptions\ElementNotFoundException;
use \Monolog\Logger;
use rombar\PgExplorerBundle\Models\dbElements\Schema;
use rombar\PgExplorerBundle\Models\dbElements\Table;

/**
 * Description of PgAnalyzer
 * type NUL > app\logs\dev.log
 * @author barbu
 */
class PgAnalyzer {

    private $logger;

    private $schemas = [];

    private $tablesByOid = [];

    private $tablesByName = [];

    private $nbSchemas = 0;

    private $fullyLoaded = false;

    private $searchPath = [];

    /**
     * @var PgRetriever
     */
    private $retriever;

    /**
     *
     * @var \Doctrine\DBAL\Driver 
     */
    //private $cacheDriver;
    const CACHE_LIFETIME = 300; //5 min of cache

    const NAME_SPACE = 'rombar\\PgExplorerBundle\\Models\\dbElements\\';

    const DB_SEPARATOR = '.';

    /**
     * @var PgMassRetriever
     */
    private $massRetriever;

    /**
     * @param Logger $logger
     * @param PgRetriever $retriever
     * @param PgMassRetriever $massRetriever
     */
    public function __construct(Logger $logger, PgRetriever $retriever, PgMassRetriever $massRetriever)
    {
        $this->logger = $logger;
        $this->retriever = $retriever;
        $this->massRetriever = $massRetriever;
    }

    public function initSchemas()
    {
        if($this->nbSchemas == 0){
            $this->schemas = $this->massRetriever->getSchemas();
            $this->nbSchemas = count($this->schemas);
            $this->searchPath = $this->massRetriever->getSearchPath();
        }

    }

    public function initSchemasElements($schema = '')
    {

        $this->initSchemas();
        $this->logger->addDebug('After Init Schemas : '.(memory_get_usage (true ) / 1048576)." MB");
        foreach ($this->massRetriever->getSchemaElements($schema) as $row) {
            try {

                $classeName = self::NAME_SPACE . ucfirst($row['type']);
                if (class_exists($classeName)) {
                    $classe = new $classeName();
                    //\Doctrine\Common\Util\Debug::dump($classe);

                    foreach ($row as $key => $value) {
                        $classe->__set($key, $value);
                    }

                    $method = 'add' . ucfirst($row['type']);
                    $this->schemas[$row['schema']]->$method($classe);
                } else {
                    throw new \Exception('No class found for type ' . $row['type']);
                }
            } catch (\Exception $exc) {

                $this->logger->info('Not possible to add the element of type ' . $row['type'] . ' in schema '.$row['schema'].' : ' . $row['name'], $exc->getTrace());
                $this->logger->addError($exc->getMessage());
                //$this->logger->err($exc->getTraceAsString());
            }
        }
        $this->logger->addDebug('After getSchemaElements : '.(memory_get_usage (true ) / 1048576)." MB");
        foreach(array_keys($this->schemas) as $schemaName){
            $this->schemas[$schemaName]->setFunctions($this->getFunctionInfo($schemaName));

            $this->schemas[$schemaName]->indexByName();


        }
        $this->logger->addDebug('After getFunctionInfo : '.(memory_get_usage (true ) / 1048576)." MB");

    }

    /**
     *
     */
    public function initAllTableInfo()
    {
        $this->massRetriever->fillTableColumns($this->schemas);
        $this->logger->addDebug('After fillTableColumns : '.(memory_get_usage (true ) / 1048576)." MB");

        $this->massRetriever->fillTableFk($this->schemas);
        $this->logger->addDebug('After fillTableFk : '.(memory_get_usage (true ) / 1048576)." MB");

        $this->massRetriever->fillParentTables($this->schemas);
        $this->logger->addDebug('After fillParentTables : '.(memory_get_usage (true ) / 1048576)." MB");

        $this->massRetriever->fillChildTables($this->schemas);
        $this->logger->addDebug('After fillChildTables : '.(memory_get_usage (true ) / 1048576)." MB");
        $this->massRetriever->fillRuleTables($this->schemas);
        $this->logger->addDebug('After fillRuleTables : '.(memory_get_usage (true ) / 1048576)." MB");
        $this->massRetriever->fillTriggerTables($this->schemas);
        $this->logger->addDebug('After fillTriggerTables : '.(memory_get_usage (true ) / 1048576)." MB");
        $this->massRetriever->fillReferencedInTables($this->schemas);
        $this->logger->addDebug('After fillReferencedInTables : '.(memory_get_usage (true ) / 1048576)." MB");
        $this->massRetriever->fillIndexesTables($this->schemas);

        $this->logger->addInfo('After fillIndexesTables : '.(memory_get_usage (true ) / 1048576)." MB");

        $this->fullyLoaded = true;
    }

    public function initCompareTableInfo()
    {
        $this->massRetriever->fillTableColumns($this->schemas);
        $this->logger->addDebug('After fillTableColumns : '.(memory_get_usage (true ) / 1048576)." MB");
    }

    public function initDefaultTableInfo()
    {
        $this->massRetriever->fillParentTables($this->schemas);
        $this->logger->addDebug('After fillParentTables : '.(memory_get_usage (true ) / 1048576)." MB");
    }

    /**
     * @param $schema
     * @param $oid
     * @return null|Table
     */
    public function getTableInfo($schema, $oid)
    {

        //var_dump($schema);
        //var_dump($oid);
        $table = null;
        try {
            if(!isset($this->schemas[$schema])){
                throw new ElementNotFoundException('Unknown schema '.$schema);
            }
            $table = $this->schemas[$schema]->getATable($oid);
            if(!$this->fullyLoaded){
                $table->setColumns($this->retriever->getColumns($schema, $table->getOid()));
                $table->setForeignKeys($this->retriever->getForeignKeys($schema, $table->getOid()));
                $table->setIndexs($this->retriever->getIndexes($schema, $table->getOid()));
                $table->setTriggers($this->retriever->getTriggers($schema, $table->getOid()));
                $table->setChildTables($this->retriever->getChildTables($schema, $table->getOid()));
                $table->setParentTables($this->retriever->getParentTables($schema, $table->getOid()));
                $table->setRules($this->retriever->getRules($schema, $table->getOid()));
                $table->setReferencedInTables($this->retriever->getReferencedInTable($table->getOid()));
            }



        } catch (\Exception $exc) {

            $this->logger->info('Not possible to get the table information');
            $this->logger->err($exc->getMessage());
            $this->logger->err($exc->getTraceAsString());
        }
        return $table;
    }

    public function getSchemas()
    {
        return $this->schemas;
    }

    /**
     * 
     * @param string $schemaName
     * @return Schema
     * @throws ElementNotFoundException
     */
    public function getSchemasByName($schemaName)
    {
        if(!isset($this->schemas[$schemaName])){
            throw new ElementNotFoundException('Unknown schema : '.$schemaName);
        }
        return $this->schemas[$schemaName];
    }

    /**
     * @return int
     */
    public function getNbSchemas()
    {
        return $this->nbSchemas;
    }

    /**
     * 
     * @param string $schemaName
     * @param int $tableOid
     * @return Table
     * @throws ElementNotFoundException
     */
    public function getTable($schemaName, $tableOid)
    {
        if ($this->nbSchemas == 0) {
            $this->initSchemas();
            $this->initSchemasElements($schemaName);
        }
        if(!isset($this->schemas[$schemaName])){
            throw new ElementNotFoundException('Table oid ('.$tableOid.') not found in schema '.$schemaName);
        }
        return $this->getTableInfo($schemaName, $tableOid);
    }

    /**
     * @param $tableOid
     * @return Table
     * @throws ElementNotFoundException
     */
    public function getTableByOid($tableOid)
    {
        $this->initTables();

        if(!isset($this->tablesByOid[$tableOid])){
            throw new ElementNotFoundException('Table oid not found : '.$tableOid);
        }

        return $this->schemas[$this->tablesByOid[$tableOid]]->getATable($tableOid);
    }

    /**
     * @param $schemaName
     * @param $name
     * @return Table
     * @throws ElementNotFoundException
     */
    public function getTableByName($schemaName,$name)
    {
        $this->initTables();
        $key = $schemaName.self::DB_SEPARATOR.$name;
        if(!isset($this->tablesByName[$key])){
            throw new ElementNotFoundException('Table name not found : '.$key);
        }
        return $this->schemas[$schemaName]->getATable($this->tablesByName[$key]);
    }

    public function getFunctionByName($schemaName,$name)
    {
        if($this->retriever->getIndexType() == PgRetriever::INDEX_TYPE_NAME){
            return $this->schemas[$schemaName]->getAFunction($name);
        }else{
            throw new \Exception('Functionnality only availablie with PgRetriever::INDEX_TYPE_NAME activated. Feel free to contribute :)');
        }

    }

    public function initTables()
    {
        if (count($this->tablesByOid) == 0) {
            foreach ($this->schemas as $schemaName => $schema) {
                foreach ($schema->getTables() as $table) {
                    $this->tablesByOid[$table->getOid()] = $schemaName;
                }
            }
        }

        if (count($this->tablesByName) == 0) {
            foreach ($this->schemas as $schemaName => $schema) {
                foreach ($schema->getTables() as $table) {
                    $this->tablesByName[$schemaName.self::DB_SEPARATOR.$table->getName()] = ($this->retriever->getIndexType() == PgRetriever::INDEX_TYPE_OID) ? $table->getOid() : $table->getName();
                }
            }
        }
    }

    /**
     * 
     * @param string $tableName
     * @param string $schemaName
     * @return array(array(id, label))
     */
    public function searchTableForAutocomplete($tableName, $schemaName = '')
    {
        $data = [];

        if (empty($schemaName)) {

            foreach ($this->schemas as $schema) {
                foreach ($schema->getTables() as $table) {

                    if (empty($tableName)
                        || preg_match('#^' . $tableName . '#i', $table->getName())
                        || preg_match('#^' . $tableName . '#i', $schema->getName() . '.' . $table->getName())) {
                        $data[] = array('id' => $schema->getName() . ';' . $table->getOid(),
                                    'label' => $schema->getName() . '.' . $table->getName()
                        );
                    }
                }
            }
        } else {
            foreach ($this->schemas[$schemaName]->getTables() as $table) {

                if (empty($tableName) || preg_match('#^' . $tableName . '#i', $table->getName())) {
                    $data[] = array(
                        'id' => $schemaName . ';' . $table->getOid(),
                        'label' => $schemaName . '.' . $table->getName()
                    );
                }
            }
        }

        return $data;
    }
    
    /**
     * Get tables linked to a given table (reference by and referenced by)
     * @param Table $parentTable
     * @return array array<Tables>
     */
    public function getProximityTablesFrom(Table $parentTable)
    {
        $tables = [];


        foreach ($parentTable->getForeignKeys() as $fk) {

            $table = $this->getTableByOid($fk->getParentTable());
            if (!isset($tables[$table->getOid()])) {
                $tables[$table->getOid()] = $table;
            }
        }

        foreach ($parentTable->getReferencedInTables() as $oid) {
            $table = $this->getTableByOid($oid);
            if (!isset($tables[$table->getOid()])) {
                $tables[$table->getOid()] = $table;
            }
        }
        return $tables;
    }

    /**
     * Return the SQL code and information of a given function
     * @param string $schema
     * @param string $oid
     * @return array array<Function>
     */
    public function getFunctionInfo($schema='',$oid='')
    {

        
        return $this->retriever->getFunctions($schema, $oid);
    }

    /**
     * @param array $schemas
     */
    public function setSchemas($schemas)
    {
        $this->schemas = $schemas;
    }

    /**
     * @param $schemaName
     * @return bool
     */
    public function hasSchema($schemaName)
    {
        return (isset($this->schemas[$schemaName])) ? true : false;
    }

    /**
     * @param $schemaName
     * @return int
     * @throws ElementNotFoundException
     */
    public function nbTablesInSchema($schemaName)
    {
        if(isset($this->schemas[$schemaName])){
            return count($this->schemas[$schemaName]->getTables());
        }else{
            throw new ElementNotFoundException('Schema does not exist : '.$schemaName);
        }
    }

    /**
     * @return array
     */
    public function getSchemaNames()
    {
        return array_keys($this->schemas);
    }

    public function setShemaIndex()
    {
        foreach($this->getSchemas() as $key => $schema){
            $schema->indexByName();
            $this->schemas[$key] = $schema;
        }
    }

    /**
     * @return array
     */
    public function getSearchPath()
    {
        return $this->searchPath;
    }

}
