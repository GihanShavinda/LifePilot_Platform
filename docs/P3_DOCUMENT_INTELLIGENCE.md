# LifePilot AI — P3 Document Intelligence

P3 adds a reviewable, evidence-grounded document intelligence pipeline on top of the P2 secure document store.

## Pipeline

Document
→ preprocessing
→ native text extraction
→ OCR fallback
→ deterministic parsing
→ optional LLM structured extraction
→ JSON validation
→ evidence grounding
→ confidence scoring
→ human review
→ persistence

## Grounding rule

Uploaded document text is always treated as untrusted data.

The LLM gateway receives a fixed system instruction stating that document text cannot redefine system behavior. Every LLM candidate must contain evidence text copied from the source. Candidates whose evidence does not occur in the extracted source text are rejected before persistence.

## Native extraction

- TXT: direct text read
- DOCX: XML text extracted from `word/document.xml`
- PDF: `pdftotext`

## OCR fallback

OCR runs only when native text is missing or too weak.

- JPG/PNG: Tesseract
- PDF: `pdftoppm` renders pages, then Tesseract processes each page

On Windows, install Poppler and Tesseract and either add the binaries to PATH or set:

```env
PDFTOTEXT_BINARY=C:\path\to\pdftotext.exe
PDFTOPPM_BINARY=C:\path\to\pdftoppm.exe
TESSERACT_BINARY=C:\path\to\tesseract.exe
```

## Optional LLM gateway

P3 does not hard-code a model vendor. Configure an extraction gateway with:

```env
DOCUMENT_AI_LLM_ENDPOINT=
DOCUMENT_AI_LLM_TOKEN=
DOCUMENT_AI_LLM_MODEL_VERSION=
```

The gateway receives:

```json
{
  "system_instruction": "...",
  "document_text": "...",
  "json_schema": {},
  "temperature": 0
}
```

It must return:

```json
{
  "fields": [
    {
      "field_name": "issuer",
      "value": "Example Utility",
      "normalized_value": "Example Utility",
      "confidence": 0.93,
      "page": 1,
      "evidence_text": "Example Utility"
    }
  ],
  "model_version": "provider/model-version"
}
```

If no endpoint is configured, deterministic extraction still runs and the LLM stage is skipped safely.

## Human review

Users can accept, reject or edit each extracted field. Every decision is stored in `extraction_reviews`. Reprocessing creates a new `DocumentExtraction` version instead of overwriting previous results.

## Queue

Keep the queue worker running:

```powershell
php83 artisan queue:work --tries=3 --timeout=180
```

## P3 tests

```powershell
php83 artisan test --filter=DocumentIntelligencePipelineTest
php83 artisan test --filter=DocumentExtractionReviewTest
```
