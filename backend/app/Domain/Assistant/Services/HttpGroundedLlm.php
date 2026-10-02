<?php
namespace App\Domain\Assistant\Services;
use App\Domain\Assistant\Contracts\GroundedLlm;
use Illuminate\Support\Facades\Http;
class HttpGroundedLlm implements GroundedLlm
{
    public function generate(array $payload):?array
    {
        $url=(string)config('lifepilot_ai.endpoint','');
        if($url==='')return null;
        try{
            $req=Http::timeout((int)config('lifepilot_ai.timeout',25))->acceptJson();
            $key=(string)config('lifepilot_ai.api_key','');
            if($key!=='')$req=$req->withToken($key);
            $response=$req->post($url,['model'=>$this->model(),'input'=>$payload,'response_format'=>'json']);
            if(!$response->successful())return null;
            $json=$response->json();
            if(isset($json['answer'])&&is_array($json))return $json;
            if(isset($json['output'])&&is_array($json['output']))return $json['output'];
            return null;
        }catch(\Throwable){return null;}
    }
    public function provider():string{return (string)config('lifepilot_ai.provider','http');}
    public function model():string{return (string)config('lifepilot_ai.model','grounded-assistant');}
    public function version():string{return (string)config('lifepilot_ai.model_version','1');}
}
