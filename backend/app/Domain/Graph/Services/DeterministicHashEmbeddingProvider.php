<?php
namespace App\Domain\Graph\Services;

use App\Domain\Graph\Contracts\EmbeddingProvider;

class DeterministicHashEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(private int $size=384){}
    public function dimensions():int{return $this->size;}
    public function model():string{return 'lifepilot-hash-embedding-v1';}
    public function embed(string $text):array
    {
        $v=array_fill(0,$this->size,0.0);
        $tokens=preg_split('/[^\\pL\\pN]+/u',mb_strtolower($text),-1,PREG_SPLIT_NO_EMPTY) ?: [];
        foreach($tokens as $token){
            $hash=hash('sha256',$token,true);
            $i=unpack('N',substr($hash,0,4))[1] % $this->size;
            $sign=(ord($hash[4]) & 1) ? 1.0 : -1.0;
            $v[$i]+=$sign*(1.0+min(strlen($token),20)/20);
        }
        $norm=sqrt(array_sum(array_map(fn($x)=>$x*$x,$v)));
        if($norm>0){foreach($v as $i=>$x)$v[$i]=$x/$norm;}
        return $v;
    }
}
