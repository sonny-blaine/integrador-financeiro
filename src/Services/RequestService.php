<?php

namespace SonnyBlaine\Integrator\Services;

use SonnyBlaine\Integrator\Destination\RequestCreator as DestinationRequestCreator;
use SonnyBlaine\Integrator\ResponseInterface;
use SonnyBlaine\Integrator\Source\Request as SourceRequest;
use SonnyBlaine\Integrator\Source\RequestRepository as SourceRequestRepository;
use SonnyBlaine\Integrator\Destination\RequestRepository as DestinationRequestRepository;
use SonnyBlaine\Integrator\Destination\Request as DestinationRequest;
use SonnyBlaine\Integrator\Source\Source;
use SonnyBlaine\Integrator\TryCountInterface;

/**
 * Class RequestService
 * @package SonnyBlaine\Integrator\Services
 */
class RequestService
{
    /**
     * @var DestinationRequestCreator Class to create destination requests
     */
    protected $destinationRequestCreator;

    /**
     * @var SourceRequestRepository Repository class for Source Request
     */
    protected $sourceRequestRepository;

    /**
     * @var DestinationRequestRepository Repository class for Destination Request
     */
    protected $destinationRequestRepository;

    /**
     * RequestService constructor.
     * @param DestinationRequestCreator $destinationRequestCreator
     * @param SourceRequestRepository $sourceRequestRepository
     * @param DestinationRequestRepository $destinationRequestRepository
     */
    public function __construct(
        DestinationRequestCreator $destinationRequestCreator,
        SourceRequestRepository $sourceRequestRepository,
        DestinationRequestRepository $destinationRequestRepository
    ) {
        $this->destinationRequestCreator = $destinationRequestCreator;
        $this->sourceRequestRepository = $sourceRequestRepository;
        $this->destinationRequestRepository = $destinationRequestRepository;
    }

    /**
     * @param Source $source
     * @param string $queryParameter
     * @return SourceRequest
     */
    public function createSourceRequest(Source $source, string $queryParameter): SourceRequest
    {
        if (!$source->isAllowedMultipleRequests()) {
            /**
             * @var $request SourceRequest
             */
            $request = $this->sourceRequestRepository->findOneBy([
                'source' => $source,
                'queryParameter' => $queryParameter,
            ]);

            if ($request) {
                return $request;
            }
        }

        $sourceRequest = new SourceRequest($source, $queryParameter);

        $this->sourceRequestRepository->save($sourceRequest);

        return $sourceRequest;
    }

    /**
     * @param SourceRequest $sourceRequest
     * @throws \Exception
     * @return void
     */
    public function createDestinationRequest(SourceRequest $sourceRequest): void
    {
        $this->destinationRequestCreator->create($sourceRequest);

        if (empty($sourceRequest->getDestinationRequests()->count())) {
            throw new \Exception('Destination Requests list is empty.');
        }

        $this->sourceRequestRepository->save($sourceRequest);
    }

    /**
     * @param int $sourceRequestId Source Request ID
     * @return null|object|SourceRequest
     */
    public function findSourceRequest(int $sourceRequestId): ?SourceRequest
    {
        return $this->sourceRequestRepository->find($sourceRequestId);
    }

    /**
     * @param int $destinationRequestId Destination Request ID
     * @return null|object|DestinationRequest
     */
    public function findDestinationRequest(int $destinationRequestId): ?DestinationRequest
    {
        return $this->destinationRequestRepository->find($destinationRequestId);
    }

    /**
     * @param Source $source
     * @param array $filters
     * @return array
     * @throws \Exception
     */
    public function findSourceRequestsBySource(Source $source, array $filters = [])
    {
        #validate target
        $availableTargets = [
            SourceRequestRepository::SEARCH_USING_SOURCE_REQUEST,
            SourceRequestRepository::SEARCH_USING_DESTINATION_REQUEST
        ];

        if (empty($filters['target'])) {
            $filters['target'] = SourceRequestRepository::SEARCH_USING_SOURCE_REQUEST;
        }

        if (!in_array($filters['target'], $availableTargets)) {
            $msg = sprintf(
                "Invalid Target: %s. Availables: %s", $filters['target'], implode(", ", $availableTargets)
            );

            throw new \Exception($msg, 400);
        }

        if (SourceRequestRepository::SEARCH_USING_DESTINATION_REQUEST == $filters['target']) {
            return $this->sourceRequestRepository->findBySourceUsingDestinationRequest($source, $filters);
        }

        return $this->sourceRequestRepository->findBySource($source, $filters);
    }

    /**
     * @param TryCountInterface $request
     * @param int $tryCount
     */
    public function updateTryCount(TryCountInterface $request, int $tryCount)
    {
        $request->setTryCount($tryCount);

        $this->getRepository($request)->save($request);
    }

    /**
     * @param ResponseInterface $request
     * @param bool $success
     * @param string|null $msg
     * @param string|null $errorTracer
     */
    public function updateSourceRequestResponse(
        ResponseInterface $request,
        bool $success,
        ?string $msg = null,
        ?string $errorTracer = null
    ) {
        $request->setSuccess($success);
        $request->setMsg($msg);
        $request->setErrorTracer($errorTracer);

        $this->getRepository($request)->save($request);
    }

    /**
     * @param object $request
     * @return DestinationRequestRepository|SourceRequestRepository
     */
    private function getRepository($request)
    {
        if ($request instanceof SourceRequest) {
            return $this->sourceRequestRepository;
        }

        return $this->destinationRequestRepository;
    }
}