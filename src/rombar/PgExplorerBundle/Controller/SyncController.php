<?php

namespace rombar\PgExplorerBundle\Controller;

use rombar\PgExplorerBundle\Exceptions\StructureException;
use rombar\PgExplorerBundle\Exceptions\SyncException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use rombar\PgExplorerBundle\Models;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SyncController
 * @package rombar\PgExplorerBundle\Controller
 * @Route("/sync")
 */
class SyncController extends Controller
{

    /**
     * @return array
     * @Route("/", name="sync")
     * @Template()
     *
     */
    public function indexAction()
    {

        $handler = $this->get('rombar_pgexplorerbundle.sync.synchandler');
        $handler->resetSession();
        $form = $handler->getSyncForm();



        return array('schemas' => [], 'form' => $form->createView());
    }

    /**
     * @param Request $request
     * @Route("/startSync", name="sync.startSync", options={"expose"=true})
     * @Method({"POST"})
     * @Template()
     * @return array
     */
    public function startSyncAction(Request $request)
    {

        $handler = $this->get('rombar_pgexplorerbundle.sync.synchandler');
        $form = $handler->getSyncForm(true);
        $form->handleRequest($request);

        if($form->isValid()){
            $handler->fillSession($form->getData());
        }else{
            $this->redirectToRoute('sync');
        }
        return array('schemas' => [], 'form' => $form->createView());
    }

    /**
     * @Route("/compare-structure/step/{step}",
     *          name="sync.compareStructure",
     *          condition="request.headers.get('X-Requested-With') == 'XMLHttpRequest'",
     *          requirements = {"step" = "^(schemas|tables|functions)$"}
     *          , options={"expose"=true}
     * )
     * @Method({"POST"})
     * @return Response
     * @param string $step
     *
     */
    public function compareStructureAction($step)
    {
        $data = ["ok" => 0, "message" => ""];
        try{
            $handler = $this->get('rombar_pgexplorerbundle.sync.synchandler');
            $handler->initAnalysis($this->getDoctrine());
            if($handler->getParameters()->getTestSchema()){
                $data = $handler->compare($step);
            }
        }catch (StructureException $ex){
            $data = $handler->getReport($ex->getMessage());
        }catch (\Exception $ex){
            $data = ["ok" => 0, "message" => "Technical error"];
            $this->get('logger')->addError($ex->getMessage());
            $this->get('logger')->addError($ex->getTraceAsString());
        }

        return new Response(json_encode($data));
    }

    /**
     *
     * @Route("/get-weights",
     *          name="sync.getWeights",
     *          condition="request.headers.get('X-Requested-With') == 'XMLHttpRequest'",
     *          options={"expose"=true}
     * )
     * @Method({"POST"})
     * @return Response
     */
    public function getWeightsAction()
    {
        $data = ["ok" => 0, "weights" => []];

        try{
            $handler = $this->get('rombar_pgexplorerbundle.sync.synchandler');
            $handler->initAnalysis($this->getDoctrine());
            if($handler->getParameters()->getInsertData()) {
                $data['weights'] = $handler->getWeights(
                    $handler->getParameters()->getSyncChild(),
                    $handler->getParameters()->getChildPattern()
                );

            }
            $data['ok'] = 1;
        }catch (StructureException $ex){
            $data = ["ok" => 0, "message" => $ex->getMessage()];
        }catch (SyncException $ex){
            $data = ["ok" => 0, "message" => $ex->getMessage()];
        }catch (\Exception $ex){
            $data = ["ok" => 0, "message" => "Technical error"];
            $this->get('logger')->addError($ex->getMessage());
            $this->get('logger')->addError($ex->getTraceAsString());
        }

        return new Response(json_encode($data));
    }

    /**
     * @param $weight
     * @param $limit
     * @return Response
     * @internal param $schema
     * @Route("/sync-data/weight/{weight}/limit/{limit}",
     *          name="sync.syncData",
     *          condition="request.headers.get('X-Requested-With') == 'XMLHttpRequest'",
     *          requirements = {"schema" = "^[a-z_\-0-9]+$", "limit" = "^[0-9]+$"}
     *          , options={"expose"=true}
     * )
     * @Method({"POST"})
     */
    public function syncDataAction($weight, $limit)
    {
        $data = ["ok" => 0, "message" => ""];

        try{
            $handler = $this->get('rombar_pgexplorerbundle.sync.synchandler');
            $handler->initAnalysis($this->getDoctrine());

            if($handler->getParameters()->getInsertData()){
                $data = $handler->syncWeight($weight, $limit);
            }
        }catch (StructureException $ex){
            $data = ["ok" => 0, "message" => $ex->getMessage()];
        }catch (SyncException $ex){
            $data = ["ok" => 0, "message" => $ex->getMessage()];
        }catch (\Exception $ex){
            $data = ["ok" => 0, "message" => "Technical error"];
            $this->get('logger')->addError($ex->getMessage());
            $this->get('logger')->addError($ex->getTraceAsString());
        }

        return new Response(json_encode($data));
    }
}