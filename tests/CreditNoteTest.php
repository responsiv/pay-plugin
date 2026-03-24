<?php namespace Responsiv\Pay\Tests;

use PluginTestCase;
use October\Rain\Database\Model;
use ApplicationException;
use Responsiv\Pay\Models\CreditNote;
use Responsiv\Pay\Models\Invoice;

/**
 * CreditNoteTest validates the credit note ledger system including
 * issuance, balance calculation, application to invoices, and reversal.
 */
class CreditNoteTest extends PluginTestCase
{
    //
    // Helpers
    //

    /**
     * createUser creates a test user
     */
    protected function createUser(): \RainLab\User\Models\User
    {
        Model::unguard();
        $user = \RainLab\User\Models\User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test-credit-' . uniqid() . '@example.com',
            'password' => 'testing123',
            'password_confirmation' => 'testing123',
        ]);
        Model::reguard();

        return $user;
    }

    /**
     * createInvoice creates a test invoice for the given user with a total
     */
    protected function createInvoice($user, int $total, string $currencyCode = 'USD'): Invoice
    {
        Model::unguard();
        $invoice = new Invoice;
        $invoice->user = $user;
        $invoice->first_name = $user->first_name;
        $invoice->last_name = $user->last_name;
        $invoice->email = $user->email;
        $invoice->total = $total;
        $invoice->subtotal = $total;
        $invoice->currency_code = $currencyCode;
        $invoice->save();
        Model::reguard();

        return $invoice;
    }

    //
    // Issuance Tests
    //

    /**
     * testIssueAdjustmentCreatesNote
     */
    public function testIssueAdjustmentCreatesNote()
    {
        $user = $this->createUser();

        $note = CreditNote::issueAdjustment($user, 5000, 'USD', 'Test credit');

        $this->assertNotNull($note->id);
        $this->assertEquals(5000, $note->amount);
        $this->assertEquals('USD', $note->currency_code);
        $this->assertEquals('adjustment', $note->type);
        $this->assertEquals('Test credit', $note->reason);
        $this->assertNotNull($note->issued_at);
        $this->assertNull($note->issued_by);
    }

    /**
     * testIssueAdjustmentWithAdminUser
     */
    public function testIssueAdjustmentWithAdminUser()
    {
        $user = $this->createUser();

        Model::unguard();
        $admin = \Backend\Models\User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'login' => 'admin-credit-test-' . uniqid(),
            'email' => 'admin-credit-' . uniqid() . '@example.com',
            'password' => 'testing123',
            'password_confirmation' => 'testing123',
        ]);
        Model::reguard();

        $note = CreditNote::issueAdjustment($user, 3000, 'USD', 'Admin adjustment', $admin);

        $this->assertEquals($admin->id, $note->issued_by);
    }

    /**
     * testIssueRefundCreatesNoteLinkedToInvoice
     */
    public function testIssueRefundCreatesNoteLinkedToInvoice()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, 10000);

        $note = CreditNote::issueRefund($user, $invoice, 4000, 'Partial refund');

        $this->assertNotNull($note->id);
        $this->assertEquals(4000, $note->amount);
        $this->assertEquals('refund', $note->type);
        $this->assertEquals($invoice->id, $note->invoice_id);
        $this->assertEquals('USD', $note->currency_code);
        $this->assertEquals('Partial refund', $note->reason);
    }

    /**
     * testIssueRefundInheritsCurrencyFromInvoice
     */
    public function testIssueRefundInheritsCurrencyFromInvoice()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, 5000, 'EUR');

        $note = CreditNote::issueRefund($user, $invoice, 5000);

        $this->assertEquals('EUR', $note->currency_code);
    }

    /**
     * testIssueDebitLinkedToInvoice
     */
    public function testIssueDebitLinkedToInvoice()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, 5000);

        $note = CreditNote::issueDebit($user, 3000, 'USD', 'Test', null, $invoice);

        $this->assertEquals('debit', $note->type);
        $this->assertEquals($invoice->id, $note->invoice_id);
        $this->assertEquals(3000, $note->amount);
    }

    //
    // Balance Tests
    //

    /**
     * testGetBalanceForUserSingleNote
     */
    public function testGetBalanceForUserSingleNote()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');

        $this->assertEquals(5000, CreditNote::getBalanceForUser($user, 'USD'));
    }

    /**
     * testGetBalanceForUserMultipleNotes
     */
    public function testGetBalanceForUserMultipleNotes()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 3000, 'USD', 'First');
        CreditNote::issueAdjustment($user, 2000, 'USD', 'Second');

        $this->assertEquals(5000, CreditNote::getBalanceForUser($user, 'USD'));
    }

    /**
     * testGetBalanceForUserSubtractsDebits
     */
    public function testGetBalanceForUserSubtractsDebits()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Credit');
        CreditNote::issueDebit($user, 2000, 'USD', 'Debit');

        $this->assertEquals(3000, CreditNote::getBalanceForUser($user, 'USD'));
    }

    /**
     * testGetBalanceForUserSeparatesByCurrency
     */
    public function testGetBalanceForUserSeparatesByCurrency()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'USD credit');
        CreditNote::issueAdjustment($user, 3000, 'EUR', 'EUR credit');

        $this->assertEquals(5000, CreditNote::getBalanceForUser($user, 'USD'));
        $this->assertEquals(3000, CreditNote::getBalanceForUser($user, 'EUR'));
    }

    /**
     * testGetBalanceForUserAfterSpending
     */
    public function testGetBalanceForUserAfterSpending()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 3000);

        CreditNote::applyToInvoice($user, $invoice, 3000);

        $this->assertEquals(2000, CreditNote::getBalanceForUser($user, 'USD'));
    }

    /**
     * testGetBalanceForUserZeroWithNoNotes
     */
    public function testGetBalanceForUserZeroWithNoNotes()
    {
        $user = $this->createUser();

        $this->assertEquals(0, CreditNote::getBalanceForUser($user, 'USD'));
    }

    /**
     * testGetBalanceForUserNeverNegative
     */
    public function testGetBalanceForUserNeverNegative()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 1000, 'USD', 'Credit');
        CreditNote::issueDebit($user, 5000, 'USD', 'Large debit');

        $this->assertEquals(0, CreditNote::getBalanceForUser($user, 'USD'));
    }

    //
    // Apply to Invoice Tests
    //

    /**
     * testApplyToInvoiceFullAmount
     */
    public function testApplyToInvoiceFullAmount()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 5000);

        $debitNote = CreditNote::applyToInvoice($user, $invoice, 5000);

        $this->assertInstanceOf(CreditNote::class, $debitNote);
        $this->assertEquals('debit', $debitNote->type);
        $this->assertEquals(5000, $debitNote->amount);
        $this->assertEquals($invoice->id, $debitNote->invoice_id);

        $invoice->refresh();
        $this->assertEquals(5000, $invoice->credit_applied);
        $this->assertEquals(0, $invoice->amount_due);
    }

    /**
     * testApplyToInvoicePartialAmount
     */
    public function testApplyToInvoicePartialAmount()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 10000);

        $debitNote = CreditNote::applyToInvoice($user, $invoice, 3000);

        $this->assertEquals(3000, $debitNote->amount);

        $invoice->refresh();
        $this->assertEquals(3000, $invoice->credit_applied);
        $this->assertEquals(7000, $invoice->amount_due);
    }

    /**
     * testApplyToInvoiceFailsWhenInsufficientBalance
     */
    public function testApplyToInvoiceFailsWhenInsufficientBalance()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 3000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 10000);

        $this->expectException(\ApplicationException::class);
        CreditNote::applyToInvoice($user, $invoice, 10000);
    }

    /**
     * testApplyToInvoiceCapsAtInvoiceTotal
     */
    public function testApplyToInvoiceCapsAtInvoiceTotal()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 10000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 3000);

        $debitNote = CreditNote::applyToInvoice($user, $invoice, 10000);

        $this->assertEquals(3000, $debitNote->amount);

        $invoice->refresh();
        $this->assertEquals(3000, $invoice->credit_applied);
    }

    /**
     * testApplyToInvoiceThrowsWhenNoCredit
     */
    public function testApplyToInvoiceThrowsWhenNoCredit()
    {
        $this->expectException(ApplicationException::class);

        $user = $this->createUser();
        $invoice = $this->createInvoice($user, 5000);

        CreditNote::applyToInvoice($user, $invoice, 5000);
    }

    /**
     * testApplyToInvoiceIgnoresOtherCurrencies
     */
    public function testApplyToInvoiceIgnoresOtherCurrencies()
    {
        $this->expectException(ApplicationException::class);

        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'EUR', 'Euro credit');
        $invoice = $this->createInvoice($user, 3000, 'USD');

        CreditNote::applyToInvoice($user, $invoice, 3000);
    }

    /**
     * testApplyToInvoiceIgnoresDebitNotes
     */
    public function testApplyToInvoiceIgnoresDebitNotes()
    {
        $this->expectException(ApplicationException::class);

        $user = $this->createUser();
        CreditNote::issueDebit($user, 5000, 'USD', 'Debit only');

        $invoice = $this->createInvoice($user, 3000);

        CreditNote::applyToInvoice($user, $invoice, 3000);
    }

    /**
     * testApplyToInvoiceMultipleApplicationsReduceBalance
     */
    public function testApplyToInvoiceMultipleApplicationsReduceBalance()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');

        $invoice1 = $this->createInvoice($user, 2000);
        CreditNote::applyToInvoice($user, $invoice1, 2000);

        $this->assertEquals(3000, CreditNote::getBalanceForUser($user, 'USD'));

        $invoice2 = $this->createInvoice($user, 1500);
        CreditNote::applyToInvoice($user, $invoice2, 1500);

        $this->assertEquals(1500, CreditNote::getBalanceForUser($user, 'USD'));
    }

    //
    // Remove from Invoice Tests
    //

    /**
     * testRemoveFromInvoiceRestoresBalance
     */
    public function testRemoveFromInvoiceRestoresBalance()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 5000);

        CreditNote::applyToInvoice($user, $invoice, 3000);

        $this->assertEquals(2000, CreditNote::getBalanceForUser($user, 'USD'));

        $invoice->refresh();
        CreditNote::removeFromInvoice($user, $invoice);

        $invoice->refresh();
        $this->assertEquals(0, $invoice->credit_applied);
        $this->assertEquals(5000, $invoice->amount_due);
        $this->assertEquals(5000, CreditNote::getBalanceForUser($user, 'USD'));
    }

    /**
     * testRemoveFromInvoiceReturnsNullWhenNoCredit
     */
    public function testRemoveFromInvoiceReturnsNullWhenNoCredit()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, 5000);

        $result = CreditNote::removeFromInvoice($user, $invoice);

        $this->assertNull($result);
    }

    /**
     * testRemoveFromInvoiceCreatesAdjustmentNote
     */
    public function testRemoveFromInvoiceCreatesAdjustmentNote()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 5000);

        CreditNote::applyToInvoice($user, $invoice, 3000);

        $invoice->refresh();
        $note = CreditNote::removeFromInvoice($user, $invoice);

        $this->assertInstanceOf(CreditNote::class, $note);
        $this->assertEquals('adjustment', $note->type);
        $this->assertEquals(3000, $note->amount);
        $this->assertEquals($invoice->id, $note->invoice_id);
    }

    //
    // History Tests
    //

    /**
     * testGetHistoryForUser
     */
    public function testGetHistoryForUser()
    {
        $user = $this->createUser();

        $note1 = CreditNote::issueAdjustment($user, 3000, 'USD', 'First');
        $note1->issued_at = now()->subDays(2);
        $note1->save();

        $note2 = CreditNote::issueAdjustment($user, 2000, 'USD', 'Second');
        $note2->issued_at = now()->subDays(1);
        $note2->save();

        $history = CreditNote::getHistoryForUser($user);

        $this->assertCount(2, $history);
        // Most recent first
        $this->assertEquals($note2->id, $history->first()->id);
        $this->assertEquals($note1->id, $history->last()->id);
    }

    /**
     * testGetHistoryForUserIncludesDebitNotes
     */
    public function testGetHistoryForUserIncludesDebitNotes()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 3000);
        CreditNote::applyToInvoice($user, $invoice, 3000);

        $history = CreditNote::getHistoryForUser($user);
        $types = $history->pluck('type')->sort()->values()->all();

        // Should include both the adjustment and the debit note
        $this->assertCount(2, $history);
        $this->assertEquals(['adjustment', 'debit'], $types);
    }

    /**
     * testGetHistoryForUserIsolatedBetweenUsers
     */
    public function testGetHistoryForUserIsolatedBetweenUsers()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        CreditNote::issueAdjustment($user1, 5000, 'USD', 'User 1 credit');
        CreditNote::issueAdjustment($user2, 3000, 'USD', 'User 2 credit');

        $this->assertCount(1, CreditNote::getHistoryForUser($user1));
        $this->assertCount(1, CreditNote::getHistoryForUser($user2));
    }

    //
    // Invoice Amount Due Tests
    //

    /**
     * testInvoiceAmountDueWithNoCredit
     */
    public function testInvoiceAmountDueWithNoCredit()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, 10000);

        $this->assertEquals(10000, $invoice->amount_due);
    }

    /**
     * testInvoiceAmountDueWithPartialCredit
     */
    public function testInvoiceAmountDueWithPartialCredit()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 10000);

        CreditNote::applyToInvoice($user, $invoice, 3000);

        $invoice->refresh();
        $this->assertEquals(7000, $invoice->amount_due);
    }

    /**
     * testInvoiceAmountDueNeverNegative
     */
    public function testInvoiceAmountDueNeverNegative()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, 5000);

        // Manually set credit_applied higher than total
        $invoice->credit_applied = 8000;
        $invoice->save();
        $invoice->refresh();

        $this->assertEquals(0, $invoice->amount_due);
    }
}
