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
}
