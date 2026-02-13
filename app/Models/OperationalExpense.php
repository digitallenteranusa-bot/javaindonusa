<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'description',
        'amount',
        'expense_date',
        'period_month',
        'period_year',
        'receipt_photo',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'period_month' => 'integer',
            'period_year' => 'integer',
        ];
    }

    // ================================================================
    // CONSTANTS
    // ================================================================

    const CATEGORY_SALARY = 'salary';
    const CATEGORY_RENT = 'rent';
    const CATEGORY_ELECTRICITY = 'electricity';
    const CATEGORY_INTERNET = 'internet';
    const CATEGORY_EQUIPMENT = 'equipment';
    const CATEGORY_MAINTENANCE = 'maintenance';
    const CATEGORY_OTHER = 'other';

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeByMonth($query, int $month, int $year)
    {
        return $query->where('period_month', $month)->where('period_year', $year);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSalaries($query)
    {
        return $query->where('category', self::CATEGORY_SALARY);
    }

    public function scopeNonSalary($query)
    {
        return $query->where('category', '!=', self::CATEGORY_SALARY);
    }

    // ================================================================
    // ACCESSORS & HELPERS
    // ================================================================

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::getCategories()[$this->category] ?? $this->category;
    }

    public function getReceiptUrlAttribute(): ?string
    {
        if (!$this->receipt_photo) {
            return null;
        }

        return asset('storage/' . $this->receipt_photo);
    }

    public static function getCategories(): array
    {
        return [
            self::CATEGORY_SALARY => 'Gaji Pegawai',
            self::CATEGORY_RENT => 'Sewa Kantor/Gedung',
            self::CATEGORY_ELECTRICITY => 'Listrik',
            self::CATEGORY_INTERNET => 'Internet Kantor',
            self::CATEGORY_EQUIPMENT => 'Peralatan',
            self::CATEGORY_MAINTENANCE => 'Perawatan',
            self::CATEGORY_OTHER => 'Lainnya',
        ];
    }
}
