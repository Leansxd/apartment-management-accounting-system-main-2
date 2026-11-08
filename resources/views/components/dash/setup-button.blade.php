@if ($user->is_super_admin)
    <button type="button" data-setup-guide-start class="{{ $buttonClass }}" title="Paneli adım adım kurmanızı sağlayan rehberi başlatır">
        <x-icon name="wrench" :size="14" />
        <span>Setup</span>
    </button>
@endif
