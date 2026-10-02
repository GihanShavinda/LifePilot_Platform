# P7 — Life Action Graph and Semantic Search

## Architecture
PostgreSQL application tables remain authoritative. `life_entities` and `life_relations` are derived graph indexes. `document_chunks` and `embedding_records` are derived semantic indexes and can be rebuilt with `POST /api/v1/graph/sync`.

## Embeddings and pgvector
P7 ships with a deterministic local 384-dimensional embedding provider so tests are reproducible and no third-party data leaves the machine. On PostgreSQL the migration attempts to enable the `vector` extension and adds a `vector(384)` column plus an HNSW cosine index. If the extension is unavailable, JSON embeddings remain usable via PHP cosine search. Install pgvector on the PostgreSQL server to enable database-side vector ranking.

Check PostgreSQL:
```sql
SELECT extname FROM pg_extension WHERE extname='vector';
```

## Indexing policy
Only the latest extraction of a document is indexed, and only after at least one extraction field is `accepted` or `edited`. Reviewing a field queues `IndexDocumentForSemanticSearch`. The explicit graph sync endpoint can rebuild all derived indexes.

## Authorization
Every graph/entity/chunk query begins with the authenticated user's current household ID and filters by that household. Cross-household entity lookups return 404. Source references are attached to every search result.

## Endpoints
- `POST /api/v1/graph/sync`
- `GET /api/v1/graph/entities`
- `GET /api/v1/graph/entities/{id}`
- `GET /api/v1/search?q=...`
- `POST /api/v1/documents/{id}/semantic/reindex`

## Example queries
- show warranties expiring this year
- find the receipt for my laptop
- what payments are due next week?
- which tasks came from university documents?
- show everything related to my car

## Run
```powershell
php83 artisan migrate
php83 artisan optimize:clear
php83 artisan queue:work --tries=3 --timeout=180 -v
php83 artisan test --filter=P7
```
Then sign in and open `http://localhost:5174/search`.
