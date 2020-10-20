<?php

namespace Pterodactyl\Http\Requests\Api\Application\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Support\Collection;

class UpdateServerBuildConfigurationRequest extends ServerWriteRequest
{
    /**
     * Return the rules to validate this request against.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = Server::getUpdateRulesForId($this->getModel(Server::class)->id);

        return [
            'allocation' => $rules['allocation_id'],
            'oom_disabled' => $rules['oom_disabled'],

            'limits' => 'sometimes|array',
            'limits.memory' => $this->requiredToOptional('memory', $rules['memory'], true),
            'limits.swap' => $this->requiredToOptional('swap', $rules['swap'], true),
            'limits.io' => $this->requiredToOptional('io', $rules['io'], true),
            'limits.cpu' => $this->requiredToOptional('cpu', $rules['cpu'], true),
            'limits.disk' => $this->requiredToOptional('disk', $rules['disk'], true),

            // Legacy rules to maintain backwards compatable API support without requiring
            // a major version bump.
            //
            // @see https://github.com/pterodactyl/panel/issues/1500
            'memory' => $this->requiredToOptional('memory', $rules['memory']),
            'swap' => $this->requiredToOptional('swap', $rules['swap']),
            'io' => $this->requiredToOptional('io', $rules['io']),
            'cpu' => $this->requiredToOptional('cpu', $rules['cpu']),
            'disk' => $this->requiredToOptional('disk', $rules['disk']),

            'add_allocations' => 'bail|array',
            'add_allocations.*' => 'integer',
            'remove_allocations' => 'bail|array',
            'remove_allocations.*' => 'integer',

            'feature_limits' => 'required|array',
            'feature_limits.databases' => $rules['database_limit'],
            'feature_limits.allocations' => $rules['allocation_limit'],
        ];
    }

    /**
     * Convert the allocation field into the expected format for the service handler.
     *
     * @return array
     */
    public function validated()
    {
        $data = parent::validated();

        $data['allocation_id'] = $data['allocation'];
        $data['database_limit'] = $data['feature_limits']['databases'];
        $data['allocation_limit'] = $data['feature_limits']['allocations'];
        unset($data['allocation'], $data['feature_limits']);

        // Adjust the limits field to match what is expected by the model.
        if (! empty($data['limits'])) {
            foreach ($data['limits'] as $key => $value) {
                $data[$key] = $value;
            }

            unset($data['limits']);
        }

        return $data;
    }

    /**
     * Custom attributes to use in error message responses.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'add_allocations' => 'allocations to add',
            'remove_allocations' => 'allocations to remove',
            'add_allocations.*' => 'allocation to add',
            'remove_allocations.*' => 'allocation to remove',
            'feature_limits.databases' => 'Database Limit',
            'feature_limits.allocations' => 'Allocation Limit',
        ];
    }

    /**
     * Converts existing rules for certain limits into a format that maintains backwards
     * compatability with the old API endpoint while also supporting a more correct API
     * call.
     *
     * @param string $field
     * @param array  $rules
     * @param bool   $limits
     * @return array
     *
     * @see https://github.com/pterodactyl/panel/issues/1500
     */
    protected function requiredToOptional(string $field, array $rules, bool $limits = false)
    {
        if (! in_array('required', $rules)) {
            return $rules;
        }

        return (new Collection($rules))
            ->filter(function ($value) {
                return $value !== 'required';
            })
            ->prepend($limits ? 'required_with:limits' : 'required_without:limits')
            ->toArray();
    }
}
