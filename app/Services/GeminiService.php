<?php

namespace App\Services;

use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

class GeminiService
{
    private string $apiKey;
    private string $embeddingModel = 'gemini-embedding-exp-03-07';
    private string $chatModel = 'gemini-2.0-flash';
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    public function createEmbedding(string $text): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/models/{$this->embeddingModel}:embedContent?key={$this->apiKey}", [
                        'content' => ['parts' => [['text' => $text]]]
                    ]);

            Log::info('Gemini API Response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if (!$response->successful()) {
                Log::error('Gemini API Error:', [
                    'status' => $response->status(),
                    'body' => $response->json()
                ]);
                return [];
            }

            return $response->json()['embedding']['values'] ?? [];
        } catch (\Exception $e) {
            Log::error('Error creating embedding:', [
                'message' => $e->getMessage(),
                'text' => $text
            ]);
            return [];
        }
    }

    public function generateResponse(string $prompt, array $context): string
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/models/{$this->chatModel}:generateContent?key={$this->apiKey}", [
                        'contents' => [
                            'parts' => [
                                ['text' => "Context:\n" . implode("\n", $context) . "\n\nQuestion: " . $prompt]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'topK' => 40,
                            'topP' => 0.95,
                            'maxOutputTokens' => 1024,
                        ]
                    ]);

            if (!$response->successful()) {
                Log::error('Gemini Chat API Error:', [
                    'status' => $response->status(),
                    'body' => $response->json()
                ]);
                return 'Maaf, saya tidak bisa memproses permintaan Anda saat ini.';
            }

            return $response->json()['candidates'][0]['content']['parts'][0]['text']
                ?? 'Maaf, saya tidak bisa memproses permintaan Anda.';
        } catch (\Exception $e) {
            Log::error('Error generating response:', [
                'message' => $e->getMessage(),
                'prompt' => $prompt
            ]);
            return 'Maaf, terjadi kesalahan saat memproses permintaan Anda.';
        }
    }

    public function findSimilarDocuments(array $embedding, int $limit = 5): array
    {
        $vectorString = "'[" . implode(',', $embedding) . "]'::vector";

        return DB::select(
            "SELECT text, 1 - (embedding <-> $vectorString) as similarity
             FROM documents
             ORDER BY embedding <-> $vectorString ASC
             LIMIT ?",
            [$limit]
        );
    }

    public function chunkText(string $text, int $chunkSize = 800, int $overlap = 100): array
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
