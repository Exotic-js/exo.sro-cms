<?php

namespace App\Models\SRO\Shard;

use Illuminate\Database\Eloquent\Model;

class RefObjCommon extends Model
{
    /**
     * The Database connection name for the model.
     *
     * @var string
     */
    protected $connection = 'shard';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dbo._RefObjCommon';

    /**
     * The table primary Key.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['*'];

    public function getCodeNameAttribute(): ?string
    {
        return $this->CodeName128;
    }
}