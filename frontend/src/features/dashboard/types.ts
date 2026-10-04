export type EvidenceRef = string;

export type MainDashboard = {
  today_tasks: Array<{
    id: number;
    title: string;
    priority: string;
    status: string;
    due_at: string | null;
    source_ref: string;
  }>;
  upcoming_obligations: Array<{
    id: number;
    title: string;
    type: string;
    due_at: string | null;
    amount?: number | string | null;
    currency?: string | null;
    source_ref: string;
  }>;
  overdue_items: Array<{
    id: number;
    kind: string;
    title: string;
    due_at: string | null;
    priority?: string | null;
    source_ref: string;
  }>;
  upcoming_payments: Array<{
    kind: string;
    id: number;
    title: string;
    amount: number;
    currency: string;
    due_date: string | null;
    source_ref: string;
  }>;
  active_subscriptions: {
    count: number;
    items: Array<{
      id: number;
      name: string;
      price: number;
      currency: string;
      billing_cycle: string;
      next_billing_date: string | null;
      source_ref: string;
    }>;
  };
  expiring_warranties: Array<{
    id: number;
    asset_id: number;
    asset_name?: string | null;
    provider: string;
    end_date: string | null;
    source_ref: string;
  }>;
  recent_documents: Array<{
    id: number;
    title: string;
    filename: string;
    category: string;
    processing_status: string;
    created_at: string | null;
    source_ref: string;
  }>;
  calendar_preview: Array<{
    id: number;
    title: string;
    starts_at: string | null;
    ends_at: string | null;
    timezone: string;
    status: string;
    source_ref: string;
  }>;
  ai_recommendations: Array<{
    id: number;
    severity: string;
    title: string;
    message: string;
    evidence_refs: string[];
    model_version: string;
    feature_version: string;
    generated_at: string | null;
  }>;
  generated_at: string;
};

export type DocumentDashboard = {
  total_documents: number;
  processing_state: Array<{ status: string; count: number }>;
  categories: Array<{ category: string; count: number }>;
  extraction_review_queue: Array<{
    field_id: number;
    document_id: number;
    document_title: string;
    field_name: string;
    confidence: number;
    review_status: string;
    evidence_text?: string | null;
    source_ref: string;
  }>;
  review_queue_count: number;
  generated_at: string;
};

export type FinanceDashboard = {
  period: { months: number; from: string; to: string };
  monthly_expenses: Array<{
    month: string;
    currency: string;
    total: number;
    count: number;
  }>;
  recurring_commitments: Array<{
    currency: string;
    recorded_total: number;
    records: number;
  }>;
  category_breakdown: Array<{
    currency: string;
    category: string;
    total: number;
    count: number;
  }>;
  subscription_trend: Array<{
    month: string;
    active_subscriptions: number;
    currencies: Array<{ currency: string; total_recorded_price: number }>;
  }>;
  generated_at: string;
};

export type AiDashboard = {
  recommendations: Array<{
    id: number;
    title: string;
    message: string;
    severity: string;
    confidence: number | null;
    evidence_refs: string[];
    model_version: string;
    feature_version: string;
    generated_at: string | null;
  }>;
  predictions: Array<{
    id: number;
    type: string;
    method: string;
    confidence: number;
    explanation: string;
    evidence_refs: string[];
    model_version: string;
    feature_version: string;
    prediction: Record<string, unknown>;
    generated_at: string | null;
  }>;
  pending_actions: Array<{
    id: number;
    request: string;
    origin: string;
    status: string;
    overall_risk: string;
    evidence_refs: string[];
    approval_state: string;
  }>;
  action_plans: Array<{
    id: number;
    request: string;
    origin: string;
    status: string;
    overall_risk: string;
    evidence_refs: string[];
    approval_state: string;
    approved_at?: string | null;
    executed_at?: string | null;
  }>;
  recent_grounded_answers: Array<{
    id: number;
    content: string;
    grounding_status: string;
    citations: string[];
    model_provider: string;
    model_name: string;
    model_version: string;
    response_latency_ms?: number | null;
    created_at: string | null;
  }>;
  generated_at: string;
};

export type CompletionDashboard = {
  main: MainDashboard;
  documents: DocumentDashboard;
  finance: FinanceDashboard;
  ai: AiDashboard;
};
