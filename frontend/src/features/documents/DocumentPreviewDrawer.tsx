import { Link } from "react-router-dom";
import {
  type ChangeEvent,
  useEffect,
  useState,
} from "react";

import {
  archiveDocument,
  deleteDocument,
  getDocument,
  replaceDocument,
  restoreDocument,
  signedDownload,
  updateDocument,
} from "./documentApi";

import { ExtractionReviewPanel } from "./ExtractionReviewPanel";
import type { DocumentRecord } from "./types";

// The CSS file is handled by the bundler and has no TypeScript declarations.
// @ts-expect-error TS cannot resolve side-effect CSS imports in this setup.
import "./DocumentPreviewDrawer.css";

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

  const [title, setTitle] = useState("");
  const [issuer, setIssuer] = useState("");
  const [tags, setTags] = useState("");

  useEffect(() => {
    if (!id) {
      setDoc(null);
      return;
    }

    getDocument(id).then((document) => {
      setDoc(document);
      setTitle(document.title);
      setIssuer(document.issuer ?? "");
      setTags((document.tags ?? []).join(", "));
    });
  }, [id]);

  useEffect(() => {
    if (!id) {
      return;
    }

    const previousOverflow =
      document.body.style.overflow;

    document.body.style.overflow = "hidden";

    return () => {
      document.body.style.overflow =
        previousOverflow;
    };
  }, [id]);

  if (!id || !doc) {
    return null;
  }

  async function save() {
    const document = await updateDocument(
      doc!.id,
      {
        title,
        issuer,
        tags: tags
          .split(",")
          .map((tag) => tag.trim())
          .filter(Boolean),
      },
    );

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

    event.target.value = "";
  }

  return (
    <div
      className="document-preview-backdrop"
      onClick={onClose}
      role="presentation"
    >
      <aside
        className="document-preview-drawer"
        onClick={(event) =>
          event.stopPropagation()
        }
        role="dialog"
        aria-modal="true"
        aria-label="Document details"
      >
        <div className="document-preview-header">
          <div className="document-preview-header-copy">
            <span className="document-preview-eyebrow">
              DOCUMENT
            </span>

            <h2>
              {doc.title || "Document details"}
            </h2>

            <p>
              Metadata, versions, intelligence
              and obligations
            </p>
          </div>

          <button
            type="button"
            className="document-preview-close"
            onClick={onClose}
          >
            Close
          </button>
        </div>

        <div className="document-preview-toolbar">
          <Link
            className="document-preview-toolbar-link"
            to={`/tasks?document_id=${doc.id}`}
          >
            Review obligation suggestions
          </Link>

          <Link
            className="document-preview-toolbar-link"
            to={`/finance?document_id=${doc.id}`}
          >
            Reviewed receipt → expense
          </Link>

          <Link
            className="document-preview-toolbar-link"
            to={`/calendar?document_id=${doc.id}`}
          >
            Create appointment
          </Link>
        </div>

        <div className="document-preview-content">
          <section className="document-preview-section">
            <div className="document-preview-section-heading">
              <div>
                <span className="document-preview-eyebrow">
                  DOCUMENT DETAILS
                </span>

                <h3>File information</h3>
              </div>
            </div>

            <dl className="document-preview-details">
              <dt>File</dt>
              <dd>
                {doc.original_filename}
              </dd>

              <dt>MIME</dt>
              <dd>
                {doc.mime_type}
              </dd>

              <dt>Size</dt>
              <dd>
                {Math.round(
                  doc.size / 1024,
                )}{" "}
                KB
              </dd>

              <dt>Checksum</dt>
              <dd className="document-preview-mono">
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
                    "pending"
                  }`}
                >
                  {doc.intelligence_status ??
                    "not processed"}
                </span>
              </dd>
            </dl>
          </section>

          <section className="document-preview-section">
            <div className="document-preview-section-heading">
              <div>
                <span className="document-preview-eyebrow">
                  METADATA
                </span>

                <h3>Edit metadata</h3>
              </div>
            </div>

            <div className="document-preview-form">
              <label>
                <span>Title</span>

                <input
                  value={title}
                  onChange={(event) =>
                    setTitle(
                      event.target.value,
                    )
                  }
                />
              </label>

              <label>
                <span>Issuer</span>

                <input
                  value={issuer}
                  onChange={(event) =>
                    setIssuer(
                      event.target.value,
                    )
                  }
                />
              </label>

              <label>
                <span>Tags</span>

                <input
                  value={tags}
                  onChange={(event) =>
                    setTags(
                      event.target.value,
                    )
                  }
                  placeholder="tax, home, 2026"
                />
              </label>
            </div>

            <div className="document-preview-actions">
              <button
                type="button"
                className="document-preview-primary-button"
                onClick={save}
              >
                Save metadata
              </button>

              <button
                type="button"
                className="document-preview-secondary-button"
                onClick={download}
              >
                Download
              </button>

              <label className="document-preview-file-button">
                Replace

                <input
                  hidden
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png,.docx,.txt"
                  onChange={replace}
                />
              </label>

              {doc.status === "active" ? (
                <button
                  type="button"
                  className="document-preview-secondary-button"
                  onClick={async () => {
                    await archiveDocument(
                      doc.id,
                    );

                    onChanged();
                    onClose();
                  }}
                >
                  Archive
                </button>
              ) : (
                <button
                  type="button"
                  className="document-preview-secondary-button"
                  onClick={async () => {
                    await restoreDocument(
                      doc.id,
                    );

                    onChanged();
                    onClose();
                  }}
                >
                  Restore
                </button>
              )}

              <button
                type="button"
                className="document-preview-danger-button"
                onClick={async () => {
                  if (
                    confirm(
                      "Move this document to trash?",
                    )
                  ) {
                    await deleteDocument(
                      doc.id,
                    );

                    onChanged();
                    onClose();
                  }
                }}
              >
                Delete
              </button>
            </div>
          </section>

          <section className="document-preview-section">
            <div className="document-preview-section-heading">
              <div>
                <span className="document-preview-eyebrow">
                  HISTORY
                </span>

                <h3>Version history</h3>
              </div>

              <span className="document-preview-count">
                {doc.versions?.length ?? 0}
              </span>
            </div>

            <div className="document-preview-versions">
              {doc.versions?.length ? (
                doc.versions.map(
                  (version) => (
                    <div
                      className="document-preview-version"
                      key={version.id}
                    >
                      <div>
                        <strong>
                          v
                          {
                            version.version_number
                          }
                        </strong>

                        <span>
                          {
                            version.original_filename
                          }
                        </span>
                      </div>

                      <small>
                        {Math.round(
                          version.size /
                            1024,
                        )}{" "}
                        KB
                      </small>
                    </div>
                  ),
                )
              ) : (
                <div className="document-preview-empty">
                  No version history.
                </div>
              )}
            </div>
          </section>

          <section className="document-preview-section document-preview-intelligence">
            <div className="document-preview-section-heading">
              <div>
                <span className="document-preview-eyebrow">
                  INTELLIGENCE
                </span>

                <h3>
                  Extraction review
                </h3>
              </div>
            </div>

            <ExtractionReviewPanel
              documentId={doc.id}
            />
          </section>
        </div>
      </aside>
    </div>
  );
}