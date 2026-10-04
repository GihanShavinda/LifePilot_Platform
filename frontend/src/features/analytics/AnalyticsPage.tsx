import { useEffect, useMemo, useState } from "react";
import { getAnalyticsDashboard, refreshAnalytics } from "./analyticsApi";
import type { AnalyticsDashboard, PredictionRecord } from "./types";
// The CSS file is handled by the bundler and has no TypeScript declarations.
// @ts-expect-error TS cannot resolve side-effect CSS imports in this setup.
import "./AnalyticsPage.css";

function formatMoney(value: number, currency: string) {
  return `${currency} ${Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
}

function predictionLabel(type: string) {
  return type
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function confidenceText(value: number) {
  return `${Math.round((value || 0) * 100)}%`;
}

function Evidence({ refs }: { refs?: string[] }) {
  if (!refs?.length) return null;
  return (
    <div className="analytics-evidence">
      {refs.slice(0, 8).map((ref) => (
        <span key={ref}>{ref}</span>
      ))}
      {refs.length > 8 && <span>+{refs.length - 8} more</span>}
    </div>
  );
}

function MiniBars({ values }: { values: number[] }) {
  const max = Math.max(1, ...values);
  return (
    <div className="analytics-mini-bars" aria-hidden="true">
      {values.map((value, index) => (
        <span key={index} style={{ height: `${Math.max(6, (value / max) * 100)}%` }} />
      ))}
    </div>
  );
}

function PredictionCard({ prediction }: { prediction: PredictionRecord }) {
  const status = String(prediction.prediction?.status ?? "ok");
  return (
    <article className="analytics-prediction-card">
      <div className="analytics-card-row">
        <div>
          <span className="analytics-kicker">{prediction.method}</span>
          <h3>{predictionLabel(prediction.prediction_type)}</h3>
        </div>
        <span className={`analytics-status ${status}`}>{status.replaceAll("_", " ")}</span>
      </div>
      <p>{prediction.explanation}</p>
      <div className="analytics-model-meta">
        <span>Confidence {confidenceText(prediction.confidence)}</span>
        <span>{prediction.model_version}</span>
        <span>{prediction.feature_version}</span>
      </div>
      <Evidence refs={prediction.evidence_refs} />
    </article>
  );
}

export function AnalyticsPage() {
  const [data, setData] = useState<AnalyticsDashboard | null>(null);
  const [months, setMonths] = useState(6);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState("");

  const load = async (windowMonths = months) => {
    setLoading(true);
    setError("");
    try {
      setData(await getAnalyticsDashboard(windowMonths));
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to load analytics.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load(months);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [months]);

  const refresh = async () => {
    setRefreshing(true);
    setError("");
    try {
      await refreshAnalytics();
      await load(months);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to refresh predictions.");
    } finally {
      setRefreshing(false);
    }
  };

  const currencies = useMemo(
    () => [...new Set(data?.analytics.spending_trend.map((point) => point.currency) ?? [])],
    [data],
  );

  if (loading && !data) {
    return <div className="analytics-page"><div className="analytics-loading">Loading analytics…</div></div>;
  }

  return (
    <div className="analytics-page">
      <section className="analytics-hero">
        <div>
          <span className="analytics-eyebrow">P11 · EXPLAINABLE ANALYTICS</span>
          <h1>Analytics & Predictions</h1>
          <p>
            Interpretable trends, deterministic recommendations and lightweight forecasts grounded only in records you can access.
          </p>
        </div>
        <div className="analytics-actions">
          <select value={months} onChange={(event) => setMonths(Number(event.target.value))}>
            <option value={3}>3 months</option>
            <option value={6}>6 months</option>
            <option value={12}>12 months</option>
          </select>
          <button type="button" onClick={() => void refresh()} disabled={refreshing}>
            {refreshing ? "Refreshing…" : "Refresh predictions"}
          </button>
        </div>
      </section>

      {error && <div className="analytics-error">{error}</div>}

      {data && (
        <>
          <section className="analytics-summary-grid">
            <article className="analytics-summary-card">
              <span>Task completion</span>
              <strong>{data.analytics.task_completion.rate === null ? "—" : `${data.analytics.task_completion.rate}%`}</strong>
              <small>{data.analytics.task_completion.completed} of {data.analytics.task_completion.total} tasks completed</small>
            </article>
            <article className="analytics-summary-card">
              <span>Upcoming obligations</span>
              <strong>{data.analytics.upcoming_obligations.length}</strong>
              <small>Due within the next 30 days</small>
            </article>
            <article className="analytics-summary-card">
              <span>Warranty expirations</span>
              <strong>{data.analytics.warranty_expirations.length}</strong>
              <small>Within the next 90 days</small>
            </article>
            <article className="analytics-summary-card">
              <span>Data quality warnings</span>
              <strong>{data.data_quality.filter((check) => check.status === "warning").length}</strong>
              <small>Checks that deserve review</small>
            </article>
          </section>

          <section className="analytics-grid analytics-two-column">
            <article className="analytics-panel">
              <div className="analytics-panel-header">
                <div><span className="analytics-kicker">SPENDING</span><h2>Spending trend</h2></div>
                <span>{data.analytics.period.from} → {data.analytics.period.to}</span>
              </div>
              {currencies.length === 0 ? (
                <div className="analytics-empty">No accessible expense history in this period.</div>
              ) : currencies.map((currency) => {
                const points = data.analytics.spending_trend.filter((point) => point.currency === currency);
                return (
                  <div className="analytics-series" key={currency}>
                    <div className="analytics-series-head">
                      <strong>{currency}</strong>
                      <span>{formatMoney(points.reduce((sum, point) => sum + Number(point.total), 0), currency)} total</span>
                    </div>
                    <MiniBars values={points.map((point) => Number(point.total))} />
                    <div className="analytics-month-labels">{points.map((point) => <span key={point.month}>{point.month.slice(5)}</span>)}</div>
                  </div>
                );
              })}
            </article>

            <article className="analytics-panel">
              <div className="analytics-panel-header">
                <div><span className="analytics-kicker">RECURRING COST</span><h2>Subscription commitments</h2></div>
              </div>
              {data.analytics.subscription_cost.length === 0 ? (
                <div className="analytics-empty">No active subscription commitments.</div>
              ) : (
                <div className="analytics-list">
                  {data.analytics.subscription_cost.map((item) => (
                    <div className="analytics-list-row" key={item.currency}>
                      <div><strong>{item.currency}</strong><span>{item.active_subscriptions} active subscriptions</span></div>
                      <b>{formatMoney(item.monthly_equivalent, item.currency)} / month</b>
                    </div>
                  ))}
                </div>
              )}
            </article>
          </section>

          <section className="analytics-panel analytics-insights-panel">
            <div className="analytics-panel-header">
              <div><span className="analytics-kicker">EXPLAINABLE INSIGHTS</span><h2>What changed and why</h2></div>
              <span>Every insight requires evidence</span>
            </div>
            {data.insights.length === 0 ? (
              <div className="analytics-empty">No evidence-supported insight currently crosses the reporting thresholds.</div>
            ) : (
              <div className="analytics-insight-grid">
                {data.insights.map((insight) => (
                  <article className={`analytics-insight ${insight.severity}`} key={insight.id}>
                    <span className="analytics-kicker">{insight.type.replaceAll("_", " ")}</span>
                    <h3>{insight.title}</h3>
                    <p>{insight.message}</p>
                    <Evidence refs={insight.evidence_refs} />
                  </article>
                ))}
              </div>
            )}
          </section>

          <section className="analytics-panel">
            <div className="analytics-panel-header">
              <div><span className="analytics-kicker">PREDICTIONS</span><h2>Versioned lightweight predictions</h2></div>
              <span>{data.predictions.length} prediction types</span>
            </div>
            {data.predictions.length === 0 ? (
              <div className="analytics-empty">
                No prediction snapshots yet. Click <strong>Refresh predictions</strong> to generate a safe, explainable snapshot.
              </div>
            ) : (
              <div className="analytics-prediction-grid">
                {data.predictions.map((prediction) => <PredictionCard key={prediction.id} prediction={prediction} />)}
              </div>
            )}
          </section>

          <section className="analytics-grid analytics-two-column">
            <article className="analytics-panel">
              <div className="analytics-panel-header"><div><span className="analytics-kicker">UPCOMING</span><h2>Obligations</h2></div></div>
              <div className="analytics-list">
                {data.analytics.upcoming_obligations.slice(0, 8).map((item) => (
                  <div className="analytics-list-row" key={item.id}>
                    <div><strong>{item.title}</strong><span>{new Date(item.due_at).toLocaleString()}</span></div>
                    <code>{item.source_ref}</code>
                  </div>
                ))}
                {data.analytics.upcoming_obligations.length === 0 && <div className="analytics-empty">No upcoming obligations.</div>}
              </div>
            </article>

            <article className="analytics-panel">
              <div className="analytics-panel-header"><div><span className="analytics-kicker">ASSETS</span><h2>Warranty & maintenance</h2></div></div>
              <div className="analytics-list">
                {data.analytics.warranty_expirations.slice(0, 4).map((item) => (
                  <div className="analytics-list-row" key={`w-${item.id}`}>
                    <div><strong>{item.asset_name ?? `Asset #${item.asset_id}`}</strong><span>Warranty ends {item.end_date}</span></div>
                    <code>{item.source_ref}</code>
                  </div>
                ))}
                {data.analytics.maintenance_schedule.slice(0, 4).map((item) => (
                  <div className="analytics-list-row" key={`m-${item.id}`}>
                    <div><strong>{item.title}</strong><span>{item.asset_name ?? `Asset #${item.asset_id}`} · {item.next_due_date}</span></div>
                    <code>{item.source_ref}</code>
                  </div>
                ))}
                {data.analytics.warranty_expirations.length + data.analytics.maintenance_schedule.length === 0 && (
                  <div className="analytics-empty">No warranty or maintenance events in the next 90 days.</div>
                )}
              </div>
            </article>
          </section>

          <section className="analytics-panel">
            <div className="analytics-panel-header">
              <div><span className="analytics-kicker">DATA QUALITY & DRIFT</span><h2>Prediction readiness</h2></div>
              <span>No hidden cross-currency conversion</span>
            </div>
            <div className="analytics-quality-grid">
              {data.data_quality.map((check) => (
                <article className={`analytics-quality ${check.status}`} key={check.id}>
                  <div className="analytics-card-row"><strong>{check.title}</strong><span>{check.status}</span></div>
                  <p>{check.message}</p>
                  <Evidence refs={check.evidence_refs} />
                </article>
              ))}
              {data.data_quality.length === 0 && <div className="analytics-empty">Generate predictions to run data-quality checks.</div>}
            </div>
          </section>
        </>
      )}
    </div>
  );
}
