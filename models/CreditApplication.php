<?php namespace Responsiv\Pay\Models;

use Model;

/**
 * CreditApplication tracks when credit from a CreditNote is applied to
 * an invoice. This is the debit side of the credit ledger.
 *
 * @property int $id
 * @property int $credit_note_id
 * @property int $invoice_id
 * @property int $user_id
 * @property int $amount
 * @property \Illuminate\Support\Carbon $applied_at
 * @property \Illuminate\Support\Carbon $voided_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @package responsiv\pay
 * @author Alexey Bobkov, Samuel Georges
 */
class CreditApplication extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table used by the model
     */
    public $table = 'responsiv_pay_credit_applications';

    /**
     * @var array fillable fields
     */
    protected $fillable = [
        'credit_note_id',
        'invoice_id',
        'user_id',
        'amount',
        'applied_at',
    ];

    /**
     * @var array dates are attributes to convert to Carbon instances
     */
    protected $dates = ['applied_at', 'voided_at'];

    /**
     * @var array rules for validation
     */
    public $rules = [
        'credit_note' => 'required',
        'invoice' => 'required',
        'user' => 'required',
        'amount' => 'required|integer|min:1',
    ];

    /**
     * @var array belongsTo
     */
    public $belongsTo = [
        'credit_note' => CreditNote::class,
        'invoice' => Invoice::class,
        'user' => \RainLab\User\Models\User::class,
    ];

    //
    // Scopes
    //

    /**
     * scopeApplyActive filters to non-voided applications
     */
    public function scopeApplyActive($query)
    {
        return $query->whereNull('voided_at');
    }

    //
    // Actions
    //

    /**
     * void marks this application as voided, restoring the credit back to
     * the credit note's available balance. Also updates the denormalized
     * credit_applied cache on the parent invoice.
     */
    public function void()
    {
        if ($this->voided_at) {
            return;
        }

        $this->voided_at = $this->freshTimestamp();
        $this->save();

        // Update denormalized cache on invoice (fresh load to avoid stale relation)
        if ($invoice = Invoice::find($this->invoice_id)) {
            $invoice->credit_applied = max(0, ($invoice->credit_applied ?? 0) - $this->amount);
            $invoice->save();
        }
    }
}
