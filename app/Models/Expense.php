<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'expense_number',
        'amount',
        'category',
        'description',
        'receipt_photo',
        'status',
        'expense_date',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const CATEGORY_TRANSPORT = 'transport';
    const CATEGORY_MEAL = 'meal';
    const CATEGORY_PARKING = 'parking';
    const CATEGORY_TOLL = 'toll';
    const CATEGORY_FUEL = 'fuel';
    const CATEGORY_MAINTENANCE = 'maintenance';
    const CATEGORY_OTHER = 'other';

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('expense_date', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    // ================================================================
    // ACCESSORS & HELPERS
    // ================================================================

    /**
     * Format amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    /**
     * Get category label in Indonesian
     */
    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            self::CATEGORY_TRANSPORT => 'Transportasi',
            self::CATEGORY_MEAL => 'Makan',
            self::CATEGORY_PARKING => 'Parkir',
            self::CATEGORY_TOLL => 'Tol',
            self::CATEGORY_FUEL => 'BBM',
            self::CATEGORY_MAINTENANCE => 'Perawatan',
            self::CATEGORY_OTHER => 'Lainnya',
            default => $this->category,
        };
    }

    /**
     * Get status label in Indonesian
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            default => $this->status,
        };
    }

    /**
     * Get receipt photo URL
     */
    public function getReceiptUrlAttribute(): ?string
    {
        if (!$this->receipt_photo) {
            return null;
        }

        return asset('storage/' . $this->receipt_photo);
    }

    /**
     * Approve this expense
     */
    public function approve(User $admin): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
    }

    /**
     * Reject this expense
     */
    public function reject(User $admin, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Get all categories as array
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_TRANSPORT => 'Transportasi',
            self::CATEGORY_MEAL => 'Makan',
            self::CATEGORY_PARKING => 'Parkir',
            self::CATEGORY_TOLL => 'Tol',
            self::CATEGORY_FUEL => 'BBM',
            self::CATEGORY_MAINTENANCE => 'Perawatan',
            self::CATEGORY_OTHER => 'Lainnya',
        ];
    }
}
