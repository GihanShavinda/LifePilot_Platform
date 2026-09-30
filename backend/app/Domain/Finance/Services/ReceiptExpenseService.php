<?php
namespace App\Domain\Finance\Services;
use App\Domain\Documents\Models\{Document,DocumentExtraction};
use App\Domain\Finance\Models\{Expense,Merchant};
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
class ReceiptExpenseService {
 private function reviewed(DocumentExtraction $ex,string $name):?array {
  $field=$ex->fields->first(fn($f)=>$f->field_name===$name && in_array($f->review_status instanceof \BackedEnum?$f->review_status->value:$f->review_status,['accepted','edited'],true));
  if(!$field || !is_string($field->evidence_text) || trim($field->evidence_text)==='')return null;
  $normalized=$field->normalized_value;
  if(is_array($normalized))$normalized=$normalized['value']??null;
  $value=$field->value;
  if(is_array($value))$value=$value['value']??null;
  return ['value'=>$normalized??$value,'evidence'=>$field->evidence_text,'field_id'=>$field->id];
 }
 public function preview(Document $document):array {
  $ex=$document->extractions()->with('fields')->orderByDesc('version_number')->first();
  if(!$ex)return ['eligible'=>false,'reason'=>'No document extraction exists.'];
  $kind=$this->reviewed($ex,'document_type');
  if(!$kind || !in_array(mb_strtolower((string)$kind['value']),['receipt','invoice'],true))return ['eligible'=>false,'reason'=>'Review document_type as receipt before importing.'];
  $amount=$this->reviewed($ex,'amount');$currency=$this->reviewed($ex,'currency');$date=$this->reviewed($ex,'document_date');
  if(!$amount || !$currency || !$date)return ['eligible'=>false,'reason'=>'Accept or edit receipt amount, currency, and document date.'];
  $money=str_replace(',','',(string)$amount['value']);
  $dateValue=(string)$date['value'];$cc=strtoupper((string)$currency['value']);
  if(!is_numeric($money)|| (float)$money<=0 || !preg_match('/^[A-Z]{3}$/',$cc) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateValue) || !checkdate((int)substr($dateValue,5,2),(int)substr($dateValue,8,2),(int)substr($dateValue,0,4)))return ['eligible'=>false,'reason'=>'Reviewed receipt contains an invalid amount, currency or date.'];
  $issuer=$this->reviewed($ex,'issuer');
  return ['eligible'=>true,'document_id'=>$document->id,'extraction_id'=>$ex->id,'title'=>'Receipt · '.($issuer['value']??$document->title),'amount'=>number_format((float)$money,2,'.',''),'currency'=>$cc,'expense_date'=>$dateValue,'merchant_name'=>$issuer['value']??null,'evidence'=>['amount'=>$amount,'currency'=>$currency,'document_date'=>$date,'issuer'=>$issuer]];
 }
 public function accept(Document $document, int $userId):Expense {
  return DB::transaction(function()use($document,$userId){
   $data=$this->preview($document);
   if(!$data['eligible'])throw ValidationException::withMessages(['receipt'=>$data['reason']]);
   // Re-check inside lock to avoid accepting stale/reprocessed extraction.
   $ex=DocumentExtraction::whereKey($data['extraction_id'])->lockForUpdate()->firstOrFail();
   if(Expense::withTrashed()->where('household_id',$document->household_id)->where('document_id',$document->id)->where('source','reviewed_receipt')->exists())throw ValidationException::withMessages(['receipt'=>'A reviewed receipt expense already exists for this document, including previous extraction versions.']);
   $merchant=null;
   if(!empty($data['merchant_name']))$merchant=Merchant::firstOrCreate(['household_id'=>$document->household_id,'normalized_name'=>mb_strtolower(trim((string)$data['merchant_name']))],['name'=>(string)$data['merchant_name']]);
   return Expense::create(['household_id'=>$document->household_id,'user_id'=>$userId,'document_id'=>$document->id,'receipt_extraction_id'=>$ex->id,'merchant_id'=>$merchant?->id,'title'=>$data['title'],'amount'=>$data['amount'],'currency'=>$data['currency'],'expense_date'=>$data['expense_date'],'source'=>'reviewed_receipt']);
  });
 }
}
