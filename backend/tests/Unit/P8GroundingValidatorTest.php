<?php
namespace Tests\Unit;
use App\Domain\Assistant\Services\GroundedResponseValidator;
use Tests\TestCase;
class P8GroundingValidatorTest extends TestCase
{
 public function test_hallucinated_amount_is_rejected():void{$v=new GroundedResponseValidator();$e=[['key'=>'expense:1','facts'=>['amount'=>'100.00','currency'=>'LKR']]];$r=$v->validate(['answer'=>'It cost 999.00','claims'=>[['text'=>'It cost 999.00','citations'=>['expense:1'],'factual_values'=>['999.00']]]],$e);$this->assertFalse($r['valid']);$this->assertSame('unsupported_value',$r['reason']);}
 public function test_fake_deadline_is_rejected():void{$v=new GroundedResponseValidator();$e=[['key'=>'task:1','facts'=>['due_at'=>'2026-10-10T10:00:00+00:00']]];$r=$v->validate(['answer'=>'Due Oct 30','claims'=>[['text'=>'Due Oct 30','citations'=>['task:1'],'factual_values'=>['2026-10-30']]]],$e);$this->assertFalse($r['valid']);}
 public function test_unknown_citation_is_rejected():void{$v=new GroundedResponseValidator();$r=$v->validate(['answer'=>'x','claims'=>[['text'=>'x','citations'=>['document:999'],'factual_values'=>[]]]],[]);$this->assertFalse($r['valid']);}
}
