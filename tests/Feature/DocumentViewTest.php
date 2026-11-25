<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Belge arşivinde dosyanın indirmeden tarayıcıda görüntülenmesi */
class DocumentViewTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Apartment $site;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->manager = $this->admin();
        $this->site = $this->site(1, $this->manager);
    }

    private function upload(UploadedFile $file): Document
    {
        $this->actingAs($this->manager)->post(route('site.documents.store', $this->site), [
            'title' => 'Belge', 'category' => 'Sözleşme', 'file' => $file,
        ])->assertSessionHasNoErrors();

        return Document::latest('id')->firstOrFail();
    }

    public function test_pdf_tarayicida_acilir(): void
    {
        $document = $this->upload(UploadedFile::fake()->createWithContent('sozlesme.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"));

        $response = $this->actingAs($this->manager)->get(route('site.documents.view', [$this->site, $document]));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_metin_dosyasi_duz_metin_ve_betiksiz_acilir(): void
    {
        $document = $this->upload(UploadedFile::fake()->createWithContent('not.txt', '<script>alert(1)</script>'));

        $response = $this->actingAs($this->manager)->get(route('site.documents.view', [$this->site, $document]));

        $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    public function test_tarayicida_acilamayan_tur_indirmeye_yonlenir(): void
    {
        $document = $this->upload(UploadedFile::fake()->create('arsiv.zip', 5, 'application/zip'));

        $this->actingAs($this->manager)->get(route('site.documents.view', [$this->site, $document]))
            ->assertRedirect(route('site.documents.download', [$this->site, $document]));
    }

    public function test_listede_goruntule_dugmesi_yalnizca_acilabilen_belgede_vardir(): void
    {
        $pdf = $this->upload(UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF"));
        $zip = $this->upload(UploadedFile::fake()->create('b.zip', 5, 'application/zip'));

        $this->actingAs($this->manager)->get(route('site.documents.index', $this->site))
            ->assertSee(route('site.documents.view', [$this->site, $pdf]), false)
            ->assertDontSee(route('site.documents.view', [$this->site, $zip]), false);
    }

    public function test_baska_sitenin_belgesi_goruntulenemez(): void
    {
        $document = $this->upload(UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF"));
        $other = $this->site(1, $this->manager);

        $this->actingAs($this->manager)->get(route('site.documents.view', [$other, $document]))->assertNotFound();
    }
}
