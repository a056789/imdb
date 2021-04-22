<?php

namespace App;

use Eloquent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * App\VideoPlay
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin Eloquent
 */
class VideoPlay extends Model
{
    const UPDATED_AT = null;
    protected $guarded = ['id'];
    protected $casts = ['user_id' => 'integer', 'video_id' => 'integer'];
}
