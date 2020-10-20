<?php

namespace Pterodactyl\Http\Controllers\Api\Application\Locations;

use Illuminate\Http\Response;
use Pterodactyl\Models\Location;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Locations\LocationUpdateService;
use Pterodactyl\Services\Locations\LocationCreationService;
use Pterodactyl\Services\Locations\LocationDeletionService;
use Pterodactyl\Contracts\Repository\LocationRepositoryInterface;
use Pterodactyl\Transformers\Api\Application\LocationTransformer;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Pterodactyl\Http\Requests\Api\Application\Locations\GetLocationRequest;
use Pterodactyl\Http\Requests\Api\Application\Locations\GetLocationsRequest;
use Pterodactyl\Http\Requests\Api\Application\Locations\StoreLocationRequest;
use Pterodactyl\Http\Requests\Api\Application\Locations\DeleteLocationRequest;
use Pterodactyl\Http\Requests\Api\Application\Locations\UpdateLocationRequest;

class LocationController extends ApplicationApiController
{
    /**
     * @var \Pterodactyl\Services\Locations\LocationCreationService
     */
    private $creationService;

    /**
     * @var \Pterodactyl\Services\Locations\LocationDeletionService
     */
    private $deletionService;

    /**
     * @var \Pterodactyl\Contracts\Repository\LocationRepositoryInterface
     */
    private $repository;

    /**
     * @var \Pterodactyl\Services\Locations\LocationUpdateService
     */
    private $updateService;

    /**
     * LocationController constructor.
     *
     * @param \Pterodactyl\Services\Locations\LocationCreationService       $creationService
     * @param \Pterodactyl\Services\Locations\LocationDeletionService       $deletionService
     * @param \Pterodactyl\Contracts\Repository\LocationRepositoryInterface $repository
     * @param \Pterodactyl\Services\Locations\LocationUpdateService         $updateService
     */
    public function __construct(
        LocationCreationService $creationService,
        LocationDeletionService $deletionService,
        LocationRepositoryInterface $repository,
        LocationUpdateService $updateService
    ) {
        parent::__construct();

        $this->creationService = $creationService;
        $this->deletionService = $deletionService;
        $this->repository = $repository;
        $this->updateService = $updateService;
    }

    /**
     * Return all of the locations currently registered on the Panel.
     *
     * @param \Pterodactyl\Http\Requests\Api\Application\Locations\GetLocationsRequest $request
     * @return array
     */
    public function index(GetLocationsRequest $request): array
    {
        $locations = $this->repository->setSearchTerm($request->input('search'))->paginated(50);

        return $this->fractal->collection($locations)
            ->transformWith($this->getTransformer(LocationTransformer::class))
            ->toArray();
    }

    /**
     * Return a single location.
     *
     * @param \Pterodactyl\Http\Requests\Api\Application\Locations\GetLocationRequest $request
     * @return array
     */
    public function view(GetLocationRequest $request): array
    {
        return $this->fractal->item($request->getModel(Location::class))
            ->transformWith($this->getTransformer(LocationTransformer::class))
            ->toArray();
    }

    /**
     * Store a new location on the Panel and return a HTTP/201 response code with the
     * new location attached.
     *
     * @param \Pterodactyl\Http\Requests\Api\Application\Locations\StoreLocationRequest $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = $this->creationService->handle($request->validated());

        return $this->fractal->item($location)
            ->transformWith($this->getTransformer(LocationTransformer::class))
            ->addMeta([
                'resource' => route('api.application.locations.view', [
                    'location' => $location->id,
                ]),
            ])
            ->respond(201);
    }

    /**
     * Update a location on the Panel and return the updated record to the user.
     *
     * @param \Pterodactyl\Http\Requests\Api\Application\Locations\UpdateLocationRequest $request
     * @return array
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(UpdateLocationRequest $request): array
    {
        $location = $this->updateService->handle($request->getModel(Location::class), $request->validated());

        return $this->fractal->item($location)
            ->transformWith($this->getTransformer(LocationTransformer::class))
            ->toArray();
    }

    /**
     * Delete a location from the Panel.
     *
     * @param \Pterodactyl\Http\Requests\Api\Application\Locations\DeleteLocationRequest $request
     * @return \Illuminate\Http\Response
     *
     * @throws \Pterodactyl\Exceptions\Service\Location\HasActiveNodesException
     */
    public function delete(DeleteLocationRequest $request): Response
    {
        $this->deletionService->handle($request->getModel(Location::class));

        return response('', 204);
    }
}
