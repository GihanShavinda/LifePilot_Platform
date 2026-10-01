import { Link } from "react-router-dom";
import { useAuth } from "../auth/AuthProvider";
export function DashboardPage() {
  const { user, logout } = useAuth();
  return (
    <main className="page">
      <header>
        <div>
          <h1>LifePilot</h1>
          <p>P6 calendar and notifications</p>
        </div>
        <div className="actions">
          <Link className="button-link" to="/calendar">
            Calendar
          </Link>
          <Link className="button-link" to="/notifications">
            Notifications
          </Link>
          <Link className="button-link" to="/tasks">
            Tasks
          </Link>
          <Link className="button-link" to="/finance">
            Finance
          </Link>
          <Link className="button-link" to="/documents">
            Documents
          </Link>
          <Link className="button-link" to="/settings">
            Settings
          </Link>
          <button className="secondary" onClick={() => logout()}>
            Logout
          </button>
        </div>
      </header>
      <div className="grid">
        <section className="card">
          <h2>Welcome, {user?.name}</h2>
          <p>Authenticated through a secure Sanctum browser session.</p>
          <dl>
            <dt>Email</dt>
            <dd>{user?.email}</dd>
            <dt>Timezone</dt>
            <dd>{user?.timezone}</dd>
          </dl>
        </section>
        <section className="card">
          <h2>P4 status</h2>
          <ul>
            <li>Private document vault</li>
            <li>Version history + signed downloads</li>
            <li>Metadata search and filters</li>
            <li>Queued baseline processing</li>
            <li>Malware-scanner adapter boundary</li>
            <li>Native/OCR document intelligence and review (P3)</li>
            <li>Human-approved obligation suggestions (P4)</li>
            <li>Today, upcoming, overdue, calendar, kanban (P4)</li>
            <li>Tracked expenses, subscriptions, assets and warranties (P5)</li>
            <li>
              Calendar events, timezones, private realtime notifications and
              explicit Google authorization (P6)
            </li>
          </ul>
        </section>
      </div>
    </main>
  );
}
