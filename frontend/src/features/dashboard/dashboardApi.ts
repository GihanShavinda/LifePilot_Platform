import api from "../../services/api";
import type { CompletionDashboard } from "./types";

export async function getCompletionDashboard(months = 6): Promise<CompletionDashboard> {
  const response = await api.get("/api/v1/completion/dashboard", { params: { months } });
  return response.data.data as CompletionDashboard;
}
