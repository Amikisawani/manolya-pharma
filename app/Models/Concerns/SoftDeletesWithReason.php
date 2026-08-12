<?php

namespace App\Models\Concerns;

/**
 * Soft-delete guidance for MANOLYA models.
 *
 * Use Laravel's SoftDeletes trait on models that support soft deletion.
 * When the table includes optional audit columns (`deleted_by`, `delete_reason`),
 * set them explicitly before calling delete():
 *
 *   $model->deleted_by = auth()->id();
 *   $model->delete_reason = $reason;
 *   $model->delete();
 *
 * This trait is documentation-only and does not replace SoftDeletes.
 */
trait SoftDeletesWithReason
{
    // Intentionally empty — prefer SoftDeletes + optional deleted_by / delete_reason columns.
}
