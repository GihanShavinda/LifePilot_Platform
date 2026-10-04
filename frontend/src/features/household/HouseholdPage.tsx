import { useCallback, useEffect, useMemo, useState, type FormEvent } from "react";
import { householdApi } from "./householdApi";
import type { Assignment, HouseholdDashboard, HouseholdInvitation, HouseholdRole } from "./types";
// The CSS file is handled by the bundler and has no TypeScript declarations.
// @ts-expect-error TS cannot resolve side-effect CSS imports in this setup.
import "./HouseholdPage.css";

const roleLabel = (role: string) => (role === "family_member" ? "Member" : role.charAt(0).toUpperCase() + role.slice(1));

function PermissionBadge({ role }: { role: string }) {
  const normalized = role === "family_member" ? "member" : role;
  return <span className={`household-role-badge role-${normalized}`}>{roleLabel(normalized)}</span>;
}

export function HouseholdPage() {
  const [dashboard, setDashboard] = useState<HouseholdDashboard | null>(null);
  const [assignments, setAssignments] = useState<Assignment[]>([]);
  const [invitations, setInvitations] = useState<HouseholdInvitation[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [acceptLink, setAcceptLink] = useState("");
  const [inviteEmail, setInviteEmail] = useState("");
  const [inviteRole, setInviteRole] = useState<Exclude<HouseholdRole, "owner">>("member");
  const [taskId, setTaskId] = useState("");
  const [assigneeId, setAssigneeId] = useState("");
  const [assignmentNote, setAssignmentNote] = useState("");

  const canManage = useMemo(() => ["owner", "admin"].includes(dashboard?.role ?? ""), [dashboard]);
  const canWrite = useMemo(() => ["owner", "admin", "member"].includes(dashboard?.role ?? ""), [dashboard]);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [d, a] = await Promise.all([householdApi.dashboard(), householdApi.assignments()]);
      setDashboard(d);
      setAssignments(a.data ?? a);
      if (["owner", "admin"].includes(d.role)) {
        const inv = await householdApi.invitations();
        setInvitations(inv.data ?? inv);
      } else {
        setInvitations([]);
      }
    } catch (e: any) {
      setError(e?.friendlyMessage ?? "Unable to load household workspace.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const submitInvite = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true); setError(""); setNotice(""); setAcceptLink("");
    try {
      const result = await householdApi.invite(inviteEmail, inviteRole);
      const link = `${window.location.origin}/household?invite=${encodeURIComponent(result.accept_token)}`;
      setAcceptLink(link);
      setNotice(`Invitation created for ${inviteEmail}.`);
      setInviteEmail("");
      await load();
    } catch (e: any) {
      setError(e?.friendlyMessage ?? "Invitation could not be created.");
    } finally { setBusy(false); }
  };

  const submitAssignment = async (event: FormEvent) => {
    event.preventDefault();
    if (!taskId || !assigneeId) return;
    setBusy(true); setError(""); setNotice("");
    try {
      await householdApi.assignTask(Number(taskId), Number(assigneeId), assignmentNote || undefined);
      setTaskId(""); setAssigneeId(""); setAssignmentNote("");
      setNotice("Task assigned and explicitly shared with the household.");
      await load();
    } catch (e: any) {
      setError(e?.friendlyMessage ?? "Task could not be assigned.");
    } finally { setBusy(false); }
  };

  const changeRole = async (memberId: number, role: Exclude<HouseholdRole, "owner">) => {
    setBusy(true); setError("");
    try { await householdApi.updateRole(memberId, role); await load(); }
    catch (e: any) { setError(e?.friendlyMessage ?? "Role could not be updated."); }
    finally { setBusy(false); }
  };

  const removeMember = async (memberId: number, name: string) => {
    if (!window.confirm(`Remove ${name} from this household? Their assignments will be removed and household-shared resources will no longer be accessible to them.`)) return;
    setBusy(true); setError("");
    try { await householdApi.removeMember(memberId); setNotice(`${name} was removed.`); await load(); }
    catch (e: any) { setError(e?.friendlyMessage ?? "Member could not be removed."); }
    finally { setBusy(false); }
  };

  const completeAssignment = async (id: number) => {
    setBusy(true); setError("");
    try { await householdApi.completeAssignment(id); await load(); }
    catch (e: any) { setError(e?.friendlyMessage ?? "Assignment could not be completed."); }
    finally { setBusy(false); }
  };

  if (loading) return <div className="household-loading">Loading household workspace…</div>;
  if (!dashboard) return <div className="household-error">{error || "Household workspace unavailable."}</div>;

  return (
    <div className="household-page">
      <section className="household-hero">
        <div>
          <span className="household-eyebrow">SHARED LIFE WORKSPACE</span>
          <h1>{dashboard.household.name}</h1>
          <p>Collaborate on explicitly shared tasks, documents, expenses, assets and calendar items while private resources remain private.</p>
        </div>
        <div className="household-current-role"><span>Your permission</span><PermissionBadge role={dashboard.role} /></div>
      </section>

      {error && <div className="household-alert error">{error}</div>}
      {notice && <div className="household-alert success">{notice}</div>}
      {acceptLink && <div className="household-invite-link"><strong>Local acceptance link</strong><code>{acceptLink}</code><small>The plaintext token is returned once. Production email delivery should distribute it securely.</small></div>}

      <section className="household-stats">
        <article><span>Members</span><strong>{dashboard.counts.members}</strong><small>Household collaborators</small></article>
        <article><span>Shared resources</span><strong>{dashboard.counts.shared_resources}</strong><small>Explicit household scope</small></article>
        <article><span>Open assignments</span><strong>{dashboard.counts.open_assignments}</strong><small>Tasks awaiting completion</small></article>
        <article><span>Privacy model</span><strong>Explicit</strong><small>Private by default</small></article>
      </section>

      <div className="household-grid">
        <section className="household-card members-card">
          <div className="household-card-head"><div><span>MEMBERS</span><h2>Member management</h2></div><small>{canManage ? "You can manage roles" : "Read-only member list"}</small></div>
          <div className="household-member-list">
            {dashboard.members.map((member) => {
              const role = member.role === "family_member" ? "member" : member.role;
              return <div className="household-member" key={member.id}>
                <div className="member-avatar">{(member.user?.name || member.user?.email || "U").slice(0,1).toUpperCase()}</div>
                <div className="member-copy"><strong>{member.user?.name ?? "Household member"}</strong><span>{member.user?.email}</span></div>
                <PermissionBadge role={role} />
                {canManage && role !== "owner" && <div className="member-actions">
                  <select value={role} disabled={busy} onChange={(e) => void changeRole(member.id, e.target.value as Exclude<HouseholdRole,"owner">)}>
                    <option value="admin">Admin</option><option value="member">Member</option><option value="viewer">Viewer</option>
                  </select>
                  <button className="danger-link" disabled={busy} onClick={() => void removeMember(member.id, member.user?.name ?? member.user?.email ?? "member")}>Remove</button>
                </div>}
              </div>;
            })}
          </div>
        </section>

        <section className="household-card invite-card">
          <div className="household-card-head"><div><span>INVITATIONS</span><h2>Invite a collaborator</h2></div></div>
          {canManage ? <form onSubmit={submitInvite} className="household-form">
            <label>Email<input type="email" required value={inviteEmail} onChange={(e)=>setInviteEmail(e.target.value)} placeholder="member@example.com" /></label>
            <label>Role<select value={inviteRole} onChange={(e)=>setInviteRole(e.target.value as Exclude<HouseholdRole,"owner">)}><option value="admin">Admin</option><option value="member">Member</option><option value="viewer">Viewer</option></select></label>
            <button disabled={busy}>Create invitation</button>
          </form> : <div className="household-empty">Only household owners and admins can invite members.</div>}
          {canManage && <div className="invitation-list">{invitations.slice(0,5).map((invite)=><div key={invite.id}><div><strong>{invite.email}</strong><span>{roleLabel(invite.role)} · {invite.status}</span></div><small>{new Date(invite.expires_at).toLocaleDateString()}</small></div>)}</div>}
        </section>

        <section className="household-card assignment-card">
          <div className="household-card-head"><div><span>ASSIGNMENTS</span><h2>Shared task assignments</h2></div></div>
          {canWrite && <form onSubmit={submitAssignment} className="assignment-form">
            <input type="number" min="1" required value={taskId} onChange={(e)=>setTaskId(e.target.value)} placeholder="Task ID" />
            <select required value={assigneeId} onChange={(e)=>setAssigneeId(e.target.value)}><option value="">Assign to…</option>{dashboard.members.filter(m => !["viewer"].includes(m.role as string)).map(m=><option key={m.id} value={m.user_id}>{m.user?.name ?? m.user?.email}</option>)}</select>
            <input value={assignmentNote} onChange={(e)=>setAssignmentNote(e.target.value)} placeholder="Optional note" />
            <button disabled={busy}>Assign</button>
          </form>}
          <div className="assignment-list">
            {assignments.length === 0 && <div className="household-empty">No assignments yet.</div>}
            {assignments.map((a)=><div className="assignment-row" key={a.id}><div><strong>{a.assignable_type === "task" ? `Task #${a.assignable_id}` : `${a.assignable_type} #${a.assignable_id}`}</strong><span>{a.assignee?.name ?? `User #${a.assignee_user_id}`} · {a.status}</span>{a.note && <small>{a.note}</small>}</div>{a.status !== "completed" && <button disabled={busy} onClick={()=>void completeAssignment(a.id)}>Complete</button>}</div>)}
          </div>
        </section>

        <section className="household-card privacy-card">
          <div className="privacy-mark">◈</div><span className="household-eyebrow">PRIVACY BOUNDARY</span><h2>Private by default</h2>
          <p>Being in the same household does not grant access to another member's private resources. A resource must be explicitly changed to <strong>household</strong> scope.</p>
          <ul><li>Private documents stay owner-only.</li><li>AI evidence retrieval uses the same sharing checks.</li><li>Semantic search filters private document-derived results.</li><li>Assignments explicitly share their task before assignment.</li></ul>
        </section>

        <section className="household-card activity-card">
          <div className="household-card-head"><div><span>ACTIVITY</span><h2>Shared household activity</h2></div></div>
          <div className="activity-list">{dashboard.recent_activity.length === 0 && <div className="household-empty">No collaboration activity yet.</div>}{dashboard.recent_activity.map(a=><div className="activity-row" key={a.id}><div className="activity-dot"/><div><strong>{a.description}</strong><span>{a.actor?.name ?? "System"} · {new Date(a.created_at).toLocaleString()}</span></div></div>)}</div>
        </section>
      </div>
    </div>
  );
}
