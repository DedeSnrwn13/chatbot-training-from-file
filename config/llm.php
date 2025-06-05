<?php

return [
    'default_provider' => env('LLM_DEFAULT_PROVIDER', 'gemini'),

    // Daftar provider yang tersedia untuk frontend (opsional, bisa hardcode di frontend juga)
    'available_providers' => [
        [
            'id' => 'gemini',
            'name' => 'Google Gemini',
            'embedding_dimension' => 3072, // Sesuaikan dengan dimensi model Gemini Anda
            'supported_features' => ['chat', 'embedding']
        ],
        [
            'id' => 'huggingface',
            'name' => 'Hugging Face (Free)',
            'embedding_dimension' => 384, // Sesuai all-MiniLM-L6-v2
            'supported_features' => ['chat', 'embedding']
        ],
        // Tambahkan provider lain di sini
    ],
];