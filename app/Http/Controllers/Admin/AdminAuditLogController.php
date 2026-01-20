<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AdminAuditLogExport;

class AdminAuditLogController extends Controller
{
    protected AdminAuditService $auditService;

    public function __construct(AdminAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Display audit log list
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'admin_id',
            'module',
            'action',
            'start_date',
            'end_date',
            'search',
        ]);

        $logs = $this->auditService->getAuditLogs($filters, $request->get('per_page', 20));

        // Transform logs for frontend
        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'admin' => $log->admin ? [
                    'id' => $log->admin->id,
                    'name' => $log->admin->name,
                    'role' => $log->admin->role,
                ] : null,
                'module' => $log->module,
                'module_label' => $log->module_label,
                'action' => $log->action,
                'action_label' => $log->action_label,
                'action_color' => $log->action_color,
                'description' => $log->description,
                'auditable_type' => $log->auditable_type ? class_basename($log->auditable_type) : null,
                'auditable_id' => $log->auditable_id,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->format('d/m/Y H:i:s'),
                'created_at_human' => $log->created_at->diffForHumans(),
            ];
        });

        // Get statistics
        $stats = $this->auditService->getStatistics($request->get('period', 'this_month'));

        return Inertia::render('Admin/AuditLog/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'stats' => $stats,
            'modules' => AdminAuditLog::getModules(),
            'actions' => AdminAuditLog::getActions(),
            'admins' => $this->auditService->getAdminList(),
        ]);
    }

    /**
     * Show audit log detail
     */
    public function show(AdminAuditLog $auditLog)
    {
        $auditLog->load('admin:id,name,email,role');

        // Get related record if exists
        $relatedRecord = null;
        if ($auditLog->auditable_type && $auditLog->auditable_id) {
            try {
                $relatedRecord = $auditLog->auditable;
            } catch (\Exception $e) {
                // Model might be deleted
            }
        }

        return Inertia::render('Admin/AuditLog/Show', [
            'log' => [
                'id' => $auditLog->id,
                'admin' => $auditLog->admin ? [
                    'id' => $auditLog->admin->id,
                    'name' => $auditLog->admin->name,
                    'email' => $auditLog->admin->email,
                    'role' => $auditLog->admin->role,
                ] : null,
                'module' => $auditLog->module,
                'module_label' => $auditLog->module_label,
                'action' => $auditLog->action,
                'action_label' => $auditLog->action_label,
                'action_color' => $auditLog->action_color,
                'description' => $auditLog->description,
                'auditable_type' => $auditLog->auditable_type ? class_basename($auditLog->auditable_type) : null,
                'auditable_id' => $auditLog->auditable_id,
                'old_values' => $auditLog->old_values,
                'new_values' => $auditLog->new_values,
                'metadata' => $auditLog->metadata,
                'ip_address' => $auditLog->ip_address,
                'user_agent' => $auditLog->user_agent,
                'created_at' => $auditLog->created_at->format('d/m/Y H:i:s'),
            ],
            'relatedRecord' => $relatedRecord,
        ]);
    }

    /**
     * Export audit logs
     */
    public function export(Request $request)
    {
        $filters = $request->only([
            'admin_id',
            'module',
            'action',
            'start_date',
            'end_date',
            'search',
        ]);

        // Log export action
        $this->auditService->logExport(
            AdminAuditLog::MODULE_REPORT,
            $filters,
        );

        $filename = 'audit-log-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new AdminAuditLogExport($filters), $filename);
    }

    /**
     * Get recent activities for dashboard widget
     */
    public function recent(Request $request)
    {
        $activities = $this->auditService->getRecentActivities(
            $request->get('limit', 10)
        );

        return response()->json($activities);
    }

    /**
     * Get statistics
     */
    public function statistics(Request $request)
    {
        $stats = $this->auditService->getStatistics(
            $request->get('period', 'this_month')
        );

        return response()->json($stats);
    }
}
