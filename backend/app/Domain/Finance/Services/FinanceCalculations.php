<?php
namespace App\Domain\Finance\Services;
use Carbon\CarbonImmutable;
class FinanceCalculations {
 public function nextDate(string $date,string $cycle):string {
  $d=CarbonImmutable::parse($date);
  return match($cycle){'daily'=>$d->addDay()->toDateString(),'weekly'=>$d->addWeek()->toDateString(),
   'monthly'=>$d->addMonthNoOverflow()->toDateString(),'quarterly'=>$d->addMonthsNoOverflow(3)->toDateString(),
   'yearly'=>$d->addYearNoOverflow()->toDateString(), default=>throw new \InvalidArgumentException('Invalid cycle')};
 }
 public function monthlyEquivalent(string $price,string $cycle):string {
  $factor=match($cycle){'daily'=>365.25/12,'weekly'=>52/12,'monthly'=>1,'quarterly'=>1/3,'yearly'=>1/12,default=>0};
  return number_format(round((float)$price*$factor,2),2,'.','');
 }
 public function warrantyState(string $endDate,?CarbonImmutable $today=null):string {
  $today??=CarbonImmutable::today();$end=CarbonImmutable::parse($endDate)->startOfDay();
  return $end->lt($today)?'expired':($end->lte($today->addDays(30))?'expiring':'active');
 }
}
