<?php

namespace rombar\PgExplorerBundle\Models;
use rombar\PgExplorerBundle\Models\dbElements\Table;

/**
 * Query mapper.
 * Stored in session!
 *
 * @author barbu
 */
class SqlGenerator {

    private $columns = [];
    private $tables = [];
    private $joins = [];
    private $groupBy = [];
    private $orderBy = [];
    private $tableAlias = [];

    private function generateTableAlias($tableName) {
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
            $alias2 = substr($tableName, 0, 3);
        } else {
            $alias = substr($tableName, 0, 3);
        }
        //var_dump($alias);
        if (in_array($alias, $this->tableAlias)) {
            if (!$alias2) {
                $alias = $alias . '2';
                $this->tableAlias[] = $alias;
            }if (in_array($alias2, $this->tableAlias)) {
                $alias = $alias2 . '2';
                $this->tableAlias[] = $alias;
            } else {
                $alias = $alias2;
                $this->tableAlias[] = $alias;
            }
        } else {
            $this->tableAlias[] = $alias;
        }

        return $alias;
    }

    /**
     * 
     * @param Table $table
     * @param Table $parentTable
     * @param boolean $strict default false. If false, search in both ways
     * @return string
     * @throws \Exception
     */
    private function getJoinCriteria(Table $table, Table $parentTable, $strict = false) {
        $libelle = '';

        //Look for the FK object
        if ($table && count($table->getForeignKeys()) > 0) {
            foreach ($table->getForeignKeys() as $fk) {

                //var_dump($fk);
                if ($fk->getParentTable() == $parentTable->getOid()) {
                    $cols = [];
                    $refCols = [];
                    $colNames = [];
                    $refColNames = [];
                    //var_dump($fk->getCols());
                    foreach ($fk->getCols() as $colId) {
                        $col = $table->getAColumn($colId);
                        if ($col) {
                            $cols[] = $col;
                            $colNames[] = $table->getAlias() . '.' . $col->getName();
                        } else {
                            throw new \Exception('Unknwon column ID : ' . $colId . print_r($table->getColumns(), true));
                        }
                    }

                    if (count($cols) == 0) {
                        throw new \Exception('No Column in the foreign key' . print_r($fk, true));
                    }

                    $index = 0;
                    foreach ($fk->getRefCols() as $colId) {
                        $col = $parentTable->getAColumn($colId);
                        if ($col) {
                            if (count($cols) == 1 || $cols[$index]->getType() == $col->getType()) {
                                $refCols[] = $col;
                                $refColNames[] = $parentTable->getAlias() . '.' . $col->getName();
                            } else {
                                throw new \Exception('Columns order not matching');
                            }
                        } else {
                            throw new \Exception('Unknwon column ID : ' . $colId . print_r($parentTable->getColumns(), true));
                        }
                        $index++;
                    }

                    $libelle = '(' . implode(', ', $colNames) . ') = (' . implode(', ', $refColNames) . ')';
                    break;
                }
            }
        }

        if (empty($libelle) && !$strict) {
            $libelle = $this->getJoinCriteria($parentTable, $table, true);
        }

        if (empty($libelle)) {
            throw new \Exception('No foreign key possible');
        }
        return $libelle;
    }

    /**
     *
     * @param PgAnalyzer $pgAnalyzer
     * @param string $name
     * @param string $id
     * @param string $parent
     * @return string
     * @throws Exception
     * @throws \Exception
     */
    public function addAnElement(PgAnalyzer $pgAnalyzer, $name, $id, $parent = '') {
        $libelle = '';
        switch ($name) {
            case 'table':
                $ids = explode(';', $id);
                if (count($ids) == 2) {
                    $table = $pgAnalyzer->getTableInfo($ids[0], $ids[1]);
                    $table->__set('alias', $this->generateTableAlias($table->getName()));
                    $this->tables[$id] = $table;
                    $libelle = $ids[0] . '.' . $table->getName() . ' as ' . $table->getAlias();
                }
                break;
            case 'joinTable':
                $ids = explode(';', $id);
                if (count($ids) == 2) {
                    $table = $pgAnalyzer->getTableInfo($ids[0], $ids[1]);
                    $parentTable = null;
                    $position = null;
                    foreach ($this->tables as $key => $tbl) {
                        if ($tbl->getOid() == intval($parent)) {
                            $parentTable = $tbl;
                            $position = $key;
                            break;
                        }
                    }

                    if (empty($parentTable)) {
                        throw new \Exception('Parent table not found');
                    } elseif (!$parentTable->getJoins()) {
                        $parentTable->__set('joins', []);
                    }
                    $joins = $parentTable->getJoins();

                    $table->__set('alias', $this->generateTableAlias($table->getName()));

                    $joins[] = $table;
                    $parentTable->__set('joins', $joins);
                    $this->tables[$position] = $parentTable;


                    if (in_array($parentTable->getOid(), array_keys($this->getProximityTablesFrom($table, $pgAnalyzer)))) {//Is the selected table at proximity to the parent table?
                        $libelle = $ids[0] . '.' . $table->getName() . ' as ' . $table->getAlias() . ' ON ' . $this->getJoinCriteria($table, $parentTable);
                    } else {//We have to look in the join tables
                        foreach ($parentTable->getJoins() as $tble) {
                            if ($table->getOid() != $tble->getOid()) {
                                if (in_array($tble->getOid(), array_keys($this->getProximityTablesFrom($table, $pgAnalyzer)))) {//Is the selected table at proximity to the joined table?
                                    $libelle = $ids[0] . '.' . $table->getName() . ' as ' . $table->getAlias() . ' ON ' . $this->getJoinCriteria($table, $tble);
                                }
                            }
                        }
                    }
                }
                break;
            case 'column':
                $ids = explode(';', $id);
                if (count($ids) == 3) {

                    if (count(explode('-', $ids[1])) == 2) {
                        $data = explode('-', $ids[1]);
                        $tableID = $ids[0] . ';' . $data[0];
                        $parentTable = $this->tables[$tableID];
                        if ($parentTable->getJoins()) {
                            $joins = $parentTable->getJoins();
                            $table = $joins[$data[1]];
                        } else {
                            throw new Exception('No Joined table found.');
                        }
                    } else {
                        $tableID = $ids[0] . ';' . $ids[1];
                        $table = $this->tables[$tableID];
                    }

                    //var_dump($this->tables[$tableID]->getAColumn($ids[2]));
                    $col = $table->getAColumn($ids[2]);
                    $col->__set('tableAlias', $table->getAlias());
                    $this->columns[$id] = $col;
                    $libelle = $table->getAlias() . '.' . $col->getName();
                }
                break;
            case 'groupBy':
                $this->groupBy[] = $id;
                $libelle = $id;
                break;
            case 'orderBy':
                $this->orderBy[] = $id;
                $libelle = $id;
                break;
            default:
                throw new \Exception('Unknown element : ' . $name);
                break;
        }
        return $libelle;
    }

    public function searchColumnForAutocomplete($colName) {
        $data = [];

        foreach ($this->tables as $id => $table) {
            foreach ($table->getColumns() as $col) {

                if (preg_match('#^' . $colName . '#i', $col->getName()) || preg_match('#^' . $colName . '#i', $table->getName() . '.' . $col->getName()) || preg_match('#^' . $colName . '#i', $table->getAlias() . '.' . $col->getName())) {
                    $data[] = array('id' => $id . ';' . $col->getOid(), 'label' => $table->getAlias() . '.' . $col->getName());
                }
            }
            if ($table->getJoins()) {
                foreach ($table->getJoins() as $position => $join) {
                    foreach ($join->getColumns() as $col) {
                        if (preg_match('#^' . $colName . '#i', $col->getName()) || preg_match('#^' . $colName . '#i', $join->getName() . '.' . $col->getName()) || preg_match('#^' . $colName . '#i', $join->getAlias() . '.' . $col->getName())) {
                            $data[] = array('id' => $id . '-' . $position . ';' . $col->getOid(), 'label' => $join->getAlias() . '.' . $col->getName());
                        }
                    }
                }
            }
        }
        return $data;
    }

    public function searchGroupByForAutocomplete($colName) {
        $data = [];
        if (preg_match('#^[1-9]{1}[0-9]*$#', intval($colName)) && count($this->columns) >= intval($colName)) {
            $data[] = array('id' => intval($colName), 'label' => intval($colName));
        } else {
            foreach ($this->columns as $col) {

                if (preg_match('#^' . $colName . '#i', $col->getName())) {
                    $data[] = array('id' => $col->getTableAlias() . '.' . $col->getName(), 'label' => $col->getTableAlias() . '.' . $col->getName());
                }
            }
        }
        return $data;
    }

    public function searchOrderByForAutocomplete($colName) {
        $data = [];

        if (preg_match('#^[1-9]{1}[0-9]*$#', intval($colName)) && count($this->columns) >= intval($colName)) {
            $data[] = array('id' => intval($colName), 'label' => intval($colName));
        } else {
            foreach ($this->columns as $col) {

                if (preg_match('#^' . $colName . '#i', $col->getName())) {
                    $data[] = array('id' => $col->getTableAlias() . '.' . $col->getName(), 'label' => $col->getTableAlias() . '.' . $col->getName());
                }
            }
        }
        return $data;
    }

    /**
     * 
     * @param string $term
     * @param string $schemaName
     * @param string $tableName
     * @param PgAnalyzer $pgAnalyzer
     * @return array
     */
    public function searchJoinTableForAutocomplete($term, $schemaName, $tableName, PgAnalyzer $pgAnalyzer) {
        $parentTable = null;
        $data = [];
        //Get parent table
        foreach ($this->tables as $table) {

            //echo $table->getSchema().".".$table->getName()." vs ".$schemaName.".".$tableName;
            if ($table->getSchema() == $schemaName && $table->getName() == $tableName) {
                $parentTable = $table;
                break;
            }
        }

        //Search $term among tables linked to the parent table

        foreach ($this->getProximityTablesFrom($parentTable, $pgAnalyzer) as $table) {
            if ($table && (empty($term) || preg_match('#^' . $term . '#i', $table->getName()) || preg_match('#^' . $term . '#i', $table->getSchema() . '.' . $table->getName()))) {
                $data[] = array('id' => $table->getSchema() . ';' . $table->getOid(), 'label' => $table->getSchema() . '.' . $table->getName(), 'value' => $parentTable->getOid());
            }
        }

        return $data;
    }

    /**
     * Get tables linked to a given table (reference, referenced by and among joins)
     * @param Table $parentTable
     * @param PgAnalyzer $pgAnalyzer
     * @return array<Table>
     */
    private function getProximityTablesFrom(Table $parentTable, PgAnalyzer $pgAnalyzer) {
        $tables = [];

        foreach ($pgAnalyzer->getProximityTablesFrom($parentTable) as $tbId => $tble) {
            if (!isset($tables[$tbId])) {//Avoid circular referencing and double values
                $tables[$tbId] = $tble;
            }
        }


        if ($parentTable->getJoins()) {
            foreach ($parentTable->getJoins() as $table) {
                foreach ($pgAnalyzer->getProximityTablesFrom($table) as $tbId => $tble) {
                    if (!isset($tables[$tbId])) {//Avoid circular referencing and double values
                        $tables[$tbId] = $tble;
                    }
                }
            }
        }

        return $tables;
    }

    /**
     * 
     * @param PgAnalyzer $pgAnalyzer
     * @return string
     */
    public function generateSql(PgAnalyzer $pgAnalyzer) {
        $sql = '';
        if (count($this->columns) > 0) {
            $sql .="SELECT ";

            foreach ($this->columns as $col) {
                $sql .= $col->getTableAlias() . '.' . $col->getName() . ",";
            }
            $sql = substr($sql, 0, (strlen($sql) - 1));
        }

        if (count($this->tables) > 0) {
            $sql .=" FROM ";
            foreach ($this->tables as $table) {
                $joins = '';

                if ($table->getJoins()) {
                    foreach ($table->getJoins() as $joinTable) {
                        if (in_array($table->getOid(), array_keys($this->getProximityTablesFrom($joinTable, $pgAnalyzer)))) {//Is the selected table at proximity to the parent table?
                            $joins .= ' INNER JOIN ' . $joinTable->getSchema() . '.' . $joinTable->getName() . ' as ' . $joinTable->getAlias() . ' ON ' . $this->getJoinCriteria($joinTable, $table);
                        } else {//We have to look in the join tables
                            foreach ($table->getJoins() as $tble) {
                                if ($joinTable->getOid() != $tble->getOid()) {
                                    if (in_array($tble->getOid(), array_keys($this->getProximityTablesFrom($joinTable, $pgAnalyzer)))) {//Is the selected table at proximity to the joined table?
                                        $joins .= ' INNER JOIN ' . $joinTable->getSchema() . '.' . $joinTable->getName() . ' as ' . $joinTable->getAlias() . ' ON ' . $this->getJoinCriteria($joinTable, $tble);
                                    }
                                }
                            }
                        }
                    }
                }

                $sql .= $table->getSchema() . '.' . $table->getName() . ' as ' . $table->getAlias() . $joins . ",";
            }
            $sql = substr($sql, 0, (strlen($sql) - 1));
        }

        if (count($this->groupBy) > 0) {
            $sql .=" GROUP BY ";
            foreach ($this->groupBy as $col) {
                $sql .= $col . ",";
            }
            $sql = substr($sql, 0, (strlen($sql) - 1));
        }

        if (count($this->orderBy) > 0) {
            $sql .=" ORDER BY ";
            foreach ($this->orderBy as $col) {
                $sql .= $col . ",";
            }
            $sql = substr($sql, 0, (strlen($sql) - 1));
        }
        return $sql;
    }

    public function __set($name, $value) {
        if (!isset($this->$name) || !empty($this->$value)) {
            $this->$name = $value;
        }
    }

    public function __get($name) {
        if (isset($this->$name)) {
            return $this->$name;
        } else {
            return null;
        }
    }

    public function __call($method, $arguments) {
        /**
         * To fill the collections. Method as to be like add<Pg element>. Exemple addTable(Table $table) will do $this->tables[$table->getOid()] = $table
         */
        if (preg_match('#^add([[:alnum:]]{1,})$#', $method, $matches) && is_array($arguments) && count($arguments) == 1 && is_array($arguments[0])) {
            $element = $matches[1];
            $attribut = strtolower($element) . 's';

            if (isset($this->$attribut) && is_array($this->$attribut)) {
                //\Doctrine\Common\Util\Debug::dump($arguments[0]->getOid());
                $tableau = &$this->$attribut;
                $tableau[] = $arguments[0];
            }
        } elseif (preg_match('#^getA(Table|Join|Column|GroupBy|OrderBy)$#', $method, $matches) && count($arguments) == 1) {
            $element = $matches[1];
            $attribut = strtolower($element) . 's';
            $tableau = &$this->$attribut;
            return $tableau[$arguments[0]];
        } elseif (preg_match('#^get([[:alnum:]]{1,})$#', $method, $matches) && count($arguments) == 0) {
            return $this->__get(strtolower($matches[1]));
        }
    }

}

?>
