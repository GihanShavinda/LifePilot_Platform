# P2 — Document Management Foundation

P2 adds a private household document vault without AI/OCR. Files are validated, checksummed with SHA-256, stored behind a Laravel filesystem abstraction, versioned on replacement, and never exposed through a public storage URL.

## Domain
`Document`, `DocumentVersion`, `DocumentCategory`, `DocumentTag`, `DocumentSource`, and `DocumentProcessingJob` live under `app/Domain/Documents`.

## Storage
Development defaults to a private local disk (`storage/app/private/documents`). Set `DOCUMENTS_DRIVER=s3` plus the AWS variables for S3 or MinIO. Signed access uses the storage provider's temporary URL when available, otherwise a short-lived signed Laravel route.

## Processing
Uploads and replacements create `DocumentProcessingJob` rows and dispatch `ProcessDocumentJob`. P2 performs deterministic baseline processing only. OCR, extraction, embeddings, and RAG remain P3+ work.

## Security
Household membership is checked for every object access. Viewer is read-only. Owner and Family Member can modify documents. Files are MIME-verified, size-limited, filenames sanitized, stored privately, and audited for upload/download/update/archive/delete.

## Malware boundary
`MalwareScanner` is an adapter contract. P2 binds `NullMalwareScanner`, which is intentionally a no-op integration boundary. Replace it with a ClamAV or managed scanner implementation without changing the document workflow.
