import { useNavigate } from "react-router-dom";
// The CSS file is handled by the bundler and has no TypeScript declarations.
// @ts-expect-error TS cannot resolve side-effect CSS imports in this setup.
import "./DashboardPage.css";

type StatCardProps = {
  icon: string;
  label: string;
  value: string;
  detail: string;
  tone?: "blue" | "green" | "orange" | "violet";
  onClick?: () => void;
};

function StatCard({
  icon,
  label,
  value,
  detail,
  tone = "blue",
  onClick,
}: StatCardProps) {
  return (
    <button
      type="button"
      className={`dashboard-stat-card tone-${tone}`}
      onClick={onClick}
    >
      <div className="dashboard-stat-top">
        <div className="dashboard-stat-icon">
          {icon}
        </div>

        <span className="dashboard-stat-arrow">
          ↗
        </span>
      </div>

      <div className="dashboard-stat-label">
        {label}
      </div>

      <div className="dashboard-stat-value">
        {value}
      </div>

      <div className="dashboard-stat-detail">
        {detail}
      </div>
    </button>
  );
}

export function DashboardPage() {
  const navigate = useNavigate();

  return (
    <div className="premium-dashboard">
      <section className="dashboard-hero">
        <div>
          <div className="dashboard-eyebrow">
            LIFE OVERVIEW
          </div>

          <h1>
            Have a Nice Day, Gihan
          </h1>

          <p>
            Keep documents, obligations, finances,
            schedules and life decisions organized
            from one intelligent workspace.
          </p>
        </div>

        <div className="dashboard-hero-actions">
          <button
            type="button"
            className="dashboard-secondary-button"
            onClick={() => navigate("/search")}
          >
            ⌕ Search life data
          </button>

          <button
            type="button"
            className="dashboard-primary-button"
            onClick={() => navigate("/assistant")}
          >
            ✦ Ask LifePilot AI
          </button>
        </div>
      </section>

      <section className="dashboard-stats-grid">
        <StatCard
          icon="✓"
          label="Tasks & obligations"
          value="Tasks & obligations"
          detail="Review upcoming actions"
          tone="blue"
          onClick={() => navigate("/tasks")}
        />

        <StatCard
          icon="$"
          label="Finance"
          value="Finance"
          detail="Expenses and subscriptions"
          tone="green"
          onClick={() => navigate("/finance")}
        />

        <StatCard
          icon="▤"
          label="Documents"
          value="Documents"
          detail="Files and accepted evidence"
          tone="violet"
          onClick={() => navigate("/documents")}
        />

        <StatCard
          icon="□"
          label="Calendar"
          value="Calendar"
          detail="Events and reminders"
          tone="orange"
          onClick={() => navigate("/calendar")}
        />
      </section>

      <section className="dashboard-main-grid">
        <div className="dashboard-panel dashboard-attention">
          <div className="dashboard-panel-header">
            <div>
              <span className="dashboard-panel-kicker">
                PRIORITY
              </span>

              <h2>
                Needs your attention
              </h2>
            </div>

            <button
              type="button"
              className="dashboard-link-button"
              onClick={() => navigate("/tasks")}
            >
              View all
            </button>
          </div>

          <div className="dashboard-attention-list">
            <div className="dashboard-attention-item">
              <div className="attention-indicator high" />

              <div className="attention-main">
                <strong>
                  Review upcoming obligations
                </strong>

                <span>
                  Check deadlines, renewals and payments.
                </span>
              </div>

              <button
                type="button"
                onClick={() => navigate("/tasks")}
              >
                Open
              </button>
            </div>

            <div className="dashboard-attention-item">
              <div className="attention-indicator medium" />

              <div className="attention-main">
                <strong>
                  Check documents needing review
                </strong>

                <span>
                  Verify extracted information before use.
                </span>
              </div>

              <button
                type="button"
                onClick={() => navigate("/documents")}
              >
                Open
              </button>
            </div>

            <div className="dashboard-attention-item">
              <div className="attention-indicator low" />

              <div className="attention-main">
                <strong>
                  Review notifications
                </strong>

                <span>
                  Stay ahead of reminders and alerts.
                </span>
              </div>

              <button
                type="button"
                onClick={() => navigate("/notifications")}
              >
                Open
              </button>
            </div>
          </div>
        </div>

        <div className="dashboard-panel dashboard-timeline">
          <div className="dashboard-panel-header">
            <div>
              <span className="dashboard-panel-kicker">
                UPCOMING
              </span>

              <h2>
                Life timeline
              </h2>
            </div>

            <button
              type="button"
              className="dashboard-link-button"
              onClick={() => navigate("/calendar")}
            >
              Calendar
            </button>
          </div>

          <div className="dashboard-timeline-empty">
            <div className="dashboard-timeline-icon">
              □
            </div>

            <strong>
              Your upcoming schedule
            </strong>

            <p>
              Calendar events, obligations,
              subscription renewals and deadlines
              will appear here.
            </p>

            <button
              type="button"
              onClick={() => navigate("/calendar")}
            >
              Open calendar
            </button>
          </div>
        </div>

        <div className="dashboard-panel dashboard-finance-panel">
          <div className="dashboard-panel-header">
            <div>
              <span className="dashboard-panel-kicker">
                FINANCE
              </span>

              <h2>
                Financial overview
              </h2>
            </div>

            <button
              type="button"
              className="dashboard-link-button"
              onClick={() => navigate("/finance")}
            >
              View finance
            </button>
          </div>

          <div className="dashboard-finance-content">
            <div className="dashboard-finance-highlight">
              <span>
                Current financial picture
              </span>

              <strong>
                Review spending
              </strong>

              <small>
                Track expenses, recurring costs
                and upcoming payments.
              </small>
            </div>

            <div className="dashboard-finance-bars">
              <div className="finance-bar-item">
                <div>
                  <span>Expenses</span>
                  <span>Tracked</span>
                </div>
                <div className="finance-bar">
                  <div style={{ width: "72%" }} />
                </div>
              </div>

              <div className="finance-bar-item">
                <div>
                  <span>Subscriptions</span>
                  <span>Monitored</span>
                </div>
                <div className="finance-bar">
                  <div style={{ width: "48%" }} />
                </div>
              </div>

              <div className="finance-bar-item">
                <div>
                  <span>Upcoming payments</span>
                  <span>Review</span>
                </div>
                <div className="finance-bar">
                  <div style={{ width: "36%" }} />
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="dashboard-panel dashboard-ai-card">
          <div className="dashboard-ai-mark">
            ✦
          </div>

          <span className="dashboard-panel-kicker ai">
            LIFE INTELLIGENCE
          </span>

          <h2>
            Ask LifePilot AI
          </h2>

          <p>
            Get grounded answers using only
            your authorized LifePilot records.
          </p>

          <div className="dashboard-ai-prompts">
            <button
              type="button"
              onClick={() =>
                navigate(
                  "/assistant?q=What bills do I need to pay this week?"
                )
              }
            >
              What bills are due?
            </button>

            <button
              type="button"
              onClick={() =>
                navigate(
                  "/assistant?q=What should I handle before the end of this month?"
                )
              }
            >
              What needs attention?
            </button>

            <button
              type="button"
              onClick={() =>
                navigate(
                  "/assistant?q=Show my upcoming obligations"
                )
              }
            >
              Upcoming obligations
            </button>
          </div>

          <button
            type="button"
            className="dashboard-ai-open"
            onClick={() => navigate("/assistant")}
          >
            Open AI Assistant
          </button>
        </div>

        <div className="dashboard-panel dashboard-documents">
          <div className="dashboard-panel-header">
            <div>
              <span className="dashboard-panel-kicker">
                DOCUMENTS
              </span>

              <h2>
                Document intelligence
              </h2>
            </div>

            <button
              type="button"
              className="dashboard-link-button"
              onClick={() => navigate("/documents")}
            >
              View all
            </button>
          </div>

          <div className="dashboard-document-empty">
            <div>
              <span className="document-empty-icon">
                ▤
              </span>

              <div>
                <strong>
                  Your document intelligence hub
                </strong>

                <p>
                  Upload, extract, review and connect
                  documents to your life records.
                </p>
              </div>
            </div>

            <button
              type="button"
              onClick={() => navigate("/documents")}
            >
              Open documents
            </button>
          </div>
        </div>

        <div className="dashboard-panel dashboard-life-intelligence">
          <div className="dashboard-panel-header">
            <div>
              <span className="dashboard-panel-kicker">
                GRAPH
              </span>

              <h2>
                Life Intelligence
              </h2>
            </div>

            <button
              type="button"
              className="dashboard-link-button"
              onClick={() => navigate("/search")}
            >
              Explore
            </button>
          </div>

          <div className="life-intelligence-visual">
            <div className="life-node primary">
              You
            </div>

            <div className="life-node documents">
              Documents
            </div>

            <div className="life-node finance">
              Finance
            </div>

            <div className="life-node tasks">
              Tasks
            </div>

            <div className="life-node assets">
              Assets
            </div>
          </div>

          <p className="life-intelligence-copy">
            LifePilot connects documents, obligations,
            tasks, expenses, assets, warranties and
            calendar events into a searchable life graph.
          </p>
        </div>
      </section>
    </div>
  );
}