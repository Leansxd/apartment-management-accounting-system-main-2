<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Belge arşivi: sözleşme, fatura, tutanak vb. Dosyalar herkese açık değil, panel yetkisiyle indirilir. */
class DocumentController extends Controller
{
    public function index(Request $request, Apartment $apartment)
    {
        $category = in_array($request->query('category'), Document::CATEGORIES, true) ? $request->query('category') : null;

        return view('site.ops.documents', [
            'apartment' => $apartment,
            'documents' => $apartment->documents()->with('uploader')
                ->when($category, fn ($q) => $q->where('category', $category))
                ->when($request->query('q'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
                ->latest('id')->paginate(30)->withQueryString(),
            'category' => $category,
            'expiring' => $apartment->documents()->whereNotNull('expires_on')->whereDate('expires_on', '<=', today()->addDays(30))->orderBy('expires_on')->get(),
        ]);
    }

    public function store(Request $request, Apartment $apartment)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(Document::CATEGORIES)],
            'expires_on' => ['nullable', 'date'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt,zip'],
        ]);
        $file = $request->file('file');
        $apartment->documents()->create([
            'title' => $data['title'], 'category' => $data['category'], 'expires_on' => $data['expires_on'] ?? null,
            'file_path' => $file->store("documents/{$apartment->id}", 'local'),
            'original_name' => $file->getClientOriginalName(), 'mime' => $file->getClientMimeType(), 'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Belge yüklendi.');
    }

    public function download(Apartment $apartment, Document $document)
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'Dosya bulunamadı.');

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroy(Apartment $apartment, Document $document)
    {
        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('status', 'Belge silindi.');
    }
}
