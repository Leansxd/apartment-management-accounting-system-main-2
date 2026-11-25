@props(['action', 'width' => 'w-96', 'label' => 'Düzenle'])
<span class="no-print inline-block text-left whitespace-normal">
    <button type="button" class="btn btn-secondary btn-sm" onclick="this.nextElementSibling.showModal()">{{ $label }}</button>
    <dialog class="m-auto {{ $width }} max-w-[92vw] overflow-visible bg-transparent p-0 backdrop:bg-slate-900/50">
        <form method="POST" action="{{ $action }}" class="card card-body max-h-[85vh] space-y-3 overflow-y-auto">
            @csrf @method('PUT')
            {{ $slot }}
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" class="btn btn-secondary" onclick="this.closest('dialog').close()">Vazgeç</button>
                <button class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </dialog>
</span>
