<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name'
    ];


    public function tasks(): HasMany
    {
        return $this->hasMany(UserTask::class);
    }

    public static function register($data)
    {
        $validated = [
            'telegram_id' => $data['from']['id'],
            'username' => $data['from']['username'] ?? null,
            'first_name' => $data['from']['first_name'] ?? null,
            'last_name' => $data['from']['last_name'] ?? null,
        ];

        if (self::identification($data)) {
            return response()->json(['message' => 'The user is already registered'], 409);
        }

        self::create($validated);
    }

    public static function identification($data): bool
    { 
        $telegramId = $data['from']['id'];

        return User::where('telegram_id', $telegramId)->exists();
    }
}
