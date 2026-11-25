<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditTrail;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Süper admin ekranları: tüm sistemin denetim kaydı ve veritabanı yedekleri */
class SystemController extends Controller
{
    private const RESTORE_PHRASE = 'GERİ YÜKLE';

    public function audit(Request $request): View
    {
        return view('system.audit', [
            'logs' => AuditLog::with('user', 'apartment')
                ->when($request->query('module'), fn ($q, $module) => $q->where('module', $module))
                ->when($this->dateQuery($request, 'from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                ->when($this->dateQuery($request, 'to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('action', 'like', "%{$s}%")
                    ->orWhere('reference', 'like', "%{$s}%")->orWhere('ip_address', 'like', "%{$s}%")))
                ->latest('id')->paginate(10)->withQueryString(),
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module', 'module')->all(),
        ]);
    }

    public function backups(DatabaseBackupService $backups): View
    {
        return view('system.backups', ['backups' => $backups->available(), 'phrase' => self::RESTORE_PHRASE]);
    }

    public function createBackup(DatabaseBackupService $backups, AuditTrail $audit): RedirectResponse
    {
        try {
            $path = $backups->create();
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', 'Yedek alınamadı: '.$e->getMessage());
        }
        $audit->log(null, 'CREATE_BACKUP', 'SYSTEM', basename($path));

        return back()->with('status', 'Yedek alındı.');
    }

    /** Yedeği geri yükler. Oturumlar da yedekteki haline döndüğü için kullanıcı yeniden giriş yapar. */
    public function restoreBackup(Request $request, DatabaseBackupService $backups, AuditTrail $audit): RedirectResponse
    {
        $data = $request->validate([
            'backup' => ['required', 'string'],
            'password' => ['required', 'current_password'],
            'confirm' => ['required', Rule::in([self::RESTORE_PHRASE])],
        ], [
            'password.current_password' => 'Şifreniz hatalı.',
            'confirm.in' => 'Onay için "'.self::RESTORE_PHRASE.'" yazın.',
        ]);
        $performedBy = $request->user()->email;

        try {
            $safetyBackup = $backups->restore($data['backup']);
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', 'Yedek geri yüklenemedi: '.$e->getMessage());
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $audit->log(null, 'RESTORE_BACKUP', 'SYSTEM', basename($data['backup']), [
            'safety_backup' => basename($safetyBackup), 'performed_by' => $performedBy,
        ]);

        return redirect()->route('login')->with('status', 'Yedek geri yüklendi. Lütfen yeniden giriş yapın.');
    }
}
