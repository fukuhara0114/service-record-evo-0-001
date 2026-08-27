<?php

namespace Tests\Unit;

use App\Models\LoanerMaster;
use PHPUnit\Framework\TestCase;

class LoanerProductSelectionTest extends TestCase
{
    public function test_in_stock_status_is_numeric_zero_only(): void
    {
        $this->assertTrue(LoanerMaster::isInStockStatus(0));
        $this->assertTrue(LoanerMaster::isInStockStatus('0'));
        $this->assertFalse(LoanerMaster::isInStockStatus(100));
        $this->assertFalse(LoanerMaster::isInStockStatus(null));
        $this->assertFalse(LoanerMaster::isInStockStatus(''));
    }

    public function test_selection_identity_is_loaner_id_not_shared_names(): void
    {
        $a = $this->unit(54, 'Ci7600', 'Ci7600', 0);
        $b = $this->unit(116, '【簿外】Ci7600', 'Ci7600', 0);

        $this->assertTrue(LoanerMaster::isInStockStatus($a->currentStatus));
        $this->assertNotSame((string) $a->loanerID, (string) $b->loanerID);
        $this->assertSame('Ci7600', $a->productName);
        $this->assertSame('Ci7600', $b->productName);
    }

    private function unit(int $loanerId, string $item, string $productName, int $status): LoanerMaster
    {
        $row = new LoanerMaster();
        $row->loanerID = $loanerId;
        $row->item = $item;
        $row->productName = $productName;
        $row->currentStatus = $status;

        return $row;
    }
}
