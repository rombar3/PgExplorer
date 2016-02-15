<?php
/**
 * Created by PhpStorm.
 * User: Romain
 * Date: 16/04/2015
 * Time: 14:48
 */

namespace rombar\PgExplorerBundle\Models\sync;


use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use rombar\PgExplorerBundle\Exceptions\SyncException;
use rombar\PgExplorerBundle\Models\PgAnalyzer;
use rombar\PgExplorerBundle\Models\PgRetriever;
use Symfony\Component\HttpFoundation\Session\Session;

class SyncData {
    /**
     * @var Session
     */
    private $session;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @var PgAnalyzer
     */
    private $fromAnalyzer;

    /**
     * @var PgAnalyzer
     * private $toAnalyzer;
     */


    /**
     * @var Parameters
     */
    private $parameters;

    /**
     * @var EntityManager
     */
    private $emFrom;

    /**
     * @var EntityManager
     */
    private $emTo;

    public function __construct(Registry $doctrine, Session $session, Logger $logger, Parameters $parameters){
        $this->doctrine = $doctrine;
        $this->session = $session;
        $this->logger = $logger;
        $this->parameters = $parameters;

        $this->emFrom = $this->doctrine->getManager($this->parameters->getManagerFrom());
        $this->emTo = $this->doctrine->getManager($this->parameters->getManagerTo());

        $fromRetriever = new PgRetriever($doctrine, $this->logger, $this->parameters->getManagerFrom() );
        $fromRetriever->setIndexType(PgRetriever::INDEX_TYPE_NAME);

        //$toRetriever = new PgRetriever($doctrine, $this->logger, $this->parameters->getManagerTo() );
        //$toRetriever->setIndexType(PgRetriever::INDEX_TYPE_NAME);

        $this->fromAnalyzer = new PgAnalyzer($this->logger, $fromRetriever);
        //$this->toAnalyzer = new PgAnalyzer($this->logger, $toRetriever);

        if($this->session->has(CompareStructure::SESSION_FROM_KEY)){
            $this->fromAnalyzer->setSchemas($this->session->get(CompareStructure::SESSION_FROM_KEY));
            $this->fromAnalyzer->initTables();

        }else{
           throw new SyncException('No source data');
        }

        /*if($this->session->has(CompareStructure::SESSION_TO_KEY)){
            $this->toAnalyzer->setSchemas($this->session->get(CompareStructure::SESSION_TO_KEY));
            $this->toAnalyzer->initTables();
        }else{
            throw new SyncException('No targeted data');
        }*/
    }
}