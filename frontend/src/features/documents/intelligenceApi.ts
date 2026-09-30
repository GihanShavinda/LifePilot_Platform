import { api, ensureCsrfCookie } from '../../services/api';
import type {
  DocumentExtractionRecord,
  ExtractedFieldRecord,
  ExtractionVersionSummary,
} from './intelligenceTypes';

export async function getDocumentIntelligence(
  documentId: number,
): Promise<DocumentExtractionRecord | null> {
  const response = await api.get(
    `/api/v1/documents/${documentId}/intelligence`,
  );

  return response.data.data.extraction as DocumentExtractionRecord | null;
}

export async function listExtractionVersions(
  documentId: number,
): Promise<ExtractionVersionSummary[]> {
  const response = await api.get(
    `/api/v1/documents/${documentId}/intelligence/versions`,
  );

  return response.data.data.versions as ExtractionVersionSummary[];
}

export async function reprocessDocumentIntelligence(
  documentId: number,
): Promise<void> {
  await ensureCsrfCookie();

  await api.post(
    `/api/v1/documents/${documentId}/intelligence/reprocess`,
  );
}

export async function reviewExtractedField(
  documentId: number,
  fieldId: number,
  decision: 'accept' | 'reject' | 'edit',
  value?: unknown,
  note?: string,
): Promise<ExtractedFieldRecord> {
  await ensureCsrfCookie();

  const response = await api.put(
    `/api/v1/documents/${documentId}/intelligence/fields/${fieldId}/review`,
    {
      decision,
      value,
      note,
    },
  );

  return response.data.data.field as ExtractedFieldRecord;
}
