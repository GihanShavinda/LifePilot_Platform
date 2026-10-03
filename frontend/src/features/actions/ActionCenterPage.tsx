import { useEffect, useMemo, useState } from "react";
import { actionsApi } from "./actionsApi";
import type { ActionPlan } from "./types";
// The CSS file is handled by the bundler and has no TypeScript declarations.
// @ts-expect-error TS cannot resolve side-effect CSS imports in this setup.
import "./ActionCenterPage.css";

const demoTemplates = [
  {
    label: "Create task",
    request: "Create a task I can review before execution.",
    action_type: "create_task",
    payload: { title: "Review LifePilot item", priority: "medium" },
  },
  {
    label: "Create internal event",
    request: "Create an internal calendar event after I approve it.",
    action_type: "create_calendar_event",
    payload: {
      title: "LifePilot review",
      starts_at: new Date(Date.now() + 86400000).toISOString(),
      ends_at: new Date(Date.now() + 90000000).toISOString(),
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC",
    },
  },
  {
    label: "Prepare email draft",
    request: "Prepare an email draft. Do not send anything.",
    action_type: "prepare_email_draft",
    payload: {
      subject: "LifePilot draft",
      body: "This is a draft only. Review before sending outside LifePilot.",
    },
  },
];

function riskLabel(risk: string) {
  return risk === "high" ? "High risk" : risk === "medium" ? "Medium risk" : "Low risk";
}

