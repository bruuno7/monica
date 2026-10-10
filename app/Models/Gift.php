<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gift extends Model
{
    use HasFactory;

    /** A gift the user wants to give (or gave) to the contact. */
    public const TYPE_TO_GIVE = 'to_give';

    /** A gift the contact gave to the user. */
    public const TYPE_RECEIVED = 'received';

    public const TYPES = [
        self::TYPE_TO_GIVE,
        self::TYPE_RECEIVED,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'contact_id',
        'type',
        'gift_occasion_id',
        'gift_state_id',
        'name',
        'description',
        'url',
        'estimated_price',
        'currency_id',
        'received_at',
        'given_at',
        'bought_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'received_at' => 'datetime',
        'given_at' => 'datetime',
        'bought_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<GiftOccasion, $this>
     */
    public function occasion(): BelongsTo
    {
        return $this->belongsTo(GiftOccasion::class, 'gift_occasion_id');
    }

    /**
     * @return BelongsTo<GiftState, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(GiftState::class, 'gift_state_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Whether the gift is still an idea or in progress (not offered yet).
     */
    public function isPending(): bool
    {
        return $this->type === self::TYPE_TO_GIVE && $this->given_at === null;
    }
}
