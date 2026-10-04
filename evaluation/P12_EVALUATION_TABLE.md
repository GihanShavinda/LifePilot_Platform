# P12 Evaluation Table

P12 deliberately leaves benchmark values blank until labeled cases or valid operational samples exist.

| Metric | Rules/manual | Rules + extraction | Rules + RAG | Full agentic | Measurement requirement |
|---|---:|---:|---:|---:|---|
| Extraction Accuracy | N/A | Not measured | Not measured | Not measured | Labeled expected extraction outputs |
| Field Precision | N/A | Not measured | Not measured | Not measured | TP/FP field labels |
| Field Recall | N/A | Not measured | Not measured | Not measured | TP/FN field labels |
| Document Classification Accuracy | N/A | Not measured | Not measured | Not measured | Labeled document classes |
| OCR Success Rate | N/A | Not measured | Not measured | Not measured | Defined OCR success benchmark |
| Task Suggestion Precision | N/A | Not measured | Not measured | Not measured | Human-labeled suggestion relevance |
| Deadline Extraction Accuracy | N/A | Not measured | Not measured | Not measured | Labeled deadline values |
| Retrieval Precision@K | N/A | N/A | Not measured | Not measured | Relevance labels per query |
| Retrieval Recall@K | N/A | N/A | Not measured | Not measured | Relevant-document ground truth |
| Citation Accuracy | N/A | N/A | Not measured | Not measured | Claim-to-source labels |
| Hallucination Rate | N/A | N/A | Not measured | Not measured | Factual validity labels |
| Action Approval Accuracy | N/A | N/A | N/A | Not measured | Human-defined correct approval decision |
| Action Execution Success Rate | N/A | N/A | N/A | Operationally measurable | P9 executions |
| Notification Delivery Success | N/A | N/A | N/A | Operationally measurable | P6 delivery records |
| AI Response Latency | N/A | N/A | Measurable after P12 instrumentation | Measurable after P12 instrumentation | End-to-end message timing |

Run `POST /api/v1/evaluation/run` after loading benchmark cases to populate the final comparison.
