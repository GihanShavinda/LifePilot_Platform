import { useEffect, useMemo, useState } from 'react';
import {
  getDocumentIntelligence,
  listExtractionVersions,
  reprocessDocumentIntelligence,
  reviewExtractedField,
} from './intelligenceApi';
import type {
  DocumentExtractionRecord,
  ExtractedFieldRecord,
  ExtractionVersionSummary,
} from './intelligenceTypes';

function printable(value: unknown): string {
  if (value === null || value === undefined) {
    return '';
  }

  if (typeof value === 'string' || typeof value === 'number') {
    return String(value);
  }

  return JSON.stringify(value, null, 2);
}

function confidenceLabel(value: number): string {
  return `${Math.round(value * 100)}%`;
}

export function ExtractionReviewPanel({
  documentId,
}: {
  documentId: number;
}) {
  const [extraction, setExtraction] =
    useState<DocumentExtractionRecord | null>(null);

  const [versions, setVersions] =
    useState<ExtractionVersionSummary[]>([]);

  const [loading, setLoading] = useState(true);
  const [workingField, setWorkingField] =
    useState<number | null>(null);

  const [message, setMessage] = useState('');

  async function load() {
    setLoading(true);

    try {
      const [current, history] = await Promise.all([
        getDocumentIntelligence(documentId),
        listExtractionVersions(documentId),
      ]);

      setExtraction(current);
      setVersions(history);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, [documentId]);

  const pendingCount = useMemo(
    () =>
      extraction?.fields.filter(
        (field) =>
          field.review_status === 'pending' ||
          field.review_status === 'needs_review',
      ).length ?? 0,
    [extraction],
  );

  async function decide(
    field: ExtractedFieldRecord,
    decision: 'accept' | 'reject' | 'edit',
  ) {
    let value: unknown = undefined;

    if (decision === 'edit') {
      const next = window.prompt(
        `Edit ${field.field_name}`,
        printable(field.value),
      );

      if (next === null) {
        return;
      }

      value = next;
    }

    setWorkingField(field.id);
    setMessage('');

    try {
      await reviewExtractedField(
        documentId,
        field.id,
        decision,
        value,
      );

      setMessage('Extraction review saved.');
      await load();
    } catch (error: any) {
      setMessage(
        error?.response?.data?.error?.message ??
          'Unable to save the extraction review.',
      );
    } finally {
      setWorkingField(null);
    }
  }

  async function reprocess() {
    setMessage('');

    try {
      await reprocessDocumentIntelligence(documentId);

      setMessage(
        'Reprocessing queued. Keep the queue worker running and refresh in a moment.',
      );

      window.setTimeout(() => {
        void load();
      }, 1500);
    } catch (error: any) {
      setMessage(
        error?.response?.data?.error?.message ??
          'Unable to queue document reprocessing.',
      );
    }
  }

  if (loading) {
    return (
      <section className="intelligence-panel">
        <h3>Document intelligence</h3>
        <p className="muted">Loading extraction...</p>
      </section>
    );
  }

  if (!extraction) {
    return (
      <section className="intelligence-panel">
        <div className="intelligence-heading">
          <div>
            <h3>Document intelligence</h3>
            <p className="muted">
              No extraction has been persisted yet.
            </p>
          </div>

          <button onClick={reprocess}>
            Run extraction
          </button>
        </div>

        {message && (
          <div className="success">{message}</div>
        )}
      </section>
    );
  }

  return (
    <section className="intelligence-panel">
      <div className="intelligence-heading">
        <div>
          <h3>Document intelligence</h3>

          <p className="muted">
            Extraction v{extraction.version_number}
            {' · '}
            {extraction.document_type ?? 'unclassified'}
            {' · '}
            {extraction.overall_confidence === null
              ? 'confidence unavailable'
              : `${confidenceLabel(
                  extraction.overall_confidence,
                )} overall confidence`}
          </p>
        </div>

        <button
          className="secondary"
          onClick={reprocess}
        >
          Reprocess
        </button>
      </div>

      <div className="intelligence-summary">
        <span
          className={`badge ${extraction.status}`}
        >
          {extraction.status}
        </span>

        <span>
          {pendingCount} field
          {pendingCount === 1 ? '' : 's'} awaiting review
        </span>

        <span>
          Extractor: {extraction.extractor_version}
        </span>

        {extraction.model_version && (
          <span>
            Model: {extraction.model_version}
          </span>
        )}
      </div>

      {message && (
        <div className="success">{message}</div>
      )}

      {(extraction.raw_payload
        ?.prompt_injection_signals?.length ?? 0) > 0 && (
        <div className="security-note">
          Potential prompt-injection-like text was detected
          inside the document. It was treated only as
          untrusted document data.
        </div>
      )}

      {extraction.validation_errors.length > 0 && (
        <div className="error">
          Structured extraction validation reported:
          {' '}
          {extraction.validation_errors.join(' | ')}
        </div>
      )}

      <div className="field-review-list">
        {extraction.fields.length === 0 ? (
          <div className="empty">
            No supported fields were found in this
            document.
          </div>
        ) : (
          extraction.fields.map((field) => (
            <article
              key={field.id}
              className="field-review-card"
            >
              <div className="field-review-head">
                <div>
                  <strong>
                    {field.field_name.replaceAll('_', ' ')}
                  </strong>

                  <div className="field-meta">
                    {field.source}
                    {' · '}
                    {confidenceLabel(field.confidence)}
                    {field.page
                      ? ` · page ${field.page}`
                      : ''}
                  </div>
                </div>

                <span
                  className={`badge ${field.review_status}`}
                >
                  {field.review_status}
                </span>
              </div>

              <div className="field-value">
                {printable(
                  field.normalized_value ??
                    field.value,
                )}
              </div>

              <blockquote className="evidence">
                {field.evidence_text}
              </blockquote>

              <div className="actions wrap">
                <button
                  disabled={
                    workingField === field.id
                  }
                  onClick={() =>
                    void decide(field, 'accept')
                  }
                >
                  Accept
                </button>

                <button
                  className="secondary"
                  disabled={
                    workingField === field.id
                  }
                  onClick={() =>
                    void decide(field, 'edit')
                  }
                >
                  Edit
                </button>

                <button
                  className="danger"
                  disabled={
                    workingField === field.id
                  }
                  onClick={() =>
                    void decide(field, 'reject')
                  }
                >
                  Reject
                </button>
              </div>
            </article>
          ))
        )}
      </div>

      <details className="extraction-history">
        <summary>
          Previous extraction versions ({versions.length})
        </summary>

        <div className="versions">
          {versions.map((version) => (
            <div key={version.id}>
              <b>Extraction v{version.version_number}</b>
              {' · '}
              {version.status}
              {' · '}
              {version.document_type ?? 'unclassified'}
              {version.overall_confidence !== null
                ? ` · ${confidenceLabel(
                    version.overall_confidence,
                  )}`
                : ''}
            </div>
          ))}
        </div>
      </details>
    </section>
  );
}
