<?php
/**
 * Created by PhpStorm.
 * User: Romain
 * Date: 17/04/2015
 * Time: 14:31
 */

namespace rombar\PgExplorerBundle\Models\sync;


use rombar\PgExplorerBundle\Exceptions\SyncException;
use rombar\PgExplorerBundle\Models\DbElements\Table;

class WeightCollection {

    private $weights = [];

    /**
     * @param Table $table
     * @throws SyncException
     */
    public function addTable(Table $table)
    {
        $weight = count($table->getForeignKeys());

        if(!isset($this->weights[$weight])){
            $weightContainer = new Weight();
            $weightContainer->setWeight($weight);
            $this->weights[$weight] = $weightContainer;
        }
        $this->weights[$weight]->addTableStatus($table->getOid());
    }

    /**
     * @param $weightId
     * @return Weight
     * @throws SyncException
     */
    public function getWeight($weightId)
    {
        if(!isset($this->weights[$weightId]))
        {
            throw new SyncException('No tables for weight '.$weightId);
        }
        return $this->weights[$weightId];
    }

    /**
     * @param Weight $weight
     */
    public function setWeight(Weight $weight)
    {
        $this->weights[$weight->getWeight()] = $weight;
    }

    /**
     * @return array
     */
    public function getInfos()
    {
        $infos = [];
        foreach($this->weights as $key => $weight){
            $infos[$key] = [
                'weight' => $key,
                'nbTables' => $weight->getNbTables(),
                'limit' => $weight->getLimit()];
        }

        return $infos;
    }
}