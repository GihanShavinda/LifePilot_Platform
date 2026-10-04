<?php
namespace App\Domain\Obligations\Controllers;

use App\Domain\Documents\Models\Document;
use App\Domain\Obligations\Enums\ObligationType;
use App\Domain\Obligations\Models\Obligation;
use App\Domain\Obligations\Services\{ObligationService,ObligationSuggestionService};
use App\Support\ApiResponse;
use Illuminate\Http\{JsonResponse,Request};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rules\Enum;

class ObligationController {
 private function household(Request $request, bool $write=false):int {
  $membership=$request->user()->householdMemberships()->first();
  if(!$membership) throw new AuthorizationException('Household required.');
  $role=$membership->role instanceof \BackedEnum?$membership->role->value:(string)$membership->role;
  if($write && !in_array($role,['owner','admin','member','family_member'],true)) throw new AuthorizationException('Read-only household role.');
  return $membership->household_id;
 }
 public function index(Request $request):JsonResponse {
  $id=$this->household($request);
  $query=Obligation::where('household_id',$id)->where('user_id',$request->user()->id)->latest();
  if($request->filled('status'))$query->where('status',$request->query('status'));
  return ApiResponse::success(['obligations'=>$query->paginate(30)]);
 }
 public function suggestions(Request $request,int $documentId,ObligationSuggestionService $suggestions):JsonResponse {
  $id=$this->household($request);
  $doc=Document::accessibleTo($request->user())->where('household_id',$id)->with('category')->findOrFail($documentId);
  $results=$suggestions->suggest($doc);
  if($results && \Illuminate\Support\Facades\Cache::add('p6-recommendation-event:'.$request->user()->id.':'.hash('sha256',json_encode($results)),1,now()->addHours(12)))event(new \App\Domain\Scheduling\Events\LifePilotUpdated($request->user()->id,'recommendation.ready',['document_id'=>$doc->id]));
  return ApiResponse::success(['suggestions'=>$results]);
 }
 public function store(Request $request,ObligationService $service):JsonResponse {
  $household=$this->household($request,true);
  $data=$request->validate([
   'type'=>['required',new Enum(ObligationType::class)],'title'=>'required|string|max:255',
   'description'=>'nullable|string|max:5000','amount'=>'nullable|numeric|min:0',
   'currency'=>'nullable|string|size:3','due_at'=>'nullable|date|after:now',
   'document_id'=>'nullable|integer',
  ]);
  return ApiResponse::success(['obligation'=>$service->createManual($request->user(),$household,$data)],201);
 }
 public function approve(Request $request,int $id,ObligationService $service,ObligationSuggestionService $suggestions):JsonResponse {
  $household=$this->household($request,true);
  $obligation=Obligation::where('household_id',$household)->where('user_id',$request->user()->id)->findOrFail($id);
  $task=$service->approve($request->user(),$obligation);
  return ApiResponse::success(['task'=>$task->load('reminders')],201);
 }
 public function approveSuggestion(Request $request,int $documentId,ObligationSuggestionService $suggestions,ObligationService $service):JsonResponse {
  $household=$this->household($request,true);
  $doc=Document::accessibleTo($request->user())->where('household_id',$household)->with('category')->findOrFail($documentId);
  $suggested=collect($suggestions->suggest($doc))->firstWhere('dedupe_key',$request->validate(['dedupe_key'=>'required|string|size:64'])['dedupe_key']);
  if(!$suggested) return ApiResponse::error('STALE_SUGGESTION','Refresh suggestions; selected suggestion is no longer available.',409);
  // Create-and-approve is one explicit user action; no background job can invoke this endpoint.
  $obligation=$service->createManual($request->user(),$household,array_intersect_key($suggested,array_flip(['type','title','due_at','amount','currency','document_id'])));
  $obligation->update(['document_extraction_id'=>$suggested['document_extraction_id'],'source_evidence'=>$suggested['source_evidence']]);
  return ApiResponse::success(['obligation'=>$obligation,'task'=>$service->approve($request->user(),$obligation)],201);
 }
 public function dismiss(Request $request,int $id):JsonResponse {
  $h=$this->household($request,true);
  $obligation=Obligation::where('household_id',$h)->where('user_id',$request->user()->id)->findOrFail($id);
  if($obligation->status!=='suggested')return ApiResponse::error('INVALID_STATE','Only suggestions may be dismissed.',409);
  $obligation->update(['status'=>'dismissed']);
  return ApiResponse::success(['obligation'=>$obligation]);
 }
}
