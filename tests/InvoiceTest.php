<?php namespace Responsiv\Pay\Tests;

use PluginTestCase;
use October\Rain\Database\Model;
use Responsiv\Pay\Models\Invoice;
use Responsiv\Pay\Models\InvoiceItem;
use Responsiv\Pay\Models\InvoiceStatus;
use Responsiv\Pay\Models\InvoiceStatusLog;

/**
 * InvoiceTest validates invoice model attributes, computed properties,
 * status transitions, and lifecycle methods.
 */
class InvoiceTest extends PluginTestCase
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
            'email' => 'test-invoice-' . uniqid() . '@example.com',
            'password' => 'testing123',
            'password_confirmation' => 'testing123',
        ]);
        Model::reguard();

        return $user;
    }

    /**
     * createInvoice creates a test invoice with given attributes
     */
    protected function createInvoice($user, array $attrs = []): Invoice
    {
        Model::unguard();
        $invoice = new Invoice;
        $invoice->user = $user;
        $invoice->first_name = $user->first_name;
        $invoice->last_name = $user->last_name;
        $invoice->email = $user->email;
        $invoice->currency_code = 'USD';

        foreach ($attrs as $key => $value) {
            $invoice->$key = $value;
        }

        $invoice->save();
        Model::reguard();

        return $invoice;
    }

    //
    // Computed Attribute Tests
    //

    /**
     * testAmountDueEqualsTotal
     */
    public function testAmountDueEqualsTotal()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['total' => 10000, 'subtotal' => 10000]);

        $this->assertEquals(10000, $invoice->amount_due);
    }

    /**
     * testAmountDueSubtractsCredit
     */
    public function testAmountDueSubtractsCredit()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, [
            'total' => 10000,
            'subtotal' => 10000,
            'credit_applied' => 3000,
        ]);

        $this->assertEquals(7000, $invoice->amount_due);
    }

    /**
     * testAmountDueNeverNegative
     */
    public function testAmountDueNeverNegative()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, [
            'total' => 5000,
            'subtotal' => 5000,
            'credit_applied' => 8000,
        ]);

        $this->assertEquals(0, $invoice->amount_due);
    }

    /**
     * testAmountDueWithNullTotal
     */
    public function testAmountDueWithNullTotal()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0]);

        $this->assertEquals(0, $invoice->amount_due);
    }

    /**
     * testOriginalSubtotalIncludesDiscount
     */
    public function testOriginalSubtotalIncludesDiscount()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, [
            'subtotal' => 8000,
            'discount' => 2000,
            'total' => 8000,
        ]);

        $this->assertEquals(10000, $invoice->original_subtotal);
    }

    /**
     * testFinalSubtotalExcludesTax
     */
    public function testFinalSubtotalExcludesTax()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, [
            'subtotal' => 10000,
            'tax' => 1000,
            'total' => 11000,
        ]);

        // prices_include_tax is false, so final_subtotal = subtotal + tax
        $this->assertEquals(11000, $invoice->final_subtotal);
    }

    /**
     * testFinalSubtotalTaxInclusive
     */
    public function testFinalSubtotalTaxInclusive()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, [
            'subtotal' => 11000,
            'tax' => 1000,
            'total' => 11000,
            'prices_include_tax' => true,
        ]);

        // prices_include_tax is true, so final_subtotal = subtotal (tax already included)
        $this->assertEquals(11000, $invoice->final_subtotal);
    }

    /**
     * testFinalDiscountIncludesTax
     */
    public function testFinalDiscountIncludesTax()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, [
            'discount' => 2000,
            'discount_tax' => 200,
            'subtotal' => 8000,
            'total' => 8000,
        ]);

        $this->assertEquals(2200, $invoice->final_discount);
    }

    //
    // Street Address Tests
    //

    /**
     * testStreetAddressGetter
     */
    public function testStreetAddressGetter()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, [
            'address_line1' => '123 Main St',
            'address_line2' => 'Suite 100',
            'subtotal' => 0,
            'total' => 0,
        ]);

        $this->assertEquals("123 Main St\nSuite 100", $invoice->street_address);
    }

    /**
     * testStreetAddressSetter
     */
    public function testStreetAddressSetter()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);

        $invoice->street_address = "456 Oak Ave\nApt 2B";
        $invoice->save();
        $invoice->refresh();

        $this->assertEquals('456 Oak Ave', $invoice->address_line1);
        $this->assertEquals('Apt 2B', $invoice->address_line2);
    }

    /**
     * testStreetAddressSetterSingleLine
     */
    public function testStreetAddressSetterSingleLine()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);

        $invoice->street_address = "789 Elm Blvd";
        $invoice->save();
        $invoice->refresh();

        $this->assertEquals('789 Elm Blvd', $invoice->address_line1);
        $this->assertEquals('', $invoice->address_line2);
    }

    //
    // Hash and Lookup Tests
    //

    /**
     * testInvoiceGeneratesHash
     */
    public function testInvoiceGeneratesHash()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);

        $this->assertNotNull($invoice->hash);
        $this->assertNotEmpty($invoice->hash);
    }

    /**
     * testFindByInvoiceHash
     */
    public function testFindByInvoiceHash()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);

        $found = Invoice::findByInvoiceHash($invoice->hash);

        $this->assertNotNull($found);
        $this->assertEquals($invoice->id, $found->id);
    }

    /**
     * testFindByInvoiceHashReturnsNullForBadHash
     */
    public function testFindByInvoiceHashReturnsNullForBadHash()
    {
        $this->assertNull(Invoice::findByInvoiceHash('nonexistenthash'));
    }

    /**
     * testHashesAreUnique
     */
    public function testHashesAreUnique()
    {
        $user = $this->createUser();
        $invoice1 = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);
        $invoice2 = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);

        $this->assertNotEquals($invoice1->hash, $invoice2->hash);
    }

    //
    // Invoiced At Tests
    //

    /**
     * testInvoicedAtReturnsSentAt
     */
    public function testInvoicedAtReturnsSentAt()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, [
            'subtotal' => 0,
            'total' => 0,
            'sent_at' => now()->subDays(5),
        ]);

        $this->assertEquals(
            $invoice->sent_at->toDateString(),
            $invoice->invoiced_at->toDateString()
        );
    }

    /**
     * testInvoicedAtFallsBackToCreatedAt
     */
    public function testInvoicedAtFallsBackToCreatedAt()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);

        // sent_at is null, should fall back to created_at
        $this->assertNull($invoice->sent_at);
        $this->assertEquals(
            $invoice->created_at->toDateString(),
            $invoice->invoiced_at->toDateString()
        );
    }

    //
    // Due Date Tests
    //

    /**
     * testIsPastDueDateWithNoDueDate
     */
    public function testIsPastDueDateWithNoDueDate()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);

        // No due_at → always considered past due
        $this->assertTrue($invoice->is_past_due_date);
    }

    /**
     * testIsPastDueDateWithFutureDate
     */
    public function testIsPastDueDateWithFutureDate()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, [
            'subtotal' => 0,
            'total' => 0,
            'due_at' => now()->addDays(30),
        ]);

        $this->assertFalse($invoice->is_past_due_date);
    }

    /**
     * testIsPastDueDateWithPastDate
     */
    public function testIsPastDueDateWithPastDate()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, [
            'subtotal' => 0,
            'total' => 0,
            'due_at' => now()->subDays(1),
        ]);

        $this->assertTrue($invoice->is_past_due_date);
    }

    //
    // Status Tests
    //

    /**
     * testNewInvoiceGetsDraftStatus
     */
    public function testNewInvoiceGetsDraftStatus()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);
        $invoice->refresh();

        $this->assertEquals('draft', $invoice->status_code);
    }

    /**
     * testStatusCodeAttribute
     */
    public function testStatusCodeAttribute()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);
        $invoice->refresh();

        $this->assertEquals('draft', $invoice->status_code);

        // Transition to approved
        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_APPROVED);
        $invoice->refresh();

        $this->assertEquals('approved', $invoice->status_code);
    }

    /**
     * testStatusTransitionDraftToApproved
     */
    public function testStatusTransitionDraftToApproved()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);
        $invoice->refresh();

        $result = $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_APPROVED);
        $this->assertTrue($result);

        $invoice->refresh();
        $this->assertEquals('approved', $invoice->status_code);
    }

    /**
     * testStatusTransitionApprovedToPaid
     */
    public function testStatusTransitionApprovedToPaid()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);
        $invoice->refresh();

        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_APPROVED);
        $invoice->refresh();

        $result = $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_PAID);
        $this->assertTrue($result);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status_code);
    }

    /**
     * testStatusTransitionPaidToRefunded
     */
    public function testStatusTransitionPaidToRefunded()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);
        $invoice->refresh();

        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_APPROVED);
        $invoice->refresh();
        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_PAID);
        $invoice->refresh();

        $result = $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_REFUNDED);
        $this->assertTrue($result);

        $invoice->refresh();
        $this->assertEquals('refunded', $invoice->status_code);
    }

    /**
     * testRefundedIsTerminal — refunded status cannot transition further
     */
    public function testRefundedIsTerminal()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);
        $invoice->refresh();

        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_APPROVED);
        $invoice->refresh();
        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_PAID);
        $invoice->refresh();
        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_REFUNDED);
        $invoice->refresh();

        $result = $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_VOID);
        $this->assertFalse($result);

        $invoice->refresh();
        $this->assertEquals('refunded', $invoice->status_code);
    }

    /**
     * testVoidIsTerminal — void status cannot transition further
     */
    public function testVoidIsTerminal()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);
        $invoice->refresh();

        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_VOID);
        $invoice->refresh();

        $result = $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_PAID);
        $this->assertFalse($result);

        $invoice->refresh();
        $this->assertEquals('void', $invoice->status_code);
    }

    /**
     * testCannotSkipToPaidFromDraft — draft can go to paid directly
     * (this is a valid shortcut in the status map)
     */
    public function testDraftCanSkipToPaid()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);
        $invoice->refresh();

        $result = $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_PAID);
        $this->assertTrue($result);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status_code);
    }

    /**
     * testDuplicateStatusChangeIsIgnored
     */
    public function testDuplicateStatusChangeIsIgnored()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);
        $invoice->refresh();

        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_APPROVED);
        $invoice->refresh();

        // Same status again — should return false (no-op)
        $result = $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_APPROVED);
        $this->assertFalse($result);
    }

    //
    // Payment Processing Tests
    //

    /**
     * testIsPaidAttribute
     */
    public function testIsPaidAttribute()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);

        $this->assertEmpty($invoice->is_paid);
    }

    /**
     * testMarkAsPaymentProcessed
     */
    public function testMarkAsPaymentProcessed()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 5000, 'total' => 5000]);

        $result = $invoice->markAsPaymentProcessed('Test payment');
        $this->assertTrue($result);

        $invoice->refresh();
        $this->assertNotNull($invoice->processed_at);
        $this->assertNotEmpty($invoice->is_paid);
        $this->assertEquals('paid', $invoice->status_code);
    }

    /**
     * testMarkAsPaymentProcessedIdempotent
     */
    public function testMarkAsPaymentProcessedIdempotent()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 5000, 'total' => 5000]);

        $invoice->markAsPaymentProcessed();
        $invoice->refresh();
        $processedAt = $invoice->processed_at;

        // Second call should return false (already processed)
        $result = $invoice->markAsPaymentProcessed();
        $this->assertFalse($result);

        // processed_at should not change
        $invoice->refresh();
        $this->assertEquals($processedAt->timestamp, $invoice->processed_at->timestamp);
    }

    /**
     * testIsPaymentSubmittedWhenPaid
     */
    public function testIsPaymentSubmittedWhenPaid()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 5000, 'total' => 5000]);

        $invoice->markAsPaymentProcessed();
        $invoice->refresh();

        $this->assertTrue($invoice->is_payment_submitted);
    }

    /**
     * testIsPaymentSubmittedWhenApproved
     */
    public function testIsPaymentSubmittedWhenApproved()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 5000, 'total' => 5000]);
        $invoice->refresh();

        $invoice->updateInvoiceStatus(InvoiceStatus::STATUS_APPROVED);
        $invoice->refresh();

        $this->assertTrue($invoice->is_payment_submitted);
    }

    /**
     * testIsPaymentSubmittedWhenDraft
     */
    public function testIsPaymentSubmittedWhenDraft()
    {
        $user = $this->createUser();
        $invoice = $this->createInvoice($user, ['subtotal' => 5000, 'total' => 5000]);
        $invoice->refresh();

        $this->assertFalse($invoice->is_payment_submitted);
    }

    //
    // Throwaway Tests
    //

    /**
     * testMakeThrowaway
     */
    public function testMakeThrowaway()
    {
        $invoice = Invoice::makeThrowaway();

        $this->assertTrue($invoice->is_throwaway);
    }

    /**
     * testMakeThrowawayWithUser
     */
    public function testMakeThrowawayWithUser()
    {
        $user = $this->createUser();
        $invoice = Invoice::makeThrowaway($user);

        $this->assertTrue($invoice->is_throwaway);
        $this->assertEquals($user->first_name, $invoice->first_name);
        $this->assertEquals($user->email, $invoice->email);
    }

    /**
     * testConvertToPermanent
     */
    public function testConvertToPermanent()
    {
        $user = $this->createUser();
        $invoice = Invoice::makeThrowaway($user);
        $invoice->total = 0;
        $invoice->subtotal = 0;
        $invoice->currency_code = 'USD';
        $invoice->save();

        $this->assertTrue($invoice->is_throwaway);

        $invoice->convertToPermanent();
        $invoice->refresh();

        $this->assertFalse((bool) $invoice->is_throwaway);
    }

    /**
     * testPaymentProcessedConvertsThrowaway
     */
    public function testPaymentProcessedConvertsThrowaway()
    {
        $user = $this->createUser();
        $invoice = Invoice::makeThrowaway($user);
        $invoice->total = 5000;
        $invoice->subtotal = 5000;
        $invoice->currency_code = 'USD';
        $invoice->save();

        $this->assertTrue($invoice->is_throwaway);

        $invoice->markAsPaymentProcessed();
        $invoice->refresh();

        $this->assertFalse((bool) $invoice->is_throwaway);
    }

    //
    // Scope Tests
    //

    /**
     * testScopeApplyUser
     */
    public function testScopeApplyUser()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        $this->createInvoice($user1, ['subtotal' => 0, 'total' => 0]);
        $this->createInvoice($user1, ['subtotal' => 0, 'total' => 0]);
        $this->createInvoice($user2, ['subtotal' => 0, 'total' => 0]);

        $this->assertEquals(2, Invoice::applyUser($user1)->count());
        $this->assertEquals(1, Invoice::applyUser($user2)->count());
    }

    /**
     * testScopeApplyUnpaid
     */
    public function testScopeApplyUnpaid()
    {
        $user = $this->createUser();
        $paid = $this->createInvoice($user, ['subtotal' => 5000, 'total' => 5000]);
        $this->createInvoice($user, ['subtotal' => 3000, 'total' => 3000]);

        $paid->markAsPaymentProcessed();

        $unpaid = Invoice::applyUser($user)->applyUnpaid()->get();
        $this->assertCount(1, $unpaid);
        $this->assertEquals(3000, $unpaid->first()->total);
    }

    /**
     * testScopeApplyThrowaway
     */
    public function testScopeApplyThrowaway()
    {
        $user = $this->createUser();
        $this->createInvoice($user, ['subtotal' => 0, 'total' => 0]);

        $throwaway = Invoice::makeThrowaway($user);
        $throwaway->total = 0;
        $throwaway->subtotal = 0;
        $throwaway->currency_code = 'USD';
        $throwaway->save();

        $this->assertCount(1, Invoice::applyUser($user)->applyThrowaway()->get());
        $this->assertCount(1, Invoice::applyUser($user)->applyNotThrowaway()->get());
    }

    //
    // MakeForUser Tests
    //

    /**
     * testMakeForUser
     */
    public function testMakeForUser()
    {
        $user = $this->createUser();
        $invoice = Invoice::makeForUser($user);

        $this->assertEquals($user->first_name, $invoice->first_name);
        $this->assertEquals($user->last_name, $invoice->last_name);
        $this->assertEquals($user->email, $invoice->email);
    }
}
