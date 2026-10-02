<?php
return [
    'chunk_size' => (int)env('LIFEPILOT_GRAPH_CHUNK_SIZE', 900),
    'chunk_overlap' => (int)env('LIFEPILOT_GRAPH_CHUNK_OVERLAP', 150),
    'embedding_dimensions' => (int)env('LIFEPILOT_EMBEDDING_DIMENSIONS', 384),
    'default_limit' => (int)env('LIFEPILOT_SEARCH_LIMIT', 15),
];
