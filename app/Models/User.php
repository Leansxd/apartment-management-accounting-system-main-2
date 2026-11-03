<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Görüntüleyenin görebileceği kullanıcılar: süper admin herkesi; admin yalnızca kendi sitelerinin
     * sakinlerini, o sitelerin yöneticilerini ve kendisini görür (siteler arası veri sızıntısı yok).
     */
    public function scopeVisibleTo(Builder $query, self $viewer): void
    {
        if ($viewer->is_super_admin) {
            return;
        }
        $siteIds = $viewer->accessibleApartments()->pluck('id');

        $query->where(fn ($q) => $q
            ->whereIn('id', Member::whereIn('apartment_id', $siteIds)->whereNotNull('user_id')->select('user_id'))
            ->orWhereIn('id', DB::table('apartment_manager')->whereIn('apartment_id', $siteIds)->select('user_id'))
            ->orWhere('id', $viewer->id));
    }


    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function managedApartments(): BelongsToMany
    {
        return $this->belongsToMany(Apartment::class, 'apartment_manager');
    }

    /** Sakin olarak bağlı olduğu üyelikler (silinmemiş) */
    public function memberships(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /** Panelde görebileceği apartmanlar: süper admin hepsini, admin sahip/yönetici olduklarını */
    public function accessibleApartments(): Builder
    {
        $query = Apartment::query()->orderBy('name');
        if ($this->is_super_admin) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('owner_id', $this->id)
            ->orWhereHas('managers', fn ($m) => $m->whereKey($this->id)));
    }

    /** Bu kullanıcının sakin olarak görebileceği daire id'leri (verilen apartmanda) */
    public function residentUnitIds(Apartment $apartment): array
    {
        return $this->memberships()->where('apartment_id', $apartment->id)->pluck('unit_id')->unique()->values()->all();
    }
}
