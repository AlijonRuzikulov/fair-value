<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request left on the public website ("Ариза").
 */
class Lead extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'phone', 'email', 'service', 'message', 'status', 'handled_by', 'notes', 'locale', 'ip_address'];

    protected function casts(): array
    {
        return ['status' => LeadStatus::class];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
