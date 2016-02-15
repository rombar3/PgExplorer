<?php
/**
 * Created by PhpStorm.
 * User: Romain
 * Date: 20/04/2015
 * Time: 11:13
 */
namespace rombar\PgExplorerBundle\Models\sync;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\ResultSetMapping;
use rombar\PgExplorerBundle\Exceptions\SyncException;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Monolog\Logger;
use rombar\PgExplorerBundle\Models\dbElements\Table;
use Symfony\Component\HttpFoundation\Session\Session;
use rombar\PgExplorerBundle\Models\PgAnalyzer;
use rombar\PgExplorerBundle\Models\PgRetriever;
use rombar\PgExplorerBundle\Models\PgMassRetriever;

class PgSynchronizer {

    const MAX_TRY_TABLE_SYNC = 100;

    const NULL_VALUE = '##NULL##';

    const INSERT_VALUES_TABLE = 'f';

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

    private $alias = ['f'];

    /**
     * @var WeightCollection
     */
    private $weightCollection;

    const TABLE_NOT_SYNC = 'noSync';

    private $nbTables = 0;

    private $nbTablesSync = 0;

    /**
     * @param Registry $doctrine
     * @param Session $session
     * @param Logger $logger
     * @param Parameters $parameters
     * @throws SyncException
     */
    public function __construct(Registry $doctrine, Session $session, Logger $logger, Parameters $parameters)
    {
        $this->doctrine = $doctrine;
        $this->session = $session;
        $this->logger = $logger;
        $this->parameters = $parameters;

        $fromRetriever = new PgRetriever($doctrine, $this->logger, $this->parameters->getManagerFrom() );
        $fromRetriever->setIndexType(PgRetriever::INDEX_TYPE_OID);
        $fromMassRetriever = new PgMassRetriever($doctrine, $this->logger, $this->parameters->getManagerFrom());


        $this->fromAnalyzer = new PgAnalyzer($this->logger, $fromRetriever, $fromMassRetriever);


        if($this->session->has(SyncHandler::SESSION_FROM_KEY)){
            $this->fromAnalyzer->setSchemas($this->session->get(SyncHandler::SESSION_FROM_KEY));
            $this->fromAnalyzer->initTables();

        }else{
            throw new SyncException('No source data in session');
        }

        if($this->session->has(SyncHandler::SESSION_WEIGHT_COLLECTION_KEY)){
            $this->weightCollection = $this->session->get(SyncHandler::SESSION_WEIGHT_COLLECTION_KEY);
        }
    }

