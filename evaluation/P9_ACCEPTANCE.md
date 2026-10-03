# P9 Acceptance Checklist

- [ ] Migration creates action_policies, action_plans, action_steps, action_approvals, action_executions and action_results.
- [ ] Safe plan remains in preview and does not create side effects.
- [ ] Unsupported action is rejected.
- [ ] Assistant-origin action without evidence is rejected.
- [ ] Execution before approval is rejected.
- [ ] High-risk approval requires explicit acknowledgement.
- [ ] Duplicate execute request does not duplicate the underlying entity.
- [ ] Failed multi-step database execution rolls back prior side effects.
- [ ] Audit endpoint includes plan, approvals, executions, verification and results.
- [ ] Financial transaction execution is rejected.
- [ ] Action Center loads at /actions.
- [ ] npm run build passes.
- [ ] php83 artisan test --filter=P9 passes.
- [ ] Full backend regression suite passes.
