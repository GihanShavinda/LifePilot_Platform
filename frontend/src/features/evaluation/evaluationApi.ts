import api, { ensureCsrfCookie } from "../../services/api";
import type { EvaluationSummary, EvaluationRun } from "./types";

export async function getEvaluationSummary(): Promise<EvaluationSummary> {
  const response = await api.get("/api/v1/evaluation/summary");
  return response.data.data as EvaluationSummary;
}

export async function runEvaluation(label = "P12 final evaluation"): Promise<EvaluationRun> {
  await ensureCsrfCookie();
  const response = await api.post("/api/v1/evaluation/run", { label });
  return response.data.data.run as EvaluationRun;
}

export async function downloadEvaluation(format: "json"|"csv"|"pdf") {
  const response = await api.get(`/api/v1/evaluation/export/${format}`, { responseType: "blob" });
  const blob = new Blob([response.data], { type: response.headers["content-type"] });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = `lifepilot-p12-evaluation.${format}`;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
}
