<?php
/**
 * Created by PhpStorm.
 * User: Romain
 * Date: 25/10/2015
 * Time: 10:45
 */

namespace rombar\PgExplorerBundle\Models;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\EntityManager;
use \Monolog\Logger;
use rombar\PgExplorerBundle\Exceptions\ElementNotFoundException;
use rombar\PgExplorerBundle\Models\dbElements\ChildTable;
use rombar\PgExplorerBundle\Models\dbElements\Column;
use rombar\PgExplorerBundle\Models\dbElements\Fonction;
use rombar\PgExplorerBundle\Models\dbElements\ForeignKey;
use rombar\PgExplorerBundle\Models\dbElements\Index;
use rombar\PgExplorerBundle\Models\dbElements\ParentTable;
use rombar\PgExplorerBundle\Models\dbElements\Rule;
use rombar\PgExplorerBundle\Models\dbElements\Schema;
use rombar\PgExplorerBundle\Models\dbElements\Trigger;

class PgMassRetriever
{

    /**
     * http://www.postgresql.org/docs/9.0/static/catalog-pg-class.html
     * @var EntityManager
     */
    private $em;

    /**
     * @var Logger
     */
    private $logger;

    const DIVIDER  = 1000;

    private $index_type = PgRetriever::INDEX_TYPE_OID;

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
     * @param EntityManager $em
     */
    public function setEm($em)
    {
        $this->em = $em;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param Logger $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public function getSchemas()
    {
        $sql = "SELECT n.nspname AS name,
                pg_catalog.pg_get_userbyid(n.nspowner) AS owner
              FROM pg_catalog.pg_namespace n
              WHERE n.nspname !~ '^pg_' AND n.nspname <> 'information_schema'
              ORDER BY 1";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('owner', 'owner');


        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, PgRetriever::CACHE_LIFETIME);

        $schemas = [];

        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $schema = new Schema();
            foreach ($row as $key => $value) {
                $method = 'set' . ucfirst($key);
                $schema->$method($value);
            }
            $schemas[$schema->getName()] = $schema;
        }

