<?php
/**
 * Pterodactyl - Panel
 * Copyright (c) 2015 - 2017 Dane Everitt <dane@daneeveritt.com>.
 *
 * This software is licensed under the terms of the MIT license.
 * https://opensource.org/licenses/MIT
 */

namespace Pterodactyl\Services\Eggs\Sharing;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Egg;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Contracts\Repository\EggRepositoryInterface;
use Pterodactyl\Contracts\Repository\NestRepositoryInterface;
use Pterodactyl\Exceptions\Service\Egg\BadJsonFormatException;
use Pterodactyl\Exceptions\Service\InvalidFileUploadException;
use Pterodactyl\Contracts\Repository\EggVariableRepositoryInterface;

class EggImporterService
{
    /**
     * @var \Illuminate\Database\ConnectionInterface
     */
    protected $connection;

    /**
     * @var \Pterodactyl\Contracts\Repository\EggVariableRepositoryInterface
     */
    protected $eggVariableRepository;

    /**
     * @var \Pterodactyl\Contracts\Repository\NestRepositoryInterface
     */
    protected $nestRepository;

    /**
     * @var \Pterodactyl\Contracts\Repository\EggRepositoryInterface
     */
    protected $repository;

    /**
     * EggImporterService constructor.
     *
     * @param \Illuminate\Database\ConnectionInterface                         $connection
     * @param \Pterodactyl\Contracts\Repository\EggRepositoryInterface         $repository
     * @param \Pterodactyl\Contracts\Repository\EggVariableRepositoryInterface $eggVariableRepository
     * @param \Pterodactyl\Contracts\Repository\NestRepositoryInterface        $nestRepository
     */
    public function __construct(
        ConnectionInterface $connection,
        EggRepositoryInterface $repository,
        EggVariableRepositoryInterface $eggVariableRepository,
        NestRepositoryInterface $nestRepository
    ) {
        $this->connection = $connection;
        $this->eggVariableRepository = $eggVariableRepository;
        $this->repository = $repository;
        $this->nestRepository = $nestRepository;
    }

    /**
     * Take an uploaded JSON file and parse it into a new egg.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param int                           $nest
     * @return \Pterodactyl\Models\Egg
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     * @throws \Pterodactyl\Exceptions\Service\Egg\BadJsonFormatException
     * @throws \Pterodactyl\Exceptions\Service\InvalidFileUploadException
     */
    public function handle(UploadedFile $file, int $nest): Egg
    {
        if ($file->getError() !== UPLOAD_ERR_OK || ! $file->isFile()) {
            throw new InvalidFileUploadException(trans('exceptions.nest.importer.file_error'));
        }

        $parsed = json_decode($file->openFile()->fread($file->getSize()));
        if (json_last_error() !== 0) {
            throw new BadJsonFormatException(trans('exceptions.nest.importer.json_error', [
                'error' => json_last_error_msg(),
            ]));
        }

        if (object_get($parsed, 'meta.version') !== 'PTDL_v1') {
            throw new InvalidFileUploadException(trans('exceptions.nest.importer.invalid_json_provided'));
        }

        $nest = $this->nestRepository->getWithEggs($nest);
        $this->connection->beginTransaction();

        $egg = $this->repository->create([
            'uuid' => Uuid::uuid4()->toString(),
            'nest_id' => $nest->id,
            'author' => object_get($parsed, 'author'),
            'name' => object_get($parsed, 'name'),
            'description' => object_get($parsed, 'description'),
            'docker_image' => object_get($parsed, 'image'),
            'config_files' => object_get($parsed, 'config.files'),
            'config_startup' => object_get($parsed, 'config.startup'),
            'config_logs' => object_get($parsed, 'config.logs'),
            'config_stop' => object_get($parsed, 'config.stop'),
            'startup' => object_get($parsed, 'startup'),
            'script_install' => object_get($parsed, 'scripts.installation.script'),
            'script_entry' => object_get($parsed, 'scripts.installation.entrypoint'),
            'script_container' => object_get($parsed, 'scripts.installation.container'),
            'copy_script_from' => null,
        ], true, true);

        collect($parsed->variables)->each(function ($variable) use ($egg) {
            $this->eggVariableRepository->create(array_merge((array) $variable, [
                'egg_id' => $egg->id,
            ]));
        });

        $this->connection->commit();

        return $egg;
    }
}
