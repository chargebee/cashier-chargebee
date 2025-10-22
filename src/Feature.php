<?php

namespace Chargebee\Cashier;

use Chargebee\Resources\Feature\Feature as ChargebeeFeature;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $primaryKey = 'chargebee_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['chargebee_id', 'json_data'];

    protected $casts = [
        'json_data' => 'array',
    ];

    public function toChargebeeFeature(): ChargebeeFeature
    {
        $payload = $this->jsonData ?? [];

        return ChargebeeFeature::from($payload);
    }
}
