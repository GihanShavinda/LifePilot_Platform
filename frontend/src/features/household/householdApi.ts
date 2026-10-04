import { api } from "../../services/api";
import type {
  Assignment,
  HouseholdActivity,
  HouseholdDashboard,
  HouseholdInvitation,
  HouseholdRole,
  SharingScope,
} from "./types";

export const householdApi = {
  dashboard: async () =>
    (await api.get("/api/v1/households/current/dashboard")).data.data as HouseholdDashboard,

  activity: async () =>
    (await api.get("/api/v1/households/current/activity")).data.data.activity,

  invitations: async () =>
    (await api.get("/api/v1/households/current/invitations")).data.data.invitations,

  invite: async (email: string, role: Exclude<HouseholdRole, "owner">) =>
    (await api.post("/api/v1/households/current/invitations", { email, role })).data.data as {
      invitation: HouseholdInvitation;
      accept_token: string;
    },

  acceptInvitation: async (token: string) =>
    (await api.post(`/api/v1/household-invitations/${encodeURIComponent(token)}/accept`)).data.data,

  declineInvitation: async (token: string) =>
    (await api.post(`/api/v1/household-invitations/${encodeURIComponent(token)}/decline`)).data.data,

  updateRole: async (memberId: number, role: Exclude<HouseholdRole, "owner">) =>
    (await api.put(`/api/v1/households/current/members/${memberId}/role`, { role })).data.data,

  removeMember: async (memberId: number) =>
    (await api.delete(`/api/v1/households/current/members/${memberId}`)).data.data,

  assignments: async () =>
    (await api.get("/api/v1/assignments")).data.data.assignments,

  assignTask: async (taskId: number, assigneeUserId: number, note?: string) =>
    (await api.post(`/api/v1/tasks/${taskId}/assign`, { assignee_user_id: assigneeUserId, note })).data.data.assignment as Assignment,

  completeAssignment: async (assignmentId: number) =>
    (await api.post(`/api/v1/assignments/${assignmentId}/complete`)).data.data.assignment as Assignment,

  sharing: async (type: string, id: number) =>
    (await api.get(`/api/v1/shared-resources/${type}/${id}`)).data.data as {
      resource_type: string;
      resource_id: number;
      scope: SharingScope;
      owner_user_id: number;
    },

  updateSharing: async (type: string, id: number, scope: SharingScope) =>
    (await api.put(`/api/v1/shared-resources/${type}/${id}`, { scope })).data.data.sharing,
};

export type { HouseholdActivity };
