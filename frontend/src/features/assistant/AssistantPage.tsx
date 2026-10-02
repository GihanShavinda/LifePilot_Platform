import { FormEvent, useEffect, useState } from "react";
import { assistantApi } from "./assistantApi";
import type { AssistantMessage, ConversationSession } from "./types";
// The CSS file is handled by the bundler and has no TypeScript declarations.
// @ts-expect-error TS cannot resolve side-effect CSS imports in this setup.
import "./assistant.css";
export function AssistantPage() {
  const [sessions, setSessions] = useState<ConversationSession[]>([]);
  const [session, setSession] = useState<ConversationSession | null>(null);
  const [messages, setMessages] = useState<AssistantMessage[]>([]);
  const [q, setQ] = useState("");
  const [busy, setBusy] = useState(false);
  async function refresh() {
    const d = await assistantApi.sessions();
    setSessions(d.data ?? d);
  }
  useEffect(() => {
    refresh();
  }, []);
  async function open(id: number) {
    const s = await assistantApi.show(id);
    setSession(s);
    setMessages(s.messages ?? []);
  }
  async function ensure() {
    if (session) return session;
    const s = await assistantApi.createSession();
    setSession(s);
    setSessions((x) => [s, ...x]);
    return s;
  }
  async function send(e: FormEvent) {
    e.preventDefault();
    if (!q.trim() || busy) return;
    const text = q.trim();
    setQ("");
    setBusy(true);
    try {
      const s = await ensure();
      setMessages((x) => [
        ...x,
        {
          id: Date.now(),
          role: "user",
          content: text,
          created_at: new Date().toISOString(),
        },
      ]);
      const m = await assistantApi.ask(s.id, text);
      setMessages((x) => [...x, m]);
      await refresh();
    } finally {
      setBusy(false);
    }
  }
  return (
    <main className="assistant-shell">
      <aside className="assistant-sessions">
        <div className="assistant-head">
          <h2>LifePilot AI</h2>
          <button
            onClick={async () => {
              const s = await assistantApi.createSession();
              setSession(s);
              setMessages([]);
              await refresh();
            }}
          >
            New
          </button>
        </div>
        {sessions.map((s) => (
          <button
            className={session?.id === s.id ? "active" : ""}
            key={s.id}
            onClick={() => open(s.id)}
          >
            {s.title || `Conversation ${s.id}`}
          </button>
        ))}
      </aside>
      <section className="assistant-chat">
        <header>
          <h1>Grounded life assistant</h1>
          <p>
            Answers are limited to your authorized LifePilot records. Drafts
            never execute actions automatically.
          </p>
        </header>
        <div className="assistant-messages">
          {messages.length === 0 && (
            <div className="assistant-empty">
              Ask about bills, spending, warranties, documents, tasks, calendar
              items, or request a draft task/event.
            </div>
          )}
          {messages.map((m) => (
            <article key={m.id} className={`assistant-message ${m.role}`}>
              <div className="assistant-role">
                {m.role === "assistant" ? "LifePilot AI" : "You"}
              </div>
              <pre>{m.content}</pre>
              {m.role === "assistant" && (
                <>
                  <div className="assistant-meta">
                    Grounding: {m.grounding_status || "unknown"}
                  </div>
                  {m.citations?.length ? (
                    <div className="assistant-citations">
                      Sources: {m.citations.join(", ")}
                    </div>
                  ) : null}
                  {m.metadata?.draft ? (
                    <pre className="assistant-draft">
                      Draft only — review before creating:
                      {JSON.stringify(m.metadata.draft, null, 2)}
                    </pre>
                  ) : null}
                  <div className="assistant-feedback">
                    <button onClick={() => assistantApi.feedback(m.id, 1)}>
                      Helpful
                    </button>
                    <button onClick={() => assistantApi.feedback(m.id, -1)}>
                      Not helpful
                    </button>
                  </div>
                </>
              )}
            </article>
          ))}
        </div>
        <form className="assistant-compose" onSubmit={send}>
          <textarea
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="What bills do I need to pay this week?"
            rows={3}
          />
          <button disabled={busy || !q.trim()}>
            {busy ? "Checking evidence…" : "Ask"}
          </button>
        </form>
      </section>
    </main>
  );
}
