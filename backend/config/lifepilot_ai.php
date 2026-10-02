<?php
return [
    'provider' => env('LIFEPILOT_AI_PROVIDER', 'http'),
    'endpoint' => env('LIFEPILOT_AI_ENDPOINT', ''),
    'api_key' => env('LIFEPILOT_AI_API_KEY', ''),
    'model' => env('LIFEPILOT_AI_MODEL', 'grounded-assistant'),
    'model_version' => env('LIFEPILOT_AI_MODEL_VERSION', '1'),
    'timeout' => (int)env('LIFEPILOT_AI_TIMEOUT', 25),
    'max_evidence_items' => (int)env('LIFEPILOT_AI_MAX_EVIDENCE_ITEMS', 60),
];
