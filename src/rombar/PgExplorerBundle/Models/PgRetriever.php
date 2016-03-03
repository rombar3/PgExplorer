<?php
/**
 * Created by PhpStorm.
 * User: Romain
 * Date: 09/04/2015
 * Time: 17:12
 */

namespace rombar\PgExplorerBundle\Models;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\EntityManager;
use \Monolog\Logger;
use rombar\PgExplorerBundle\Models\dbElements\ChildTable;
use rombar\PgExplorerBundle\Models\dbElements\Column;
use rombar\PgExplorerBundle\Models\dbElements\Fonction;
use rombar\PgExplorerBundle\Models\dbElements\ForeignKey;
use rombar\PgExplorerBundle\Models\dbElements\Index;
use rombar\PgExplorerBundle\Models\dbElements\ParentTable;
use rombar\PgExplorerBundle\Models\dbElements\Rule;
use rombar\PgExplorerBundle\Models\dbElements\Trigger;

class PgRetriever {

    /**
     * http://www.postgresql.org/docs/9.0/static/catalog-pg-class.html
     * @var EntityManager
     */
    private $em;

    /**
     * @var Logger
     */
    private $logger;

    /**
     *
     * @var \Doctrine\DBAL\Driver
     */
    //private $cacheDriver;
    const CACHE_LIFETIME = 300; //5 min of cache
    const NAME_SPACE = 'rombar\\PgExplorerBundle\\Models\\';

    const INDEX_TYPE_OID = 'oid';
    const INDEX_TYPE_NAME = 'name';

    private $index_type = self::INDEX_TYPE_OID;

    /**
     * @param Registry $doctrine
     * @param Logger $logger
     * @param param string $managerName
     */
    public function __construct(Registry $doctrine, Logger $logger, $managerName = 'pg')
    {
        $this->em = $doctrine->getManager($managerName);
        $this->logger = $logger;

    }

    /**
     * @return EntityManager
     */
    public function getEm()
    {
        return $this->em;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param $schema
     * @param $tableOid
     * @return array
     */
    public function getColumns($schema, $tableOid)
    {
        //Filling the columns
        $sql = "SELECT attrelid as table,
                            a.attnum as oid,
                            a.attname as name,
                            pg_catalog.format_type(a.atttypid, a.atttypmod) as type,
                            (SELECT substring(pg_catalog.pg_get_expr(d.adbin, d.adrelid) for 128)
                             FROM pg_catalog.pg_attrdef d
                             WHERE d.adrelid = a.attrelid AND d.adnum = a.attnum AND a.atthasdef) as default,
                            (not a.attnotnull)::text as nullable,
                            a.attnum as position,
                            (SELECT c.collname FROM pg_catalog.pg_collation c, pg_catalog.pg_type t
                             WHERE c.oid = a.attcollation AND t.oid = a.atttypid AND a.attcollation <> t.typcollation) AS attcollation
                          FROM pg_catalog.pg_attribute a
                          WHERE a.attrelid = '" . pg_escape_string($tableOid) . "'
                            AND a.attnum > 0 AND NOT a.attisdropped
                          ORDER BY a.attnum
                        ";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('table', 'table');
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('type', 'type');
        $rsm->addScalarResult('default', 'default');
        $rsm->addScalarResult('nullable', 'nullable');
        $rsm->addScalarResult('position', 'position');
        $rsm->addScalarResult('attcollation', 'attcollation');

        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, self::CACHE_LIFETIME);

        $columns = [];

        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $column = new Column($schema);
            foreach ($row as $key => $value) {
                $column->__set($key, $value);
            }

            if($this->index_type == self::INDEX_TYPE_OID){
                $columns[$row['oid']] = $column;
            }elseif($this->index_type == self::INDEX_TYPE_NAME){
                $columns[$row['name']] = $column;
            }

        }

