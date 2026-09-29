<?php
namespace App\Support;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
class PaginationMeta { public static function from(LengthAwarePaginator $p): array { return ['pagination'=>['current_page'=>$p->currentPage(),'per_page'=>$p->perPage(),'total'=>$p->total(),'last_page'=>$p->lastPage()]]; } }
