import { FormEvent, useState } from "react";
import { semanticSearch, syncGraph } from "./searchApi";
import type { SearchResult } from "./types.ts";
// The CSS file is handled by the bundler and has no TypeScript declarations.
// @ts-expect-error TS cannot resolve side-effect CSS imports in this setup.
import "./search.css";

export function SearchPage() {
  const [q, setQ] = useState("");
  const [type, setType] = useState("");
  const [items, setItems] = useState<SearchResult[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  async function submit(e: FormEvent) {
    e.preventDefault();
    if (q.trim().length < 2) return;
    setLoading(true);
    setError("");
    try {
      setItems(await semanticSearch(q.trim(), type));
    } catch (err: any) {
      setError(err?.response?.data?.message || "Search failed.");
    } finally {
      setLoading(false);
    }
  }
  async function rebuild() {
    setLoading(true);
    setError("");
    try {
      await syncGraph();
      if (q.trim()) setItems(await semanticSearch(q.trim(), type));
    } catch (err: any) {
      setError(err?.response?.data?.message || "Index rebuild failed.");
    } finally {
      setLoading(false);
    }
  }
  return (
    <main className="page">
      <section className="page-header">
        <div>
          <p className="eyebrow">P7 · LIFE ACTION GRAPH</p>
          <h1>Search your life admin</h1>
          <p>
            Hybrid keyword + semantic search across reviewed documents and
            linked life entities. Every result shows its source.
          </p>
        </div>
        <button
          className="secondary-button"
          onClick={rebuild}
          disabled={loading}
        >
          Rebuild graph & index
        </button>
      </section>
      <form className="panel search-bar" onSubmit={submit}>
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="e.g. find the receipt for my laptop"
        />
        <select value={type} onChange={(e) => setType(e.target.value)}>
          <option value="">All entity types</option>
          {[
            "document",
            "task",
            "obligation",
            "event",
            "expense",
            "subscription",
            "asset",
            "warranty",
            "organization",
            "person",
            "location",
          ].map((x) => (
            <option key={x} value={x}>
              {x}
            </option>
          ))}
        </select>
        <button disabled={loading}>{loading ? "Searching…" : "Search"}</button>
      </form>
      <div className="query-examples">
        <button onClick={() => setQ("show warranties expiring this year")}>
          Warranties expiring this year
        </button>
        <button onClick={() => setQ("find the receipt for my laptop")}>
          Laptop receipt
        </button>
        <button onClick={() => setQ("what payments are due next week?")}>
          Payments next week
        </button>
        <button
          onClick={() => setQ("which tasks came from university documents?")}
        >
          University tasks
        </button>
        <button onClick={() => setQ("show everything related to my car")}>
          Everything related to my car
        </button>
      </div>
      {error && <div className="error-banner">{error}</div>}
      <section className="search-results">
        {items.map((r) => (
          <article className="panel search-result" key={`${r.kind}-${r.id}`}>
            <div className="search-result-head">
              <div>
                <span className="badge">{r.kind}</span>
                <h3>{r.title}</h3>
              </div>
              <strong>{Math.round(r.score * 100)}%</strong>
            </div>
            {r.snippet && <p>{r.snippet}</p>}
            <details>
              <summary>Source references ({r.sources.length})</summary>
              <pre>{JSON.stringify(r.sources, null, 2)}</pre>
            </details>
          </article>
        ))}
        {!loading && q && items.length === 0 && (
          <div className="empty-state">
            No authorized results matched this query.
          </div>
        )}
      </section>
    </main>
  );
}
