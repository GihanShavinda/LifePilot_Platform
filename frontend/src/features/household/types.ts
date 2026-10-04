export type HouseholdRole = "owner" | "admin" | "member" | "viewer";
export type SharingScope = "private" | "household";

export type HouseholdMember = {
  id: number;
  household_id: number;
  user_id: number;
  role: HouseholdRole | "family_member";
  user?: { id: number; name: string; email: string };
};

export type HouseholdActivity = {
  id: number;
  event_type: string;
  subject_type?: string | null;
  subject_id?: string | null;
  description: string;
  metadata?: Record<string, unknown> | null;
  created_at: string;
  actor?: { id: number; name: string; email: string } | null;
};

export type Assignment = {
  id: number;
  assignable_type: string;
  assignable_id: number;
  assignee_user_id: number;
  assigned_by_user_id: number;
  status: "assigned" | "completed";
  note?: string | null;
  assigned_at: string;
  completed_at?: string | null;
  assignee?: { id: number; name: string; email: string };
  assigned_by?: { id: number; name: string; email: string };
};

export type HouseholdDashboard = {
  household: { id: number; name: string };
  role: HouseholdRole;
  counts: {
    members: number;
    shared_resources: number;
    open_assignments: number;
  };
  members: HouseholdMember[];
  recent_activity: HouseholdActivity[];
};

export type HouseholdInvitation = {
  id: number;
  email: string;
  role: Exclude<HouseholdRole, "owner">;
  status: string;
  expires_at: string;
  created_at: string;
};
