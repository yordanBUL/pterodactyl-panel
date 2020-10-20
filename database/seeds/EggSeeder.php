<?php

use Pterodactyl\Models\Nest;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Pterodactyl\Services\Eggs\Sharing\EggImporterService;
use Pterodactyl\Contracts\Repository\EggRepositoryInterface;
use Pterodactyl\Contracts\Repository\NestRepositoryInterface;
use Pterodactyl\Exceptions\Repository\RecordNotFoundException;
use Pterodactyl\Services\Eggs\Sharing\EggUpdateImporterService;

class EggSeeder extends Seeder
{
    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    private $filesystem;

    /**
     * @var \Pterodactyl\Services\Eggs\Sharing\EggImporterService
     */
    private $importerService;

    /**
     * @var \Pterodactyl\Contracts\Repository\NestRepositoryInterface
     */
    private $nestRepository;

    /**
     * @var \Pterodactyl\Contracts\Repository\EggRepositoryInterface
     */
    private $repository;

    /**
     * @var \Pterodactyl\Services\Eggs\Sharing\EggUpdateImporterService
     */
    private $updateImporterService;

    /**
     * EggSeeder constructor.
     *
     * @param \Pterodactyl\Services\Eggs\Sharing\EggImporterService       $importerService
     * @param \Pterodactyl\Contracts\Repository\EggRepositoryInterface    $repository
     * @param \Pterodactyl\Services\Eggs\Sharing\EggUpdateImporterService $updateImporterService
     * @param \Illuminate\Filesystem\Filesystem                           $filesystem
     * @param \Pterodactyl\Contracts\Repository\NestRepositoryInterface   $nestRepository
     */
    public function __construct(
        EggImporterService $importerService,
        EggRepositoryInterface $repository,
        EggUpdateImporterService $updateImporterService,
        Filesystem $filesystem,
        NestRepositoryInterface $nestRepository
    ) {
        $this->filesystem = $filesystem;
        $this->importerService = $importerService;
        $this->repository = $repository;
        $this->updateImporterService = $updateImporterService;
        $this->nestRepository = $nestRepository;
    }

    /**
     * Run the egg seeder.
     */
    public function run()
    {
        $this->getEggsToImport()->each(function ($nest) {
            $this->parseEggFiles($this->findMatchingNest($nest));
        });
    }

    /**
     * Return a list of eggs to import.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getEggsToImport(): Collection
    {
        return collect([
            'Minecraft',
            'Source Engine',
            'Voice Servers',
            'Rust',
        ]);
    }

    /**
     * Find the nest that these eggs should be attached to.
     *
     * @param string $nestName
     * @return \Pterodactyl\Models\Nest
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    private function findMatchingNest(string $nestName): Nest
    {
        return $this->nestRepository->findFirstWhere([
            ['author', '=', 'support@pterodactyl.io'],
            ['name', '=', $nestName],
        ]);
    }

    /**
     * Loop through the list of egg files and import them.
     *
     * @param \Pterodactyl\Models\Nest $nest
     */
    private function parseEggFiles(Nest $nest)
    {
        $files = $this->filesystem->allFiles(database_path('seeds/eggs/' . kebab_case($nest->name)));

        $this->command->alert('Updating Eggs for Nest: ' . $nest->name);
        collect($files)->each(function ($file) use ($nest) {
            /* @var \Symfony\Component\Finder\SplFileInfo $file */
            $decoded = json_decode($file->getContents());
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->command->error('JSON decode exception for ' . $file->getFilename() . ': ' . json_last_error_msg());
            }

            $file = new UploadedFile($file->getPathname(), $file->getFilename(), 'application/json', $file->getSize());

            try {
                $egg = $this->repository->setColumns('id')->findFirstWhere([
                    ['author', '=', $decoded->author],
                    ['name', '=', $decoded->name],
                    ['nest_id', '=', $nest->id],
                ]);

                $this->updateImporterService->handle($egg->id, $file);

                return $this->command->info('Updated ' . $decoded->name);
            } catch (RecordNotFoundException $exception) {
                $this->importerService->handle($file, $nest->id);

                return $this->command->comment('Created ' . $decoded->name);
            }
        });

        $this->command->line('');
    }
}
