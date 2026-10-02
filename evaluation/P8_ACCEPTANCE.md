# P8 Acceptance

- conversation sessions/messages persist
- retrieval traces persist household-scoped evidence
- model/provider/version metadata persists
- feedback persists per assistant message
- every validated factual claim has known citations
- unsupported amount/date output is rejected
- conflicting accepted evidence can be reported only with both citations
- other-household data never enters retrieval traces
- prompt-like retrieved text is marked untrusted
- no-evidence questions produce deterministic no-evidence responses
- draft task/event responses do not create actions
- deterministic calculation never combines currencies
- `/assistant` is behind ProtectedRoute
- P8 tests pass and full P1-P8 regression suite passes
