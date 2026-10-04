import { useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";
import { useNavigate } from "react-router-dom";
import { getCompletionDashboard } from "./dashboardApi";
import type { CompletionDashboard } from "./types";
// The CSS file is handled by the bundler and has no TypeScript declarations.
// @ts-expect-error TS cannot resolve side-effect CSS imports in this setup.
import "./DashboardPage.css";

type Tab = "main" | "documents" | "finance" | "ai";

function money(
  value: number | string | null | undefined,
  currency?: string | null,
) {
  if (value === null || value === undefined || value === "") return "—";
  return `${currency ?? ""} ${Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })}`.trim();
}

function Empty({ children }: { children: ReactNode }) {
  return <div className="p12-empty">{children}</div>;
}

function Evidence({ refs }: { refs?: string[] }) {
  if (!refs?.length) return null;
  return (
    <div className="p12-evidence">
      {refs.slice(0, 6).map((ref) => (
        <span key={ref}>{ref}</span>
      ))}
    </div>
  );
}

export function DashboardPage() {
  const navigate = useNavigate();
  const [data, setData] = useState<CompletionDashboard | null>(null);
  const [tab, setTab] = useState<Tab>("main");
  const [months, setMonths] = useState(6);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError("");
    getCompletionDashboard(months)
      .then((result) => active && setData(result))
      .catch(
        (err) =>
          active &&
          setError(
            err instanceof Error ? err.message : "Unable to load dashboard.",
          ),
      )
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [months]);

  const paymentTotals = useMemo(() => {
    const result = new Map<string, number>();
    for (const item of data?.main.upcoming_payments ?? []) {
      result.set(
        item.currency,
        (result.get(item.currency) ?? 0) + Number(item.amount || 0),
      );
    }
    return [...result.entries()];
  }, [data]);

  if (loading && !data)
    return (
      <div className="p12-dashboard">
        <div className="p12-loading">Loading LifePilot dashboard…</div>
      </div>
    );

  return (
    <div className="p12-dashboard">
      <section className="p12-hero">
        <div>
          <span className="p12-eyebrow">LIFEPILOT AI · P12</span>
          <h1>Your life operations center</h1>
          <p>
            One privacy-aware view across tasks, obligations, documents,
            finance, calendar, grounded AI and approved actions.
          </p>
        </div>
        <div className="p12-hero-actions">
          <select
            value={months}
            onChange={(e) => setMonths(Number(e.target.value))}
          >
            <option value={3}>3 months</option>
            <option value={6}>6 months</option>
            <option value={12}>12 months</option>
          </select>
          <button onClick={() => navigate("/assistant")}>
            ✦ Ask LifePilot AI
          </button>
        </div>
      </section>

      {error && <div className="p12-error">{error}</div>}

      <nav className="p12-tabs" aria-label="Dashboard views">
        {(["main", "documents", "finance", "ai"] as Tab[]).map((item) => (
          <button
            key={item}
            className={tab === item ? "active" : ""}
            onClick={() => setTab(item)}
          >
            {item === "main"
              ? "Main"
              : item === "documents"
                ? "Documents"
                : item === "finance"
                  ? "Finance"
                  : "AI & Actions"}
          </button>
        ))}
      </nav>

      {data && tab === "main" && (
        <>
          <section className="p12-kpis">
            <article>
              <span>Today's tasks</span>
              <strong>{data.main.today_tasks.length}</strong>
              <small>Due today</small>
            </article>
            <article>
              <span>Upcoming obligations</span>
              <strong>{data.main.upcoming_obligations.length}</strong>
              <small>Next 30 days</small>
            </article>
            <article>
              <span>Overdue items</span>
              <strong>{data.main.overdue_items.length}</strong>
              <small>Need attention</small>
            </article>
            <article>
              <span>Active subscriptions</span>
              <strong>{data.main.active_subscriptions.count}</strong>
              <small>Tracked commitments</small>
            </article>
          </section>

          <section className="p12-grid two">
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">TODAY</span>
                  <h2>Tasks</h2>
                </div>
                <button onClick={() => navigate("/tasks")}>View tasks</button>
              </header>
              {data.main.today_tasks.length ? (
                data.main.today_tasks.map((item) => (
                  <div className="p12-row" key={item.id}>
                    <div>
                      <strong>{item.title}</strong>
                      <span>
                        {item.priority} · {item.status}
                      </span>
                    </div>
                    <code>{item.source_ref}</code>
                  </div>
                ))
              ) : (
                <Empty>No tasks due today.</Empty>
              )}
            </article>
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">ATTENTION</span>
                  <h2>Overdue</h2>
                </div>
              </header>
              {data.main.overdue_items.length ? (
                data.main.overdue_items.map((item) => (
                  <div
                    className="p12-row danger"
                    key={`${item.kind}-${item.id}`}
                  >
                    <div>
                      <strong>{item.title}</strong>
                      <span>
                        {item.kind} ·{" "}
                        {item.due_at
                          ? new Date(item.due_at).toLocaleString()
                          : "No date"}
                      </span>
                    </div>
                    <code>{item.source_ref}</code>
                  </div>
                ))
              ) : (
                <Empty>No overdue items.</Empty>
              )}
            </article>
          </section>

          <section className="p12-grid three">
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">PAYMENTS</span>
                  <h2>Upcoming</h2>
                </div>
                <button onClick={() => navigate("/finance")}>Finance</button>
              </header>
              {paymentTotals.length > 0 && (
                <div className="p12-money-summary">
                  {paymentTotals.map(([currency, total]) => (
                    <strong key={currency}>{money(total, currency)}</strong>
                  ))}
                </div>
              )}
              {data.main.upcoming_payments.length ? (
                data.main.upcoming_payments.slice(0, 6).map((item) => (
                  <div className="p12-row" key={`${item.kind}-${item.id}`}>
                    <div>
                      <strong>{item.title}</strong>
                      <span>{item.due_date}</span>
                    </div>
                    <b>{money(item.amount, item.currency)}</b>
                  </div>
                ))
              ) : (
                <Empty>No upcoming payments.</Empty>
              )}
            </article>
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">WARRANTIES</span>
                  <h2>Expiring</h2>
                </div>
              </header>
              {data.main.expiring_warranties.length ? (
                data.main.expiring_warranties.map((item) => (
                  <div className="p12-row" key={item.id}>
                    <div>
                      <strong>
                        {item.asset_name ?? `Asset #${item.asset_id}`}
                      </strong>
                      <span>
                        {item.provider} · {item.end_date}
                      </span>
                    </div>
                    <code>{item.source_ref}</code>
                  </div>
                ))
              ) : (
                <Empty>No warranties expiring within 90 days.</Empty>
              )}
            </article>
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">CALENDAR</span>
                  <h2>Preview</h2>
                </div>
                <button onClick={() => navigate("/calendar")}>Open</button>
              </header>
              {data.main.calendar_preview.length ? (
                data.main.calendar_preview.slice(0, 6).map((item) => (
                  <div className="p12-row" key={item.id}>
                    <div>
                      <strong>{item.title}</strong>
                      <span>
                        {item.starts_at
                          ? new Date(item.starts_at).toLocaleString()
                          : ""}
                      </span>
                    </div>
                  </div>
                ))
              ) : (
                <Empty>No upcoming events.</Empty>
              )}
            </article>
          </section>

          <section className="p12-grid two">
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">DOCUMENTS</span>
                  <h2>Recent evidence</h2>
                </div>
                <button onClick={() => navigate("/documents")}>
                  Documents
                </button>
              </header>
              {data.main.recent_documents.length ? (
                data.main.recent_documents.map((doc) => (
                  <div className="p12-row" key={doc.id}>
                    <div>
                      <strong>{doc.title || doc.filename}</strong>
                      <span>
                        {doc.category} · {doc.processing_status}
                      </span>
                    </div>
                    <code>{doc.source_ref}</code>
                  </div>
                ))
              ) : (
                <Empty>No recent documents.</Empty>
              )}
            </article>
            <article className="p12-panel ai">
              <header>
                <div>
                  <span className="p12-kicker">EXPLAINABLE AI</span>
                  <h2>Recommendations</h2>
                </div>
                <button onClick={() => navigate("/analytics")}>
                  Analytics
                </button>
              </header>
              {data.main.ai_recommendations.length ? (
                data.main.ai_recommendations.map((item) => (
                  <div className="p12-insight" key={item.id}>
                    <strong>{item.title}</strong>
                    <p>{item.message}</p>
                    <Evidence refs={item.evidence_refs} />
                  </div>
                ))
              ) : (
                <Empty>
                  No evidence-backed recommendations currently meet reporting
                  thresholds.
                </Empty>
              )}
            </article>
          </section>
        </>
      )}

      {data && tab === "documents" && (
        <>
          <section className="p12-kpis">
            <article>
              <span>Total documents</span>
              <strong>{data.documents.total_documents}</strong>
            </article>
            <article>
              <span>Review queue</span>
              <strong>{data.documents.review_queue_count}</strong>
            </article>
            <article>
              <span>Categories</span>
              <strong>{data.documents.categories.length}</strong>
            </article>
            <article>
              <span>Processing states</span>
              <strong>{data.documents.processing_state.length}</strong>
            </article>
          </section>
          <section className="p12-grid two">
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">PIPELINE</span>
                  <h2>Processing state</h2>
                </div>
              </header>
              {data.documents.processing_state.map((item) => (
                <div className="p12-row" key={item.status}>
                  <strong>{item.status}</strong>
                  <b>{item.count}</b>
                </div>
              ))}
            </article>
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">CATEGORIES</span>
                  <h2>Document mix</h2>
                </div>
              </header>
              {data.documents.categories.map((item) => (
                <div className="p12-row" key={item.category}>
                  <strong>{item.category}</strong>
                  <b>{item.count}</b>
                </div>
              ))}
            </article>
          </section>
          <article className="p12-panel">
            <header>
              <div>
                <span className="p12-kicker">HUMAN REVIEW</span>
                <h2>Extraction-review queue</h2>
              </div>
              <button onClick={() => navigate("/documents")}>
                Open documents
              </button>
            </header>
            {data.documents.extraction_review_queue.length ? (
              data.documents.extraction_review_queue.map((item) => (
                <div className="p12-row" key={item.field_id}>
                  <div>
                    <strong>
                      {item.document_title} · {item.field_name}
                    </strong>
                    <span>
                      {item.review_status} · confidence{" "}
                      {Math.round((item.confidence || 0) * 100)}%
                    </span>
                  </div>
                  <code>{item.source_ref}</code>
                </div>
              ))
            ) : (
              <Empty>No extracted fields require review.</Empty>
            )}
          </article>
        </>
      )}

      {data && tab === "finance" && (
        <>
          <section className="p12-grid two">
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">MONTHLY EXPENSES</span>
                  <h2>Recorded spending</h2>
                </div>
              </header>
              {data.finance.monthly_expenses.length ? (
                data.finance.monthly_expenses.map((item) => (
                  <div
                    className="p12-row"
                    key={`${item.currency}-${item.month}`}
                  >
                    <span>
                      {item.month} · {item.count} records
                    </span>
                    <strong>{money(item.total, item.currency)}</strong>
                  </div>
                ))
              ) : (
                <Empty>No expense history for this period.</Empty>
              )}
            </article>
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">RECURRING</span>
                  <h2>Commitments</h2>
                </div>
              </header>
              {data.finance.recurring_commitments.length ? (
                data.finance.recurring_commitments.map((item) => (
                  <div className="p12-row" key={item.currency}>
                    <span>{item.records} records</span>
                    <strong>{money(item.recorded_total, item.currency)}</strong>
                  </div>
                ))
              ) : (
                <Empty>No recurring commitments recorded.</Empty>
              )}
            </article>
          </section>
          <section className="p12-grid two">
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">CATEGORIES</span>
                  <h2>Breakdown</h2>
                </div>
              </header>
              {data.finance.category_breakdown.slice(0, 12).map((item) => (
                <div
                  className="p12-row"
                  key={`${item.currency}-${item.category}`}
                >
                  <div>
                    <strong>{item.category}</strong>
                    <span>{item.count} records</span>
                  </div>
                  <b>{money(item.total, item.currency)}</b>
                </div>
              ))}
            </article>
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">SUBSCRIPTIONS</span>
                  <h2>Trend</h2>
                </div>
              </header>
              {data.finance.subscription_trend.map((item) => (
                <div className="p12-row" key={item.month}>
                  <span>{item.month}</span>
                  <strong>{item.active_subscriptions} active</strong>
                </div>
              ))}
            </article>
          </section>
        </>
      )}

      {data && tab === "ai" && (
        <>
          <section className="p12-kpis">
            <article>
              <span>Recommendations</span>
              <strong>{data.ai.recommendations.length}</strong>
            </article>
            <article>
              <span>Predictions</span>
              <strong>{data.ai.predictions.length}</strong>
            </article>
            <article>
              <span>Pending actions</span>
              <strong>{data.ai.pending_actions.length}</strong>
            </article>
            <article>
              <span>Recent grounded answers</span>
              <strong>{data.ai.recent_grounded_answers.length}</strong>
            </article>
          </section>
          <section className="p12-grid two">
            <article className="p12-panel ai">
              <header>
                <div>
                  <span className="p12-kicker">RECOMMENDATIONS</span>
                  <h2>Evidence-backed</h2>
                </div>
              </header>
              {data.ai.recommendations.length ? (
                data.ai.recommendations.map((item) => (
                  <div className="p12-insight" key={item.id}>
                    <strong>{item.title}</strong>
                    <p>{item.message}</p>
                    <Evidence refs={item.evidence_refs} />
                  </div>
                ))
              ) : (
                <Empty>No recommendations.</Empty>
              )}
            </article>
            <article className="p12-panel">
              <header>
                <div>
                  <span className="p12-kicker">PENDING ACTIONS</span>
                  <h2>Approval state</h2>
                </div>
                <button onClick={() => navigate("/actions")}>
                  Action Center
                </button>
              </header>
              {data.ai.action_plans.length ? (
                data.ai.action_plans.map((plan) => (
                  <div className="p12-row" key={plan.id}>
                    <div>
                      <strong>{plan.request}</strong>
                      <span>
                        {plan.overall_risk} risk · {plan.approval_state}
                      </span>
                    </div>
                    <span className={`p12-badge ${plan.approval_state}`}>
                      {plan.status}
                    </span>
                  </div>
                ))
              ) : (
                <Empty>No action plans.</Empty>
              )}
            </article>
          </section>
          <article className="p12-panel">
            <header>
              <div>
                <span className="p12-kicker">PREDICTIONS</span>
                <h2>Confidence & explanation</h2>
              </div>
              <button onClick={() => navigate("/analytics")}>
                Full analytics
              </button>
            </header>
            <div className="p12-card-grid">
              {data.ai.predictions.map((item) => (
                <div className="p12-prediction" key={item.id}>
                  <span>{item.method}</span>
                  <strong>{item.type.replaceAll("_", " ")}</strong>
                  <b>Confidence {Math.round((item.confidence || 0) * 100)}%</b>
                  <p>{item.explanation}</p>
                  <small>
                    {item.model_version} · {item.feature_version}
                  </small>
                  <Evidence refs={item.evidence_refs} />
                </div>
              ))}
            </div>
            {!data.ai.predictions.length && (
              <Empty>No prediction snapshots yet.</Empty>
            )}
          </article>
          <article className="p12-panel">
            <header>
              <div>
                <span className="p12-kicker">GROUNDED ASSISTANT</span>
                <h2>Recent answers</h2>
              </div>
            </header>
            {data.ai.recent_grounded_answers.map((item) => (
              <div className="p12-insight" key={item.id}>
                <strong>{item.grounding_status}</strong>
                <p>{item.content}</p>
                <span>
                  {item.response_latency_ms
                    ? `${item.response_latency_ms} ms`
                    : "Latency not instrumented for older message"}
                </span>
                <Evidence refs={item.citations} />
              </div>
            ))}
          </article>
        </>
      )}
    </div>
  );
}
