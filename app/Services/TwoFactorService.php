<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

/** Doğrulama uygulaması (TOTP) kodları ve tek kullanımlık kurtarma kodları */
class TwoFactorService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /** Doğrulama uygulamasının okuyacağı QR kod (satır içi SVG) */
    public function qrCode(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl((string) config('app.name'), $user->email, $secret);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(192), new SvgImageBackEnd)))->writeString($url);

        return (string) preg_replace('/^<\?xml.*?\?>\s*/s', '', $svg);
    }

    /** Altı haneli kodu doğrular. Kabul edilen kod aynı süre içinde ikinci kez kullanılamaz. */
    public function verifyCode(User $user, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return DB::transaction(function () use ($user, $code) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->two_factor_secret === null) {
                return false;
            }
            $timestamp = $this->google2fa->verifyKeyNewer($locked->two_factor_secret, $code, $locked->two_factor_last_timestamp ?? 0);
            if ($timestamp === false) {
                return false;
            }
            $locked->forceFill(['two_factor_last_timestamp' => $timestamp])->save();

            return true;
        });
    }

    /** @return list<string> */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => strtoupper(bin2hex(random_bytes(3)).'-'.bin2hex(random_bytes(3))))->all();
    }

    /** Geçerli kurtarma kodunu bir kez kullanır. @return int|null kalan kod sayısı; kod geçersizse null */
    public function consumeRecoveryCode(User $user, string $code): ?int
    {
        $code = strtoupper(trim($code));

        return DB::transaction(function () use ($user, $code) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $codes = collect($locked->two_factor_recovery_codes ?? []);
            $match = $codes->first(fn (string $stored) => hash_equals($stored, $code));
            if ($match === null) {
                return null;
            }
            $remaining = $codes->reject(fn (string $stored) => $stored === $match)->values()->all();
            $locked->forceFill(['two_factor_recovery_codes' => $remaining])->save();

            return count($remaining);
        });
    }

    /** İki adımlı doğrulamayı tamamen kapatır */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null, 'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null, 'two_factor_last_timestamp' => null,
        ])->save();
    }
}
