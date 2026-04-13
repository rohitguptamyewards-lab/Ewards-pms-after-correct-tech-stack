<?php
namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait Auditable
{
    public static function bootAuditable(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event) {
                try {
                    DB::table('audit_logs')->insert([
                        'user_id'     => auth()->id(),
                        'action'      => $event,
                        'entity_type' => $model->getTable(),
                        'entity_id'   => $model->getKey(),
                        'changes'     => json_encode($model->getChanges()),
                        'ip_address'  => request()?->ip(),
                        'created_at'  => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Audit log failed: ' . $e->getMessage());
                }
            });
        }
    }
}
