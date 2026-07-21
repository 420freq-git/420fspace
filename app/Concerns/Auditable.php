<?php

namespace App\Concerns;

use App\Models\AuditLog;

/**
 * Catat perubahan model (created/updated/deleted) ke audit_logs.
 * Model bisa override auditLabel() dan property $auditExclude.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => $m->writeAudit('created'));
        static::updated(fn ($m) => $m->writeAudit('updated'));
        static::deleted(fn ($m) => $m->writeAudit('deleted'));
    }

    public function writeAudit(string $event): void
    {
        $changes = null;

        if ($event === 'updated') {
            $excluded = array_merge(
                ['created_at', 'updated_at'],
                property_exists($this, 'auditExclude') ? $this->auditExclude : [],
            );
            $dirty = collect($this->getChanges())->except($excluded);
            if ($dirty->isEmpty()) {
                return;
            }
            $changes = [];
            foreach ($dirty as $field => $newRaw) {
                $changes[$field] = [$this->getRawOriginal($field), $newRaw];
            }
        }

        $user = auth()->user();

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'sistem',
            'user_role' => $user?->role?->value,
            'event' => $event,
            'auditable_type' => class_basename($this),
            'auditable_id' => $this->getKey(),
            'label' => $this->auditLabel(),
            'changes' => $changes,
        ]);
    }

    public function auditLabel(): string
    {
        return class_basename($this).' #'.$this->getKey();
    }
}
