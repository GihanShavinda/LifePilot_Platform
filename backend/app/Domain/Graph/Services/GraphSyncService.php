<?php
namespace App\Domain\Graph\Services;

use App\Domain\Graph\Enums\{LifeEntityType as E,LifeRelationType as R};
use App\Domain\Graph\Models\LifeEntity;
use App\Domain\Documents\Models\Document;
use App\Domain\Obligations\Models\{Obligation,Task};
use App\Domain\Finance\Models\{Expense,Subscription,Asset,Warranty};
use App\Domain\Scheduling\Models\CalendarEvent;
use App\Domain\Users\Models\User;

class GraphSyncService
{
    public function __construct(private LifeGraph $graph,private EntityMatcher $matcher){}
    public function syncHousehold(int $householdId):array
    {
        $counts=['entities'=>0,'relations'=>0];
        foreach(User::whereHas('householdMemberships',fn($q)=>$q->where('household_id',$householdId))->get() as $u){
            $this->graph->entity($householdId,E::User->value,'user',$u->id,$u->name,['email'=>$u->email],['type'=>'user','id'=>$u->id]); $counts['entities']++;
        }
        foreach(Document::where('household_id',$householdId)->get() as $d){
            $de=$this->graph->entity($householdId,E::Document->value,'document',$d->id,$d->title ?: $d->original_filename,['mime_type'=>$d->mime_type,'status'=>$d->status],['type'=>'document','document_id'=>$d->id]); $counts['entities']++;
            $ex=$d->extractions()->latest('version_number')->first();
            if($ex){
                foreach($ex->fields()->whereIn('review_status',['accepted','edited'])->get() as $f){
                    $value=trim((string)($f->normalized_value ?: $f->value)); if($value==='')continue;
                    if(in_array($f->field_name,['issuer','organization_names'],true)){$vals=$this->values($value);foreach($vals as $v){$o=$this->matcher->findOrCreateNamed($householdId,E::Organization->value,$v,['type'=>'extracted_field','document_id'=>$d->id,'field_id'=>$f->id]);$this->graph->relate($householdId,$de,$o,R::IssuedBy->value,[],['type'=>'extracted_field','document_id'=>$d->id,'field_id'=>$f->id]);$counts['relations']++;}}
                    if($f->field_name==='person_names'){foreach($this->values($value) as $v){$p=$this->matcher->findOrCreateNamed($householdId,E::Person->value,$v,['type'=>'extracted_field','document_id'=>$d->id,'field_id'=>$f->id]);$this->graph->relate($householdId,$de,$p,R::RefersTo->value,[],['type'=>'extracted_field','document_id'=>$d->id,'field_id'=>$f->id]);$counts['relations']++;}}
                    if(in_array($f->field_name,['addresses','location'],true)){foreach($this->values($value) as $v){$l=$this->matcher->findOrCreateNamed($householdId,E::Location->value,$v,['type'=>'extracted_field','document_id'=>$d->id,'field_id'=>$f->id]);$this->graph->relate($householdId,$de,$l,R::RefersTo->value,[],['type'=>'extracted_field','document_id'=>$d->id,'field_id'=>$f->id]);$counts['relations']++;}}
                }
            }
        }
        foreach(Obligation::where('household_id',$householdId)->get() as $o){$oe=$this->graph->entity($householdId,E::Obligation->value,'obligation',$o->id,$o->title,['type'=>$o->type,'due_at'=>$o->due_at?->toIso8601String(),'status'=>$o->status],['type'=>'obligation','id'=>$o->id,'document_id'=>$o->document_id]);$counts['entities']++;if($o->document_id && ($de=$this->source($householdId,'document',$o->document_id))) {$this->graph->relate($householdId,$de,$oe,R::CreatesObligation->value,[],['type'=>'document','document_id'=>$o->document_id]);$counts['relations']++;}}
        foreach(Task::where('household_id',$householdId)->get() as $t){$te=$this->graph->entity($householdId,E::Task->value,'task',$t->id,$t->title,['priority'=>$t->priority,'status'=>$t->effectiveStatus(),'due_at'=>$t->due_at?->toIso8601String(),'labels'=>$t->labels,'document_title'=>$t->document?->title],['type'=>'task','id'=>$t->id,'document_id'=>$t->document_id]);$counts['entities']++;if($t->obligation_id && ($oe=$this->source($householdId,'obligation',$t->obligation_id))){$this->graph->relate($householdId,$oe,$te,R::GeneratesTask->value);$counts['relations']++;}if($t->document_id && ($de=$this->source($householdId,'document',$t->document_id))){$this->graph->relate($householdId,$te,$de,R::EvidencedBy->value,[],['type'=>'document','document_id'=>$t->document_id]);$counts['relations']++;}if($t->completed_at && ($ue=$this->source($householdId,'user',$t->user_id))){$this->graph->relate($householdId,$te,$ue,R::CompletedBy->value,[],['type'=>'task','id'=>$t->id]);$counts['relations']++;}}
        foreach(CalendarEvent::where('household_id',$householdId)->get() as $e){$ee=$this->graph->entity($householdId,E::Event->value,'calendar_event',$e->id,$e->title,['starts_at'=>$e->starts_at?->toIso8601String(),'ends_at'=>$e->ends_at?->toIso8601String(),'timezone'=>$e->timezone],['type'=>'calendar_event','id'=>$e->id]);$counts['entities']++; if($e->task_id && ($te=$this->source($householdId,'task',$e->task_id))){$this->graph->relate($householdId,$te,$ee,R::ScheduledAs->value);$counts['relations']++;} if($e->document_id && ($de=$this->source($householdId,'document',$e->document_id))){$this->graph->relate($householdId,$ee,$de,R::EvidencedBy->value);$counts['relations']++;}}
        foreach(Expense::where('household_id',$householdId)->get() as $x){$xe=$this->graph->entity($householdId,E::Expense->value,'expense',$x->id,$x->title,['amount'=>$x->amount,'currency'=>$x->currency,'expense_date'=>$x->expense_date?->toDateString()],['type'=>'expense','id'=>$x->id,'document_id'=>$x->document_id]);$counts['entities']++;if($x->merchant){$m=$this->matcher->findOrCreateNamed($householdId,E::Organization->value,$x->merchant->name,['type'=>'merchant','id'=>$x->merchant->id]);$this->graph->relate($householdId,$xe,$m,R::PaidTo->value);$counts['relations']++;}if($x->document_id && ($de=$this->source($householdId,'document',$x->document_id))){$this->graph->relate($householdId,$xe,$de,R::EvidencedBy->value);$counts['relations']++;}}
        foreach(Subscription::where('household_id',$householdId)->get() as $s){$se=$this->graph->entity($householdId,E::Subscription->value,'subscription',$s->id,$s->name,['provider'=>$s->provider,'price'=>$s->price,'currency'=>$s->currency,'next_billing_date'=>$s->next_billing_date?->toDateString(),'status'=>$s->status],['type'=>'subscription','id'=>$s->id,'document_id'=>$s->document_id]);$counts['entities']++;if($s->provider){$p=$this->matcher->findOrCreateNamed($householdId,E::Organization->value,$s->provider,['type'=>'subscription_provider']);$this->graph->relate($householdId,$se,$p,R::PaidTo->value);$counts['relations']++;}}
        foreach(Asset::where('household_id',$householdId)->get() as $a){$ae=$this->graph->entity($householdId,E::Asset->value,'asset',$a->id,$a->name,['brand'=>$a->brand,'model'=>$a->model,'serial_number'=>$a->serial_number,'location'=>$a->location,'status'=>$a->status],['type'=>'asset','id'=>$a->id,'document_id'=>$a->document_id]);$counts['entities']++;foreach(User::whereHas('householdMemberships',fn($q)=>$q->where('household_id',$householdId))->limit(1)->get() as $u){if($ue=$this->source($householdId,'user',$u->id)){$this->graph->relate($householdId,$ue,$ae,R::Owns->value);$counts['relations']++;}}if($a->location){$l=$this->matcher->findOrCreateNamed($householdId,E::Location->value,$a->location,['type'=>'asset_location','asset_id'=>$a->id]);$this->graph->relate($householdId,$ae,$l,R::RelatedTo->value);$counts['relations']++;}if($a->document_id && ($de=$this->source($householdId,'document',$a->document_id))){$this->graph->relate($householdId,$ae,$de,R::EvidencedBy->value,[],['type'=>'document','document_id'=>$a->document_id]);$counts['relations']++;}}
        foreach(Warranty::where('household_id',$householdId)->get() as $w){$we=$this->graph->entity($householdId,E::Warranty->value,'warranty',$w->id,'Warranty: '.$w->provider,['provider'=>$w->provider,'start_date'=>$w->start_date?->toDateString(),'end_date'=>$w->end_date?->toDateString(),'status'=>$w->status],['type'=>'warranty','id'=>$w->id,'document_id'=>$w->proof_document_id]);$counts['entities']++;if($ae=$this->source($householdId,'asset',$w->asset_id)){$this->graph->relate($householdId,$ae,$we,R::HasWarranty->value);$counts['relations']++;}if($w->proof_document_id && ($de=$this->source($householdId,'document',$w->proof_document_id))){$this->graph->relate($householdId,$we,$de,R::EvidencedBy->value);$counts['relations']++;}}
        return $counts;
    }
    private function source(int $h,string $sourceType,int $sourceId):?LifeEntity{return LifeEntity::where(['household_id'=>$h,'source_type'=>$sourceType,'source_id'=>(string)$sourceId])->first();}
    private function values(string $v):array{$decoded=json_decode($v,true); if(is_array($decoded))return array_values(array_filter(array_map('strval',$decoded))); return array_values(array_filter(array_map('trim',preg_split('/[;|\\n]+/',$v) ?: [$v])));}
}