        return $columns;
    }

    /**
     * @param $schema
     * @param $tableOid
     * @return array
     */
    public function getIndexes($schema, $tableOid)
    {
        //Filling Indexes
        $sql = "SELECT c2.relname as name,
                                c2.relname as oid,
                                i.indisprimary as \"isPrimary\",
                                i.indisunique as \"isUnique\",
                                i.indisclustered as \"isClustered\",
                                i.indisvalid as \"isValid\",
                                pg_catalog.pg_get_indexdef(i.indexrelid, 0, true) as \"creationQuery\",
                                pg_catalog.pg_get_constraintdef(con.oid, true) as \"constraintDef\",
                                contype,
                                condeferrable,
                                condeferred,
                                c2.reltablespace
                        FROM pg_catalog.pg_class c
                            INNER JOIN pg_catalog.pg_index i ON c.oid = i.indrelid
                            INNER JOIN pg_catalog.pg_class c2 ON i.indexrelid = c2.oid
                            LEFT JOIN pg_catalog.pg_constraint con ON (conrelid = i.indrelid AND conindid = i.indexrelid AND contype IN ('p','u','x'))
                    WHERE c.oid = '" . pg_escape_string($tableOid) . "'
                    ORDER BY i.indisprimary DESC,
                        i.indisunique DESC,
                        c2.relname";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('isPrimary', 'isPrimary');

        $rsm->addScalarResult('isUnique', 'isUnique');
        $rsm->addScalarResult('isClustered', 'isClustered');
        $rsm->addScalarResult('isValid', 'isValid');
        $rsm->addScalarResult('creationQuery', 'creationQuery');

        $rsm->addScalarResult('constraintDef', 'constraintDef');
        $rsm->addScalarResult('contype', 'contype');
        $rsm->addScalarResult('condeferrable', 'condeferrable');
        $rsm->addScalarResult('condeferred', 'condeferred');
        $rsm->addScalarResult('reltablespace', 'reltablespace');
        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, self::CACHE_LIFETIME);

        $indexes = [];
        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $index = new Index($schema, $tableOid);
            foreach ($row as $key => $value) {
                if ($key == 'creationQuery') {
                    $data = explode('USING', $value);
                    if (count($data) == 2) {
                        $infos = explode(' ', trim($data[1]));
                        $type = $infos[0];
                        $columns = trim(substr($data[1], strlen($infos[0]) + 2));
                        $index->__set('type', $type);
                        $index->__set('columns', $columns);
                    } else {
                        $index->__set('type', $value);
                    }
                } else {
                    $index->__set($key, $value);
                }
            }

            if($this->index_type == self::INDEX_TYPE_OID){
                $indexes[$row['oid']] = $index;
            }elseif($this->index_type == self::INDEX_TYPE_NAME){
                $indexes[$row['name']] = $index;
            }

        }
        return $indexes;
    }

    /**
     * @param $schema
     * @param $tableOid
     * @return array
     */
    public function getForeignKeys($schema,$tableOid)
    {
        //Foreign Keys
        $sql = "SELECT conname as name,
                                conname as oid,
                                confrelid as \"parentTable\",
                                CASE WHEN confupdtype = 'a' THEN 'no action'
                                     WHEN confupdtype = 'r' THEN 'restrict'
                                     WHEN confupdtype = 'c' THEN 'cascade'
                                     WHEN confupdtype = 'n' THEN 'set null'
                                     WHEN confupdtype = 'd' THEN 'set default'
                                     ELSE NULL::text
                                END as  \"updateType\",
                                CASE WHEN confdeltype = 'a' THEN 'no action'
                                     WHEN confdeltype = 'r' THEN 'restrict'
                                     WHEN confdeltype = 'c' THEN 'cascade'
                                     WHEN confdeltype = 'n' THEN 'set null'
                                     WHEN confdeltype = 'd' THEN 'set default'
                                     ELSE NULL::text
                                END as  \"deleteType\",
                                CASE WHEN confmatchtype = 'f' THEN 'full'
                                    WHEN confmatchtype = 'p' THEN 'partial'
                                    WHEN confmatchtype = 'u' THEN 'simple'
                                    ELSE NULL::text
                                END as \"matchType\",
                                conkey as \"cols\",
                                confkey as \"refCols\",
                            pg_catalog.pg_get_constraintdef(r.oid, true) as \"creationQuery\"
                        FROM pg_catalog.pg_constraint r
                        WHERE  r.contype = 'f'
                            AND r.conrelid = '" . pg_escape_string($tableOid) . "'
                        ORDER BY 1";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');

        $rsm->addScalarResult('parentTable', 'parentTable');
        $rsm->addScalarResult('updateType', 'updateType');
        $rsm->addScalarResult('deleteType', 'deleteType');
        $rsm->addScalarResult('matchType', 'matchType');
        $rsm->addScalarResult('cols', 'cols');
        $rsm->addScalarResult('refCols', 'refCols');
        $rsm->addScalarResult('creationQuery', 'creationQuery');

        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, self::CACHE_LIFETIME);

        $foreignKeys = [];
        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $foreignKey = new ForeignKey($schema, $tableOid);
            foreach ($row as $key => $value) {
                $foreignKey->__set($key, (($key == 'cols' || $key == 'refCols') ? explode(',', str_replace(array('{', '}', ' '), '', $value)) : $value));
            }

            if($this->index_type == self::INDEX_TYPE_OID){
                $foreignKeys[$row['oid']] = $foreignKey;
            }elseif($this->index_type == self::INDEX_TYPE_NAME){
                $foreignKeys[$row['name']] = $foreignKey;
            }
        }

        return $foreignKeys;
    }

    /**
     * @param $tableOid
     * @return array
     */
    public function getReferencedInTable($tableOid)
    {
        //Get Table referencing this table
        $sql = "SELECT r.conrelid as oid
                        FROM pg_catalog.pg_constraint r
                        WHERE r.confrelid = '" . pg_escape_string($tableOid) . "'
                            AND r.contype = 'f'
                        ORDER BY 1";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, self::CACHE_LIFETIME);

        $referencedInTables = [];

        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $referencedInTables[] = $row['oid'];
        }
        return $referencedInTables;
    }

    /**
     * @param $schema
     * @param $tableOid
     * @return array
     */
    public function getTriggers($schema,$tableOid)
    {
        //Triggers
        $sql = "SELECT t.tgname as name,
                                t.oid as oid,
                                pg_catalog.pg_get_triggerdef(t.oid, true) as \"creationQuery\",
                                t.tgenabled as \"isEnabled\",
                                tgfoid as \"functionOid\",
                                p.proname as \"functionName\"
                        FROM pg_catalog.pg_trigger t
                            LEFT JOIN pg_catalog.pg_proc p ON t.tgfoid=p.oid
                        WHERE t.tgrelid = '" . pg_escape_string($tableOid) . "'
                            AND NOT t.tgisinternal
                        ORDER BY 1";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');

        $rsm->addScalarResult('creationQuery', 'creationQuery');
        $rsm->addScalarResult('isEnabled', 'isEnabled');
        $rsm->addScalarResult('functionOid', 'functionOid');
        $rsm->addScalarResult('functionName', 'functionName');
        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, self::CACHE_LIFETIME);

        $triggers = [];
        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $trigger = new Trigger($schema, $tableOid);
            foreach ($row as $key => $value) {
                $trigger->__set($key, $value);
            }

            if($this->index_type == self::INDEX_TYPE_OID){
                $triggers[$row['oid']] = $trigger;
            }elseif($this->index_type == self::INDEX_TYPE_NAME){
                $triggers[$row['name']] = $trigger;
            }

        }

        return $triggers;
    }

    /**
     * @param $schema
     * @param $tableOid
     * @return array
     */
    public function getRules($schema,$tableOid)
    {
        //Rules
        $sql = "SELECT r.rulename as oid,
                                r.rulename as name,
                                trim(trailing ';' from pg_catalog.pg_get_ruledef(r.oid, true)) as detail,
                                ev_enabled as enabled
                        FROM pg_catalog.pg_rewrite r
                        WHERE r.ev_class = '" . pg_escape_string($tableOid) . "'
                        ORDER BY 1";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');

        $rsm->addScalarResult('detail', 'detail');
        $rsm->addScalarResult('enabled', 'enabled');
        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, self::CACHE_LIFETIME);

        $rules = [];
        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $rule = new Rule($schema, $tableOid);
            foreach ($row as $key => $value) {
                $rule->__set($key, $value);
            }

            if($this->index_type == self::INDEX_TYPE_OID){
                $rules[$row['oid']] = $rule;
            }elseif($this->index_type == self::INDEX_TYPE_NAME){
                $rules[$row['name']] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @param $schema
     * @param $tableOid
     * @return array
     */
    public function getChildTables($schema,$tableOid)
    {
        //Child tables
        $sql = " SELECT c.oid,
                            c.oid::pg_catalog.regclass as name
                    FROM pg_catalog.pg_class c, pg_catalog.pg_inherits i
                    WHERE c.oid=i.inhrelid
                        AND i.inhparent = '" . pg_escape_string($tableOid) . "'
                    ORDER BY c.oid::pg_catalog.regclass::pg_catalog.text";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');
        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, self::CACHE_LIFETIME);

        $childs = [];
        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $child = new ChildTable($schema, $tableOid);
            foreach ($row as $key => $value) {
                $child->__set($key, $value);
            }
            
            if($this->index_type == self::INDEX_TYPE_OID){
                $childs[$row['oid']] = $child;
            }elseif($this->index_type == self::INDEX_TYPE_NAME){
                $childs[$row['name']] = $child;
            }
        }
        return $childs;
    }

    /**
     * @param $schema
     * @param $tableOid
     * @return array
     */
    public function getParentTables($schema,$tableOid)
    {
        //Inherit
        $sql = "
        WITH RECURSIVE inh AS (
                   SELECT i.inhrelid, i.inhparent
                   FROM pg_catalog.pg_inherits i
                   WHERE i.inhrelid = '" . pg_escape_string($tableOid) . "'
                   UNION
                   SELECT i.inhrelid, inh.inhparent
                   FROM inh INNER JOIN pg_catalog.pg_inherits i ON (inh.inhrelid = i.inhparent)
            )
            SELECT n.nspname as schema,
                   c.oid,
                   c.oid::pg_catalog.regclass as name,
                   c.relname as table_name,
                   --c2.relname as parent_name,
                   c2.oid as table
            FROM inh
                   INNER JOIN pg_catalog.pg_class c ON (inh.inhrelid = c.oid)
                   INNER JOIN pg_catalog.pg_namespace n ON (c.relnamespace = n.oid)
                   INNER JOIN pg_catalog.pg_class c2 ON (inh.inhparent = c2.oid)
        ";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('schema', 'schema');
        $rsm->addScalarResult('table', 'table');
        $rsm->addScalarResult('table_name', 'table_name');
        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, self::CACHE_LIFETIME);

        $parents = [];
        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $child = new ParentTable($schema, $row['table']);
            foreach ($row as $key => $value) {
                $child->__set($key, $value);
            }
            
            if($this->index_type == self::INDEX_TYPE_OID){
                $parents[$row['oid']] = $child;
            }elseif($this->index_type == self::INDEX_TYPE_NAME){
                $parents[$row['name']] = $child;
            }
        }

        return $parents;
    }

    /**
     * @param string $schema
     * @param string $oid
     * @return array
     */
    public function getFunctions($schema='',$oid='')
    {
        $oidSql = '';
        if(!empty($oid)){
            $oidSql = " AND p.oid = $oid";
        }

        $schemaSql = '';
        if(!empty($schema)){
            $schemaSql = " AND n.nspname='$schema'";
        }

        $sql = "SELECT p.oid,
            proowner as owner,
            n.nspname as schema,
            p.proname as name,
            pg_catalog.pg_get_function_result(p.oid) as \"resultDataType\",
            pg_catalog.pg_get_function_arguments(p.oid) as \"argumentDataTypes\",
           CASE
            WHEN p.proisagg THEN 'agg'
            WHEN p.proiswindow THEN 'window'
            WHEN p.prorettype = 'pg_catalog.trigger'::pg_catalog.regtype THEN 'trigger'
            ELSE 'normal'
          END as type,
            CASE WHEN not p.proisagg THEN pg_get_functiondef(p.oid) ELSE null END as code
          FROM pg_catalog.pg_proc p
               LEFT JOIN pg_catalog.pg_namespace n ON n.oid = p.pronamespace
          WHERE pg_catalog.pg_function_is_visible(p.oid) $oidSql $schemaSql
          ORDER BY 1,2,4";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('owner', 'owner');
        $rsm->addScalarResult('schema', 'schema');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('resultDataType', 'resultDataType');
        $rsm->addScalarResult('argumentDataTypes', 'argumentDataTypes');
        $rsm->addScalarResult('type', 'type');
        $rsm->addScalarResult('code', 'code');


        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $functions = [];
        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $fct = new Fonction($schema);
            foreach ($row as $key => $value) {
                $fct->__set($key, $value);
            }


            if($this->index_type == self::INDEX_TYPE_OID){
                $functions[$row['oid']] = $fct;
            }elseif($this->index_type == self::INDEX_TYPE_NAME){
                $functions[$row['name']] = $fct;
            }
        }

        return $functions;
    }

    public function setManager(Registry $doctrine, $name)
    {
        $this->em = $doctrine->getManager($name);
    }

    /**
     * @return string
     */
    public function getIndexType()
    {
        return $this->index_type;
    }

    /**
     * @param string $index_type
     */
    public function setIndexType($index_type)
    {
        $this->index_type = $index_type;
    }

}