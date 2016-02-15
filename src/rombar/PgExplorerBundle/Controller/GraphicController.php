<?php

namespace rombar\PgExplorerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use rombar\PgExplorerBundle\Models;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class GraphicController
 * @package rombar\PgExplorerBundle\Controller
 * @Route("/graphic")
 */
class GraphicController extends Controller
{

    /**
     * @Route("/", name="graphic")
     * @Method({"GET"})
     * @Template()
     */
    public function graphicAction()
    {
        $pgAnalyzer = $this->get('rombar_pgexplorerbundle.pganalyzer');
        $pgAnalyzer->initSchemasElements();
        $pgAnalyzer->initAllTableInfo();
        //\Doctrine\Common\Util\Debug::dump($pgAnalyzer->getSchemas());
        //var_dump($pgAnalyzer->getSchemas());
        return array('schemas' => $pgAnalyzer->getSchemas());
    }

    /**
     * @Route("/export-neo4j", name="export-neo4j")
     * @Method({"GET"})
     */
    public function exportNeo4jAction()
    {
        $toReturn = ['ok' => 0, "message" => "Technical error"];
        try{
            $pgAnalyzer = $this->get('rombar_pgexplorerbundle.pganalyzer');
            $pgAnalyzer->initSchemasElements();
            $pgAnalyzer->initAllTableInfo();

            $neo4jExport = new Models\graphic\Neo4jExport();

            $files = $neo4jExport->exportDataToCsv($pgAnalyzer);

            $toReturn = ['ok' => 1,
                        "message" => "Files created",
                        'files' => $files];
        }catch (\Exception $ex){
            $toReturn = ['ok' => 0, "message" => $ex->getMessage(), "trace" => nl2br($ex->getTraceAsString())];
        }


        return new JsonResponse($toReturn);
    }

    /**
     * @Route("/download-csv/file/{fileName}", name="download-csv")
     * @Method({"GET"})
     * @param $fileName
     * @return Response
     */
    public function downloadCsvAction($fileName)
    {
        $file = sys_get_temp_dir().DIRECTORY_SEPARATOR.$fileName;

        if(is_file($file)){
            $content = file_get_contents($file);
        }else{
            $content = "File not found";
        }

        return new Response($content, 200, array(
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"'
        ));
    }

    /**
     * @Route("/getTree", name="getTree")
     * @Method({"POST"})
     * @Template()
     */
    public function getTreeAction()
    {
        $request = $this->container->get('request');

        $pgAnalyzer = $this->get('rombar_pgexplorerbundle.pganalyzer');
        $pgAnalyzer->initSchemasElements();
        $pgAnalyzer->initAllTableInfo();

        $ids = trim($request->get('ids'));
        $linkedTables = intval($request->get('linkedTables'));
        $tables = [];
        if ($request->isXmlHttpRequest() && !empty($ids)) {
            foreach(explode(';', $ids) as $id){
                if(preg_match('#^schema#', $id)){
                    $data = explode('##', $id);
                    $schema = $pgAnalyzer->getSchemasByName($data[1]);
                    foreach($schema->getTables() as $table){
                        $tables[$table->getOid()] = $table;
                    }
                }elseif(preg_match('#^table#', $id)){
                    $data = explode('##', $id);
                    $tables[$data[2]] = $pgAnalyzer->getTable($data[1], $data[2]);

                }
            }
        }


        return array('tables' => $tables, 'linkedTables' => $linkedTables, 'pgAnalyzer' => $pgAnalyzer);
    }
}