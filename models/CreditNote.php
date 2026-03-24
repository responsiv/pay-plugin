<?php namespace Responsiv\Pay\Models;

use Db;
use Event;
use Model;
use ApplicationException;

/**
 * CreditNote represents credit owed to a customer, functioning as the
 * credit side of a double-entry ledger. Credit is applied to invoices
 * via CreditApplication records (the debit side).
 *
 * @property int $id
 * @property int $user_id
 * @property int $invoice_id
 * @property int $amount
 * @property string $currency_code
 * @property string $reason
 * @property string $type
 * @property int $issued_by
 * @property \Illuminate\Support\Carbon $issued_at
 * @property \Illuminate\Support\Carbon $voided_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property int $available_balance
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class CreditNote extends Model
{
    use \October\Rain\Database\Traits\Validation;

    const TYPE_REFUND = 'refund';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_DEBIT = 'debit';
    const TYPE_PROMOTION = 'promotion';

    /**
     * @var string table used by the model
     */
    public $table = 'responsiv_pay_credit_notes';

    /**
     * @var array dates are attributes to convert to Carbon instances
     */
    protected $dates = ['issued_at', 'voided_at'];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'user' => 'required',
        'amount' => 'required|integer|min:1',
        'currency_code' => 'required',
        'type' => 'required|in:refund,adjustment,debit,promotion',
    ];

    /**
     * @var array belongsTo
     */
    public $belongsTo = [
        'user' => \RainLab\User\Models\User::class,
        'invoice' => Invoice::class,
        'issued_by_user' => [\Backend\Models\User::class, 'key' => 'issued_by'],
    ];

    /**
     * @var array hasMany
     */
    public $hasMany = [
        'applications' => CreditApplication::class,
    ];

    /**
     * getAvailableBalanceAttribute returns the remaining balance on this
     * credit note after subtracting all active (non-voided) applications.
     */
    public function getAvailableBalanceAttribute(): int
    {
        if ($this->voided_at || $this->type === static::TYPE_DEBIT) {
            return 0;
        }

        $applied = $this->applications()
            ->whereNull('voided_at')
            ->sum('amount');

        return max(0, $this->amount - $applied);
    }

    /**
     * getTypeOptions returns available type options for form dropdowns
     */
    public function getTypeOptions(): array
    {
        return [
            self::TYPE_REFUND => 'Refund',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_DEBIT => 'Debit',
            self::TYPE_PROMOTION => 'Promotion',
        ];
    }

    //
    // Scopes
    //

    /**
     * scopeApplyUser filters by user
     */
    public function scopeApplyUser($query, $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * scopeApplyActive filters to non-voided credit notes
     */
    public function scopeApplyActive($query)
    {
        return $query->whereNull('voided_at');
    }

    /**
     * scopeApplyType filters by credit note type
     */
    public function scopeApplyType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * scopeApplyCurrency filters by currency code
     */
    public function scopeApplyCurrency($query, $currencyCode)
    {
        return $query->where('currency_code', $currencyCode);
    }

    //
    // Actions
    //

    /**
     * void marks this credit note as voided. Any unapplied balance becomes
     * unavailable. Already-applied amounts are not reversed.
     */
    public function void()
    {
        if ($this->voided_at) {
            return;
        }

        $this->voided_at = $this->freshTimestamp();
        $this->save();

        Event::fire('responsiv.pay.creditNote.voided', [$this]);
    }

    //
    // Static API
    //

    /**
     * issueRefund creates a credit note linked to an invoice refund.
     * The currency is inherited from the source invoice.
     */
    public static function issueRefund($user, $invoice, $amount, $reason = null): static
    {
        $note = new static;
        $note->user = $user;
        $note->invoice = $invoice;
        $note->amount = $amount;
        $note->currency_code = $invoice->currency_code;
        $note->reason = $reason ?: "Refund for invoice #{$invoice->id}";
        $note->type = static::TYPE_REFUND;
        $note->issued_at = $note->freshTimestamp();
        $note->save();

        Event::fire('responsiv.pay.creditNote.issued', [$note]);

        return $note;
    }

    /**
     * issueAdjustment creates a credit note for a manual admin adjustment
     * or promotion. Currency must be specified explicitly.
     */
    public static function issueAdjustment($user, $amount, $currencyCode, $reason, $adminUser = null): static
    {
        $note = new static;
        $note->user = $user;
        $note->amount = $amount;
        $note->currency_code = $currencyCode;
        $note->reason = $reason;
        $note->type = static::TYPE_ADJUSTMENT;
        $note->issued_at = $note->freshTimestamp();

        if ($adminUser) {
            $note->issued_by = $adminUser->id;
        }

        $note->save();

        Event::fire('responsiv.pay.creditNote.issued', [$note]);

        return $note;
    }

    /**
     * issueDebit creates a debit note that subtracts from a user's credit balance.
     * Currency must be specified explicitly.
     */
    public static function issueDebit($user, $amount, $currencyCode, $reason, $adminUser = null): static
    {
        $note = new static;
        $note->user = $user;
        $note->amount = $amount;
        $note->currency_code = $currencyCode;
        $note->reason = $reason;
        $note->type = static::TYPE_DEBIT;
        $note->issued_at = $note->freshTimestamp();

        if ($adminUser) {
            $note->issued_by = $adminUser->id;
        }

        $note->save();

        Event::fire('responsiv.pay.creditNote.issued', [$note]);

        return $note;
    }

    /**
     * getBalanceForUser returns the credit balance for a user in a given currency.
     * Balance = issued credit minus debits minus spent (applied) credit.
     */
    public static function getBalanceForUser($user, $currencyCode = null): int
    {
        if ($currencyCode === null) {
            $currencyCode = \Currency::getActiveCode();
        }

        $issued = static::applyUser($user)
            ->applyActive()
            ->applyCurrency($currencyCode)
            ->where('type', '!=', static::TYPE_DEBIT)
            ->sum('amount');

        $debited = static::applyUser($user)
            ->applyActive()
            ->applyCurrency($currencyCode)
            ->applyType(static::TYPE_DEBIT)
            ->sum('amount');

        $spent = CreditApplication::where('user_id', $user->id)
            ->whereNull('voided_at')
            ->whereHas('credit_note', function($q) use ($currencyCode) {
                $q->where('currency_code', $currencyCode);
            })
            ->sum('amount');

        return (int) $issued - (int) $debited - (int) $spent;
    }

    /**
     * getHistoryForUser returns all credit notes for a user in a given currency,
     * with their applications, ordered by most recent first.
     */
    public static function getHistoryForUser($user, $currencyCode = null)
    {
        $query = static::applyUser($user)
            ->with('applications')
            ->orderBy('issued_at', 'desc');

        if ($currencyCode !== null) {
            $query->applyCurrency($currencyCode);
        }

        return $query->get();
    }

    /**
     * applyToInvoice distributes credit across the user's available credit
     * notes (FIFO by issued_at) and creates CreditApplication records.
     *
     * Uses database-level locking to prevent double-spending in concurrent
     * checkout scenarios.
     *
     * @return \Illuminate\Support\Collection<CreditApplication>
     */
    public static function applyToInvoice($user, $invoice, $requestedAmount)
    {
        return Db::transaction(function() use ($user, $invoice, $requestedAmount) {
            // Lock this user's active credit notes for the invoice currency
            $creditNotes = static::where('user_id', $user->id)
                ->where('currency_code', $invoice->currency_code)
                ->whereNull('voided_at')
                ->lockForUpdate()
                ->orderBy('issued_at')
                ->get();

            $available = $creditNotes->sum('available_balance');
            $amountToApply = min($requestedAmount, $available, $invoice->total);

            if ($amountToApply <= 0) {
                throw new ApplicationException('Insufficient store credit');
            }

            // Distribute across notes (FIFO by issued_at)
            $applications = collect();
            $remaining = $amountToApply;

            foreach ($creditNotes as $note) {
                if ($remaining <= 0) {
                    break;
                }

                $noteAvailable = $note->available_balance;
                if ($noteAvailable <= 0) {
                    continue;
                }

                $applyFromNote = min($remaining, $noteAvailable);

                $application = CreditApplication::create([
                    'credit_note_id' => $note->id,
                    'invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                    'amount' => $applyFromNote,
                    'applied_at' => now(),
                ]);

                $applications->push($application);
                $remaining -= $applyFromNote;
            }

            // Update denormalized cache on invoice
            $invoice->credit_applied = ($invoice->credit_applied ?? 0) + $amountToApply;
            $invoice->save();

            Event::fire('responsiv.pay.creditNote.applied', [$user, $invoice, $applications]);
            Event::fire('responsiv.pay.invoice.creditApplied', [$invoice, $amountToApply]);

            return $applications;
        });
    }
}