export function ActionCenterPage() {
  const [plans, setPlans] = useState<ActionPlan[]>([]);
  const [selected, setSelected] = useState<ActionPlan | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [highAck, setHighAck] = useState(false);

  const selectedApprovedIds = useMemo(
    () => selected?.steps.filter((s) => s.status !== "rejected").map((s) => s.id) ?? [],
    [selected]
  );

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const response = await actionsApi.list();
      const rows = response.data ?? response;
      setPlans(rows);
      if (!selected && rows.length) setSelected(rows[0]);
    } catch (e: any) {
      setError(e?.response?.data?.message ?? "Unable to load action plans.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  async function createTemplate(template: (typeof demoTemplates)[number]) {
    setBusy(true);
    setError(null);
    try {
      const plan = await actionsApi.create({
        request: template.request,
        origin: "user",
        steps: [
          {
            action_type: template.action_type,
            title: template.label,
            payload: template.payload,
          },
        ],
      });
      setPlans((current) => [plan, ...current.filter((p) => p.id !== plan.id)]);
      setSelected(plan);
      setHighAck(false);
    } catch (e: any) {
      setError(e?.response?.data?.message ?? "Could not create action preview.");
    } finally {
      setBusy(false);
    }
  }

  async function approve() {
    if (!selected) return;
    setBusy(true);
    setError(null);
    try {
      const plan = await actionsApi.approve(
        selected.id,
        selectedApprovedIds,
        highAck,
        "Reviewed in Action Center"
      );
      setSelected(plan);
      setPlans((items) => items.map((p) => (p.id === plan.id ? plan : p)));
    } catch (e: any) {
      setError(e?.response?.data?.message ?? "Approval failed.");
    } finally {
      setBusy(false);
    }
  }

  async function execute() {
    if (!selected) return;
    setBusy(true);
    setError(null);
    try {
      const plan = await actionsApi.execute(selected.id);
      setSelected(plan);
      setPlans((items) => items.map((p) => (p.id === plan.id ? plan : p)));
    } catch (e: any) {
      setError(e?.response?.data?.message ?? "Execution failed.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="action-center-page">
      <div className="action-center-header">
        <div>
          <span className="action-center-eyebrow">P9 SAFE AGENTIC WORKFLOWS</span>
          <h1>Action Center</h1>
          <p>
            LifePilot can propose actions, but execution stays behind policy checks,
            evidence checks, risk review and your approval.
          </p>
        </div>
        <div className="action-center-safety-badge">Approval-first execution</div>
      </div>

      {error && <div className="action-center-error">{error}</div>}

      <div className="action-template-row">
        {demoTemplates.map((template) => (
          <button key={template.label} disabled={busy} onClick={() => void createTemplate(template)}>
            <span>＋</span>
            {template.label}
          </button>
        ))}
      </div>

      <div className="action-center-layout">
        <aside className="action-plan-list-card">
          <div className="action-card-heading">
            <div>
              <span>PLANS</span>
              <h2>Recent previews</h2>
            </div>
            <button onClick={() => void load()}>↻</button>
          </div>

          {loading ? (
            <div className="action-empty">Loading action plans…</div>
          ) : plans.length === 0 ? (
            <div className="action-empty">Create a preview to begin.</div>
          ) : (
            <div className="action-plan-list">
              {plans.map((plan) => (
                <button
                  key={plan.id}
                  className={selected?.id === plan.id ? "active" : ""}
                  onClick={() => {
                    setSelected(plan);
                    setHighAck(false);
                  }}
                >
                  <div className="action-plan-list-top">
                    <strong>Plan #{plan.id}</strong>
                    <span className={`risk ${plan.overall_risk}`}>{riskLabel(plan.overall_risk)}</span>
                  </div>
                  <p>{plan.request}</p>
                  <small>{plan.status.replaceAll("_", " ")}</small>
                </button>
              ))}
            </div>
          )}
        </aside>

        <section className="action-plan-detail-card">
          {!selected ? (
            <div className="action-empty large">
              <strong>No action plan selected</strong>
              <span>Choose a plan or create a safe preview.</span>
            </div>
          ) : (
            <>
              <div className="action-detail-header">
                <div>
                  <span className="action-center-eyebrow">PREVIEW</span>
                  <h2>{selected.request}</h2>
                </div>
                <div className={`action-risk-pill ${selected.overall_risk}`}>
                  {riskLabel(selected.overall_risk)}
                </div>
              </div>

              <div className="action-policy-strip">
                <div><strong>1</strong><span>Plan</span></div>
                <div><strong>2</strong><span>Validate</span></div>
                <div><strong>3</strong><span>Approve</span></div>
                <div><strong>4</strong><span>Execute</span></div>
                <div><strong>5</strong><span>Verify</span></div>
                <div><strong>6</strong><span>Audit</span></div>
              </div>

              <div className="action-step-list">
                {selected.steps.map((step) => (
                  <article key={step.id} className="action-step-card">
                    <div className="action-step-number">{step.sequence}</div>
                    <div className="action-step-content">
                      <div className="action-step-top">
                        <div>
                          <h3>{step.title}</h3>
                          <code>{step.action_type}</code>
                        </div>
                        <span className={`risk ${step.risk_level}`}>{riskLabel(step.risk_level)}</span>
                      </div>

                      <p>{String(step.preview?.effect ?? step.description ?? "Proposed LifePilot action")}</p>

                      <div className="action-step-meta">
                        <span>Status: {step.status}</span>
                        <span>{step.requires_approval ? "Approval required" : "No approval required"}</span>
                        {step.external_effect && <span>External-effect boundary</span>}
                      </div>

                      {step.evidence_refs?.length ? (
                        <div className="action-evidence-row">
                          {step.evidence_refs.map((ref) => <span key={ref}>{ref}</span>)}
                        </div>
                      ) : (
                        <div className="action-no-evidence">Direct user instruction — no assistant evidence asserted.</div>
                      )}
                    </div>
                  </article>
                ))}
              </div>

              {selected.overall_risk === "high" &&
                ["preview", "approved", "partially_approved"].includes(selected.status) && (
                  <label className="high-risk-confirmation">
                    <input
                      type="checkbox"
                      checked={highAck}
                      onChange={(e) => setHighAck(e.target.checked)}
                    />
                    <span>
                      I explicitly reviewed the high-risk step. I understand LifePilot P9 only prepares supported
                      integration requests and does not perform financial transactions.
                    </span>
                  </label>
                )}

              <div className="action-detail-footer">
                <div className="action-status-summary">
                  <span>Plan status</span>
                  <strong>{selected.status.replaceAll("_", " ")}</strong>
                </div>

                <div className="action-footer-buttons">
                  {selected.status === "preview" && (
                    <button className="action-approve-button" disabled={busy} onClick={() => void approve()}>
                      Approve selected steps
                    </button>
                  )}

                  {["approved", "partially_approved"].includes(selected.status) && (
                    <button className="action-execute-button" disabled={busy} onClick={() => void execute()}>
                      Execute approved actions
                    </button>
                  )}
                </div>
              </div>
            </>
          )}
        </section>
      </div>
    </div>
  );
}
