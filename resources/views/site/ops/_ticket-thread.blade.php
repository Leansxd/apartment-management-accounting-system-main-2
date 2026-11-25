{{-- Talep yazışması. $t: talep (messages.user yüklü), $attachmentRoute: fn (mesaj) => fotoğraf adresi --}}
@if ($t->messages->isNotEmpty())
    <ul class="mt-3 space-y-2">
        @foreach ($t->messages as $message)
            <li @class(['rounded-xl border px-3 py-2 text-sm', 'border-slate-200 bg-slate-50' => $message->is_staff, 'border-slate-200' => ! $message->is_staff])>
                <p class="text-xs font-medium text-slate-500">{{ $message->is_staff ? 'Yönetim' : ($message->user?->name ?? 'Sakin') }} · {{ $message->created_at->format('d.m.Y H:i') }}</p>
                @if ($message->body)<p class="whitespace-pre-line text-slate-900">{{ $message->body }}</p>@endif
                @if ($message->attachment_path)
                    <a href="{{ $attachmentRoute($message) }}" target="_blank" rel="noopener"><img src="{{ $attachmentRoute($message) }}" alt="{{ $message->attachment_name }}" loading="lazy" class="mt-1 max-h-48 rounded-lg"></a>
                @endif
            </li>
        @endforeach
    </ul>
@endif
