<?php namespace Responsiv\Pay\Models;

use Db;
use Event;
use Model;
use ApplicationException;

/**
 * CreditNote implements a simple ledger for store credit. Credit notes
 * (refund, adjustment, promotion) increase the balance; debit notes
 * decrease it. Balance = sum(credits) - sum(debits).
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
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
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
    protected $dates = ['issued_at'];

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
     * Currency must be specified explicitly. An optional invoice can be linked.
     */
    public static function issueDebit($user, $amount, $currencyCode, $reason, $adminUser = null, $invoice = null): static
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

        if ($invoice) {
            $note->invoice_id = $invoice->id;
        }

        $note->save();

        Event::fire('responsiv.pay.creditNote.issued', [$note]);

        return $note;
    }

    /**
     * getBalanceForUser returns the credit balance for a user in a given currency.
     * Simple ledger: balance = sum(credits) - sum(debits).
     */
    public static function getBalanceForUser($user, $currencyCode = null): int
    {
        if ($currencyCode === null) {
            $currencyCode = \Currency::getActiveCode();
        }

        $credited = static::applyUser($user)
            ->applyCurrency($currencyCode)
            ->where('type', '!=', static::TYPE_DEBIT)
            ->sum('amount');

        $debited = static::applyUser($user)
            ->applyCurrency($currencyCode)
            ->applyType(static::TYPE_DEBIT)
            ->sum('amount');

        return max(0, (int) $credited - (int) $debited);
    }

    /**
     * getHistoryForUser returns all credit notes for a user in a given currency,
     * ordered by most recent first.
     */
    public static function getHistoryForUser($user, $currencyCode = null)
    {
        $query = static::applyUser($user)
            ->orderBy('issued_at', 'desc');

        if ($currencyCode !== null) {
            $query->applyCurrency($currencyCode);
        }

        return $query->get();
    }

    /**
     * applyToInvoice creates a debit note that spends credit toward an invoice.
     * Uses database-level locking to prevent double-spending in concurrent
     * checkout scenarios.
     */
    public static function applyToInvoice($user, $invoice, $requestedAmount): static
    {
        return Db::transaction(function() use ($user, $invoice, $requestedAmount) {
            // Lock credit notes to serialize concurrent access
            static::where('user_id', $user->id)
                ->where('currency_code', $invoice->currency_code)
                ->lockForUpdate()
                ->count();

            $balance = static::getBalanceForUser($user, $invoice->currency_code);
            $amountToApply = min($requestedAmount, $invoice->total);

            if ($amountToApply <= 0 || $balance < $amountToApply) {
                throw new ApplicationException('Insufficient store credit');
            }

            $note = static::issueDebit(
                $user,
                $amountToApply,
                $invoice->currency_code,
                __('Applied to invoice'),
                null,
                $invoice
            );

            // Update denormalized cache on invoice
            $invoice->credit_applied = ($invoice->credit_applied ?? 0) + $amountToApply;
            $invoice->save();

            Event::fire('responsiv.pay.creditNote.applied', [$user, $invoice, $note]);
            Event::fire('responsiv.pay.invoice.creditApplied', [$invoice, $amountToApply]);

            return $note;
        });
    }

    /**
     * removeFromInvoice reverses credit applied to an invoice by issuing
     * a credit note (adjustment) linked to the invoice.
     */
    public static function removeFromInvoice($user, $invoice): ?static
    {
        $creditApplied = $invoice->credit_applied;
        if ($creditApplied <= 0) {
            return null;
        }

        $note = new static;
        $note->user = $user;
        $note->amount = $creditApplied;
        $note->currency_code = $invoice->currency_code;
        $note->reason = __('Credit reversed');
        $note->type = static::TYPE_ADJUSTMENT;
        $note->invoice_id = $invoice->id;
        $note->issued_at = $note->freshTimestamp();
        $note->save();

        $invoice->credit_applied = 0;
        $invoice->save();

        Event::fire('responsiv.pay.creditNote.issued', [$note]);

        return $note;
    }
}
