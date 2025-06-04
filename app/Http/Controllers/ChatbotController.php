<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ChatbotController extends Controller
{
    private GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    public function train(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:txt,md,html,pdf|max:10240'
            ]);

            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();

            Log::info('Starting file processing', [
                'filename' => $fileName,
                'size' => $file->getSize()
            ]);

            $text = $this->extractText($file);
            $chunks = $this->geminiService->chunkText($text);

            Log::info('Text chunking completed', [
                'chunks_count' => count($chunks)
            ]);

            DB::beginTransaction();

            $successCount = 0;
            foreach ($chunks as $index => $chunk) {
                $embedding = $this->geminiService->createEmbedding($chunk);

                if (!empty($embedding)) {
                    Log::info('Creating embedding for chunk', [
                        'chunk_index' => $index,
                        'embedding_size' => count($embedding)
                    ]);

                    Document::create([
                        'source_file_name' => $fileName,
                        'text' => $chunk,
                        'embedding' => $embedding
                    ]);
                    $successCount++;
                } else {
                    Log::warning('Empty embedding received for chunk', [
                        'chunk_index' => $index,
                        'chunk_length' => strlen($chunk)
                    ]);
                }
            }

            DB::commit();

            Log::info('Training completed', [
                'total_chunks' => count($chunks),
                'successful_embeddings' => $successCount
            ]);

            return back()->with('success', 'Training completed successfully');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error during training:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Error during training: ' . $e->getMessage());
        }
    }

    public function index()
    {
        return Inertia::render('Chat');
    }

    public function chat(Request $request)
    {
        try {
            $request->validate([
                'prompt' => 'required|string|max:1000'
            ]);

            $prompt = $request->input('prompt');
            Log::info('Chat request received:', ['prompt' => $prompt]);

            $promptEmbedding = $this->geminiService->createEmbedding($prompt);

            if (empty($promptEmbedding)) {
                Log::error('Empty embedding received for prompt', [
                    'prompt' => $prompt
                ]);
                return back()->with([
                    'response' => 'Maaf, saya tidak bisa memproses permintaan Anda saat ini.'
                ]);
            }

            Log::info('Searching similar documents', [
                'embedding_size' => count($promptEmbedding)
            ]);

            $similarDocs = $this->geminiService->findSimilarDocuments($promptEmbedding);

            Log::info('Similar documents found', [
                'count' => count($similarDocs),
                'docs' => $similarDocs
            ]);

            $context = array_map(fn($doc) => $doc->text, $similarDocs);
            $response = $this->geminiService->generateResponse($prompt, $context);

            Log::info('Generated response:', [
                'response' => $response
            ]);

            return back()->with([
                'response' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('Error during chat:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with([
                'response' => 'Maaf, terjadi kesalahan saat memproses permintaan Anda.'
            ]);
        }
    }

    private function extractText($file): string
    {
        try {
            $extension = $file->getClientOriginalExtension();

            if ($extension === 'pdf') {
                $parser = new Parser();
                $pdf = $parser->parseFile($file->path());
                $text = $pdf->getText();
            } else {
                $text = file_get_contents($file->path());
            }

            Log::info('Text extracted from file', [
                'extension' => $extension,
                'text_length' => strlen($text)
            ]);

            return $text;

        } catch (\Exception $e) {
            Log::error('Error extracting text:', [
                'message' => $e->getMessage(),
                'file_extension' => $file->getClientOriginalExtension()
            ]);
            throw $e;
        }
    }
}
