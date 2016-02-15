<?php
/**
 * Created by PhpStorm.
 * User: Romain
 * Date: 17/04/2015
 * Time: 14:20
 */

namespace rombar\PgExplorerBundle\Models\sync;


use rombar\PgExplorerBundle\Exceptions\SyncException;

class Weight {

    private $weight;

    private $limit = 0;

    private $nbTables;

    private $tablesStatus = [];

    const MAX_TABLES_PER_REQUEST = 2;

    /**
     * @return mixed
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param mixed $weight
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    /**
     * @return mixed
     */
    public function getNbTables()
    {
        return $this->nbTables;
    }

    /**
     * @param mixed $nbTables
     */
    public function setNbTables($nbTables)
    {
        $this->nbTables = $nbTables;
    }

    /**
     * @return array
     */
    public function getTablesStatus()
    {
        return $this->tablesStatus;
    }

    /**
     * @param array $tablesStatus
     */
    public function setTablesStatus($tablesStatus)
    {
        $this->tablesStatus = $tablesStatus;
    }

    /**
     * @param $tableId
     * @param bool $status
     * @throws SyncException
     */
    public function addTableStatus($tableId, $status = false)
    {
        if(isset($this->tablesStatus[$tableId])){
            throw new SyncException('Table ID already exists : '.$tableId);
        }else{
            $this->tablesStatus[$tableId] = $status;
            $this->nbTables = count($this->tablesStatus);
            if($this->nbTables > self::MAX_TABLES_PER_REQUEST){
                $this->limit = self::MAX_TABLES_PER_REQUEST;
            }
        }
    }

    /**
     * @param $tableId
     * @param bool $status
     * @throws SyncException
     */
    public function updateTableStatus($tableId,$status = true)
    {
        if(isset($this->tablesStatus[$tableId])) {
            $this->tablesStatus[$tableId] = $status;
        }else{
            throw new SyncException('Table ID does not exist : '.$tableId);
        }
    }

    /**
     * @param int $limit
     * @return array
     */
    public function getUnsyncTables($limit)
    {
        $tablesToSync = [];

        foreach($this->tablesStatus as $tableId => $status){
            if($status === false){
                $tablesToSync[] = $tableId;
            }

            if($limit > 0 && count($tablesToSync) == intval($limit)){
                break;
            }
        }
        return $tablesToSync;
    }

    public function isTableSynchronized($tableOid)
    {
        if(isset($this->tablesStatus[$tableOid])){
            return $this->tablesStatus[$tableOid];
        }else{
            throw new SyncException('Unknown table oid : '.$tableOid);
        }
    }
}