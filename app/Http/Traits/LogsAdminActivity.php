<?php

namespace App\Http\Traits;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;

trait LogsAdminActivity
{
    /**
     * Log create action
     */
    protected function auditCreate(string $module, Model $model, ?string $description = null): AdminAuditLog
    {
        return AdminAuditLog::logCreate(
            $module,
            $model,
            $description ?? $this->generateCreateDescription($module, $model)
        );
    }

    /**
     * Log update action
     */
    protected function auditUpdate(string $module, Model $model, array $oldValues, ?string $description = null): AdminAuditLog
    {
        return AdminAuditLog::logUpdate(
            $module,
            $model,
            $description ?? $this->generateUpdateDescription($module, $model),
            $oldValues
        );
    }

    /**
     * Log delete action
     */
    protected function auditDelete(string $module, Model $model, ?string $description = null): AdminAuditLog
    {
        return AdminAuditLog::logDelete(
            $module,
            $model,
            $description ?? $this->generateDeleteDescription($module, $model)
        );
    }

    /**
     * Log custom action
     */
    protected function auditAction(
        string $module,
        string $action,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): AdminAuditLog {
        return AdminAuditLog::log(
            $module,
            $action,
            $description,
            $model,
            $oldValues,
            $newValues,
            $metadata
        );
    }

    /**
     * Generate create description
     */
    protected function generateCreateDescription(string $module, Model $model): string
    {
        $moduleLabel = AdminAuditLog::getModules()[$module] ?? ucfirst($module);
        $identifier = $this->getModelIdentifier($model);

        return "Menambah {$moduleLabel}: {$identifier}";
    }

    /**
     * Generate update description
     */
    protected function generateUpdateDescription(string $module, Model $model): string
    {
        $moduleLabel = AdminAuditLog::getModules()[$module] ?? ucfirst($module);
        $identifier = $this->getModelIdentifier($model);

        return "Mengubah {$moduleLabel}: {$identifier}";
    }

    /**
     * Generate delete description
     */
    protected function generateDeleteDescription(string $module, Model $model): string
    {
        $moduleLabel = AdminAuditLog::getModules()[$module] ?? ucfirst($module);
        $identifier = $this->getModelIdentifier($model);

        return "Menghapus {$moduleLabel}: {$identifier}";
    }

    /**
     * Get model identifier
     */
    protected function getModelIdentifier(Model $model): string
    {
        if (isset($model->name)) {
            return $model->name;
        }
        if (isset($model->customer_id)) {
            return $model->customer_id;
        }
        if (isset($model->invoice_number)) {
            return $model->invoice_number;
        }
        if (isset($model->email)) {
            return $model->email;
        }

        return "#{$model->id}";
    }
}
