<?php
namespace App\Domain\Graph\Services;

use App\Domain\Graph\Models\LifeEntity;

class EntityMatcher
{
    public function normalize(string $value):string
    {
        $value=mb_strtolower(trim($value));
        $value=preg_replace('/[^\\pL\\pN]+/u',' ',$value) ?? $value;
        return trim(preg_replace('/\\s+/',' ',$value) ?? $value);
    }
    public function canonical(string $type,string $value):string{return $type.':'.$this->normalize($value);}
    public function findOrCreateNamed(int $householdId,string $type,string $label,array $source=[]):LifeEntity
    {
        $key=$this->canonical($type,$label);
        return LifeEntity::firstOrCreate(
            ['household_id'=>$householdId,'canonical_key'=>$key],
            ['entity_type'=>$type,'label'=>trim($label),'search_text'=>$this->normalize($label),'metadata'=>[],'source_reference'=>$source]
        );
    }
}
