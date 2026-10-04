import api, { ensureCsrfCookie } from "../../services/api";
import type { AnalyticsDashboard, AnalyticsInsight, DataQualityCheck, PredictionRecord } from "./types";

const base = "/api/v1/analytics";

export async function getAnalyticsDashboard(months = 6): Promise<AnalyticsDashboard> {
  const response = await api.get(`${base}/dashboard`, { params: { months } });
  return response.data.data as AnalyticsDashboard;
}

export async function refreshAnalytics(): Promise<{
  predictions: PredictionRecord[];
  insights: AnalyticsInsight[];
  data_quality: DataQualityCheck[];
  model_version: string;
  feature_version: string;
  generated_at: string;
}> {
  await ensureCsrfCookie();
  const response = await api.post(`${base}/refresh`);
  return response.data.data;
}

export async function getPredictions(): Promise<PredictionRecord[]> {
  const response = await api.get(`${base}/predictions`);
  return response.data.data.predictions.data as PredictionRecord[];
}

export async function getInsights(): Promise<AnalyticsInsight[]> {
  const response = await api.get(`${base}/insights`);
  return response.data.data.insights as AnalyticsInsight[];
}

export async function getDataQuality(): Promise<DataQualityCheck[]> {
  const response = await api.get(`${base}/data-quality`);
  return response.data.data.checks as DataQualityCheck[];
}
