<?php

namespace rombar\PgExplorerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use rombar\PgExplorerBundle\Models;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class BrowserController
 * @package rombar\PgExplorerBundle\Controller
 * @Route("/browser")
 */
class BrowserController extends Controller
{

    /**
     * @Route("/getTableInfo", name="browser.getTableInfo", options={"expose"=true})
     * @Method({"POST"})
     * @Template()
     * @param Request $request
     * @return array
     */
    public function getTableInfoAction(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $pgAnalyzer = $this->get('rombar_pgexplorerbundle.pganalyzer');

            $schema = $request->get('schema');
            $oid = $request->get('oid');
            $pgAnalyzer->initSchemasElements($schema);
            $table = $pgAnalyzer->getTableInfo($schema, $oid);
            return array('table' => $table);
        }
    }

    /**
     * @Route("/getFunctionInfo", name="browser.getFunctionInfo", options={"expose"=true})
     * @Method({"POST"})
     * @Template()
     */
    public function getFunctionInfoAction()
    {
        $request = $this->container->get('request');

        if ($request->isXmlHttpRequest()) {
            $pgAnalyzer = $this->get('rombar_pgexplorerbundle.pganalyzer');

            $oid = $request->get('oid');
            //$pgAnalyzer->initSchemasElements($schema);
            $fcts = $pgAnalyzer->getFunctionInfo('', $oid);

            if(count($fcts) == 1){
                return array('function' => $fcts[$oid]);
            }else{
                return array('function' => null);
            }
        }
    }
}