        return $schemas;
    }

    /**
     * @param $schema
     * @return array
     */
    public function getSchemaElements($schema)
    {
        $this->em->clear();
        $sql = "SELECT n.nspname as schema,
                c.relname as name,
                CASE c.relkind WHEN 'r' THEN 'table'
                    WHEN 'v' THEN 'view'
                    WHEN 'i' THEN 'index'
                    WHEN 'S' THEN 'sequence'
                    WHEN 's' THEN 'special'
                    WHEN 'f' THEN 'foreign table'
                END as type,
                pg_catalog.pg_get_userbyid(c.relowner) as owner,
                c.oid,
                c.relchecks,
                c.relkind,
                c.relhasindex,
                c.relhasrules,
                c.relhastriggers,
                c.relhasoids,
                c.reltablespace,
                CASE WHEN c.reloftype = 0 THEN '' ELSE c.reloftype::pg_catalog.regtype::pg_catalog.text END as reloftype,
                c.relpersistence
              FROM pg_catalog.pg_class c
                   LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
              WHERE c.relkind IN ('r','v','S','f','')
                    AND n.nspname <> 'pg_catalog'
                    AND n.nspname <> 'information_schema'
                    AND n.nspname !~ '^pg_toast'
                    --AND pg_catalog.pg_table_is_visible(c.oid)
                    " . (($schema != '') ? " AND n.nspname = '" . pg_escape_string($schema) . "'" : '') . "
              ORDER BY 1,3,2";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('schema', 'schema');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('type', 'type');
        $rsm->addScalarResult('owner', 'owner');

        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('relchecks', 'relchecks');
        $rsm->addScalarResult('relkind', 'relkind');
        $rsm->addScalarResult('relhasindex', 'relhasindex');
        $rsm->addScalarResult('relhastriggers', 'relhastriggers');
        $rsm->addScalarResult('relhasoids', 'relhasoids');
        $rsm->addScalarResult('reltablespace', 'reltablespace');
        $rsm->addScalarResult('reloftype', 'reloftype');
        $rsm->addScalarResult('relpersistence', 'relpersistence');

        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, PgRetriever::CACHE_LIFETIME);
        return $stmt->getResult(AbstractQuery::HYDRATE_ARRAY);
    }

    /**
     * @param $sql
     * @return int
     */
    private function nbItems($sql)
    {
        $sql = "with query as ($sql)
                SELECT count(*) as nb
                FROM query";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('nb', 'nb');

        $stmt = $this->em->createNativeQuery($sql, $rsm);

        return intval($stmt->getSingleScalarResult());
    }

    /**
     * @param array $schemas
     */
    public function fillTableColumns(&$schemas)
    {
        $this->em->clear();
        $sql = "SELECT n.nspname as schema,
                    a.attrelid as table,
                    c.relname as table_name,
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
                    INNER JOIN pg_catalog.pg_class c ON c.oid = a.attrelid
                    INNER JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                  WHERE a.attnum > 0 AND NOT a.attisdropped
                    AND c.relkind = 'r'
                    AND n.nspname <> 'pg_catalog'
                    AND n.nspname <> 'information_schema'
                    AND n.nspname !~ '^pg_toast'
                    --AND pg_catalog.pg_table_is_visible(c.oid)
                  ORDER BY n.nspname, a.attrelid, a.attnum
                        ";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('schema', 'schema');
        $rsm->addScalarResult('table', 'table');
        $rsm->addScalarResult('table_name', 'table_name');
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('type', 'type');
        $rsm->addScalarResult('default', 'default');
        $rsm->addScalarResult('nullable', 'nullable');
        $rsm->addScalarResult('position', 'position');
        $rsm->addScalarResult('attcollation', 'attcollation');

        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, PgRetriever::CACHE_LIFETIME);


        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $column = new Column($row['schema']);
            foreach ($row as $key => $value) {
                $column->__set($key, $value);
            }

            if($this->index_type == PgRetriever::INDEX_TYPE_OID){
                $schemas[$row['schema']]->addTableColumn($row['table'], $column);
            }elseif($this->index_type == PgRetriever::INDEX_TYPE_NAME){
                $schemas[$row['schema']]->addTableColumn($row['table_name'], $column);
            }
            unset($column);
        }
        unset($stmt);
    }

    /**
     * @param array $schemas
     */
    public function fillTableFk(&$schemas)
    {
        $this->em->clear();
        //Foreign Keys
        $sql = "SELECT n2.nspname as schema,
               c2.oid as table,
               c2.relname as table_name,
               r.conname as name,
               r.conname as oid,
               r.confrelid as \"parentTable\",
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
               r.conkey as \"cols\",
               r.confkey as \"refCols\",
               pg_catalog.pg_get_constraintdef(r.oid, true) as \"creationQuery\"
        FROM pg_catalog.pg_constraint r
               INNER JOIN pg_catalog.pg_class c ON c.oid = r.confrelid
                                                   AND c.relkind = 'r' --AND pg_catalog.pg_table_is_visible(c.oid)
               INNER JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                                                       AND n.nspname <> 'pg_catalog'
                                                       AND n.nspname <> 'information_schema'
                                                       AND n.nspname !~ '^pg_toast'
                                                       AND n.nspname !~ '^pg_temp'
                                                       AND n.nspname <> 'londiste'
                                                       AND n.nspname <> 'pgq'
               INNER JOIN pg_catalog.pg_class c2 ON c2.oid = r.conrelid AND c2.relkind = 'r' --AND pg_catalog.pg_table_is_visible(c2.oid)
               INNER JOIN pg_catalog.pg_namespace n2 ON n2.oid = c2.relnamespace
                                                       AND n2.nspname <> 'pg_catalog'
                                                       AND n2.nspname <> 'information_schema'
                                                       AND n2.nspname !~ '^pg_toast'
                                                       AND n2.nspname !~ '^pg_temp'
                                                       AND n2.nspname <> 'londiste'
                                                       AND n2.nspname <> 'pgq'
        WHERE  r.contype = 'f'

        ORDER BY 1";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('schema', 'schema');
        $rsm->addScalarResult('table', 'table');
        $rsm->addScalarResult('table_name', 'table_name');
        $rsm->addScalarResult('parentTable', 'parentTable');
        $rsm->addScalarResult('updateType', 'updateType');
        $rsm->addScalarResult('deleteType', 'deleteType');
        $rsm->addScalarResult('matchType', 'matchType');
        $rsm->addScalarResult('cols', 'cols');
        $rsm->addScalarResult('refCols', 'refCols');
        $rsm->addScalarResult('creationQuery', 'creationQuery');

        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, PgRetriever::CACHE_LIFETIME);

        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $foreignKey = new ForeignKey($row['schema'], $row['table']);
            foreach ($row as $key => $value) {
                $foreignKey->__set($key, (($key == 'cols' || $key == 'refCols') ? explode(',', str_replace(array('{', '}', ' '), '', $value)) : $value));
            }

            if($this->index_type == PgRetriever::INDEX_TYPE_OID){
                $schemas[$row['schema']]->addTableFk($row['table'], $foreignKey);
            }elseif($this->index_type == PgRetriever::INDEX_TYPE_NAME){
                $schemas[$row['schema']]->addTableFk($row['table_name'], $foreignKey);
            }
            unset($foreignKey);
        }
        unset($stmt);
    }

    public function fillParentTables(&$schemas)
    {
        $this->em->clear();
        //Inherit
        $sql = "WITH RECURSIVE inh AS (
                   SELECT i.inhrelid, i.inhparent
                   FROM pg_catalog.pg_inherits i
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
                   INNER JOIN pg_catalog.pg_class c2 ON (inh.inhparent = c2.oid)";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('schema', 'schema');
        $rsm->addScalarResult('table', 'table');
        $rsm->addScalarResult('table_name', 'table_name');


        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, PgRetriever::CACHE_LIFETIME);

        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $child = new ParentTable($row['schema'], $row['table']);
            foreach ($row as $key => $value) {
                $child->__set($key, $value);
            }

            if($this->index_type == PgRetriever::INDEX_TYPE_OID){
                $schemas[$row['schema']]->addTableParentTable($row['oid'], $child);
            }elseif($this->index_type == PgRetriever::INDEX_TYPE_NAME){
                $schemas[$row['schema']]->addTableParentTable($row['table_name'], $child);
            }
            unset($child);
        }
        unset($stmt);
    }

    /**
     * @param $schemas
     */
    public function fillChildTables(&$schemas)
    {
        $this->em->clear();
        //Child tables
        $sql = " SELECT c.oid,
                        c.oid::pg_catalog.regclass as name,
                        i.inhparent as table,
                        c2.relname as table_name,
                        n.nspname as schema
                FROM pg_catalog.pg_class c
                  INNER JOIN pg_catalog.pg_inherits i ON c.oid = i.inhparent
                  INNER JOIN pg_catalog.pg_class c2 ON c2.oid = i.inhparent
                  INNER JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                WHERE c2.relkind = 'r'
                    AND n.nspname <> 'pg_catalog'
                    AND n.nspname <> 'information_schema'
                    AND n.nspname !~ '^pg_toast'
                    --AND pg_catalog.pg_table_is_visible(c2.oid)
                ORDER BY c.oid::pg_catalog.regclass::pg_catalog.text";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('schema', 'schema');
        $rsm->addScalarResult('table', 'table');
        $rsm->addScalarResult('table_name', 'table_name');

        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, PgRetriever::CACHE_LIFETIME);


        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $child = new ChildTable($row['schema'], $row['table']);
            foreach ($row as $key => $value) {
                $child->__set($key, $value);
            }

            if($this->index_type == PgRetriever::INDEX_TYPE_OID){
                $schemas[$row['schema']]->addTableChildTable($row['table'], $child);
            }elseif($this->index_type == PgRetriever::INDEX_TYPE_NAME){
                $schemas[$row['schema']]->addTableChildTable($row['table_name'], $child);
            }
            unset($child);
        }
        unset($stmt);
    }

    /**
     * @param $schemas
     */
    public function fillRuleTables(&$schemas)
    {
        $this->em->clear();
        //Rules
        $sql = "SELECT r.rulename as oid,
                                r.rulename as name,
                                trim(trailing ';' from pg_catalog.pg_get_ruledef(r.oid, true)) as detail,
                                ev_enabled as enabled,
                                n.nspname as schema,
                                r.ev_class as table,
                                c.relname as table_name
                        FROM pg_catalog.pg_rewrite r
                          INNER JOIN pg_catalog.pg_class c ON c.oid = r.ev_class
                          INNER JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                        WHERE  c.relkind = 'r'
                            AND n.nspname <> 'pg_catalog'
                            AND n.nspname <> 'information_schema'
                            AND n.nspname !~ '^pg_toast'
                            --AND pg_catalog.pg_table_is_visible(c.oid)
                        ORDER BY 1";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');

        $rsm->addScalarResult('detail', 'detail');
        $rsm->addScalarResult('enabled', 'enabled');

        $rsm->addScalarResult('schema', 'schema');
        $rsm->addScalarResult('table', 'table');
        $rsm->addScalarResult('table_name', 'table_name');

        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, PgRetriever::CACHE_LIFETIME);

        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $rule = new Rule($row['schema'], $row['table']);
            foreach ($row as $key => $value) {
                $rule->__set($key, $value);
            }

            if($this->index_type == PgRetriever::INDEX_TYPE_OID){
                $schemas[$row['schema']]->addTableRule($row['table'], $rule);
            }elseif($this->index_type == PgRetriever::INDEX_TYPE_NAME){
                $schemas[$row['schema']]->addTableRule($row['table_name'], $rule);
            }
            unset($rule);
        }
        unset($stmt);
    }

    /**
     * @param $schemas
     */
    public function fillTriggerTables(&$schemas)
    {
        $this->em->clear();
        //Triggers
        $sql = "SELECT t.tgname as name,
                                t.oid as oid,
                                pg_catalog.pg_get_triggerdef(t.oid, true) as \"creationQuery\",
                                t.tgenabled as \"isEnabled\",
                                tgfoid as \"functionOid\",
                                p.proname as \"functionName\",
                                t.tgrelid as table,
                                n.nspname as schema,
                                c.relname as table_name
                        FROM pg_catalog.pg_trigger t
                            LEFT JOIN pg_catalog.pg_proc p ON t.tgfoid=p.oid
                            INNER JOIN pg_catalog.pg_class c ON c.oid = t.tgrelid
                            INNER JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                        WHERE NOT t.tgisinternal
                            AND c.relkind = 'r'
                            AND n.nspname <> 'pg_catalog'
                            AND n.nspname <> 'information_schema'
                            AND n.nspname !~ '^pg_toast'
                            --AND pg_catalog.pg_table_is_visible(c.oid)
                        ORDER BY 1";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('name', 'name');

        $rsm->addScalarResult('creationQuery', 'creationQuery');
        $rsm->addScalarResult('isEnabled', 'isEnabled');
        $rsm->addScalarResult('functionOid', 'functionOid');
        $rsm->addScalarResult('functionName', 'functionName');
        $rsm->addScalarResult('schema', 'schema');
        $rsm->addScalarResult('table', 'table');
        $rsm->addScalarResult('table_name', 'table_name');
        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, PgRetriever::CACHE_LIFETIME);

        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $trigger = new Trigger($row['schema'], $row['table']);
            foreach ($row as $key => $value) {
                $trigger->__set($key, $value);
            }

            if($this->index_type == PgRetriever::INDEX_TYPE_OID){
                $schemas[$row['schema']]->addTableTrigger($row['table'], $trigger);
            }elseif($this->index_type == PgRetriever::INDEX_TYPE_NAME){
                $schemas[$row['schema']]->addTableTrigger($row['table_name'], $trigger);
            }
            unset($trigger);
        }
        unset($stmt);
    }

    /**
     * @param $schemas
     */
    public function fillReferencedInTables(&$schemas)
    {
        $this->em->clear();
        //Get Table referencing this table
        $sql = "SELECT r.conrelid as oid,
                    r.confrelid as table,
                    n.nspname as schema,
                    c.relname as table_name
                FROM pg_catalog.pg_constraint r
                  INNER JOIN pg_catalog.pg_class c ON c.oid = r.confrelid
                  INNER JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                WHERE r.contype = 'f'
                    AND c.relkind = 'r'
                    AND n.nspname <> 'pg_catalog'
                    AND n.nspname <> 'information_schema'
                    AND n.nspname !~ '^pg_toast'
                    --AND pg_catalog.pg_table_is_visible(c.oid)
                ORDER BY 1";
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('oid', 'oid');
        $rsm->addScalarResult('schema', 'schema');
        $rsm->addScalarResult('table', 'table');
        $rsm->addScalarResult('table_name', 'table_name');
        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, PgRetriever::CACHE_LIFETIME);


        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $schemas[$row['schema']]->addTableReferenced($row['table'], $row['oid']);

        }
        unset($stmt);
    }

    public function fillIndexesTables(&$schemas)
    {
        $this->em->clear();
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
                                c2.reltablespace,
                                c.oid as table,
                                n.nspname as schema,
                                c.relname as table_name
                        FROM pg_catalog.pg_class c
                            INNER JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                            INNER JOIN pg_catalog.pg_index i ON c.oid = i.indrelid
                            INNER JOIN pg_catalog.pg_class c2 ON i.indexrelid = c2.oid
                            LEFT JOIN pg_catalog.pg_constraint con ON (conrelid = i.indrelid AND conindid = i.indexrelid AND contype IN ('p','u','x'))
                    WHERE c.relkind = 'r'
                        AND n.nspname <> 'pg_catalog'
                        AND n.nspname <> 'information_schema'
                        AND n.nspname !~ '^pg_toast'
                        --AND pg_catalog.pg_table_is_visible(c.oid)
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

        $rsm->addScalarResult('schema', 'schema');
        $rsm->addScalarResult('table', 'table');
        $rsm->addScalarResult('table_name', 'table_name');

        $stmt = $this->em->createNativeQuery($sql, $rsm);
        $stmt->useResultCache(true, PgRetriever::CACHE_LIFETIME);


        foreach ($stmt->getResult(AbstractQuery::HYDRATE_ARRAY) as $row) {
            $index = new Index($row['schema'], $row['table']);
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

            if($this->index_type == PgRetriever::INDEX_TYPE_OID){
                $schemas[$row['schema']]->addTableIndex($row['table'], $index);
            }elseif($this->index_type == PgRetriever::INDEX_TYPE_NAME){
                $schemas[$row['schema']]->addTableIndex($row['table_name'], $index);
            }

            unset($index);
        }
        unset($stmt);
    }
}