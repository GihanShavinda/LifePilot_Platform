import { useEffect, useState } from "react";
import { downloadEvaluation, getEvaluationSummary, runEvaluation } from "./evaluationApi";
import type { EvaluationSummary } from "./types";
import "./EvaluationPage.css";

function metricValue(value:number|null, unit:string|null) {
  if (value === null || value === undefined) return "Not measured";
  return `${Number(value).toLocaleString(undefined,{maximumFractionDigits:2})}${unit ?? ""}`;
}

export function EvaluationPage() {
  const [data,setData] = useState<EvaluationSummary|null>(null);
  const [loading,setLoading] = useState(true);
  const [running,setRunning] = useState(false);
  const [error,setError] = useState("");

  const load = async () => {
    setLoading(true); setError("");
    try { setData(await getEvaluationSummary()); }
    catch (err) { setError(err instanceof Error ? err.message : "Unable to load evaluation."); }
    finally { setLoading(false); }
  };

  useEffect(()=>{ void load(); },[]);

  const run = async () => {
    setRunning(true); setError("");
    try { await runEvaluation(); await load(); }
    catch (err) { setError(err instanceof Error ? err.message : "Evaluation failed."); }
    finally { setRunning(false); }
  };

  return <div className="evaluation-page">
    <section className="evaluation-hero">
      <div><span>P12 · FINAL VALIDATION</span><h1>Evaluation & Production Readiness</h1><p>Benchmark LifePilot without inventing scores. Metrics require labeled cases or valid operational samples, while security checks remain explicit about manual-review items.</p></div>
      <div className="evaluation-actions"><button onClick={()=>void run()} disabled={running}>{running?"Running…":"Run evaluation"}</button><button className="secondary" onClick={()=>void downloadEvaluation("pdf")}>PDF</button><button className="secondary" onClick={()=>void downloadEvaluation("csv")}>CSV</button><button className="secondary" onClick={()=>void downloadEvaluation("json")}>JSON</button></div>
    </section>

    {error && <div className="evaluation-error">{error}</div>}
    {loading && !data ? <div className="evaluation-loading">Loading evaluation…</div> : data && <>
      <section className="evaluation-summary">
        <article><span>Latest run</span><strong>{data.latest_run?.id ? `#${data.latest_run.id}` : "—"}</strong><small>{data.latest_run?.generated_at ? new Date(data.latest_run.generated_at).toLocaleString() : "No run yet"}</small></article>
        <article><span>Metric catalog</span><strong>{data.metric_catalog.length}</strong><small>Required P12 metrics</small></article>
        <article><span>Security checks</span><strong>{data.security_review.length}</strong><small>{data.security_review.filter(x=>x.status==="manual_review").length} manual reviews</small></article>
        <article><span>Golden rule</span><strong>0</strong><small>Fabricated benchmark scores</small></article>
      </section>

      <section className="evaluation-panel">
        <header><div><span>COMPARISON</span><h2>System variants</h2></div></header>
        <div className="evaluation-variants">{data.comparison.map(variant=><article key={variant.variant}><h3>{variant.label}</h3><div className="evaluation-capabilities">{variant.capabilities.map(item=><span key={item}>{item.replaceAll("_"," ")}</span>)}</div><div className="evaluation-metric-list">{variant.metrics.length?variant.metrics.map(metric=><div key={metric.metric_key}><div><strong>{metric.label}</strong><small>{metric.status} · n={metric.sample_size}</small></div><b>{metricValue(metric.value,metric.unit)}</b></div>):<p>No persisted run yet.</p>}</div></article>)}</div>
      </section>

      <section className="evaluation-panel">
        <header><div><span>SECURITY REVIEW</span><h2>Production-readiness controls</h2></div></header>
        <div className="evaluation-security">{data.security_review.map(check=><article className={check.status} key={check.check_key}><div><strong>{check.title}</strong><span>{check.status.replaceAll("_"," ")}</span></div><p>{check.description}</p><small>{check.evidence.join(" · ")}</small></article>)}</div>
      </section>
    </>}
  </div>;
}
