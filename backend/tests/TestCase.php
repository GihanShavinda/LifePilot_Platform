<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
abstract class TestCase extends BaseTestCase
{
 protected function setUp():void{parent::setUp();$this->withHeaders(['Accept'=>'application/json','Origin'=>'http://localhost:5174','Referer'=>'http://localhost:5174/']);}
}
