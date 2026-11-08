@if (session('status'))
    <div role="status" class="mb-4.5 flex items-center gap-2.5 rounded-2xl border border-emerald-200/80 bg-emerald-50/80 px-4 py-3 text-xs font-medium text-emerald-700">
        <x-icon name="check" :size="15" />
        <span>{{ session('status') }}</span>
    </div>
@endif
@if ($errors->any())
    <div role="alert" class="mb-4.5 flex items-center gap-2.5 rounded-2xl border border-rose-200/80 bg-rose-50/80 px-4 py-3 text-xs font-medium text-rose-600">
        <x-icon name="alert-circle" :size="15" />
        <span>{{ $errors->first() }}</span>
    </div>
@endif
