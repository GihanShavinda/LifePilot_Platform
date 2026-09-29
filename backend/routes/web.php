<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn()=>response()->json(['service'=>'LifePilot API','status'=>'ok']));
