<?php

namespace Tests\Unit;

use App\Casts\TrimmedDecimal;
use App\Models\Batch;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrimmedDecimalCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_strips_trailing_zeros(): void
    {
        $this->assertSame('12', TrimmedDecimal::format('12.000', 3));
        $this->assertSame('12.5', TrimmedDecimal::format('12.500', 3));
        $this->assertSame('0', TrimmedDecimal::format('0.000', 3));
        $this->assertSame('6600', TrimmedDecimal::format('6600.00', 2));
    }

    public function test_product_stock_fields_serialize_without_trailing_zeros(): void
    {
        $this->seed();

        $product = Product::query()->firstOrFail();
        $product->update([
            'min_stock' => '20',
            'critical_stock' => '5.5',
        ]);
        $product->refresh();

        $this->assertSame('20', $product->min_stock);
        $this->assertSame('5.5', $product->critical_stock);
        $this->assertSame('20', $product->toArray()['min_stock']);
    }

    public function test_batch_quantity_serializes_cleanly(): void
    {
        $this->seed();

        $batch = Batch::query()->where('quantity_on_hand', '>', 0)->firstOrFail();
        $batch->update(['quantity_on_hand' => '12']);
        $batch->refresh();

        $this->assertSame('12', $batch->quantity_on_hand);
        $this->assertSame('12', $batch->toArray()['quantity_on_hand']);
    }
}
