<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\LLMServiceInterface;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ChatbotController extends Controller
{
    private LLMServiceInterface $llmService;

    public function __construct(LLMServiceInterface $llmService)
    {
        $this->llmService = $llmService;
    }

    public function index()
    {
        // Mendapatkan daftar provider LLM yang tersedia dari config
        $availableProviders = config('llm.available_providers', []);
        return Inertia::render('Chat', [ // Mengarahkan ke halaman Upload untuk training
            'availableLlmProviders' => $availableProviders
        ]);
    }

    public function upload()
    {
        // Mendapatkan daftar provider LLM yang tersedia dari config
        $availableProviders = config('llm.available_providers', []);
        return Inertia::render('Upload', [ // Mengarahkan ke halaman Upload untuk training
            'availableLlmProviders' => $availableProviders
        ]);
    }

    public function train(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:txt,md,html,pdf|max:10240',
                'llm_provider' => 'required|string', // Validasi untuk provider LLM
            ]);

            $llmProvider = $request->input('llm_provider');

            // Override binding untuk request train ini
            // Ini akan membuat instance service LLM sesuai pilihan dari frontend
            $this->llmService = app()->makeWith(LLMServiceInterface::class, ['chosenProvider' => $llmProvider]);

            // PENTING: Perlu diingat konsistensi dimensi embedding!
            // Jika Anda mengizinkan training dengan provider embedding berbeda,
            // dan ingin mencari di database, semua embedding harus dari provider yang sama,
            // atau Anda harus punya strategi pencarian yang lebih kompleks.
            // Saat ini, findSimilarDocuments akan memfilter berdasarkan provider dan dimensi.

            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();

            Log::info('Starting file processing for training', [
                'filename' => $fileName,
                'size' => $file->getSize(),
                'provider' => $this->llmService->getProviderName()
            ]);

            $text = $this->extractText($file);
            // Gunakan metode chunkText dari service LLM yang sudah di-resolve
            $chunks = $this->llmService->chunkText($text);

            Log::info('Text chunking completed', [
                'chunks_count' => count($chunks)
            ]);

            DB::beginTransaction();

            // Opsional: Hapus dokumen lama untuk provider/dimensi yang sama sebelum training baru
            // Jika Anda ingin memastikan data bersih untuk provider/dimensi tertentu
            // Contoh (sangat hati-hati menggunakannya karena menghapus data!):
            // Document::where('embedding_provider', $this->llmService->getProviderName())
            //         ->where('embedding_dimension', $this->llmService->getEmbeddingDimension())
            //         ->delete();

            $successCount = 0;
            foreach ($chunks as $index => $chunk) {
                $embedding = $this->llmService->createEmbedding($chunk);

                // Validasi dimensi embedding yang diterima
                if (!empty($embedding) && count($embedding) === $this->llmService->getEmbeddingDimension()) {
                    Log::info('Creating embedding for chunk', [
                        'chunk_index' => $index,
                        'embedding_size' => count($embedding),
                        'provider' => $this->llmService->getProviderName()
                    ]);

                    Document::create([
                        // 'chat_bot_id' => $chatBotId, // Jika knowledge base per bot
                        'source_file_name' => $fileName,
                        'text' => $chunk,
                        'embedding' => $embedding, // Simpan embedding
                        'embedding_provider' => $this->llmService->getProviderName(), // Simpan provider
                        'embedding_dimension' => count($embedding), // Simpan dimensi
                    ]);
                    $successCount++;
                } else {
                    Log::warning('Empty or incorrect dimension embedding received for chunk', [
                        'chunk_index' => $index,
                        'chunk_length' => strlen($chunk),
                        'expected_dimension' => $this->llmService->getEmbeddingDimension(),
                        'actual_dimension' => count($embedding)
                    ]);
                }

                sleep(2);
            }

            DB::commit();

            Log::info('Training completed', [
                'total_chunks' => count($chunks),
                'successful_embeddings' => $successCount,
                'provider' => $this->llmService->getProviderName()
            ]);

            return back()->with('success', 'Training completed successfully for ' . $this->llmService->getProviderName());

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error during training:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_name' => $request->file('file') ? $request->file('file')->getClientOriginalName() : 'N/A'
            ]);

            return back()->with('error', 'Error during training: ' . $e->getMessage());
        }
    }

    public function chat(Request $request)
    {
        try {
            $request->validate([
                'prompt' => 'required|string|max:1000',
                'llm_provider' => 'required|string', // Ambil provider dari frontend
            ]);

            $prompt = $request->input('prompt');
            $llmProvider = $request->input('llm_provider');

            // Override binding untuk request ini
            $this->llmService = app()->makeWith(LLMServiceInterface::class, ['chosenProvider' => $llmProvider]);


            Log::info('Chat request received:', ['prompt' => $prompt, 'provider' => $llmProvider]);

            // Dapatkan embedding untuk prompt
            $promptEmbedding = $this->llmService->createEmbedding($prompt);

            if (empty($promptEmbedding) || count($promptEmbedding) !== $this->llmService->getEmbeddingDimension()) {
                Log::error('Empty or incorrect dimension embedding received for prompt', [
                    'prompt' => $prompt,
                    'expected_dimension' => $this->llmService->getEmbeddingDimension(),
                    'actual_dimension' => count($promptEmbedding)
                ]);
                return back()->with([
                    'response' => 'Maaf, saya tidak bisa memproses permintaan Anda saat ini karena masalah embedding.'
                ]);
            }

            Log::info('Searching similar documents', [
                'embedding_size' => count($promptEmbedding),
                'provider' => $this->llmService->getProviderName()
            ]);

            // Pass embedding_provider dan embedding_dimension ke findSimilarDocuments
            $similarDocs = $this->llmService->findSimilarDocuments($promptEmbedding);

            Log::info('Similar documents found', [
                'count' => count($similarDocs),
                'docs_sample' => array_map(fn($doc) => ['text_length' => strlen($doc->text), 'similarity' => $doc->similarity], $similarDocs)
            ]);

            $context = array_map(fn($doc) => $doc->text, $similarDocs);
            $response = $this->llmService->generateResponse($prompt, $context /*, $history */);

            Log::info('Generated response:', [
                'provider' => $this->llmService->getProviderName(),
                'response' => $response
            ]);

            // *** KEMBALIKAN INI ***
            return back()->with([
                'response' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('Error during chat:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'prompt_input' => $request->input('prompt')
            ]);

            return back()->with([
                'response' => 'Maaf, terjadi kesalahan saat memproses permintaan Anda: ' . $e->getMessage()
            ]);
        }
    }

    private function extractText($file): string
    {
        // ... (kode extractText tidak berubah) ...
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