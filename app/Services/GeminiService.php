<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Document; // Tetap pakai ini untuk findSimilarDocuments
use Illuminate\Support\Facades\DB; // Tetap pakai ini untuk findSimilarDocuments

class GeminiService implements LLMServiceInterface
{
    private string $apiKey;
    private string $embeddingModel = 'gemini-embedding-exp-03-07';
    private string $chatModel = 'gemini-2.0-flash';
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    // Dimensi embedding untuk model Gemini ini
    private int $embeddingDimension = 3072; // Umumnya Gemini-pro-embedding adalah 768 atau 1536. Sesuaikan dengan yang Anda pakai (sebelumnya 3072 disebut)
    // Penting: Pastikan ini sesuai dengan 'vector(XXXX)' di migrasi documents.
    // Jika model Anda gemini-embedding-exp-03-07 adalah 3072, maka gunakan 3072.

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        // Validasi dan penyesuaian embeddingDimension harus sesuai model yang dipakai
        // Jika gemini-embedding-exp-03-07 output 3072, maka embeddingDimension harus 3072.
        // Jika api_key tidak ditemukan, bisa throw exception atau log error.
        if (empty($this->apiKey)) {
            Log::error('GEMINI_API_KEY is not set in .env');
            // throw new \Exception('Gemini API key is not configured.');
        }
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    public function getEmbeddingDimension(): int
    {
        return $this->embeddingDimension;
    }

    public function createEmbedding(string $text): array
    {
        try {
            $response = Http::retry(
                // ***** PERBAIKI INI *****
                5, // Coba lagi maksimal 5 kali (sebelumnya 3)
                2000, // Jeda awal 2 detik (sebelumnya 1 detik), akan digandakan (2s, 4s, 8s, 16s, 32s)
                function ($exception) {
                    // Hanya retry jika status 429 (Too Many Requests)
                    return $exception instanceof RequestException &&
                        $exception->response &&
                        $exception->response->status() === 429;
                }
            )->withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post("{$this->baseUrl}/models/{$this->embeddingModel}:embedContent?key={$this->apiKey}", [
                        'content' => ['parts' => [['text' => $text]]]
                    ]);

            if (!$response->successful()) {
                Log::error('Gemini Embedding API Error:', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                    'text_input' => $text
                ]);
                return [];
            }

            return $response->json()['embedding']['values'] ?? [];

        } catch (\Exception $e) {
            Log::error('Error creating Gemini embedding:', [
                'message' => $e->getMessage(),
                'text' => substr($text, 0, 200) . '...'
            ]);
            return [];
        }
    }

    public function generateResponse(string $prompt, array $context, array $history = []): string
    {
        try {
            // Membangun riwayat percakapan untuk Gemini
            $contents = [];
            if (!empty($history)) {
                foreach ($history as $h) {
                    $contents[] = ['role' => $h['role'], 'parts' => [['text' => $h['content']]]];
                }
            }
            // Tambahkan konteks RAG dan prompt pengguna sebagai bagian dari pesan terakhir
            $user_message = "Context:\n" . implode("\n", $context) . "\n\nQuestion: " . $prompt;
            $contents[] = ['role' => 'user', 'parts' => [['text' => $user_message]]];


            $response = Http::retry(
                // ***** PERBAIKI INI JUGA *****
                5, // Coba lagi maksimal 5 kali
                2000, // Jeda awal 2 detik
                function ($exception) {
                    return $exception instanceof RequestException &&
                        $exception->response &&
                        $exception->response->status() === 429;
                }
            )->withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post("{$this->baseUrl}/models/{$this->chatModel}:generateContent?key={$this->apiKey}", [
                        'contents' => $contents,
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
                    'body' => $response->json(),
                    'prompt_input' => $prompt
                ]);
                return 'Maaf, saya tidak bisa memproses permintaan Anda saat ini (Gemini API Error).';
            }

            // Pastikan struktur respons sesuai
            return $response->json()['candidates'][0]['content']['parts'][0]['text']
                ?? 'Maaf, saya tidak bisa memproses permintaan Anda (Gemini Response Error).';
        } catch (\Exception $e) {
            Log::error('Error generating Gemini response:', [
                'message' => $e->getMessage(),
                'prompt' => substr($prompt, 0, 200) . '...'
            ]);
            return 'Maaf, terjadi kesalahan saat memproses permintaan Anda (Gemini Exception).';
        }
    }

    // Metode ini tetap di sini karena ia berinteraksi dengan database kita sendiri
    public function findSimilarDocuments(array $embedding, int $limit = 5): array
    {
        // ***** PERBAIKI BARIS INI *****
        // Kita perlu secara eksplisit meng-cast array numerik ke tipe 'vector' dengan dimensi yang benar
        $vectorString = 'ARRAY[' . implode(',', $embedding) . ']::vector(' . $this->getEmbeddingDimension() . ')';

        // Saya juga tambahkan embedding_provider dan embedding_dimension ke SELECT
        // agar hasil findSimilarDocuments lebih lengkap jika diperlukan
        return DB::select(
            "SELECT id, source_file_name, text, embedding_provider, embedding_dimension, 1 - (embedding <=> $vectorString) as similarity
             FROM documents
             WHERE embedding_provider = ? AND embedding_dimension = ?
             ORDER BY similarity DESC -- Biasanya diurutkan DESC untuk similarity atau ASC untuk distance
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