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
            $this->fromAnalyzer->initAllTableInfo();

            $this->session->set(SyncHandler::SESSION_FROM_KEY, $this->fromAnalyzer->getSchemas());
        }

        if($this->session->has(SyncHandler::SESSION_TO_KEY)){
            $this->toAnalyzer->setSchemas($this->session->get(SyncHandler::SESSION_TO_KEY));
            $this->toAnalyzer->initTables();
        }else{
            $this->toAnalyzer->initSchemas();
            $this->toAnalyzer->initSchemasElements();
            $this->toAnalyzer->initAllTableInfo();
            $this->toAnalyzer->initTables();

            $this->session->set(SyncHandler::SESSION_TO_KEY, $this->toAnalyzer->getSchemas());
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
                throw new StructureException('Schema '.$name.' is missing.');
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

        foreach($fromCols as $fromName => $fromCol){

            $toCol = $toCols[$fromName];
            if(empty($toCol)){
                throw new StructureException('Targeted database has a missing column '.$fromCol->getName().' in table '.$fromCol->getSchema().PgAnalyzer::DB_SEPARATOR.$fromCol->getTable());
            }
            foreach($columnClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method){

                if(preg_match('#^get#', $method->name) && $method->name != 'getTable'){
                    $getter = $method->name;

                    if($fromCol->$getter() != $toCol->$getter()){
                        $this->logger->addWarning($getter.' : ' .$fromCol->$getter().' vs '.$toCol->$getter());
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