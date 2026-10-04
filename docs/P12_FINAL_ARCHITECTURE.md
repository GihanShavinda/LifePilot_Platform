# LifePilot AI — Final Architecture

## System architecture

```mermaid
flowchart TB
    U[Web User] --> FE[React + TypeScript + Vite]
    FE -->|Sanctum / CSRF| API[Laravel 12 API]

    API --> AUTH[Auth / Profile / RBAC]
    API --> DOC[Document Evidence Domain]
    API --> TASK[Tasks & Obligations]
    API --> FIN[Finance / Assets / Subscriptions]
    API --> CAL[Calendar / Notifications]
    API --> COL[Household Collaboration]
    API --> GRAPH[Life Action Graph + Hybrid Search]
    API --> ASST[Grounded Assistant]
    API --> ACT[Safe Agentic Actions]
    API --> ANA[Explainable Analytics]
    API --> P12[P12 Dashboards / Evaluation / Reporting]

    DOC --> FILES[(Private document storage)]
    AUTH --> DB[(PostgreSQL)]
    DOC --> DB
    TASK --> DB
    FIN --> DB
    CAL --> DB
    COL --> DB
    GRAPH --> DB
    ASST --> DB
    ACT --> DB
    ANA --> DB
    P12 --> DB

    CAL --> REDIS[(Redis Queue / Cache)]
    CAL --> REV[Laravel Reverb]
    REV --> FE

    DOC --> PIPE[OCR / deterministic extraction / review]
    PIPE --> GRAPH
    GRAPH --> RET[Sharing-aware retrieval]
    RET --> ASST
    ASST --> VALID[Grounded response validation]
    VALID --> FE
    ASST --> ACT
    ACT --> POLICY[Policy + evidence + risk]
    POLICY --> PREVIEW[Preview]
    PREVIEW --> APPROVE[User approval]
    APPROVE --> EXEC[Idempotent executor]
    EXEC --> AUDIT[Verification + audit]

    ANA --> P12
    DOC --> P12
    TASK --> P12
    FIN --> P12
    CAL --> P12
    ASST --> P12
    ACT --> P12
```

## Trust boundaries

1. Browser input is untrusted and validated by Laravel requests/controllers.
2. Uploaded documents are **evidence, not instructions**.
3. Retrieved text is untrusted before it enters the grounded-assistant prompt.
4. Same-household membership does not imply access to another member's private resources.
5. AI recommendations do not execute high-impact actions directly.
6. P9 action execution requires preview, policy/evidence validation, risk classification and approval.
7. P12 evaluation refuses to create benchmark scores when labeled ground truth is absent.

## Core technology

- Frontend: React, TypeScript, Vite, React Router.
- API: Laravel 12 / PHP 8.3.
- Authentication: Laravel Sanctum.
- Database: PostgreSQL.
- Queue/cache: Redis.
- Realtime: Laravel Reverb.
- Search: deterministic embeddings / hybrid retrieval with pgvector optional and PHP cosine fallback.
- AI: provider adapter behind a grounded validation layer; deterministic fallback remains available.
