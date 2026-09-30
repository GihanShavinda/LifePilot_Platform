# P5 Life Finance integration

P5 adds **recorded** expenses, manually confirmed receipt imports, tracked subscriptions and confirmed subscription payments, assets, warranties and maintenance. Charge detection is a suggestion, not a transaction. Nothing calls providers or cancels subscriptions.

## Merge order

Merge P5 on top of your **working local P4**. Keep local changes to the P3 deterministic parser, your green theme, and your existing `.env`. The included `backend/routes/api.php`, `backend/routes/console.php`, `frontend/src/app/router.tsx`, dashboard, and stylesheet are based on the supplied P4 ZIP; inspect diffs if you've changed them locally.

## First-time commands (Windows)

```powershell
cd 'F:\My_Projects\Lifepilot_Platform\lifepilot\backend'
Set-Alias php83 'C:\php-8.3.35-nts-Win32-vs16-x64\php.exe'
docker start lifepilot_redis
php83 artisan migrate
php83 artisan optimize:clear
php83 artisan route:list --path=api/v1/finance
php83 artisan test --filter=LifeFinance
php83 artisan test
```

## Daily terminals

1. Laravel: `php83 artisan serve --host=localhost --port=8000`
2. Queue (with Poppler and Tesseract paths set): `php83 artisan queue:work --tries=3 --timeout=180 -v`
3. Scheduler: `php83 artisan schedule:work`
4. React from `frontend`: `npm install; npm run dev` (expect localhost:5174 based on your local config)
5. Test/debug: `php83 artisan test --filter=LifeFinance` or `php83 artisan tinker`

Open `/finance`. Receipt imports can be loaded directly at `/finance?document_id=<ID>`, provided the latest P3 extraction is a reviewed receipt with accepted/edited `document_type`, `amount`, `currency`, and `document_date`. **No automatic expense is created.**

## Rules and limitations

- Households isolate all finance resources; only owners and family members mutate; document attachments must be in the same household.
- Spending totals group by currency, **not** converted into one figure. Budget values are category placeholders; asset purchase prices are **not** current values.
- Recurrence cost is a forecast. To record a recurring expense occurrence, choose *Confirm next*; subscription charges require *Record paid amount*.
- Warranties and maintenance generate P4 reminder tasks only for future deadlines. Subscription tracking can be stopped but the external subscription is never cancelled.
- CSV column order: `date,title,amount,currency,merchant,category,description`. Max 1000 rows and 2 MiB; import checks header, formats and deduplicates using a content key per household.
- Recurring charge detection groups multiple expenses for a merchant. **It is only a candidate signal; it does not establish regular cadence, create a subscription, or charge a card.**
- To import from documents, the reviewed field values must be semantically correct; UI approval is still required. If your P3 parser mistakenly labels a bill as a receipt, correct that review before import.
- `php83 artisan schedule:list` should include the P5 daily `RefreshLifeFinanceReminders` job as well as P4 recurrence/reminder jobs.

## Acceptance steps

1. Create categories; add two expenses in different currencies; confirm totals are currency-separated.
2. Import a reviewed P3 receipt; ensure the suggestion alone creates nothing. Confirm once and verify duplicate protection.
3. Create a recurring expense; confirm next occurrence manually.
4. Add a monthly subscription; verify the P4 renewal reminder task, update price, inspect change insight, and record a confirmed payment.
5. Verify duplicate subscription rejection, cancellation *tracking only*, and household isolation.
6. Add an asset and a warranty ending in 20 days; confirm expiring state and its linked reminder task.
7. Add a maintenance deadline and inspect the P4 task list.
8. Export CSV, import into a separate household for a round-trip test, then re-import and verify duplicate skips.
9. Run targeted and full backend tests; run `npm run build` on your Windows installation.

There is no actual payment gateway, price-feed integration, bank sync, market asset valuation, provider cancellation or push/email delivery in P5.
