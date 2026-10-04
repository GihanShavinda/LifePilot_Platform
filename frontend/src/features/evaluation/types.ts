export type EvaluationMetric = {
  variant:string;
  metric_key:string;
  label:string;
  value:number|null;
  unit:string|null;
  status:string;
  sample_size:number;
  explanation:string;
};

export type EvaluationRun = {
  id:number;
  label:string;
  status:string;
  summary?:Record<string,unknown>;
  generated_at:string|null;
  metrics:EvaluationMetric[];
};

export type VariantComparison = {
  variant:string;
  label:string;
  capabilities:string[];
  metrics:EvaluationMetric[];
};

export type SecurityCheck = {
  check_key:string;
  status:string;
  title:string;
  description:string;
  evidence:string[];
  reviewed_at:string|null;
};

export type EvaluationSummary = {
  latest_run:EvaluationRun|null;
  comparison:VariantComparison[];
  metric_catalog:Array<{metric_key:string;label:string}>;
  security_review:SecurityCheck[];
};
