<?php

namespace Tests\Unit;

use App\Domain\Actions\Enums\{ActionRiskLevel, ActionType};
use App\Domain\Actions\Services\ActionPolicyEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class P9ActionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_task_is_low_risk_and_integration_request_is_high_risk(): void
    {
        $engine = app(ActionPolicyEngine::class);

        $this->assertSame(ActionRiskLevel::Low, $engine->policy(ActionType::CreateTask)->risk_level);
        $this->assertSame(ActionRiskLevel::High, $engine->policy(ActionType::RequestIntegrationAction)->risk_level);
        $this->assertTrue($engine->policy(ActionType::RequestIntegrationAction)->requires_explicit_high_risk_ack);
    }

    public function test_financial_execution_is_out_of_scope(): void
    {
        $this->expectException(ValidationException::class);

        app(ActionPolicyEngine::class)->validatePayload(
            ActionType::RequestIntegrationAction,
            ['integration' => 'bank', 'operation' => 'make_payment']
        );
    }
}
