<?php

namespace rombar\PgExplorerBundle\Models\sync;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Monolog\Logger;
use rombar\PgExplorerBundle\Exceptions\StructureException;
use rombar\PgExplorerBundle\Exceptions\SyncException;
use rombar\PgExplorerBundle\Models\PgMassRetriever;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SyncHandler {

    /**
     * @var Parameters
     */
    private $parameters;

    /**
     * @var Session
     */
    private $session;

    const SESSION_PARAMETERS_KEY = 'sync.parameters';
    const SESSION_WEIGHT_COLLECTION_KEY = 'sync.weightCollection';
    const SESSION_FROM_KEY = 'sync.from';
    const SESSION_TO_KEY = 'sync.to';
    const COMPARE_OK = 'OK';

    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @var FormFactory
     */
    private $formFactory;

    /**
     * @var $router
     */
    private $router;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var CompareStructure
     */
    private $compareStructure;

    /**
     * @var PgSynchronizer
     */
    private $pgSynchronizer;

    /**
     * @var array
     */
    private $schemas;

    public function __construct(Session $session,
                                Registry $doctrine,
                                FormFactory $formFactory,
                                Router $router,
                                Logger $logger)
    {
        $this->session = $session;
        $this->formFactory = $formFactory;
        $this->router = $router;
        $this->doctrine = $doctrine;
        $this->logger = $logger;

        if($this->session->has(self::SESSION_PARAMETERS_KEY)){
            $this->parameters = $this->session->get(self::SESSION_PARAMETERS_KEY);
        }else{
            $this->resetSession();
        }

    }

    public function resetSession()
    {
        //Ré-init sync
        if($this->session->has(self::SESSION_PARAMETERS_KEY)){
            $this->session->remove(self::SESSION_PARAMETERS_KEY);
        }

        if($this->session->has(self::SESSION_FROM_KEY)){
            $this->session->remove(self::SESSION_FROM_KEY);
        }

        if($this->session->has(self::SESSION_TO_KEY)){
            $this->session->remove(self::SESSION_TO_KEY);
        }

        if($this->session->has(self::SESSION_WEIGHT_COLLECTION_KEY)){
            $this->session->remove(self::SESSION_WEIGHT_COLLECTION_KEY);
        }

        $this->parameters = new Parameters();

        $this->parameters->setMaxNbLinesToInsert(1000);
        $this->parameters->setManagerFrom('prod');
        $this->parameters->setManagerTo('preprod');
        $this->parameters->setTestSchema(true);
        $this->parameters->setInsertData(false);
        $this->parameters->setSyncChild(false);
    }

    private function getSchemaList()
    {
        if(count($this->schemas) == 0){
            $pgMassRetriever = new PgMassRetriever($this->doctrine, $this->logger);
            $this->schemas = $pgMassRetriever->getSchemas();
        }

        $schemaList = ['all' => 'All'];

        foreach($this->schemas as $name => $schema){
            $schemaList[$name] = $name;
        }

        return $schemaList;
    }

    private function getManagerList()
    {
        $managers = [];
        foreach(array_keys($this->doctrine->getManagerNames()) as $em){
            $managers[$em] = $em;
        }


        if(isset($managers['default'])){
            unset($managers['default']);
        }

        return $managers;
    }

    /**
     * @param bool $readOnly
     * @return Form
     */
    public function getSyncForm($readOnly=false)
    {

        $form = $this->formFactory->createBuilder('form', $this->parameters)
            ->setAction($this->router->generate('sync.startSync', [], UrlGeneratorInterface::ABSOLUTE_PATH))
            ->add('maxNbLinesToInsert', 'integer', [
                'precision' => 0,
                'required' => true,
                'read_only' => $readOnly
            ])
            ->add('managerFrom', 'choice', [
                'choices' => $this->getManagerList(),
                'required' => true,
                'empty_value' => 'Choose reference database',
                'read_only' => $readOnly
            ])
            ->add('managerTo', 'choice', [
                'choices' => $this->getManagerList(),
                'required' => true,
                'empty_value' => 'Choose targeted database',
                'read_only' => $readOnly
            ])
            ->add('testSchema', 'checkbox', [
                'required' => false,
                'read_only' => $readOnly
            ])
            ->add('insertData', 'checkbox', [
                'required' => false,
                'read_only' => $readOnly
            ])
            ->add('syncChild', 'checkbox', [
                'required' => false,
                'read_only' => $readOnly
            ])
            ->add('childPattern', 'text', [
                'required' => false,
                'read_only' => $readOnly
            ])
            ->add('schemas', 'choice', [
                'choices' => $this->getSchemaList() ,
                'multiple' => true,
                'required' => false,
                'read_only' => $readOnly
            ])
            ->add('tables', 'choice', [
                'choices' => ['all' => 'all'],
                'multiple' => true,
                'required' => false,
                'read_only' => $readOnly
            ])
            ->add('save', 'submit')
            ->add('cancel', 'button', ['attr' =>
                [
                    'id' => 'cancelButton',
                    'class' => 'btn-primary',
                    'onclick' => "location.assign(Routing.generate('sync'));"
                ]
            ])
            ->getForm();


        return $form;
    }

    /**
     * @return Parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param Parameters $parameters
     */
    public function setParameters(Parameters $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @param $getData
     */
    public function fillSession($getData)
    {
        $this->resetSession();
        $this->session->set(self::SESSION_PARAMETERS_KEY, $getData);
    }

    /**
     * @param Registry $doctrine
     */
    public function initAnalysis(Registry $doctrine)
    {
        $this->compareStructure = new CompareStructure($doctrine, $this->session, $this->logger, $this->parameters);
        if($this->session->has(self::SESSION_FROM_KEY)) {
            $this->pgSynchronizer = new PgSynchronizer($doctrine, $this->session, $this->logger, $this->parameters);
        }
    }

    /**
     * @param $step
     * @return string
     * @throws \Exception
     * @throws StructureException
     * @return string
     */
    public function compare($step)
    {
        switch($step){
            case "schemas":
                $this->compareStructure->compareSchemas();
                break;
            case "tables":
                $this->compareStructure->compareTables();
                break;

            case "functions":
                $this->compareStructure->compareFunctions();
                break;
            default:
                throw new \Exception('Wrong step given');
        }
        return $this->getReport(self::COMPARE_OK);
    }

    /**
     * @return array
     * @throws SyncException
     */
    public function getWeights()
    {
        $this->pgSynchronizer->initSync();
        $this->logger->addInfo('sync Child : '.$this->getParameters()->getSyncChild() .' with pattern'. $this->getParameters()->getChildPattern());
        return $this->pgSynchronizer->generateWeightCollection($this->getParameters()->getSyncChild(), $this->getParameters()->getChildPattern());
    }

    /**
     * @param $weight
     * @param $limit
     * @return string
     */
    public function syncWeight($weight, $limit)
    {
        return $this->pgSynchronizer->syncWeight($weight, $limit);
    }

    /**
     * @param $message
     * @return array
     */
    public function getReport($message)
    {
        return [
            'ok' => ($message == self::COMPARE_OK) ? 1 : 0,
            'message' => $message,
            'nbItems' => $this->compareStructure->getNbItems(),
            'nbItemsTested' => $this->compareStructure->getNbItemsTested()];
    }

}