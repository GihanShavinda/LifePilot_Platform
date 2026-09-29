import axios from "axios";
const baseURL =
  (import.meta as ImportMeta & { env?: { VITE_API_URL?: string } }).env
    ?.VITE_API_URL ?? "http://localhost:8000";
export const api = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: "application/json" },
});
export async function ensureCsrfCookie() {
  await api.get("/sanctum/csrf-cookie");
}
api.interceptors.response.use(
  (r) => r,
  (err) => {
    const message =
      err?.response?.data?.error?.message ??
      err?.response?.data?.message ??
      "Request failed";
    return Promise.reject(Object.assign(err, { friendlyMessage: message }));
  },
);
export default api;
