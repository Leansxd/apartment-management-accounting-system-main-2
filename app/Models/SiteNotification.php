<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Site içi bildirim: unit_id boşsa tüm site, doluysa yalnızca o daire. */
class SiteNotification extends Model
{
    protected $table = 'notifications';

    protected $guarded = ['id'];

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class)->withTrashed(); }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_reads', 'notification_id')->withPivot('read_at');
    }
}
