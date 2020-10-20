<?php

namespace Pterodactyl\Models;

use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Validable;
use Illuminate\Database\Eloquent\Model;
use Pterodactyl\Services\Acl\Api\AdminAcl;
use Sofa\Eloquence\Contracts\CleansAttributes;
use Sofa\Eloquence\Contracts\Validable as ValidableContract;

class ApiKey extends Model implements CleansAttributes, ValidableContract
{
    use Eloquence, Validable;

    /**
     * Different API keys that can exist on the system.
     */
    const TYPE_NONE = 0;
    const TYPE_ACCOUNT = 1;
    const TYPE_APPLICATION = 2;
    const TYPE_DAEMON_USER = 3;
    const TYPE_DAEMON_APPLICATION = 4;

    /**
     * The length of API key identifiers.
     */
    const IDENTIFIER_LENGTH = 16;

    /**
     * The length of the actual API key that is encrypted and stored
     * in the database.
     */
    const KEY_LENGTH = 32;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'api_keys';

    /**
     * Cast values to correct type.
     *
     * @var array
     */
    protected $casts = [
        'allowed_ips' => 'json',
        'user_id' => 'int',
        'r_' . AdminAcl::RESOURCE_USERS => 'int',
        'r_' . AdminAcl::RESOURCE_ALLOCATIONS => 'int',
        'r_' . AdminAcl::RESOURCE_DATABASE_HOSTS => 'int',
        'r_' . AdminAcl::RESOURCE_SERVER_DATABASES => 'int',
        'r_' . AdminAcl::RESOURCE_EGGS => 'int',
        'r_' . AdminAcl::RESOURCE_LOCATIONS => 'int',
        'r_' . AdminAcl::RESOURCE_NESTS => 'int',
        'r_' . AdminAcl::RESOURCE_NODES => 'int',
        'r_' . AdminAcl::RESOURCE_PACKS => 'int',
        'r_' . AdminAcl::RESOURCE_SERVERS => 'int',
    ];

    /**
     * Fields that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'identifier',
        'token',
        'allowed_ips',
        'memo',
        'last_used_at',
    ];

    /**
     * Fields that should not be included when calling toArray() or toJson()
     * on this model.
     *
     * @var array
     */
    protected $hidden = ['token'];

    /**
     * Rules defining what fields must be passed when making a model.
     *
     * @var array
     */
    protected static $applicationRules = [
        'identifier' => 'required',
        'memo' => 'required',
        'user_id' => 'required',
        'token' => 'required',
        'key_type' => 'present',
    ];

    /**
     * Rules to protect against invalid data entry to DB.
     *
     * @var array
     */
    protected static $dataIntegrityRules = [
        'user_id' => 'exists:users,id',
        'key_type' => 'integer|min:0|max:4',
        'identifier' => 'string|size:16|unique:api_keys,identifier',
        'token' => 'string',
        'memo' => 'nullable|string|max:500',
        'allowed_ips' => 'nullable|json',
        'last_used_at' => 'nullable|date',
        'r_' . AdminAcl::RESOURCE_USERS => 'integer|min:0|max:3',
        'r_' . AdminAcl::RESOURCE_ALLOCATIONS => 'integer|min:0|max:3',
        'r_' . AdminAcl::RESOURCE_DATABASE_HOSTS => 'integer|min:0|max:3',
        'r_' . AdminAcl::RESOURCE_SERVER_DATABASES => 'integer|min:0|max:3',
        'r_' . AdminAcl::RESOURCE_EGGS => 'integer|min:0|max:3',
        'r_' . AdminAcl::RESOURCE_LOCATIONS => 'integer|min:0|max:3',
        'r_' . AdminAcl::RESOURCE_NESTS => 'integer|min:0|max:3',
        'r_' . AdminAcl::RESOURCE_NODES => 'integer|min:0|max:3',
        'r_' . AdminAcl::RESOURCE_PACKS => 'integer|min:0|max:3',
        'r_' . AdminAcl::RESOURCE_SERVERS => 'integer|min:0|max:3',
    ];

    /**
     * @var array
     */
    protected $dates = [
        self::CREATED_AT,
        self::UPDATED_AT,
        'last_used_at',
    ];
}
