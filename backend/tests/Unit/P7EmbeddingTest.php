<?php
namespace Tests\Unit;
use App\Domain\Graph\Services\DeterministicHashEmbeddingProvider;
use PHPUnit\Framework\TestCase;
class P7EmbeddingTest extends TestCase
{
 public function test_embedding_is_deterministic_and_384_dimensions():void{$p=new DeterministicHashEmbeddingProvider(384);$a=$p->embed('laptop warranty receipt');$b=$p->embed('laptop warranty receipt');$this->assertCount(384,$a);$this->assertSame($a,$b);}
}
