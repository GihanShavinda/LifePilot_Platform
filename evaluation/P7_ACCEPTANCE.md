# P7 acceptance

- PostgreSQL source tables remain authoritative.
- LifeEntity and LifeRelation are rebuildable derived indexes.
- Person/organization canonical matching prevents spacing/case duplicates.
- Accepted document text is chunked; unreviewed-only documents are not indexed.
- EmbeddingRecord contains deterministic 384-d embeddings and content hashes.
- pgvector is used when installed; JSON/PHP cosine is a development fallback.
- Hybrid search combines semantic and keyword relevance.
- Every result includes non-empty `sources`.
- All graph/search APIs are household scoped.
- Cross-household graph entity fetches return 404.
- P7 automated tests pass.
