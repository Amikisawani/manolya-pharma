<?php

namespace App\Http\Controllers\Document;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocumentOcrJob;
use App\Models\Document as DocumentModel;
use App\Models\DocumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('documents.view'), 403);

        $documents = DocumentModel::query()
            ->with(['versions' => fn ($q) => $q->orderByDesc('version')])
            ->search($request->string('q')->toString())
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (DocumentModel $doc) {
                $latest = $doc->versions->first();

                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'type' => $doc->type,
                    'current_version' => $doc->current_version,
                    'updated_at' => $doc->updated_at,
                    'ocr_status' => $latest?->ocr_status,
                    'ocr_engine' => $latest?->ocr_engine,
                    'snippet' => $this->snippet($doc->search_text),
                ];
            });

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function show(Request $request, DocumentModel $document): Response
    {
        abort_unless($request->user()?->can('documents.view'), 403);

        $document->load(['versions' => fn ($q) => $q->orderByDesc('version')->with('uploader:id,name')]);

        return Inertia::render('Documents/Show', [
            'document' => $document,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('documents.upload'), 403);

        $data = $request->validate([
            'type' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/'.$request->user()->tenant_id, 'local');

        $document = DocumentModel::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'type' => $data['type'],
            'title' => $data['title'],
            'current_version' => 1,
            'search_text' => $data['title'],
        ]);

        $version = DocumentVersion::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'document_id' => $document->id,
            'version' => 1,
            'disk_path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'ocr_status' => 'pending',
            'uploaded_by' => $request->user()->id,
        ]);

        ProcessDocumentOcrJob::dispatch($version->id);

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Document téléversé — OCR en file d’attente.');
    }

    public function download(Request $request, DocumentModel $document, DocumentVersion $version): StreamedResponse
    {
        abort_unless($request->user()?->can('documents.view'), 403);
        abort_unless($version->document_id === $document->id, 404);
        abort_unless(Storage::disk('local')->exists($version->disk_path), 404);

        $name = Str::slug($document->title).'-v'.$version->version;

        return Storage::disk('local')->download($version->disk_path, $name);
    }

    public function reprocess(Request $request, DocumentModel $document): RedirectResponse
    {
        abort_unless($request->user()?->can('documents.upload'), 403);

        $version = $document->versions()->orderByDesc('version')->firstOrFail();
        $version->update(['ocr_status' => 'pending', 'ocr_error' => null]);
        ProcessDocumentOcrJob::dispatch($version->id);

        return back()->with('success', 'OCR relancé.');
    }

    private function snippet(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        return Str::limit(preg_replace('/\s+/', ' ', $text) ?? $text, 140);
    }
}
