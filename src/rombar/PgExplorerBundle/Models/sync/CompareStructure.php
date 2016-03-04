<?php
/**
 * Created by PhpStorm.
 * User: Romain
 * Date: 15/04/2015
 * Time: 16:06
 */

namespace rombar\PgExplorerBundle\Models\sync;


use Doctrine\Bundle\DoctrineBundle\Registry;
use Monolog\Logger;
use rombar\PgExplorerBundle\Exceptions\StructureException;
use rombar\PgExplorerBundle\Models\PgMassRetriever;
use rombar\PgExplorerBundle\Models\Utils;
use Symfony\Component\HttpFoundation\Session\Session;
use rombar\PgExplorerBundle\Models\PgAnalyzer;
use rombar\PgExplorerBundle\Models\PgRetriever;
use rombar\PgExplorerBundle\Exceptions\ElementNotFoundException;

class CompareStructure {

    /**
     * @var Session
     */
    private $session;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @var PgAnalyzer
     */
    private $fromAnalyzer;

    /**
     * @var PgAnalyzer
     */
    private $toAnalyzer;

    /**
     * @var Parameters
     */
    private $parameters;

    private $nbItems = 0;

    private $nbItemsTested = 0;

    /**
     * @param Registry $doctrine
     * @param Session $session
     * @param Logger $logger
     * @param Parameters $parameters
     */
    public function __construct(Registry $doctrine, Session $session, Logger $logger, Parameters $parameters)
    {
        $this->doctrine = $doctrine;
        $this->session = $session;
        $this->logger = $logger;
        $this->parameters = $parameters;

        $fromRetriever = new PgRetriever($doctrine, $this->logger, $this->parameters->getManagerFrom() );
        $fromRetriever->setIndexType(PgRetriever::INDEX_TYPE_NAME);
        $fromMassRetriever = new PgMassRetriever($doctrine, $this->logger, $this->parameters->getManagerFrom());

        $toRetriever = new PgRetriever($doctrine, $this->logger, $this->parameters->getManagerTo() );
        $toRetriever->setIndexType(PgRetriever::INDEX_TYPE_NAME);
        $toMassRetriever = new PgMassRetriever($doctrine, $this->logger, $this->parameters->getManagerTo());

        $this->fromAnalyzer = new PgAnalyzer($this->logger, $fromRetriever, $fromMassRetriever);
        $this->toAnalyzer = new PgAnalyzer($this->logger, $toRetriever, $toMassRetriever);

        if($this->session->has(SyncHandler::SESSION_FROM_KEY)){
            $this->fromAnalyzer->setSchemas($this->session->get(SyncHandler::SESSION_FROM_KEY));
            $this->fromAnalyzer->initTables();
        }else{
            $this->fromAnalyzer->initSchemas();
            $this->fromAnalyzer->initSchemasElements();
            $this->fromAnalyzer->initCompareTableInfo();

            //$this->session->set(SyncHandler::SESSION_FROM_KEY, $this->fromAnalyzer->getSchemas());
        }

        if($this->session->has(SyncHandler::SESSION_TO_KEY)){
            $this->toAnalyzer->setSchemas($this->session->get(SyncHandler::SESSION_TO_KEY));
            $this->toAnalyzer->initTables();
        }else{
            $this->toAnalyzer->initSchemas();
            $this->toAnalyzer->initSchemasElements();
            $this->toAnalyzer->initCompareTableInfo();
            $this->toAnalyzer->initTables();

            //$this->session->set(SyncHandler::SESSION_TO_KEY, $this->toAnalyzer->getSchemas());
        }

    }

    /**
     * @throws StructureException
     */
    public function compareSchemas()
    {
        $this->nbItems = $this->fromAnalyzer->getNbSchemas();
        $this->nbItemsTested = 0;
        foreach($this->fromAnalyzer->getSchemaNames() as $name){
            $this->nbItemsTested++;
            if(!$this->toAnalyzer->hasSchema($name)){
                if($this->fromAnalyzer->nbTablesInSchema($name)){
                    throw new StructureException('Schema '.$name.' is missing.');
                }else{
                    $this->logger->addWarning('Schema '.$name.' has no tables!');
                }

            }
        }

    }

    /**
     * @throws StructureException
     */
    public function compareTables()
    {
        try{
            $this->nbItems = 0;
            $this->nbItemsTested = 0;
            foreach($this->fromAnalyzer->getSchemas() as $schemaName => $schema){
                $this->nbItems += count($schema->getTables());

                foreach($schema->getTables() as $fromTableName => $fromTable){
                    $this->nbItemsTested++;
                    $toTable = $this->toAnalyzer->getTableByName($schemaName, $fromTable->getName());

                    //From Here it's a deeper inspectiion of the table structure
                    $this->compareColumns($fromTable->getColumns(), $toTable->getColumns());
                }
            }
        }catch (ElementNotFoundException $ex){
            throw new StructureException('Problem in targeted database : '.$ex->getMessage());
        }catch(\Exception $ex){
            throw new StructureException($ex->getMessage());
        }

    }

