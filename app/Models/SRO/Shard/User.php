<?php

namespace App\Models\SRO\Shard;

use App\Models\SRO\Account\TbUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

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
    protected $table = 'dbo._User';

    /**
     * The table primary Key
     *
     * @var string
     */
    protected $primaryKey = 'UserJID';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'UserJID', 'CharID'
    ];

    public function tbUser()
    {
        if (config('global.server.version') === 'vSRO') {
            return $this->belongsTo(TbUser::class, 'UserJID', 'JID');
        } else {
            return $this->belongsTo(TbUser::class, 'UserJID', 'JID');
        }
    }
}
