import { api } from "../../services/api";
import type { ActionPlan } from "./types";

export type CreatePlanPayload = {
  request: string;
  origin?: "user" | "assistant";
  evidence_refs?: string[];
  idempotency_key?: string;
  source_conversation_message_id?: number;
  metadata?: Record<string, unknown>;
  steps: Array<{
    action_type: string;
    title?: string;
    description?: string;
    payload: Record<string, unknown>;
    evidence_refs?: string[];
  }>;
};

export const actionsApi = {
  list: async () =>
    (await api.get("/api/v1/action-plans")).data.data,

  show: async (id: number) =>
    (await api.get(`/api/v1/action-plans/${id}`)).data.data.plan as ActionPlan,

  create: async (payload: CreatePlanPayload) =>
    (await api.post("/api/v1/action-plans", payload)).data.data.plan as ActionPlan,

  approve: async (
    id: number,
    stepIds: number[],
    highRiskAck = false,
    comment?: string
  ) =>
    (
      await api.post(`/api/v1/action-plans/${id}/approve`, {
        step_ids: stepIds,
        high_risk_ack: highRiskAck,
        comment,
      })
    ).data.data.plan as ActionPlan,

  execute: async (id: number) =>
    (await api.post(`/api/v1/action-plans/${id}/execute`)).data.data.plan as ActionPlan,

  cancel: async (id: number) =>
    (await api.post(`/api/v1/action-plans/${id}/cancel`)).data.data.plan as ActionPlan,

  audit: async (id: number) =>
    (await api.get(`/api/v1/action-plans/${id}/audit`)).data.data,

  fromAssistant: async (messageId: number) =>
    (await api.post(`/api/v1/assistant/messages/${messageId}/action-plan`)).data.data.plan as ActionPlan,
};
