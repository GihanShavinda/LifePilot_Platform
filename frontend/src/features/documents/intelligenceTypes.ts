export type ReviewStatus =
  | 'pending'
  | 'needs_review'
  | 'accepted'
  | 'rejected'
  | 'edited';

export type ExtractionEvidence = {
  id: number;
  page: number | null;
  evidence_text: string;
  source: string;
};

export type ExtractedFieldRecord = {
  id: number;
  field_name: string;
  value: unknown;
  normalized_value: unknown;
  confidence: number;
  page: number | null;
  evidence_text: string;
  extractor_version: string;
  model_version: string | null;
  review_status: ReviewStatus;
  source: 'deterministic' | 'llm' | string;
  evidence: ExtractionEvidence[];
};

export type DocumentExtractionRecord = {
  id: number;
  version_number: number;
  status:
    | 'queued'
    | 'processing'
    | 'needs_review'
    | 'completed'
    | 'failed';
  document_type: string | null;
  overall_confidence: number | null;
  extractor_version: string;
  model_version: string | null;
  validation_errors: string[];
  raw_payload?: {
    text_method?: string;
    prompt_injection_signals?: string[];
    rejected_ungrounded_fields?: unknown[];
  } | null;
  fields: ExtractedFieldRecord[];
  created_at: string | null;
  finished_at: string | null;
};

export type ExtractionVersionSummary = {
  id: number;
  version_number: number;
  status: string;
  document_type: string | null;
  overall_confidence: number | null;
  extractor_version: string;
  model_version: string | null;
  created_at: string | null;
};
