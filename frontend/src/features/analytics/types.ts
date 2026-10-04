export type SpendingPoint = {
  month: string;
  currency: string;
  total: number;
  count: number;
};

export type OverduePoint = {
  month: string;
  due_tasks: number;
  overdue_tasks: number;
};

export type PredictionRecord = {
  id: number;
  prediction_type: string;
  method: string;
  model_version: string;
  feature_version: string;
  input_fingerprint: string;
  prediction: Record<string, unknown>;
  confidence: number;
  explanation: string;
  evidence_refs: string[];
  generated_at: string;
};

export type AnalyticsInsight = {
  id: number;
  insight_key: string;
  type: string;
  severity: "info" | "warning" | "success" | string;
  title: string;
  message: string;
  metrics?: Record<string, unknown>;
  evidence_refs: string[];
  model_version: string;
  feature_version: string;
  generated_at: string;
};

export type DataQualityCheck = {
  id: number;
  check_key: string;
  status: "ok" | "warning" | "info" | string;
  title: string;
  message: string;
  metrics?: Record<string, unknown>;
  evidence_refs?: string[];
  generated_at: string;
};

export type AnalyticsDashboard = {
  analytics: {
    period: { months: number; from: string; to: string };
    spending_trend: SpendingPoint[];
    recurring_cost_trend: SpendingPoint[];
    task_completion: { total: number; completed: number; rate: number | null };
    overdue_task_trend: OverduePoint[];
    document_categories: { category: string; count: number }[];
    subscription_cost: { currency: string; active_subscriptions: number; monthly_equivalent: number }[];
    upcoming_obligations: {
      id: number;
      title: string;
      type: string;
      due_at: string;
      amount?: string | number | null;
      currency?: string | null;
      source_ref: string;
    }[];
    warranty_expirations: {
      id: number;
      asset_id: number;
      asset_name?: string | null;
      provider: string;
      end_date: string;
      source_ref: string;
    }[];
    maintenance_schedule: {
      id: number;
      asset_id: number;
      asset_name?: string | null;
      title: string;
      next_due_date: string;
      source_ref: string;
    }[];
    generated_at: string;
  };
  predictions: PredictionRecord[];
  insights: AnalyticsInsight[];
  data_quality: DataQualityCheck[];
  needs_refresh: boolean;
};
