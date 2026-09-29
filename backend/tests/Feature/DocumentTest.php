<?php
namespace Tests\Feature;
use App\Domain\Documents\Models\Document;
use App\Domain\Users\Models\Household;
use App\Domain\Users\Models\HouseholdMember;
use App\Domain\Users\Models\User;
use Database\Seeders\DocumentReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class DocumentTest extends TestCase
{
 use RefreshDatabase;
 private function actor(string $role='owner'):User{$this->seed(DocumentReferenceSeeder::class);$u=User::factory()->create();$h=Household::create(['name'=>'Test Household']);HouseholdMember::create(['household_id'=>$h->id,'user_id'=>$u->id,'role'=>$role]);Sanctum::actingAs($u);return $u;}
 public function test_upload():void{Storage::fake('documents');$this->actor();$r=$this->post('/api/v1/documents',['file'=>UploadedFile::fake()->create('bill.pdf',100,'application/pdf'),'title'=>'Electricity Bill','category'=>'bill']);$r->assertCreated()->assertJsonPath('data.document.category','bill');$this->assertDatabaseHas('documents',['title'=>'Electricity Bill']);$this->assertDatabaseCount('document_versions',1);}
 public function test_invalid_mime():void{Storage::fake('documents');$this->actor();$this->post('/api/v1/documents',['file'=>UploadedFile::fake()->create('bad.exe',10,'application/x-msdownload')])->assertStatus(422);}
 public function test_authorization_blocks_other_household():void{Storage::fake('documents');$u=$this->actor();$other=User::factory()->create();$h=Household::create(['name'=>'Other']);HouseholdMember::create(['household_id'=>$h->id,'user_id'=>$other->id,'role'=>'owner']);$d=Document::create(['user_id'=>$other->id,'household_id'=>$h->id,'title'=>'Private','original_filename'=>'x.txt','mime_type'=>'text/plain','size'=>1,'storage_disk'=>'documents','storage_path'=>'x','checksum'=>str_repeat('a',64),'status'=>'active','processing_status'=>'ready']);$this->getJson('/api/v1/documents/'.$d->id)->assertForbidden();}
 public function test_versioning():void{Storage::fake('documents');$this->actor();$id=$this->post('/api/v1/documents',['file'=>UploadedFile::fake()->create('a.pdf',100,'application/pdf')])->json('data.document.id');$this->post('/api/v1/documents/'.$id.'/replace',['file'=>UploadedFile::fake()->create('b.pdf',120,'application/pdf')])->assertOk();$this->assertDatabaseCount('document_versions',2);}
 public function test_archive_restore():void{Storage::fake('documents');$this->actor();$id=$this->post('/api/v1/documents',['file'=>UploadedFile::fake()->create('a.txt',10,'text/plain')])->json('data.document.id');$this->postJson('/api/v1/documents/'.$id.'/archive')->assertOk()->assertJsonPath('data.document.status','archived');$this->postJson('/api/v1/documents/'.$id.'/restore')->assertOk()->assertJsonPath('data.document.status','active');}
 public function test_signed_access():void{Storage::fake('documents');$this->actor();$id=$this->post('/api/v1/documents',['file'=>UploadedFile::fake()->create('a.pdf',100,'application/pdf')])->json('data.document.id');$this->postJson('/api/v1/documents/'.$id.'/signed-url')->assertOk()->assertJsonStructure(['data'=>['url','expires_in_seconds']]);}
 public function test_metadata_search():void{Storage::fake('documents');$this->actor();$this->post('/api/v1/documents',['file'=>UploadedFile::fake()->create('a.txt',10,'text/plain'),'title'=>'Vehicle insurance renewal','issuer'=>'SafeCover','category'=>'insurance']);$this->getJson('/api/v1/documents?search=Vehicle')->assertOk()->assertJsonCount(1,'data');$this->getJson('/api/v1/documents?category=insurance&issuer=SafeCover')->assertOk()->assertJsonCount(1,'data');}
}
