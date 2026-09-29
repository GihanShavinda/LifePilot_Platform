<?php
namespace App\Domain\Profiles\Controllers;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Profiles\Requests\UpdateProfileRequest;
use App\Domain\Profiles\Resources\ProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
class ProfileController
{
 public function show(Request $request){$profile=$request->user()->profile()->firstOrCreate([],['first_name'=>$request->user()->name,'locale'=>'en']); return ApiResponse::success(['profile'=>new ProfileResource($profile),'timezone'=>$request->user()->timezone]);}
 public function update(UpdateProfileRequest $request,AuditService $audit){$data=$request->validated();$timezone=$data['timezone']??null;unset($data['timezone']);$profile=$request->user()->profile()->firstOrCreate([],['first_name'=>$request->user()->name,'locale'=>'en']);$profile->fill($data)->save();if($timezone){$request->user()->update(['timezone'=>$timezone]);}$audit->record('profile.updated',$request->user(),$profile,['fields'=>array_keys($request->safe()->except(['timezone']))]);return ApiResponse::success(['profile'=>new ProfileResource($profile),'timezone'=>$request->user()->fresh()->timezone]);}
 public function destroy(Request $request,AuditService $audit){$profile=$request->user()->profile;if($profile){$audit->record('profile.deleted',$request->user(),$profile);$profile->delete();}return ApiResponse::success(['message'=>'Profile details cleared.']);}
}
