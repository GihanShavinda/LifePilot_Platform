import { api } from "../../services/api";
import type { ConversationSession, AssistantMessage } from "./types";
import type { ActionPlan } from "../actions/types";

export const assistantApi = {
  sessions: async () =>
    (await api.get("/api/v1/assistant/sessions")).data.data,

  createSession: async (title?: string) =>
    (await api.post("/api/v1/assistant/sessions", { title })).data.data
      .session as ConversationSession,

  show: async (id: number) =>
    (await api.get(`/api/v1/assistant/sessions/${id}`)).data.data
      .session as ConversationSession,

  ask: async (id: number, question: string) =>
    (
      await api.post(`/api/v1/assistant/sessions/${id}/messages`, {
        question,
      })
    ).data.data.message as AssistantMessage,

  feedback: async (
    messageId: number,
    rating: -1 | 1,
    comment?: string
  ) =>
    (
      await api.post(`/api/v1/assistant/messages/${messageId}/feedback`, {
        rating,
        comment,
      })
    ).data.data,

  createActionPlan: async (messageId: number) =>
    (
      await api.post(
        `/api/v1/assistant/messages/${messageId}/action-plan`
      )
    ).data.data.plan as ActionPlan,
};
