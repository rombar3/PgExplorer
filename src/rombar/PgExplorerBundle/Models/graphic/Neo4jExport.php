<?php
/**
 * Created by PhpStorm.
 * User: Romain
 * Date: 04/07/2015
 * Time: 20:38
 */

namespace rombar\PgExplorerBundle\Models\graphic;

use rombar\PgExplorerBundle\Models\PgAnalyzer;

class Neo4jExport {

    const NODE_FILENAME = 'export_nodes';

    const LINKS_FILENAME = 'export_links';

    public function exportDataToCsv(PgAnalyzer $pgAnalyzer)
    {
        $fileNames = [
          'nodes' => self::NODE_FILENAME.'_'.time().'.csv',
           'links' => self::LINKS_FILENAME.'_'.time().'.csv',
        ];

        $handleNodes = fopen(sys_get_temp_dir().DIRECTORY_SEPARATOR.$fileNames['nodes'], 'w');
        $handleLinks = fopen(sys_get_temp_dir().DIRECTORY_SEPARATOR.$fileNames['links'], 'w');
        $headerNodes = ['tableId:ID', 'name', ':LABEL'];
        $headerLinks = [':START_ID', 'role', ':END_ID', ':TYPE'];

        fputcsv($handleNodes, $headerNodes);
        fputcsv($handleLinks, $headerLinks);

        foreach ($pgAnalyzer->getSchemas() as $schema) {

            foreach($schema->getTables() as $table){
                fputcsv($handleNodes,
                    [
                        $schema->getName().PgAnalyzer::DB_SEPARATOR.$table->getName(),
                        $table->getName(),
                        $schema->getName().'|Table'
                        .((count($table->getChildTables())) ? '|Parent' : '')
                        .((count($table->getParentTables())) ? '|Child' : ''),
                    ], ",", "'"
                );

                foreach($table->getForeignKeys() as $fk){
                    $parentTable = $pgAnalyzer->getTableByOid($fk->getParentTable());
                    fputcsv($handleLinks,
                        [
                            $table->getSchema().PgAnalyzer::DB_SEPARATOR.$table->getName(),
                            'reference',
                            $parentTable->getSchema().PgAnalyzer::DB_SEPARATOR.$parentTable->getName(),
                            'FOREIGN_KEY'
                        ], ",", "'"
                    );
                }

                foreach($table->getParentTables() as $parent){
                    $parentTable = $pgAnalyzer->getTableByOid($parent->getOid());
                    fputcsv($handleLinks,
                        [
                            $table->getSchema().PgAnalyzer::DB_SEPARATOR.$table->getName(),
                            'inherit',
                            $parentTable->getSchema().PgAnalyzer::DB_SEPARATOR.$parentTable->getName(),
                            'INHERIT'
                        ], ",", "'"
                    );
                }
            }
        }

        fclose($handleNodes);
        fclose($handleLinks);
        return $fileNames;
    }
}