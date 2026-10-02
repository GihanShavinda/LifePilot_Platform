# P8 Grounded Assistant

## Architecture

User Question -> IntentClassifier -> AssistantAccess -> EvidenceBundleBuilder -> retrieval trace -> AssistantPromptBuilder -> GroundedLlm -> GroundedResponseValidator -> response + citations.

If the model is unavailable, malformed, uncited, cites unknown evidence, injects unsupported dates/amounts, or proposes unsupported draft facts, `DeterministicFallbackResponder` is used instead.

## Evidence sources

P8 reads only records from the current household: obligations, tasks, expenses, subscriptions, assets, warranties, calendar events, accepted/edited extraction fields, P7 document chunks and Life Action Graph entities returned by hybrid search. PostgreSQL source records remain authoritative.

## Document trust

Document snippets and extraction evidence are data, not instructions. The prompt marks them as `<UNTRUSTED_RETRIEVED_CONTENT>`. Prompt-like text inside a document does not change assistant policy.

## Citations

Each model claim must cite evidence keys. The validator resolves those keys against the retrieval bundle. Dates and currency amounts found in claim text are automatically checked against cited evidence even if a model omits them from `factual_values`. Stored assistant messages also include `metadata.citation_details` with source table/document references.

## Draft actions

`generate_draft_task` and `generate_draft_event` return draft metadata only. P8 does not create Task or CalendarEvent records. A later explicit user action can submit a reviewed draft through the existing P4/P6 APIs.

## Deterministic mode

Leave `LIFEPILOT_AI_ENDPOINT=` blank. Search/upcoming answers, per-currency expense calculations and evidence-based draft proposals continue to work without an external LLM.
