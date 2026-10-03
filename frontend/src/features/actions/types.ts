export type ActionRiskLevel = "low" | "medium" | "high";
export type ActionPlanStatus =
  | "preview"
  | "approved"
  | "partially_approved"
  | "executing"
  | "completed"
  | "failed"
  | "cancelled";

export type ActionStep = {
  id: number;
  sequence: number;
  action_type: string;
  risk_level: ActionRiskLevel;
  status: string;
  title: string;
  description?: string | null;
  payload: Record<string, unknown>;
  evidence_refs?: string[] | null;
  preview?: Record<string, unknown> | null;
  requires_approval: boolean;
  external_effect: boolean;
};

export type ActionPlan = {
  id: number;
  request: string;
  origin: string;
  status: ActionPlanStatus;
  overall_risk: ActionRiskLevel;
  evidence_refs?: string[] | null;
  metadata?: Record<string, unknown> | null;
  steps: ActionStep[];
  created_at: string;
  approved_at?: string | null;
  executed_at?: string | null;
};
