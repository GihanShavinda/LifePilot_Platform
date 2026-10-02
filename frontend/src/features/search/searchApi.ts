import api from "../../services/api";
import type { SearchResult } from "./types.ts";
export async function semanticSearch(q: string, entityType?: string) {
  const { data } = await api.get("/api/v1/search", {
    params: { q, entity_type: entityType || undefined },
  });
  return data.data.results as SearchResult[];
}
export async function syncGraph() {
  const { data } = await api.post("/api/v1/graph/sync");
  return data.data;
}
export async function graphEntity(id: number) {
  const { data } = await api.get(`/api/v1/graph/entities/${id}`);
  return data.data;
}
