import {Link} from 'react-router-dom';
import {
  type ChangeEvent,
  useEffect,
  useState,
} from 'react';

import {
  archiveDocument,
  deleteDocument,
  getDocument,
  replaceDocument,
  restoreDocument,
  signedDownload,
  updateDocument,
} from './documentApi';

import { ExtractionReviewPanel } from './ExtractionReviewPanel';
import type { DocumentRecord } from './types';

export function DocumentPreviewDrawer({
  id,
  onClose,
  onChanged,
}: {
  id: number | null;
  onClose: () => void;
  onChanged: () => void;
}) {
  const [doc, setDoc] =
    useState<DocumentRecord | null>(null);

  const [title, setTitle] = useState('');
  const [issuer, setIssuer] = useState('');
  const [tags, setTags] = useState('');

  useEffect(() => {
    if (!id) {
      setDoc(null);
      return;
    }

    getDocument(id).then((document) => {
      setDoc(document);
      setTitle(document.title);
      setIssuer(document.issuer ?? '');
      setTags((document.tags ?? []).join(', '));
    });
  }, [id]);

  if (!id || !doc) {
    return null;
  }

  async function save() {
    const document = await updateDocument(doc!.id, {
      title,
      issuer,
      tags: tags
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean),
    });

    setDoc(document);
    onChanged();
  }

  async function download() {
    window.location.href =
      await signedDownload(doc!.id);
  }

  async function replace(
    event: ChangeEvent<HTMLInputElement>,
  ) {
    const file = event.target.files?.[0];

    if (!file) {
      return;
    }

    const document = await replaceDocument(
      doc!.id,
      file,
    );

    setDoc(document);
    onChanged();
  }

  return (
    <div
      className="drawer-backdrop"
      onClick={onClose}
    >
      <aside
        className="drawer drawer-wide"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="actions" style={{marginBottom:10}}><Link className="button-link secondary" to={`/tasks?document_id=${doc.id}`}>Review obligation suggestions</Link><Link className="button-link secondary" to={`/finance?document_id=${doc.id}`}>Reviewed receipt → expense</Link><Link className="button-link secondary" to={`/calendar?document_id=${doc.id}`}>Create appointment</Link></div>
        <div className="drawer-head">
          <div>
            <h2>Document details</h2>
            <p className="muted">
              Metadata, versions, intelligence and P4 obligations
            </p>
          </div>

          <button
            className="secondary"
            onClick={onClose}
          >
            Close
          </button>
        </div>

        <dl>
          <dt>File</dt>
          <dd>{doc.original_filename}</dd>

          <dt>MIME</dt>
          <dd>{doc.mime_type}</dd>

          <dt>Size</dt>
          <dd>
            {Math.round(doc.size / 1024)} KB
          </dd>

          <dt>Checksum</dt>
          <dd className="mono">
            {doc.checksum}
          </dd>

          <dt>Processing</dt>
          <dd>
            <span
              className={`badge ${doc.processing_status}`}
            >
              {doc.processing_status}
            </span>
          </dd>

          <dt>Intelligence</dt>
          <dd>
            <span
              className={`badge ${
                doc.intelligence_status ??
                'pending'
              }`}
            >
              {doc.intelligence_status ??
                'not processed'}
            </span>
          </dd>
        </dl>

        <label>
          Title
          <input
            value={title}
            onChange={(event) =>
              setTitle(event.target.value)
            }
          />
        </label>

        <label>
          Issuer
          <input
            value={issuer}
            onChange={(event) =>
              setIssuer(event.target.value)
            }
          />
        </label>

        <label>
          Tags
          <input
            value={tags}
            onChange={(event) =>
              setTags(event.target.value)
            }
            placeholder="tax, home, 2026"
          />
        </label>

        <div className="actions wrap">
          <button onClick={save}>
            Save metadata
          </button>

          <button
            className="secondary"
            onClick={download}
          >
            Download
          </button>

          <label className="button-label">
            Replace
            <input
              hidden
              type="file"
              accept=".pdf,.jpg,.jpeg,.png,.docx,.txt"
              onChange={replace}
            />
          </label>

          {doc.status === 'active' ? (
            <button
              className="secondary"
              onClick={async () => {
                await archiveDocument(doc.id);
                onChanged();
                onClose();
              }}
            >
              Archive
            </button>
          ) : (
            <button
              className="secondary"
              onClick={async () => {
                await restoreDocument(doc.id);
                onChanged();
                onClose();
              }}
            >
              Restore
            </button>
          )}

          <button
            className="danger"
            onClick={async () => {
              if (
                confirm(
                  'Move this document to trash?',
                )
              ) {
                await deleteDocument(doc.id);
                onChanged();
                onClose();
              }
            }}
          >
            Delete
          </button>
        </div>

        <h3>Version history</h3>

        <div className="versions">
          {doc.versions?.map((version) => (
            <div key={version.id}>
              <b>v{version.version_number}</b>
              {' '}
              {version.original_filename}
              {' · '}
              {Math.round(version.size / 1024)} KB
            </div>
          ))}
        </div>

        <ExtractionReviewPanel
          documentId={doc.id}
        />
      </aside>
    </div>
  );
}