    /**
     * @param array $fromCols
     * @param array $toCols
     * @throws StructureException
     * @throws \Exception
     */
    public function compareColumns($fromCols, $toCols)
    {
        $columnClass = new \ReflectionClass('rombar\PgExplorerBundle\Models\dbElements\Column');

        if(count($fromCols) == 0 || count($toCols) == 0){
            throw new \Exception('A table has no columns!');
        }

        $ignoredMethods = ['getTable', 'getRealPosition', 'getOid'];

        list($namesFrom, $namesTo) = $this->compareColumnsNames($fromCols, $toCols);

        foreach($namesFrom as $fromKey => $fromColName){
            $fromCol = $fromCols[$fromKey];
            $toCol = $toCols[array_search($fromColName, $namesTo)];

            foreach($columnClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method){

                if(preg_match('#^get#', $method->name) && !in_array($method->name, $ignoredMethods)){
                    $getter = $method->name;

                    if($fromCol->$getter() != $toCol->$getter()){
                        //Special hook for nextval.
                        // With certain search path, the schema can be omitted wich make a false positive.
                        if($getter == 'getDefault' && preg_match('/^nextval/', $fromCol->$getter())){
                            $diff = str_replace('.', '', Utils::stringDiff($fromCol->$getter(), $toCol->$getter()));
                            $this->logger->addDebug('diff search_path : '.$diff);

                            if(in_array($diff, $this->toAnalyzer->getSearchPath())
                                    || in_array($diff, $this->fromAnalyzer->getSearchPath())
                            ){
                                $this->logger->addWarning('Bypass by search_path test for '.$getter.' : ' .$fromCol->$getter().' vs '.$toCol->$getter());
                                continue;
                            }

                        }

                        $this->logger->addWarning('Column '.$fromColName.'->'.$getter.'() : ' .$fromCol->$getter().' vs '.$toCol->$getter());
                        var_dump($fromCol);
                        var_dump($toCol);
                        throw new StructureException('Targeted database has a different column '.$fromCol->getName().
                            ' in table '.$fromCol->getSchema().
                            PgAnalyzer::DB_SEPARATOR.$this->fromAnalyzer->getTable(
                                $fromCol->getSchema(),
                                $fromCol->getTable()
                            )->getName().
                            ' check the property '.substr($getter, 3) );
                    }
                }
            }
        }
    }

    /**
     * @param array $fromCols
     * @param array $toCols
     * @return array
     * @throws StructureException
     */
    private function compareColumnsNames($fromCols, $toCols)
    {
        $namesTo = [];
        foreach($toCols as $key => $col){
            $namesTo[$key] = $col->getName();
        }

        $namesFrom = [];
        foreach($fromCols as $key => $col){
            $namesFrom[$key] = $col->getName();
            if(!in_array($col->getName(), $namesTo)){
                throw new StructureException('Targeted database has a missing column '.$col->getName().' in table '.$col->getSchema().PgAnalyzer::DB_SEPARATOR.$col->getTable());
            }
        }
        return [$namesFrom, $namesTo];
    }

    /**
     * @throws StructureException
     */
    public function compareFunctions()
    {
        try{
            foreach($this->fromAnalyzer->getSchemas() as $schemaName => $schema){
                foreach($schema->getFunctions() as $fromFonctionName => $fromFonction){
                    $toFonction = $this->fromAnalyzer->getFunctionByName($schemaName, $fromFonctionName);
                    //From Here it's a deeper inspectiion of the function structure
                }
            }
        }catch (ElementNotFoundException $ex){
            throw new StructureException('Problem in targeted database : '.$ex->getMessage());
        }catch(\Exception $ex){
            throw new StructureException($ex->getMessage());
        }
    }

    /**
     * @return PgAnalyzer
     */
    public function getToAnalyzer()
    {
        return $this->toAnalyzer;
    }

    /**
     * @param PgAnalyzer $toAnalyzer
     */
    public function setToAnalyzer(PgAnalyzer $toAnalyzer)
    {
        $this->toAnalyzer = $toAnalyzer;
    }

    /**
     * @return PgAnalyzer
     */
    public function getFromAnalyzer()
    {
        return $this->fromAnalyzer;
    }

    /**
     * @param PgAnalyzer $fromAnalyzer
     */
    public function setFromAnalyzer(PgAnalyzer $fromAnalyzer)
    {
        $this->fromAnalyzer = $fromAnalyzer;
    }

    /**
     * @return Parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param Parameters $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @return int
     */
    public function getNbItemsTested()
    {
        return $this->nbItemsTested;
    }

    /**
     * @return int
     */
    public function getNbItems()
    {
        return $this->nbItems;
    }

}