<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use function Codewithkyrian\Transformers\Pipelines\pipeline;

class HuggingFaceService implements LLMServiceInterface
{
    private string $apiKey;
    // Model embedding yang populer (dimensi 384)

    // private string $embeddingModel = 'sentence-transformers/all-MiniLM-L6-v2';
    // private string $embeddingModel = 'thenlper/gte-small'; // atau 'intfloat/e5-small-v2'
    private string $embeddingModel = 'Xenova/all-MiniLM-L6-v2';


    // Model chat yang populer
    private string $chatModel = 'mistralai/Mistral-7B-Instruct-v0.2'; // Atau 'google/gemma-7b-it'
    private string $baseUrl = 'https://api-inference.huggingface.co';

    private int $embeddingDimension = 384; // Dimensi untuk all-MiniLM-L6-v2

    public function __construct()
    {
        $this->apiKey = config('services.huggingface.api_key');
        if (empty($this->apiKey)) {
            Log::error('HUGGINGFACE_API_KEY is not set in .env');
            // throw new \Exception('HuggingFace API key is not configured.');
        }
    }

    public function getProviderName(): string
    {
        return 'huggingface';
    }

    public function getEmbeddingDimension(): int
    {
        return $this->embeddingDimension;
    }

    public function createEmbedding(string $text): array
    {
        try {
            // Gunakan model lokal dari transformers-php
            $extractor = pipeline('feature-extraction', 'Xenova/all-MiniLM-L6-v2');

            // Lakukan ekstraksi embedding dengan pooling 'mean' dan normalize true
            $embeddings = $extractor($text, pooling: 'mean', normalize: true);

            // Ambil hasil embedding (biasanya hanya satu array)
            return $embeddings[0] ?? [];
        } catch (\Throwable $e) {
            Log::error('Error creating embedding (transformers-php):', [
                'message' => $e->getMessage(),
                'text' => substr($text, 0, 200) . '...',
            ]);
            return [];
        }
    }

    public function generateResponse(string $prompt, array $context, array $history = []): string
    {
        try {
            // Hugging Face Instruct model sering menerima prompt dalam format tertentu
            // Contoh format untuk Mistral Instruct:
            $full_prompt = "";
            if (!empty($history)) {
                foreach ($history as $h) {
                    $full_prompt .= ($h['role'] === 'user' ? '<s>[INST]' : '[/INST]') . $h['content'] . '</s>';
                }
            }
            $full_prompt .= "<s>[INST]Context:\n" . implode("\n", $context) . "\n\nQuestion: " . $prompt . "[/INST]";


            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/models/{$this->chatModel}", [
                        'inputs' => $full_prompt,
                        'parameters' => [ // Parameters for text generation
                            'max_new_tokens' => 1024,
                            'temperature' => 0.7,
                            'top_k' => 40,
                            'top_p' => 0.95,
                            'do_sample' => true,
                            'return_full_text' => false, // Hanya return generated text, bukan prompt + generated text
                        ],
                        'options' => ['wait_for_model' => true]
                    ]);

            if (!$response->successful()) {
                Log::error('HuggingFace Chat API Error:', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                    'prompt_input' => $prompt
                ]);
                return 'Maaf, saya tidak bisa memproses permintaan Anda saat ini (HuggingFace API Error).';
            }

            // Respon dari HF Inference API biasanya array of objects dengan 'generated_text'
            return $response->json()[0]['generated_text'] ?? 'Maaf, saya tidak bisa memproses permintaan Anda (HuggingFace Response Error).';
        } catch (\Exception $e) {
            Log::error('Error generating HuggingFace response:', [
                'message' => $e->getMessage(),
                'prompt' => substr($prompt, 0, 200) . '...'
            ]);
            return 'Maaf, terjadi kesalahan saat memproses permintaan Anda (HuggingFace Exception).';
        }
    }

    // Metode ini tetap di sini karena ia berinteraksi dengan database kita sendiri
    public function findSimilarDocuments(array $embedding, int $limit = 5): array
    {
        // ***** PERBAIKI BARIS INI *****
        // Kita perlu secara eksplisit meng-cast array numerik ke tipe 'vector' dengan dimensi yang benar
        $vectorString = 'ARRAY[' . implode(',', $embedding) . ']::vector(' . $this->getEmbeddingDimension() . ')';

        // Saya juga tambahkan embedding_provider dan embedding_dimension ke SELECT
        return DB::select(
            "SELECT id, source_file_name, text, embedding_provider, embedding_dimension, 1 - (embedding <=> $vectorString) as similarity
             FROM documents
             WHERE embedding_provider = ? AND embedding_dimension = ?
             ORDER BY similarity DESC
             LIMIT ?",
            [$this->getProviderName(), $this->getEmbeddingDimension(), $limit]
        );
    }

    /**
     * Chunks a given text into smaller pieces.
     * @param string $text The text to chunk.
     * @param int $chunkSize The desired size of each chunk (in words).
     * @param int $overlap The overlap between consecutive chunks (in words).
     * @return array An array of text chunks.
     */
    public function chunkText(string $text, int $chunkSize = 1000, int $overlap = 100): array
    {
        $words = explode(' ', $text);
        $chunks = [];
        $i = 0;

        while ($i < count($words)) {
            $chunk = array_slice($words, $i, $chunkSize);
            $chunks[] = implode(' ', $chunk);
            $i += ($chunkSize - $overlap);
        }

        return $chunks;
    }
}