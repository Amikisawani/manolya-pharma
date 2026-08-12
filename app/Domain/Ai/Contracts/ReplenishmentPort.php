<?php

namespace App\Domain\Ai\Contracts;

interface ReplenishmentPort
{
    /**
     * @return list<array{product_id: string, warehouse_id: string, suggested_qty: string, reason: string}>
     */
    public function suggest(string $warehouseId): array;
}
