<?php
namespace App\Domain\Graph\Contracts;
interface EmbeddingProvider
{
    public function dimensions():int;
    public function model():string;
    /** @return array<int,float> */ public function embed(string $text):array;
}
