<?php
namespace Tests\Unit;
use App\Domain\Graph\Services\EntityMatcher;
use PHPUnit\Framework\TestCase;
class P7EntityMatcherTest extends TestCase
{
 public function test_duplicate_person_org_normalization():void{$m=new EntityMatcher();$this->assertSame($m->canonical('organization','Example University'),$m->canonical('organization',' example   university '));$this->assertSame($m->canonical('person','Jane A. Doe'),$m->canonical('person','Jane A Doe'));}
}
