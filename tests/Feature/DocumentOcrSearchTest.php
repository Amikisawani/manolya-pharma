<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentOcrSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_runs_ocr_and_indexes_text_for_search(): void
    {
        $this->seed();
        Storage::fake('local');

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $file = UploadedFile::fake()->createWithContent(
            'facture-distrimed.txt',
            "Facture DISTRIMED Congo\nLot PARA-500\nMontant 125000 Fc\nKinshasa Bandal",
        );

        $this->actingAs($owner)
            ->post(route('documents.store'), [
                'type' => 'invoice',
                'title' => 'Facture Distrimed août',
                'file' => $file,
            ])
            ->assertRedirect();

        $document = Document::query()->firstOrFail();
        $version = DocumentVersion::query()->where('document_id', $document->id)->firstOrFail();

        $this->assertSame('completed', $version->ocr_status);
        $this->assertNotEmpty($version->ocr_text);
        $this->assertStringContainsString('DISTRIMED', (string) $document->fresh()->search_text);
        $this->assertStringContainsString('PARA-500', (string) $document->fresh()->search_text);

        $this->actingAs($owner)
            ->get(route('documents.index', ['q' => 'PARA-500']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Index')
                ->has('documents.data', 1)
            );
    }

    public function test_search_miss_returns_empty(): void
    {
        $this->seed();
        Storage::fake('local');

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('documents.store'), [
                'type' => 'other',
                'title' => 'Contrat local',
                'file' => UploadedFile::fake()->createWithContent('note.txt', 'Simple note interne'),
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->get(route('documents.index', ['q' => 'inexistant-xyz']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('documents.data', 0));
    }
}
