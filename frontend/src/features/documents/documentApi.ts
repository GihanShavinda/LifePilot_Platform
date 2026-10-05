import { api, ensureCsrfCookie } from "../../services/api";
import type { DocumentFilters, DocumentRecord } from "./types";
export async function listDocuments(filters: DocumentFilters = {}) {
  const r = await api.get("/api/v1/documents", { params: filters });
  return r.data as { success: boolean; data: DocumentRecord[]; meta: any };
}
export async function getDocument(id: number) {
  const r = await api.get(`/api/v1/documents/${id}`);
  return r.data.data.document as DocumentRecord;
}
export async function uploadDocument(
  file: File,
  meta: Record<string, any>,
  onProgress?: (p: number) => void,
) {
  await ensureCsrfCookie();
  const f = new FormData();
  f.append("file", file);
  Object.entries(meta).forEach(([k, v]) => {
    if (v === undefined || v === null || v === "") return;
    if (k === "tags" && Array.isArray(v))
      v.forEach((x: string) => f.append("tags[]", x));
    else f.append(k, String(v));
  });
  const r = await api.post("/api/v1/documents", f, {
    headers: { "Content-Type": "multipart/form-data" },
    onUploadProgress: (e) => {
      if (e.total && onProgress)
        onProgress(Math.round((e.loaded / e.total) * 100));
    },
  });
  return r.data.data.document as DocumentRecord;
}
export async function updateDocument(id: number, payload: any) {
  await ensureCsrfCookie();
  const r = await api.put(`/api/v1/documents/${id}`, payload);
  return r.data.data.document as DocumentRecord;
}
export async function replaceDocument(
  id: number,
  file: File,
  onProgress?: (p: number) => void,
) {
  await ensureCsrfCookie();
  const f = new FormData();
  f.append("file", file);
  const r = await api.post(`/api/v1/documents/${id}/replace`, f, {
    headers: { "Content-Type": "multipart/form-data" },
    onUploadProgress: (e) => {
      if (e.total && onProgress)
        onProgress(Math.round((e.loaded / e.total) * 100));
    },
  });
  return r.data.data.document as DocumentRecord;
}
export async function archiveDocument(id: number) {
  await ensureCsrfCookie();
  return (await api.post(`/api/v1/documents/${id}/archive`)).data;
}
export async function restoreDocument(id: number) {
  await ensureCsrfCookie();
  return (await api.post(`/api/v1/documents/${id}/restore`)).data;
}
export async function deleteDocument(id: number) {
  await ensureCsrfCookie();
  return (await api.delete(`/api/v1/documents/${id}`)).data;
}
export async function signedDownload(id: number) {
  await ensureCsrfCookie();
  const r = await api.post(`/api/v1/documents/${id}/signed-url`);
  return r.data.data.url as string;
}
export async function listCategories() {
  const r = await api.get("/api/v1/document-categories");
  return r.data.data.categories as Array<{ slug: string; name: string }>;
}
