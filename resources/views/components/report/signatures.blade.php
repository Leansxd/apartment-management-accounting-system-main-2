{{-- Resmi çıktının sonundaki imza ve onay alanları (yalnızca yazdırırken görünür) --}}
@props(['roles' => ['Düzenleyen', 'Yönetici', 'Denetim Kurulu']])
<div class="print-only print-keep mt-10">
    <table class="w-full text-center text-[9pt]" style="border-collapse: collapse">
        <tr>
            @foreach ($roles as $role)
                <td class="px-4 align-bottom" style="width: {{ 100 / count($roles) }}%">
                    <div class="h-14"></div>
                    <div class="border-t border-black pt-1 font-semibold">{{ $role }}</div>
                    <div class="text-[8pt]">Ad Soyad / İmza / Tarih</div>
                </td>
            @endforeach
        </tr>
    </table>
</div>
