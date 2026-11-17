<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\PromptComposer;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'common_prompt', 'fix_prompt'])]
#[Hidden(['password', 'remember_token', 'api_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function outfitThemes(): HasMany
    {
        return $this->hasMany(OutfitTheme::class);
    }

    public function guidelines(): HasMany
    {
        return $this->hasMany(Guideline::class);
    }

    public function outfitLayouts(): HasMany
    {
        return $this->hasMany(OutfitLayout::class);
    }

    public function fashionModels(): HasMany
    {
        return $this->hasMany(FashionModel::class);
    }

    public function coverTemplates(): HasMany
    {
        return $this->hasMany(CoverTemplate::class);
    }

    public function outfits(): HasMany
    {
        return $this->hasMany(Outfit::class);
    }

    /** Tài khoản mình đã chia sẻ toàn bộ outfit cho (họ xem được cả outfit riêng tư của mình). */
    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'outfit_shares', 'owner_id', 'viewer_id')->withTimestamps()->orderBy('name');
    }

    /** Tài khoản đã chia sẻ outfit của họ cho mình. */
    public function sharedOwners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'outfit_shares', 'viewer_id', 'owner_id')->withTimestamps()->orderBy('name');
    }

    /** Mình có được xem toàn bộ outfit của $ownerId không (chính mình hoặc được chia sẻ). */
    public function canViewOutfitsOf(int $ownerId): bool
    {
        return $ownerId === $this->id || $this->sharedOwners()->whereKey($ownerId)->exists();
    }

    public function stylingRules(): HasMany
    {
        return $this->hasMany(StylingRule::class);
    }

    public function stylingIdeas(): HasMany
    {
        return $this->hasMany(StylingIdea::class);
    }

    public function outfitItems(): HasMany
    {
        return $this->hasMany(OutfitItem::class);
    }

    public function fixPrompt(): string
    {
        return filled($this->fix_prompt) ? trim($this->fix_prompt) : PromptComposer::defaultFix();
    }

    /** Tạo API token mới. Chỉ lưu bản băm; trả về token gốc để hiện cho người dùng một lần. */
    public function issueApiToken(): string
    {
        $plain = Str::random(48);
        $this->forceFill(['api_token' => hash('sha256', $plain)])->save();

        return $plain;
    }

    public static function findByApiToken(string $plain): ?self
    {
        return $plain === '' ? null : static::where('api_token', hash('sha256', $plain))->first();
    }
}
