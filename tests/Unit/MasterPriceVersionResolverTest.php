<?php

namespace Tests\Unit;

use App\Services\MasterPriceVersionResolver;
use PHPUnit\Framework\TestCase;

class MasterPriceVersionResolverTest extends TestCase
{
    public function test_normalize_price_as_of_rejects_pre_2001(): void
    {
        $resolver = new MasterPriceVersionResolver();

        $this->assertNull($resolver->normalizePriceAsOfDate('2000-12-31'));
        $this->assertNull($resolver->normalizePriceAsOfDate('0000-00-00'));
        $this->assertSame('2001-01-01', $resolver->normalizePriceAsOfDate('2001-01-01'));
        $this->assertSame('2024-04-01', $resolver->normalizePriceAsOfDate('2024-04-01T15:00:00.000000Z'));
    }

    public function test_loaner_price_as_of_uses_own_order_date_only(): void
    {
        $resolver = new MasterPriceVersionResolver();

        $this->assertSame('2024-04-01', $resolver->resolveLoanerPriceAsOf('2024-04-01', '2010-01-01'));
        $this->assertNull($resolver->resolveLoanerPriceAsOf(null, '2010-01-01'));
        $this->assertNull($resolver->resolveLoanerPriceAsOf('2000-01-01', '2010-01-01'));
    }
}
