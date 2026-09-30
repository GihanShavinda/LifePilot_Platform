<?php
namespace App\Domain\Finance\Services;
use App\Domain\Finance\Models\{Expense,Merchant,ExpenseCategory};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class FinanceCsvService {
 public const HEADER=['date','title','amount','currency','merchant','category','description'];
 public function import(string $file,int $h,int $user):array {
  $fh=fopen($file,'rb');if(!$fh)throw ValidationException::withMessages(['file'=>'Could not open CSV.']);
  try {
   $headers=fgetcsv($fh);if(!$headers)throw ValidationException::withMessages(['file'=>'Missing CSV header.']);
   $headers=array_map(fn($v)=>mb_strtolower(trim((string)$v)), $headers);
   if(count($headers)!==count(array_unique($headers)))throw ValidationException::withMessages(['file'=>'Duplicate CSV header.']);
   foreach(['date','title','amount','currency'] as $required)if(!in_array($required,$headers,true))throw ValidationException::withMessages(['file'=>'Missing column: '.$required]);
   $rows=[];$line=1;
   while(($raw=fgetcsv($fh))!==false){$line++;if($line>1001)throw ValidationException::withMessages(['file'=>'Limit is 1,000 rows.']);
    if(count($raw)===1 && trim($raw[0])==='')continue;
    if(count($raw)!==count($headers))throw ValidationException::withMessages(['file'=>"Row $line column count mismatch."]);
    $row=array_combine($headers,$raw);$date=trim($row['date']);$currency=strtoupper(trim($row['currency']));$amount=trim($row['amount']);
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))||!is_numeric($amount)||(float)$amount<=0||!preg_match('/^[A-Z]{3}$/',$currency)||trim($row['title'])==='')throw ValidationException::withMessages(['file'=>"Invalid date, title, amount or currency at row $line."]);
    $row['currency']=$currency;$row['amount']=number_format((float)$amount,2,'.','');$row['date']=$date;
    $row['hash']=hash('sha256',implode('|',[$date,mb_strtolower(trim($row['title'])),$row['amount'],$currency,mb_strtolower(trim($row['merchant']??''))]));$rows[]=$row;
   }
   return DB::transaction(function()use($rows,$h,$user){$created=0;$skipped=0;
    foreach($rows as $r){if(Expense::withTrashed()->where('household_id',$h)->where('import_hash',$r['hash'])->exists()){$skipped++;continue;}
     $m=null;if(!empty(trim($r['merchant']??''))){$name=trim($r['merchant']);$m=Merchant::firstOrCreate(['household_id'=>$h,'normalized_name'=>mb_strtolower($name)],['name'=>$name]);}
      $category=null; if(!empty(trim($r['category']??''))){$cat=trim($r['category']);$slug=\Illuminate\Support\Str::slug($cat);$category=ExpenseCategory::firstOrCreate(['household_id'=>$h,'slug'=>$slug],['name'=>$cat]);}
     Expense::create(['expense_category_id'=>$category?->id,'household_id'=>$h,'user_id'=>$user,'title'=>trim($r['title']),'description'=>$r['description']??null,'merchant_id'=>$m?->id,'amount'=>$r['amount'],'currency'=>$r['currency'],'expense_date'=>$r['date'],'source'=>'csv','import_hash'=>$r['hash']]);$created++;
    }return ['created'=>$created,'skipped_duplicates'=>$skipped];});
  }finally{fclose($fh);}
 }
}
