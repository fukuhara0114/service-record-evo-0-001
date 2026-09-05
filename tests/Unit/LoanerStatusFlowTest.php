<?php

namespace Tests\Unit;

use App\Support\LoanerStatusFlow;
use PHPUnit\Framework\TestCase;

class LoanerStatusFlowTest extends TestCase
{
    public function test_master_current_status_mirrors_case_status_below_complete(): void
    {
        $this->assertSame(0, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(0));
        $this->assertSame(20, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(20));
        $this->assertSame(100, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(100));
        $this->assertSame(150, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(150));
        $this->assertSame(200, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(200));
        $this->assertSame(300, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(300));
        $this->assertSame(388, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(388));
        $this->assertSame(393, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(393));
        $this->assertSame(396, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(396));
        $this->assertSame(399, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(399));
        $this->assertSame(399, LoanerStatusFlow::masterCurrentStatusFromCaseStatus('399'));
    }

    public function test_master_current_status_is_stock_when_case_is_complete_or_beyond(): void
    {
        $this->assertSame(0, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(400));
        $this->assertSame(0, LoanerStatusFlow::masterCurrentStatusFromCaseStatus('400'));
        $this->assertSame(0, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(401));
        $this->assertSame(0, LoanerStatusFlow::masterCurrentStatusFromCaseStatus(650));
    }

    public function test_complete_or_beyond_threshold(): void
    {
        $this->assertFalse(LoanerStatusFlow::isCompleteOrBeyond(399));
        $this->assertTrue(LoanerStatusFlow::isCompleteOrBeyond(400));
        $this->assertTrue(LoanerStatusFlow::isCompleteOrBeyond(650));
    }

    public function test_invoice_complete_marks_master_lending_out_for_service_loaner_rma(): void
    {
        $this->assertTrue(LoanerStatusFlow::shouldMarkMasterLendingOutOnInvoiceComplete(
            385,
            3,
            'service',
            'loaner',
        ));
        $this->assertTrue(LoanerStatusFlow::shouldMarkMasterLendingOutOnInvoiceComplete(
            '385',
            '3',
            null,
            'Loaner',
        ));
        $this->assertTrue(LoanerStatusFlow::shouldMarkMasterLendingOutOnInvoiceComplete(
            385,
            3,
            '',
            'LOANER',
        ));
    }

    public function test_invoice_complete_does_not_mark_master_lending_out_otherwise(): void
    {
        $this->assertFalse(LoanerStatusFlow::shouldMarkMasterLendingOutOnInvoiceComplete(
            385,
            3,
            'loaner',
            'loaner',
        ));
        $this->assertFalse(LoanerStatusFlow::shouldMarkMasterLendingOutOnInvoiceComplete(
            385,
            400,
            'service',
            'loaner',
        ));
        $this->assertFalse(LoanerStatusFlow::shouldMarkMasterLendingOutOnInvoiceComplete(
            385,
            350,
            'service',
            'loaner',
        ));
        $this->assertFalse(LoanerStatusFlow::shouldMarkMasterLendingOutOnInvoiceComplete(
            385,
            3,
            'service',
            '12345',
        ));
        $this->assertFalse(LoanerStatusFlow::shouldMarkMasterLendingOutOnInvoiceComplete(
            300,
            3,
            'service',
            'loaner',
        ));
    }

    public function test_associated_id_is_bound_on_save_for_loaner_and_legacy_service_loaner_cases(): void
    {
        $this->assertTrue(LoanerStatusFlow::shouldBindMasterAssociatedIdOnSave('loaner', null));
        $this->assertTrue(LoanerStatusFlow::shouldBindMasterAssociatedIdOnSave('loaner', '123'));
        $this->assertTrue(LoanerStatusFlow::shouldBindMasterAssociatedIdOnSave('service', 'loaner'));
        $this->assertTrue(LoanerStatusFlow::shouldBindMasterAssociatedIdOnSave(null, 'Loaner'));
        $this->assertTrue(LoanerStatusFlow::shouldBindMasterAssociatedIdOnSave('', 'LOANER'));
        $this->assertFalse(LoanerStatusFlow::shouldBindMasterAssociatedIdOnSave('service', '12345'));
        $this->assertFalse(LoanerStatusFlow::shouldBindMasterAssociatedIdOnSave('waiting_list', 'loaner'));
        $this->assertFalse(LoanerStatusFlow::shouldBindMasterAssociatedIdOnSave(null, null));
    }

    public function test_associated_case_kind_distinguishes_loaner_and_legacy(): void
    {
        $this->assertSame('loaner', LoanerStatusFlow::associatedCaseKind('loaner', null));
        $this->assertSame('loaner', LoanerStatusFlow::associatedCaseKind('loaner', '123'));
        $this->assertSame('legacy', LoanerStatusFlow::associatedCaseKind('service', 'loaner'));
        $this->assertSame('legacy', LoanerStatusFlow::associatedCaseKind(null, 'Loaner'));
        $this->assertSame('legacy', LoanerStatusFlow::associatedCaseKind('', 'LOANER'));
        $this->assertNull(LoanerStatusFlow::associatedCaseKind('service', '12345'));
        $this->assertNull(LoanerStatusFlow::associatedCaseKind('waiting_list', 'loaner'));
        $this->assertNull(LoanerStatusFlow::associatedCaseKind(null, null));
    }
}