    public function initSync()
    {
        $this->fromAnalyzer->setShemaIndex();
        $this->session->set(SyncHandler::SESSION_FROM_KEY, $this->fromAnalyzer->getSchemas());
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
     * @param boolean $syncChild
     * @param string $childPattern
     * @return array
     * @throws SyncException
     */
    public function generateWeightCollection($syncChild, $childPattern)
    {
        $this->weightCollection = new WeightCollection();
        foreach($this->fromAnalyzer->getSchemas() as $schema){

            foreach($schema->getTables() as $table){
                try{
                    $tableName = $schema->getName().PgAnalyzer::DB_SEPARATOR.$table->getName().'('.$table->getOid().')';
                    $this->logger->addInfo('Test table '.$tableName.'
                        with '.count($table->getParentTables()).' parents'

                    );
                    if(count($table->getParentTables()) == 0){
                        $this->weightCollection->addTable($table);
                        $this->logger->addInfo('Add table '.$tableName);
                    }elseif(count($table->getParentTables())
                        && $syncChild && empty($childPattern)
                    ){
                        $this->weightCollection->addTable($table);
                        $this->logger->addInfo('Add table '.$tableName);
                    }elseif(count($table->getParentTables())
                        && $syncChild
                        && preg_match('/'.$childPattern.'/', $table->getName())
                    ){
                        $this->weightCollection->addTable($table);
                        $this->logger->addInfo('Add table '.$tableName);
                    }

                }catch (SyncException $ex){
                    $this->logger->addError($ex->getMessage());
                    $this->logger->addError($ex->getTraceAsString());
                    throw new SyncException($ex->getMessage());
                }

            }
        }
        $this->session->set(SyncHandler::SESSION_WEIGHT_COLLECTION_KEY, $this->weightCollection);
        return $this->weightCollection->getInfos();
    }

    /**
     * @param $weightId
     * @param $limit
     * @return array
     */
    public function syncWeight($weightId,$limit)
    {
        $message = 'Technical error';
        try{
            $this->doctrine->getConnection($this->parameters->getManagerTo())->beginTransaction();
            $this->nbTables = 0;
            $this->nbTablesSync = 0;

            $weight = $this->weightCollection->getWeight($weightId);
            $batch = $weight->getUnsyncTables($limit);

            if(count($batch) == 0){
                $message = 'No table to sync for weight '.$weightId.' and limit '.$limit;
            }
            $notSyncTable = [];
            foreach($batch as $tableOid){
                $this->nbTables++;
                $message = $this->syncTable($tableOid, $weight);
                if($message == self::TABLE_NOT_SYNC){
                    $table = $this->fromAnalyzer->getTableByOid($tableOid);
                    $notSyncTable[$tableOid] = $table->getSchema().PgAnalyzer::DB_SEPARATOR.$table->getName();
                }else{
                    $this->nbTablesSync++;
                }

            }

            if(count($notSyncTable) && $limit == 0){
                $nbTry = 0;
                while($nbTry <= self::MAX_TRY_TABLE_SYNC){

                    foreach(array_keys($notSyncTable) as $tableOid){
                        $message = $this->syncTable($tableOid, $weight);
                        if($message != self::TABLE_NOT_SYNC){
                            unset($notSyncTable[$tableOid]);
                            $this->nbTablesSync++;
                        }
                    }
                    if(count($notSyncTable) == 0){
                        break;
                    }
                    $nbTry++;
                }
                if($nbTry >= self::MAX_TRY_TABLE_SYNC){
                    $this->logger->addWarning('Some tables are not synchronisable at this state : '.implode(', ',$notSyncTable));
                }else{
                    $this->logger->addInfo('Dependencies solved in '.$nbTry.' loops');
                }
            }

            $this->doctrine->getConnection($this->parameters->getManagerTo())->commit();
            $this->updateWeight($weight);
        }catch (SyncException $ex){
            $this->doctrine->getConnection($this->parameters->getManagerTo())->rollback();
            $message = $ex->getMessage();
        }catch (\Exception $ex){
            $this->doctrine->getConnection($this->parameters->getManagerTo())->rollback();
            $this->logger->addError($ex->getMessage());
            $this->logger->addError($ex->getTraceAsString());
            $message = $ex->getMessage();
        }

        return $this->getReport($message);
    }

    /**
     * @param $message
     * @return array
     */
    private function getReport($message)
    {
        return [
            'ok' => ($message == SyncHandler::COMPARE_OK) ? 1 : 0,
            'message' => $message,
            'nbTables' => $this->nbTables,
            'nbTablesSync' => $this->nbTablesSync];
    }

    private function updateWeight(Weight $weight)
    {
        $this->weightCollection->setWeight($weight);
        $this->session->set(SyncHandler::SESSION_WEIGHT_COLLECTION_KEY, $this->weightCollection);
    }

    private function isTableSynchronizable(Table $table)
    {
       // $this->logger->addDebug('Table to analyze : '.$table->getSchema().PgAnalyzer::DB_SEPARATOR.$table->getName().' with '.count($table->getForeignKeys()).' fks');

        if(count($table->getForeignKeys())){
            foreach($table->getForeignKeys() as $fk){
                $parentTable = $this->fromAnalyzer->getTableByOid($fk->getParentTable());
               // $this->logger->addDebug('Parent Table to analyze : '.$parentTable->getSchema().PgAnalyzer::DB_SEPARATOR.$parentTable->getName());
                if($this->isTableSynchronized($parentTable->getOid(), count($parentTable->getForeignKeys()))){
                    return true;
                }else{
                    return false;
                }
            }
        }else{
            return true;
        }
    }

    /**
     * @param $tableOid
     * @param $weight
     * @return bool
     */
    private function isTableSynchronized($tableOid, $weight)
    {
        try{
            return $this->weightCollection->getWeight($weight)->isTableSynchronized($tableOid);
        }catch (\Exception $ex){
            $this->logger->addError($ex->getMessage());
            $this->logger->addError($ex->getTraceAsString());
            return false;
        }

    }

    /**
     * @param Table $table
     * @return array
     */
    private function getFromTableData(Table $table)
    {
        $tableFullName = $table->getSchema().PgAnalyzer::DB_SEPARATOR.$table->getName();

        $sql = "SELECT ";
        $rsm = new ResultSetMapping();

        foreach($table->getColumns() as $col){
            $sql .= "coalesce(".$col->getName() . "::text, '".self::NULL_VALUE."') as ".$col->getName().", ";
            $rsm->addScalarResult($col->getName(), $col->getName());
        }
        $sql = substr($sql, 0, strlen($sql) - 2);
        $sql .= " FROM ".$tableFullName;

        if($table->getPrimaryKey() && count($table->getPrimaryKey())){
            $sql .= " ORDER BY ".implode(' DESC , ',explode(',', str_replace(['(', ')'], '', $table->getPrimaryKey()->getColumns())));
        }


        if($this->getNbLinesInTable($tableFullName) > $this->parameters->getMaxNbLinesToInsert()){
            $sql .= " LIMIT ".$this->parameters->getMaxNbLinesToInsert();
        }

        $stmt = $this->doctrine->getManager($this->parameters->getManagerFrom())->createNativeQuery($sql, $rsm);
        return $stmt->getResult(AbstractQuery::HYDRATE_ARRAY);

    }

    private function getNbLinesInTable($tableFullName)
    {
        $sql = "SELECT count(*) as nb FROM ".pg_escape_string($tableFullName);

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('nb', 'nb');

        $stmt = $this->doctrine->getManager($this->parameters->getManagerFrom())->createNativeQuery($sql, $rsm);
        return $stmt->getSingleScalarResult();
    }

    /**
     * @param Table $table
     * @param $data
     * @return string
     */
    private function insertData(Table $table, $data)
    {
        $message = 'OK';

        try{
            $tableFullName = $table->getSchema().PgAnalyzer::DB_SEPARATOR.$table->getName();
            $cols = [];
            $types = [];
            foreach($table->getColumns() as $col){
                $cols[] = $col->getName();
                $types[$col->getName()] = $col->getType();
            }

            $sql = "INSERT INTO ".$tableFullName.'('.implode(', ', $cols).")
                    SELECT ".self::INSERT_VALUES_TABLE.".".pg_escape_string(implode(', f.', $cols))."
                    FROM (VALUES";
            foreach($data as $row){
                $sql .= '(';
                foreach($cols as $col){
                    $sql .= ((self::NULL_VALUE == $row[$col])?'NULL':"'".pg_escape_string($row[$col])."'")."::".pg_escape_string($types[$col]).', ';
                }
                $sql = substr($sql, 0, strlen($sql) - 2);
                $sql .= '),';

            }
            $sql = substr($sql, 0, strlen($sql) - 1);
            $sql .= " ) as ".self::INSERT_VALUES_TABLE."(".implode(', ', $cols).") ";

            //Ignore already existing primary keys
            $pk = $table->getPrimaryKey();
            $pkColumns = $cols; //No Primary Key, all columns are the primary key
            $tableAlias = $this->getTableAlias($table->getName());

            if($pk){
                $pkColumns = explode(',',
                    str_replace(
                        ['(', ')', ' '],
                        '',
                        $pk->getColumns()
                    )
                );
            }


            $sql .= " LEFT JOIN ".$tableFullName ." ".$tableAlias. " ON (".self::INSERT_VALUES_TABLE.".".implode(', '.self::INSERT_VALUES_TABLE.'.', $pkColumns).") = (".$tableAlias.".".implode(', '.$tableAlias.'.', $pkColumns).")";

            //Prevent problems with incomplete FK

            foreach($table->getForeignKeys() as $index => $fk){
                $parentTable = $this->fromAnalyzer->getTableByOid($fk->getParentTable());
                $parentAlias = $this->getTableAlias($parentTable->getName());
                $sql .= $this->getJoinCriteria($table, $parentTable, $parentAlias);
            }

            //Where Clause for left joins
            $sql .= " WHERE ".$tableAlias.".".$pkColumns[0]. " IS NULL";

            //Check for triggers
            if($table->hasTriggers()){
                $this->disableUserTriggers($table);
            }
            $this->doctrine->getConnection($this->parameters->getManagerTo())->executeQuery($sql);

            if($table->hasTriggers()){
                $this->enableUserTriggers($table);
            }

            //Clean alias
            $this->alias = ['f'];
        }catch (\Exception $ex){
            $errMessage = $ex->getMessage();
            if(strlen($errMessage) > 500){//Limitation to keep the log readable
                $errMessage = substr($ex->getMessage(), 0, 100).' ... '.substr($ex->getMessage(), -300);
            }
            $message = $errMessage;
            $this->logger->addError($errMessage);
            $this->logger->addError($ex->getTraceAsString());
        }

        return $message;
    }

    /**
     * @param $tableName
     * @return string
     */
    public function getTableAlias($tableName)
    {

        $alias = '';
        $alias2 = '';

        if (preg_match('#^[[:alnum:]]+_[[:alnum:]]+$#', $tableName)) {
            foreach (explode('_', $tableName) as $elmt) {
                $alias .= substr($elmt, 0, 1);
                $alias2 .= substr($elmt, 0, 3);
            }
            if ($alias == 'as') {//Reserved keyword
                $alias = $alias2;
            }
        } elseif (preg_match('#^[[:alnum:]]{3,}$#', $tableName)) {
            $alias = substr($tableName, 0, 1);

        } else {
            $alias = substr($tableName, 0, 3);
        }

        if(in_array($alias, $this->alias))
        {
            $newAlias = null;
            $iteration = 1;
            while(empty($newAlias)){
                $newAlias = $alias . '_'.$iteration;
                if(!in_array($newAlias, $this->alias)){
                    $alias = $newAlias;
                    break;
                }else{
                    $newAlias = null;
                }
                $iteration++;
            }
        }

        $this->alias[] = $alias;
        return $alias;
    }

    /**
     *
     * @param Table $table
     * @param Table $parentTable
     * @param $parentAlias
     * @return string
     * @throws \Exception
     * @internal param bool $strict default false. If false, search in both ways
     * @TODO Manage null value with foreign key. For now we do always inner join => Null value are ignored
     */
    private function getJoinCriteria(Table $table, Table $parentTable, $parentAlias)
    {
        $libelle = '';
        $joinType = 'INNER';
        //Look for the FK object
        if ($table && count($table->getForeignKeys()) > 0) {
            foreach ($table->getForeignKeys() as $fk) {

                //var_dump($fk);
                if ($fk->getParentTable() == $parentTable->getOid()) {
                    $cols = [];
                    $refCols = [];
                    $colNames = [];
                    $refColNames = [];
                    //From table
                    foreach ($fk->getCols() as $position) {
                        $col = null;
                        foreach($table->getColumns() as $colTable){
                            if($colTable->getPosition() == $position){
                               /* if($colTable->getNullable()){
                                    $joinType = 'LEFT';
                                }*/
                                $col = $colTable;
                                break;
                            }
                        }
                        if ($col) {
                            $cols[] = $col;
                            $colNames[] = self::INSERT_VALUES_TABLE.'.' . $col->getName();
                        } else {
                            throw new \Exception('Unknwon column position : ' . $position . print_r($table->getColumns(), true));
                        }
                    }

                    if (count($cols) == 0) {
                        throw new \Exception('No Column in the foreign key' . print_r($fk, true));
                    }

                    //Reference table
                    $index = 0;
                    foreach ($fk->getRefCols() as $position) {
                        $col = null;
                        foreach($parentTable->getColumns() as $colTable){
                            if($colTable->getPosition() == $position){
                                $col = $colTable;
                                break;
                            }
                        }
                        if ($col) {
                            if (count($cols) == 1 || $cols[$index]->getType() == $col->getType()) {
                                $refCols[] = $col;
                                $refColNames[] = $parentAlias . '.' . $col->getName();
                            } else {
                                throw new \Exception('Columns order not matching');
                            }
                        } else {
                            throw new \Exception('Unknwon column position : ' . $position . print_r($parentTable->getColumns(), true));
                        }
                        $index++;
                    }
                    $libelle .= " $joinType JOIN ".$parentTable->getSchema().PgAnalyzer::DB_SEPARATOR.$parentTable->getName()
                        .' '.$parentAlias
                        . ' ON (' . implode(', ', $colNames) . ') = (' . implode(', ', $refColNames) . ')';
                    break;
                }
            }
        }
        if (empty($libelle)) {
            throw new \Exception('No foreign key possible');
        }
        return $libelle;
    }

    /**
     * @param Table $table
     * @TODO Disable only active triggers
     */
    private function disableUserTriggers(Table $table)
    {
        $tableFullName = $table->getSchema().PgAnalyzer::DB_SEPARATOR.$table->getName();
        $sql = "ALTER TABLE ".$tableFullName." DISABLE TRIGGER USER";
        $this->doctrine->getConnection($this->parameters->getManagerTo())->executeQuery($sql);
    }

    /**
     * @param Table $table
     */
    private function enableUserTriggers(Table $table)
    {
        $tableFullName = $table->getSchema().PgAnalyzer::DB_SEPARATOR.$table->getName();
        $sql = "ALTER TABLE ".$tableFullName." ENABLE TRIGGER USER";
        $this->doctrine->getConnection($this->parameters->getManagerTo())->executeQuery($sql);
    }

    /**
     * @param $tableOid
     * @param $weight
     * @return string
     * @throws SyncException
     * @throws \rombar\PgExplorerBundle\Exceptions\ElementNotFoundException
     */
    private function syncTable($tableOid, Weight $weight)
    {
        $table = $this->fromAnalyzer->getTableByOid($tableOid);

        if ($this->isTableSynchronizable($table)) {
            $data = $this->getFromTableData($table);
            if(count($data) > 0){
                $message = $this->insertData($table, $data);
            }else{
                $this->logger->addInfo('Table '.$table->getSchema() . PgAnalyzer::DB_SEPARATOR . $table->getName().' is empty');
                $message = 'OK';//Nothing to do. Table is empty
            }


            if ($message == 'OK') {
                $weight->updateTableStatus($table->getOid(), true);
                return $message;
            } else {
                throw new SyncException('Table ' . $table->getSchema() . PgAnalyzer::DB_SEPARATOR . $table->getName() . ' not sync : ' . $message);
            }
        } else {
            $this->logger->addWarning('Table not Synchronizable (yet?) : ' . $table->getSchema() . PgAnalyzer::DB_SEPARATOR . $table->getName());
            return self::TABLE_NOT_SYNC;

        }
    }
}