<?php

namespace Tests\Feature;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Documents\Models\ExtractedField;

use App\Domain\Finance\Models\Subscription;

use App\Domain\Users\Models\User;
use App\Domain\Users\Models\Household;
use App\Domain\Users\Models\HouseholdMember;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LifeFinanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an authenticated household member.
     *
     * @return array{0: User, 1: Household}
     */
    private function actor(
        string $role = 'owner'
    ): array {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'Finance home',
        ]);

        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        Sanctum::actingAs($user);

        return [$user, $household];
    }

    /**
     * Create a synthetic receipt and extraction.
     */
    private function receipt(
        User $user,
        Household $household,
        string $review = 'accepted'
    ): Document {
        $document = Document::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'title' => 'Store receipt',
            'original_filename' => 'receipt.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'storage_disk' => 'documents',
            'storage_path' => 'receipt.txt',
            'checksum' => str_repeat('d', 64),
            'status' => 'active',
            'processing_status' => 'ready',
        ]);

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'uploaded_by' => $user->id,
            'version_number' => 1,
            'original_filename' => 'receipt.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'storage_disk' => 'documents',
            'storage_path' => 'receipt.txt',
            'checksum' => str_repeat('d', 64),
        ]);

        $extraction = DocumentExtraction::create([
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'version_number' => 1,
            'status' => 'needs_review',
            'extractor_version' => 'test',
            'source_text' =>
                'Receipt total LKR 123.45 on 2026-09-29 ' .
                'Issuer Example Store',
        ]);

        $fields = [
            'document_type' => 'receipt',
            'amount' => '123.45',
            'currency' => 'LKR',
            'document_date' => '2026-09-29',
            'issuer' => 'Example Store',
        ];

        foreach ($fields as $name => $value) {
            ExtractedField::create([
                'document_extraction_id' => $extraction->id,
                'field_name' => $name,
                'value' => $value,
                'normalized_value' => $value,
                'confidence' => 0.98,
                'page' => 1,
                'evidence_text' => $value,
                'extractor_version' => 'test',
                'review_status' => $review,
                'source' => 'deterministic',
                'fingerprint' => hash(
                    'sha256',
                    $name . $value
                ),
            ]);
        }

        return $document;
    }

    /**
     * Manual expense CRUD and separate currency totals.
     */
    public function test_manual_expense_crud_and_currency_separated_totals(): void
    {
        $this->actor();

        $payload = [
            'title' => 'Lunch',
            'amount' => '100.00',
            'currency' => 'LKR',
            'expense_date' => today()->toDateString(),
        ];

        $id = $this->postJson(
            '/api/v1/finance/expenses',
            $payload
        )
            ->assertCreated()
            ->json('data.expense.id');

        $this->putJson(
            "/api/v1/finance/expenses/{$id}",
            ['title' => 'Dinner']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.expense.title',
                'Dinner'
            );

        $this->postJson(
            '/api/v1/finance/expenses',
            array_merge($payload, [
                'currency' => 'USD',
                'amount' => '5.00',
            ])
        )->assertCreated();

        $totals = $this->getJson(
            '/api/v1/finance/dashboard'
        )
            ->assertOk()
            ->json('data.monthly_totals');

        $this->assertCount(2, $totals);

        $this->deleteJson(
            "/api/v1/finance/expenses/{$id}"
        )->assertOk();

        $this->assertSoftDeleted(
            'expenses',
            ['id' => $id]
        );
    }

    /**
     * Receipt suggestions require human acceptance.
     */
    public function test_receipt_needs_human_review_and_explicit_acceptance_without_duplicates(): void
    {
        [$user, $household] = $this->actor();

        $document = $this->receipt(
            $user,
            $household,
            'pending'
        );

        $url = "/api/v1/documents/{$document->id}/receipt-expense-suggestion";

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath(
                'data.suggestion.eligible',
                false
            );

        $this->postJson(
            "{$url}/accept"
        )->assertUnprocessable();

        $extraction = $document
            ->extractions()
            ->latest('id')
            ->first();

        $extraction->fields()->update([
            'review_status' => 'accepted',
        ]);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath(
                'data.suggestion.eligible',
                true
            );

        $this->assertDatabaseCount(
            'expenses',
            0
        );

        $this->postJson(
            "{$url}/accept"
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.expense.document_id',
                $document->id
            );

        $this->postJson(
            "{$url}/accept"
        )->assertUnprocessable();

        $this->assertDatabaseCount(
            'expenses',
            1
        );
    }

    /**
     * Prevent duplicate subscriptions and track price changes.
     */
    public function test_subscription_duplicate_prevention_and_price_change(): void
    {
        $this->actor();

        $payload = [
            'name' => 'Music',
            'provider' => 'Example Media',
            'price' => '9.99',
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'next_billing_date' =>
                today()->addDays(20)->toDateString(),
        ];

        $id = $this->postJson(
            '/api/v1/finance/subscriptions',
            $payload
        )
            ->assertCreated()
            ->json('data.subscription.id');

        $this->postJson(
            '/api/v1/finance/subscriptions',
            $payload
        )->assertUnprocessable();

        $this->putJson(
            "/api/v1/finance/subscriptions/{$id}",
            ['price' => '12.99']
        )->assertOk();

        $this->assertEquals(
            '9.99',
            Subscription::find($id)->previous_price
        );

        $this->deleteJson(
            "/api/v1/finance/subscriptions/{$id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.subscription.status',
                'cancelled'
            );
    }

    /**
     * Check warranty timing and household isolation.
     */
    public function test_warranty_calculation_and_document_access(): void
    {
        $this->actor();

        $assetId = $this->postJson(
            '/api/v1/finance/assets',
            ['name' => 'Laptop']
        )
            ->assertCreated()
            ->json('data.asset.id');

        $this->postJson(
            "/api/v1/finance/assets/{$assetId}/warranties",
            [
                'provider' => 'Example Warranty',
                'start_date' => today()->toDateString(),
                'end_date' =>
                    today()->addDays(20)->toDateString(),
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.warranty.status',
                'expiring'
            );

        $this->actor();

        $this->getJson(
            '/api/v1/finance/assets'
        )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.assets.data'
            );

        $this->putJson(
            "/api/v1/finance/assets/{$assetId}",
            ['name' => 'Changed']
        )->assertNotFound();
    }

    /**
     * Prevent linking documents from other households.
     */
    public function test_linked_document_must_be_same_household(): void
    {
        [$user, $household] = $this->actor();

        $document = $this->receipt(
            $user,
            $household
        );

        $this->actor();

        $this->postJson(
            '/api/v1/finance/expenses',
            [
                'title' => 'Bad link',
                'amount' => 10,
                'currency' => 'LKR',
                'expense_date' =>
                    today()->toDateString(),
                'document_id' => $document->id,
            ]
        )->assertNotFound();
    }

    /**
     * Viewers can read but cannot modify finances.
     */
    public function test_read_only_member_cannot_modify_finances(): void
    {
        $this->actor('viewer');

        $this->getJson(
            '/api/v1/finance/expenses'
        )->assertOk();

        $this->postJson(
            '/api/v1/finance/expenses',
            [
                'title' => 'Private',
                'amount' => 10,
                'currency' => 'LKR',
                'expense_date' =>
                    today()->toDateString(),
            ]
        )->assertForbidden();
    }

    /**
     * Recurring expenses remain suggestions until confirmed.
     */
    public function test_recurring_expenses_are_suggestions_until_confirmed(): void
    {
        $this->actor();

        $id = $this->postJson(
            '/api/v1/finance/expenses',
            [
                'title' => 'Gym',
                'amount' => 100,
                'currency' => 'LKR',
                'expense_date' =>
                    today()->toDateString(),
                'recurrence_cycle' => 'monthly',
            ]
        )
            ->assertCreated()
            ->json('data.expense.id');

        $this->assertDatabaseCount(
            'expenses',
            1
        );

        $this->getJson(
            "/api/v1/finance/expenses/{$id}/recurrence"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.suggestion.eligible',
                true
            );

        $this->postJson(
            "/api/v1/finance/expenses/{$id}/recurrence/confirm"
        )->assertCreated();

        $this->assertDatabaseCount(
            'expenses',
            2
        );
    }

    /**
     * Confirmed subscription payments advance the billing cycle.
     */
    public function test_confirmed_subscription_payment_advances_cycle_without_fabricating_expenses(): void
    {
        $this->actor();

        $date = today()
            ->addDays(20)
            ->toDateString();

        $payload = [
            'name' => 'Software service',
            'provider' => 'Example Cloud',
            'price' => 120,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'next_billing_date' => $date,
        ];

        $id = $this->postJson(
            '/api/v1/finance/subscriptions',
            $payload
        )
            ->assertCreated()
            ->json('data.subscription.id');

        $this->assertDatabaseCount(
            'expenses',
            0
        );

        $this->postJson(
            "/api/v1/finance/subscriptions/{$id}/payments",
            [
                'amount' => 120,
                'billing_date' => $date,
            ]
        )->assertCreated();

        $this->assertDatabaseCount(
            'expenses',
            1
        );

        $nextDate = app(
            \App\Domain\Finance\Services\FinanceCalculations::class
        )->nextDate(
            $date,
            'monthly'
        );

        $this->assertSame(
            $nextDate,
            Subscription::find($id)
                ->next_billing_date
                ->toDateString()
        );

        $this->postJson(
            "/api/v1/finance/subscriptions/{$id}/payments",
            [
                'amount' => 120,
                'billing_date' => $date,
            ]
        )->assertUnprocessable();
    }

    /**
     * CSV imports are deduplicated within the household.
     */
    public function test_csv_import_is_deduplicated_and_household_scoped(): void
    {
        $this->actor();

        $data =
            "date,title,amount,currency,merchant,category,description\n" .
            "2026-09-15,Lunch,100.50,LKR,Example Cafe,Food,Meal\n";

        $file = UploadedFile::fake()
            ->createWithContent(
                'expenses.csv',
                $data
            );

        $this->post(
            '/api/v1/finance/expenses/import',
            ['file' => $file]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.created',
                1
            );

        $file2 = UploadedFile::fake()
            ->createWithContent(
                'expenses.csv',
                $data
            );

        $this->post(
            '/api/v1/finance/expenses/import',
            ['file' => $file2]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.skipped_duplicates',
                1
            );

        $this->assertDatabaseCount(
            'expenses',
            1
        );

        $this->assertDatabaseCount(
            'expense_categories',
            1
        );
    }
}