<?php
namespace App\Domain\Graph\Services;
class DocumentChunker
{
    /** @return array<int,string> */
    public function chunk(string $text,int $size=900,int $overlap=150):array
    {
        $text=trim(preg_replace('/\\s+/u',' ',$text) ?? $text); if($text==='')return [];
        $out=[];$len=mb_strlen($text);$start=0;
        while($start<$len){$part=mb_substr($text,$start,$size); if($part==='')break; $out[]=trim($part); if($start+$size >= $len)break; $start+=max(1,$size-$overlap);}
        return array_values(array_filter($out));
    }
}
