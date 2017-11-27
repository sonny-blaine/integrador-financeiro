<?php
namespace SonnyBlaine\Integrator\Services;

use Doctrine\DBAL\Connection;
use OldSound\RabbitMqBundle\RabbitMq\Producer as RequestCreatorProducer;
use SonnyBlaine\Integrator\Source\Request;

/**
 * Class IntegratorService
 * @package SonnyBlaine\Integrator\Services
 */
class IntegratorService
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var SourceService Service class for Source
     */
    protected $sourceService;

    /**
     * @var RequestService Service class for Request
     */
    protected $requestService;

    /**
     * @var RequestCreatorProducer
     */
    protected $requestCreatorProducer;

    /**
     * IntegratorService constructor.
     * @param Connection $connection
     * @param SourceService $sourceService
     * @param RequestService $requestService
     * @param RequestCreatorProducer $rabbitProducer
     */
    public function __construct(
        Connection $connection,
        SourceService $sourceService,
        RequestService $requestService,
        RequestCreatorProducer $rabbitProducer
    ) {
        $this->connection = $connection;
        $this->sourceService = $sourceService;
        $this->requestService = $requestService;
        $this->requestCreatorProducer = $rabbitProducer;
    }

    /**
     * @param string $sourceIdentifier
     * @param string $queryParameter
     * @return Request
     */
    public function integrate(string $sourceIdentifier, string $queryParameter)
    {
        $sourceRequest = null;

        $this->connection->transactional(function () use ($sourceIdentifier, $queryParameter, &$sourceRequest) {
            $source = $this->sourceService->findByIdentifier($sourceIdentifier);

            $sourceRequest = $this->requestService->createSourceRequest($source, $queryParameter);

            if (is_null($sourceRequest->getDestinationRequests()) || empty($sourceRequest->getDestinationRequests()->count())) {
                $this->requestCreatorProducer->publish($sourceRequest->getId());
            }
        });

        return $sourceRequest;
    }

    /**
     * @param string $sourceRequestId
     * @throws \Exception
     */
    public function retryIntegrate(string $sourceRequestId)
    {
        $sourceRequest = $this->requestService->findSourceRequest($sourceRequestId);

        if (is_null($sourceRequest)) {
            throw new \Exception("Source Request not found.");
        }

        $this->requestService->updateTryCount($sourceRequest, 0);

        $this->requestCreatorProducer->publish($sourceRequestId);
    }
}