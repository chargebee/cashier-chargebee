<?php

namespace Chargebee\Cashier\Tests\Fixtures;

use Chargebee\Actions\Contracts\FeatureActionsInterface;
use Chargebee\Responses\FeatureResponse\ListFeatureResponse;
use Chargebee\Responses\FeatureResponse\RetrieveFeatureResponse;
use Chargebee\Responses\FeatureResponse\ReactivateFeatureResponse;
use Chargebee\Responses\FeatureResponse\ActivateFeatureResponse;
use Chargebee\Responses\FeatureResponse\CreateFeatureResponse;
use Chargebee\Responses\FeatureResponse\DeleteFeatureResponse;
use Chargebee\Responses\FeatureResponse\UpdateFeatureResponse;
use Chargebee\Responses\FeatureResponse\ArchiveFeatureResponse;

class FeatureActionsFixture implements FeatureActionsInterface
{
    public array $feature = [
        'feature' => [
            'id' => "Dummy-Feature-id",
            'name' => 'Free Trial',
            'description' => 'Gives 14 days of free trial access',
            'unit' => 'days',
            'resource_version' => 1,
            'updated_at' => 1690999999,
            'created_at' => 1690000000,
            'levels' => [
                [
                    'name' => 'basic',
                    'value' => '14',
                ],
            ],
            'status' => 'active',
            'type' => 'limit',
        ],
    ];
    public function retrieve(string $id, array $headers = []): RetrieveFeatureResponse
    {
        return RetrieveFeatureResponse::from([
            'feature' => [
                'id' => $id,
                'name' => 'Free Trial',
                'description' => 'Gives 14 days of free trial access',
                'unit' => 'days',
                'resource_version' => 1,
                'updated_at' => 1690999999,
                'created_at' => 1690000000,
                'levels' => [
                    [
                        'name' => 'basic',
                        'value' => '14',
                    ],
                ],
                'status' => 'active',
                'type' => 'limit',
            ],
        ]);
    }

    public function all(array $params = [], array $headers = []): ListFeatureResponse
    {
        $features = ListFeatureResponse::from([
            'list' => [
                [
                    'feature' => [
                        'id' => 'feature_free_trial',
                        'name' => 'Free Trial',
                        'description' => 'Gives 14 days of free trial access',
                        'unit' => 'days',
                        'resource_version' => 1,
                        'updated_at' => 1690999999,
                        'created_at' => 1690000000,
                        'levels' => [],
                        'status' => 'active',
                        'type' => 'limit',
                    ],
                ],
                [
                    'feature' => [
                        'id' => 'feature_priority_support',
                        'name' => 'Priority Support',
                        'description' => '24/7 support',
                        'unit' => null,
                        'resource_version' => 2,
                        'updated_at' => 1691999999,
                        'created_at' => 1691000000,
                        'levels' => [],
                        'status' => 'archived',
                        'type' => 'boolean',
                    ],
                ],
                [
                    'feature' => [
                        'id' => 'check_check',
                        'name' => 'Priority Support',
                        'description' => '24/7 support',
                        'unit' => null,
                        'resource_version' => 2,
                        'updated_at' => 1691999999,
                        'created_at' => 1691000000,
                        'levels' => [],
                        'status' => 'archived',
                        'type' => 'boolean',
                    ],
                ],
                [
                    'feature' => [
                        'id' => '11111111',
                        'name' => '12121212',
                        'description' => '24/7 support',
                        'unit' => null,
                        'resource_version' => 2,
                        'updated_at' => 1691999999,
                        'created_at' => 1691000000,
                        'levels' => [],
                        'status' => 'archived',
                        'type' => 'boolean',
                    ],
                ],
                [
                    'feature' => [
                        'id' => 'some-uuid',
                        'name' => 'myname$$$iscashier###',
                        'description' => '24/7 support',
                        'unit' => null,
                        'resource_version' => 2,
                        'updated_at' => 1691999999,
                        'created_at' => 1691000000,
                        'levels' => [],
                        'status' => 'archived',
                        'type' => 'boolean',
                    ],
                ],
            ],
            'next_offset' => 'off_456',
        ]);
        return $features;
    }

    public function create(array $params, array $headers = []): CreateFeatureResponse
    {
        return CreateFeatureResponse::from($this->feature);
    }

    public function delete(string $id, array $headers = []): DeleteFeatureResponse
    {
        return DeleteFeatureResponse::from($this->feature);
    }

    public function update(string $id, array $params = [], array $headers = []): UpdateFeatureResponse
    {
        return UpdateFeatureResponse::from($this->feature);
    }

    public function archive(string $id, array $headers = []): ArchiveFeatureResponse
    {
        return ArchiveFeatureResponse::from($this->feature);
    }

    public function activate(string $id, array $headers = []): ActivateFeatureResponse
    {
        return  ActivateFeatureResponse::from($this->feature);
    }

    public function reactivate(string $id, array $headers = []): ReactivateFeatureResponse
    {
        return ReactivateFeatureResponse::from($this->feature);
    }
}
