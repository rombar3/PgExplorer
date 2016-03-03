<?php

namespace rombar\PgExplorerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use rombar\PgExplorerBundle\Models;
use Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;

/**
 * Class SqlQueryController
 * @package rombar\PgExplorerBundle\Controller
 * @Route("sqlQuery")
 */
class SqlQueryController extends Controller
{

    /**
     * @Route("/", name="sql")
     * @Method({"GET"})
     * @Template()
     * @param Request $request
     * @return Array
     */
    public function sqlAction(Request $request)
    {
        $pgAnalyzer = $this->get('rombar_pgexplorerbundle.pganalyzer');
        $pgAnalyzer->initSchemasElements();
        $pgAnalyzer->initAllTableInfo();
        //\Doctrine\Common\Util\Debug::dump($pgAnalyzer->getSchemas());
        //var_dump($pgAnalyzer->getSchemas());
        $session = $request->getSession();
        $session->set('sqlGenerator', new Models\SqlGenerator());
        return array('schemas' => $pgAnalyzer->getSchemas());
    }

    /**
     * @Route("/doRequest", name="sqlQuery.doRequest", options={"expose"=true})
     * @Method({"POST"})
     * @Template()
     */
    public function doRequestAction()
    {
        $request = $this->container->get('request');
        $em = $this->getDoctrine()->getManager('pg');
        $sql = trim($request->get('request'));

        if ($request->isXmlHttpRequest()) {
            if (empty($sql)) {
                return array('data' => [], 'nbRows' => 0, 'columns' => [], 'message' => 'No SQL query');
            }


            try {

                //var_dump($sql);
                if (preg_match('#^[\s]*(with)?[\s\S]*(update[\s]+[[:alnum:]\._-]+[\s]+set|insert[\s]+into|truncate[\s]+|delete[\s]+from)#i', trim($sql))) {
                    return array('data' => [], 'nbRows' => 0, 'columns' => [], 'message' => 'This query is not a select!');
                } else {
                    $em->beginTransaction();
                    $stmt = $em->getConnection()->executeQuery($sql);


                    $data = $stmt->fetchAll();
                    $nbRows = $stmt->rowCount();
                    $columns = [];

                    if ($nbRows > 0) {
                        $columns = array_keys($data[0]);
                    }
                    $em->rollback();
                    return array('data' => $data, 'nbRows' => $nbRows, 'columns' => $columns, 'message' => null);
                }
            } catch (\Exception $exc) {
                $this->get('logger')->err($exc->getMessage());
                $this->get('logger')->err($exc->getTraceAsString());
                return array('data' => [], 'nbRows' => 0, 'columns' => [], 'message' => $exc->getMessage());
            }
        }
    }

    /**
     * @Route("/doExplain", name="sqlQuery.doExplain", options={"expose"=true})
     * @Method({"POST"})
     * @Template()
     */
    public function doExplainAction()
    {
        $request = $this->container->get('request');
        $em = $this->getDoctrine()->getManager('pg');
        $sql = trim($request->get('request'));
        $analyse = intval($request->get('analyse'));
        $results = null;
        if ($request->isXmlHttpRequest()) {
            try {
                $results = $em->getConnection()->executeQuery('EXPLAIN '. (($analyse) ? 'ANALYZE ' : ''). $sql)
                    ->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                return new Response('This query cannot be explained.');
            }
        }

        return array('data' => $results,'analyse' => $analyse);
    }

    /**
     * @Route("/addToSqlGenerator", name="sqlQuery.addToSqlGenerator", options={"expose"=true})
     * @Method({"POST"})
     * @return Response
     */
    public function addToSqlGeneratorAction()
    {
        $data = array('ok' => 1);
        try {
            $request = $this->container->get('request');
            if ($request->isXmlHttpRequest()) {
                $session = $request->getSession();
                $sqlGenerator = $session->get('sqlGenerator');

                $elmt = $request->get('elmt');
                $id = $request->get('id');
                $parent = $request->get('table');
                $pgAnalyzer = $this->get('rombar_pgexplorerbundle.pganalyzer');
                $pgAnalyzer->initSchemas();
                $pgAnalyzer->initSchemasElements();
                $data['libelle'] = $sqlGenerator->addAnElement($pgAnalyzer, $elmt, $id, $parent);

                $session->set('sqlGenerator', $sqlGenerator);
            }
        } catch (\Exception $e) {
            $data = array('ok' => 0, 'message' => $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        return new Response(json_encode($data));
    }

    /**
     * @Route("/generateSql", name="sqlQuery.generateSql", options={"expose"=true})
     * @Method({"POST"})
     * @return Response
     */
    public function generateSqlAction()
    {
        $data = [];
        $request = $this->container->get('request');
        if ($request->isXmlHttpRequest()) {
           
            $session = $request->getSession();
            $sqlGenerator = $session->get('sqlGenerator');
            $pgAnalyzer = $this->get('rombar_pgexplorerbundle.pganalyzer');
            $pgAnalyzer->initSchemas();
            $pgAnalyzer->initSchemasElements();
            $data = array('sql' => $sqlGenerator->generateSql($pgAnalyzer), 'message' => null);
        }
        return new Response(json_encode($data));
    }

    /**
     * @Route("/autocomplete", name="sqlQuery.autocomplete", options={"expose"=true})
     * @Method({"GET"})
     *
     * @param Request $request
     * @return Response
     */
    public function autocompleteAction(Request $request)
    {

        $data = [];
        $request = $this->container->get('request');
        if ($request->isXmlHttpRequest()) {

            $term = trim($request->get('term'));
            $population = trim($request->get('population'));
            $session = $request->getSession();
            $pgAnalyzer = $this->get('rombar_pgexplorerbundle.pganalyzer');
            $pgAnalyzer->initSchemas();

            switch ($population) {
                case 'table':
                    $infos = explode('.', $term);
                    if (count($infos) == 1) {
                        $pgAnalyzer->initSchemasElements();
                        $data = $pgAnalyzer->searchTableForAutocomplete($term);
                    } elseif (count($infos) == 2) {
                        if ($infos[0] != '' && $pgAnalyzer->getSchemasByName($infos[0])) {

                            $pgAnalyzer->initSchemasElements($infos[0]);
                            $data = $pgAnalyzer->searchTableForAutocomplete($infos[1], $infos[0]);
                        } else {
                            $pgAnalyzer->initSchemasElements();
                            $data = $pgAnalyzer->searchTableForAutocomplete($infos[1]);
                        }
                    }
                    break;

                case 'generatorCol':
                    $sqlGenerator = $session->get('sqlGenerator');
                    $data = $sqlGenerator->searchColumnForAutocomplete($term);
                    break;

                case 'generatorGroupBy':
                    $sqlGenerator = $session->get('sqlGenerator');
                    $data = $sqlGenerator->searchGroupByForAutocomplete($term);
                    break;

                case 'generatorOrderBy':
                    $sqlGenerator = $session->get('sqlGenerator');
                    $data = $sqlGenerator->searchOrderByForAutocomplete($term);
                    break;

                case 'generatorJoinTable':
                    $sqlGenerator = $session->get('sqlGenerator');
                    $pgAnalyzer->initSchemasElements();
                    $data = $sqlGenerator->searchJoinTableForAutocomplete($term, trim($request->get('schema')), trim($request->get('table')), $pgAnalyzer);
                    break;
            }
        }
        return new Response(json_encode($data));
    }
}