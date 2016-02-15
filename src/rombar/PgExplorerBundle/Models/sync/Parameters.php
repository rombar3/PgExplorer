<?php
/**
 * Created by PhpStorm.
 * User: Romain
 * Date: 10/04/2015
 * Time: 15:21
 */

namespace rombar\PgExplorerBundle\Models\sync;


class Parameters {
    private $managerFrom;
    private $managerTo;
    private $testSchema;
    private $insertData;
    private $maxNbLinesToInsert;
    private $syncChild;
    private $childPattern;
    private $schemas;
    private $tables;

    /**
     * @return mixed
     */
    public function getManagerFrom()
    {
        return $this->managerFrom;
    }

    /**
     * @param mixed $managerFrom
     */
    public function setManagerFrom($managerFrom)
    {
        $this->managerFrom = $managerFrom;
    }

    /**
     * @return mixed
     */
    public function getManagerTo()
    {
        return $this->managerTo;
    }

    /**
     * @param mixed $managerTo
     */
    public function setManagerTo($managerTo)
    {
        $this->managerTo = $managerTo;
    }

    /**
     * @return mixed
     */
    public function getTestSchema()
    {
        return $this->testSchema;
    }

    /**
     * @param mixed $testSchema
     */
    public function setTestSchema($testSchema)
    {
        $this->testSchema = $testSchema;
    }

    /**
     * @return mixed
     */
    public function getInsertData()
    {
        return $this->insertData;
    }

    /**
     * @param mixed $insertData
     */
    public function setInsertData($insertData)
    {
        $this->insertData = $insertData;
    }

    /**
     * @return mixed
     */
    public function getMaxNbLinesToInsert()
    {
        return $this->maxNbLinesToInsert;
    }

    /**
     * @param mixed $maxNbLinesToInsert
     */
    public function setMaxNbLinesToInsert($maxNbLinesToInsert)
    {
        $this->maxNbLinesToInsert = $maxNbLinesToInsert;
    }

    /**
     * @return mixed
     */
    public function getSyncChild()
    {
        return $this->syncChild;
    }

    /**
     * @param mixed $syncChild
     */
    public function setSyncChild($syncChild)
    {
        $this->syncChild = $syncChild;
    }

    /**
     * @return mixed
     */
    public function getChildPattern()
    {
        return $this->childPattern;
    }

    /**
     * @param mixed $childPattern
     */
    public function setChildPattern($childPattern)
    {
        $this->childPattern = $childPattern;
    }

    /**
     * @return mixed
     */
    public function getSchemas()
    {
        return $this->schemas;
    }

    /**
     * @param mixed $schemas
     */
    public function setSchemas($schemas)
    {
        $this->schemas = $schemas;
    }

    /**
     * @return mixed
     */
    public function getTables()
    {
        return $this->tables;
    }

    /**
     * @param mixed $tables
     */
    public function setTables($tables)
    {
        $this->tables = $tables;
    }


}