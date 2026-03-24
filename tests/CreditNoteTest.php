<?php namespace Responsiv\Pay\Tests;

use PluginTestCase;
use October\Rain\Database\Model;
use ApplicationException;
use Responsiv\Pay\Models\CreditNote;
use Responsiv\Pay\Models\CreditApplication;
use Responsiv\Pay\Models\Invoice;

/**
 * CreditNoteTest validates the credit note ledger system including
 * issuance, balance calculation, application to invoices, voiding,
 * and edge cases like partial credit and FIFO distribution.
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

    //
    // Balance Tests
    //

    /**
     * testAvailableBalanceFullyAvailable
     */
    public function testAvailableBalanceFullyAvailable()
    {
        $user = $this->createUser();
        $note = CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');

        $this->assertEquals(5000, $note->available_balance);
    }

    /**
     * testAvailableBalanceAfterPartialApplication
     */
    public function testAvailableBalanceAfterPartialApplication()
    {
        $user = $this->createUser();
        $note = CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 3000);

        CreditNote::applyToInvoice($user, $invoice, 3000);

        $note->refresh();
        $this->assertEquals(2000, $note->available_balance);
    }

    /**
     * testAvailableBalanceDebitNoteIsZero
     */
    public function testAvailableBalanceDebitNoteIsZero()
    {
        $user = $this->createUser();
        $note = CreditNote::issueDebit($user, 5000, 'USD', 'Test');

        $this->assertEquals(0, $note->available_balance);
    }

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

        $applications = CreditNote::applyToInvoice($user, $invoice, 5000);

        $this->assertCount(1, $applications);
        $this->assertEquals(5000, $applications->first()->amount);

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

        $applications = CreditNote::applyToInvoice($user, $invoice, 3000);

        $this->assertCount(1, $applications);
        $this->assertEquals(3000, $applications->first()->amount);

        $invoice->refresh();
        $this->assertEquals(3000, $invoice->credit_applied);
        $this->assertEquals(7000, $invoice->amount_due);
    }

    /**
     * testApplyToInvoiceCapsAtAvailableBalance
     */
    public function testApplyToInvoiceCapsAtAvailableBalance()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 3000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 10000);

        $applications = CreditNote::applyToInvoice($user, $invoice, 10000);

        $this->assertCount(1, $applications);
        $this->assertEquals(3000, $applications->first()->amount);

        $invoice->refresh();
        $this->assertEquals(3000, $invoice->credit_applied);
    }

    /**
     * testApplyToInvoiceCapsAtInvoiceTotal
     */
    public function testApplyToInvoiceCapsAtInvoiceTotal()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 10000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 3000);

        $applications = CreditNote::applyToInvoice($user, $invoice, 10000);

        $this->assertCount(1, $applications);
        $this->assertEquals(3000, $applications->first()->amount);

        $invoice->refresh();
        $this->assertEquals(3000, $invoice->credit_applied);
    }

    /**
     * testApplyToInvoiceFifoDistribution distributes across multiple
     * credit notes in FIFO order (oldest first)
     */
    public function testApplyToInvoiceFifoDistribution()
    {
        $user = $this->createUser();

        $note1 = CreditNote::issueAdjustment($user, 2000, 'USD', 'Oldest');
        $note1->issued_at = now()->subDays(3);
        $note1->save();

        $note2 = CreditNote::issueAdjustment($user, 3000, 'USD', 'Middle');
        $note2->issued_at = now()->subDays(2);
        $note2->save();

        $note3 = CreditNote::issueAdjustment($user, 5000, 'USD', 'Newest');
        $note3->issued_at = now()->subDays(1);
        $note3->save();

        $invoice = $this->createInvoice($user, 4000);

        $applications = CreditNote::applyToInvoice($user, $invoice, 4000);

        // Should take all 2000 from note1 and 2000 from note2
        $this->assertCount(2, $applications);
        $this->assertEquals($note1->id, $applications[0]->credit_note_id);
        $this->assertEquals(2000, $applications[0]->amount);
        $this->assertEquals($note2->id, $applications[1]->credit_note_id);
        $this->assertEquals(2000, $applications[1]->amount);

        // note3 should be untouched
        $note3->refresh();
        $this->assertEquals(5000, $note3->available_balance);
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
     * testApplyToInvoiceSkipsFullySpentNotes
     */
    public function testApplyToInvoiceSkipsFullySpentNotes()
    {
        $user = $this->createUser();

        $note1 = CreditNote::issueAdjustment($user, 2000, 'USD', 'First');
        $note1->issued_at = now()->subDays(2);
        $note1->save();

        $note2 = CreditNote::issueAdjustment($user, 3000, 'USD', 'Second');
        $note2->issued_at = now()->subDays(1);
        $note2->save();

        // Spend all of note1
        $invoice1 = $this->createInvoice($user, 2000);
        CreditNote::applyToInvoice($user, $invoice1, 2000);

        // Now apply more — should come from note2 only
        $invoice2 = $this->createInvoice($user, 1500);
        $applications = CreditNote::applyToInvoice($user, $invoice2, 1500);

        $this->assertCount(1, $applications);
        $this->assertEquals($note2->id, $applications->first()->credit_note_id);
        $this->assertEquals(1500, $applications->first()->amount);
    }

    //
    // Void Application Tests
    //

    /**
     * testVoidApplicationRestoresInvoiceCache
     */
    public function testVoidApplicationRestoresInvoiceCache()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 5000);

        $applications = CreditNote::applyToInvoice($user, $invoice, 3000);

        $invoice->refresh();
        $this->assertEquals(3000, $invoice->credit_applied);
        $this->assertEquals(2000, $invoice->amount_due);

        // Void the application
        $applications->first()->void();

        $invoice->refresh();
        $this->assertEquals(0, $invoice->credit_applied);
        $this->assertEquals(5000, $invoice->amount_due);
    }

    /**
     * testVoidApplicationIsIdempotent
     */
    public function testVoidApplicationIsIdempotent()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 5000);

        $applications = CreditNote::applyToInvoice($user, $invoice, 3000);
        $app = $applications->first();

        $app->void();
        $app->void();

        $invoice->refresh();
        $this->assertEquals(0, $invoice->credit_applied);
    }

    /**
     * testVoidApplicationRestoresNoteBalance
     */
    public function testVoidApplicationRestoresNoteBalance()
    {
        $user = $this->createUser();
        $note = CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 5000);

        $applications = CreditNote::applyToInvoice($user, $invoice, 3000);

        $note->refresh();
        $this->assertEquals(2000, $note->available_balance);

        $applications->first()->void();

        $note->refresh();
        $this->assertEquals(5000, $note->available_balance);
        $this->assertEquals(5000, CreditNote::getBalanceForUser($user, 'USD'));
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
     * testGetHistoryForUserIncludesApplications
     */
    public function testGetHistoryForUserIncludesApplications()
    {
        $user = $this->createUser();
        CreditNote::issueAdjustment($user, 5000, 'USD', 'Test');
        $invoice = $this->createInvoice($user, 3000);
        CreditNote::applyToInvoice($user, $invoice, 3000);

        $history = CreditNote::getHistoryForUser($user);

        $this->assertCount(1, $history);
        $this->assertTrue($history->first()->relationLoaded('applications'));
        $this->assertCount(1, $history->first()->applications);
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
