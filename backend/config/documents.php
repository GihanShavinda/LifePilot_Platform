<?php

return [
    'disk' => env('DOCUMENTS_DISK', 'documents'),
    'max_upload_kb' => (int) env('DOCUMENT_MAX_UPLOAD_KB', 15360),

    'intelligence' => [
        'native_text_min_chars' => (int) env('DOCUMENT_AI_NATIVE_TEXT_MIN_CHARS', 80),
        'auto_accept_confidence' => (float) env('DOCUMENT_AI_AUTO_ACCEPT_CONFIDENCE', 0.95),
        'review_confidence' => (float) env('DOCUMENT_AI_REVIEW_CONFIDENCE', 0.65),

        'pdftotext_binary' => env('PDFTOTEXT_BINARY', 'pdftotext'),
        'pdftoppm_binary' => env('PDFTOPPM_BINARY', 'pdftoppm'),
        'tesseract_binary' => env('TESSERACT_BINARY', 'tesseract'),
        'ocr_language' => env('DOCUMENT_AI_OCR_LANGUAGE', 'eng'),
        'process_timeout' => (int) env('DOCUMENT_AI_PROCESS_TIMEOUT', 120),

        'llm' => [
            'endpoint' => env('DOCUMENT_AI_LLM_ENDPOINT', ''),
            'token' => env('DOCUMENT_AI_LLM_TOKEN', ''),
            'model_version' => env('DOCUMENT_AI_LLM_MODEL_VERSION', ''),
            'timeout' => (int) env('DOCUMENT_AI_LLM_TIMEOUT', 90),
        ],
    ],
];
