<?php

namespace rombar\PgExplorerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use rombar\PgExplorerBundle\Models;


class DefaultController extends Controller {

    /**
     * @Route("/", name="homepage")
     * @Template()
     * @Method({"GET"})
     */
    public function indexAction()
    {
        $pgAnalyzer = $this->get('rombar_pgexplorerbundle.pganalyzer');
        $pgAnalyzer->initSchemasElements();
        $pgAnalyzer->initAllTableInfo();

        return array('schemas' => $pgAnalyzer->getSchemas());
    }

}
