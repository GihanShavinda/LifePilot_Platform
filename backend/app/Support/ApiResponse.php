<?php
namespace App\Support;
use Illuminate\Http\JsonResponse;
class ApiResponse
{
 public static function success(mixed $data=null,int $status=200,array $meta=[]): JsonResponse { $body=['success'=>true,'data'=>$data]; if($meta)$body['meta']=$meta; return response()->json($body,$status); }
 public static function error(string $code,string $message,int $status,array $details=[]): JsonResponse { return response()->json(['success'=>false,'error'=>['code'=>$code,'message'=>$message,'details'=>$details ?: (object)[]]],$status); }
}
