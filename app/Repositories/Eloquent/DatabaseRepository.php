<?php

namespace Pterodactyl\Repositories\Eloquent;

use Pterodactyl\Models\Database;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Pterodactyl\Contracts\Repository\DatabaseRepositoryInterface;
use Pterodactyl\Exceptions\Repository\DuplicateDatabaseNameException;

class DatabaseRepository extends EloquentRepository implements DatabaseRepositoryInterface
{
    /**
     * @var string
     */
    protected $connection = self::DEFAULT_CONNECTION_NAME;

    /**
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $database;

    /**
     * DatabaseRepository constructor.
     *
     * @param \Illuminate\Foundation\Application   $application
     * @param \Illuminate\Database\DatabaseManager $database
     */
    public function __construct(Application $application, DatabaseManager $database)
    {
        parent::__construct($application);

        $this->database = $database;
    }

    /**
     * Return the model backing this repository.
     *
     * @return string
     */
    public function model()
    {
        return Database::class;
    }

    /**
     * Set the connection name to execute statements against.
     *
     * @param string $connection
     * @return $this
     */
    public function setConnection(string $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Return the connection to execute statements against.
     *
     * @return string
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * Return all of the databases belonging to a server.
     *
     * @param int $server
     * @return \Illuminate\Support\Collection
     */
    public function getDatabasesForServer(int $server): Collection
    {
        return $this->getBuilder()->where('server_id', $server)->get($this->getColumns());
    }

    /**
     * Return all of the databases for a given host with the server relationship loaded.
     *
     * @param int $host
     * @param int $count
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getDatabasesForHost(int $host, int $count = 25): LengthAwarePaginator
    {
        return $this->getBuilder()->with('server')
            ->where('database_host_id', $host)
            ->paginate($count, $this->getColumns());
    }

    /**
     * Create a new database if it does not already exist on the host with
     * the provided details.
     *
     * @param array $data
     * @return \Pterodactyl\Models\Database
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\DuplicateDatabaseNameException
     */
    public function createIfNotExists(array $data): Database
    {
        $count = $this->getBuilder()->where([
            ['server_id', '=', array_get($data, 'server_id')],
            ['database_host_id', '=', array_get($data, 'database_host_id')],
            ['database', '=', array_get($data, 'database')],
        ])->count();

        if ($count > 0) {
            throw new DuplicateDatabaseNameException('A database with those details already exists for the specified server.');
        }

        return $this->create($data);
    }

    /**
     * Create a new database on a given connection.
     *
     * @param string $database
     * @return bool
     */
    public function createDatabase(string $database): bool
    {
        return $this->run(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $database));
    }

    /**
     * Create a new database user on a given connection.
     *
     * @param string $username
     * @param string $remote
     * @param string $password
     * @return bool
     */
    public function createUser(string $username, string $remote, string $password): bool
    {
        return $this->run(sprintf('CREATE USER `%s`@`%s` IDENTIFIED BY \'%s\'', $username, $remote, $password));
    }

    /**
     * Give a specific user access to a given database.
     *
     * @param string $database
     * @param string $username
     * @param string $remote
     * @return bool
     */
    public function assignUserToDatabase(string $database, string $username, string $remote): bool
    {
        return $this->run(sprintf(
            'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, LOCK TABLES, EXECUTE ON `%s`.* TO `%s`@`%s`',
            $database,
            $username,
            $remote
        ));
    }

    /**
     * Flush the privileges for a given connection.
     *
     * @return bool
     */
    public function flush(): bool
    {
        return $this->run('FLUSH PRIVILEGES');
    }

    /**
     * Drop a given database on a specific connection.
     *
     * @param string $database
     * @return bool
     */
    public function dropDatabase(string $database): bool
    {
        return $this->run(sprintf('DROP DATABASE IF EXISTS `%s`', $database));
    }

    /**
     * Drop a given user on a specific connection.
     *
     * @param string $username
     * @param string $remote
     * @return mixed
     */
    public function dropUser(string $username, string $remote): bool
    {
        return $this->run(sprintf('DROP USER IF EXISTS `%s`@`%s`', $username, $remote));
    }

    /**
     * Run the provided statement against the database on a given connection.
     *
     * @param string $statement
     * @return bool
     */
    private function run(string $statement): bool
    {
        return $this->database->connection($this->getConnection())->statement($statement);
    }
}
