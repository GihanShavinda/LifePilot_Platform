<?php
namespace App\Domain\Assistant\Services;
class UntrustedContentGuard
{
    public function wrap(string $text):string
    {
        $text=str_replace(["\0"],[''],$text);
        return "<UNTRUSTED_RETRIEVED_CONTENT>\n".$text."\n</UNTRUSTED_RETRIEVED_CONTENT>";
    }
    public function containsInstructionLikeText(string $text):bool
    {
        return (bool)preg_match('/\b(ignore previous|system prompt|developer message|follow these instructions|do not cite|fabricate|invent)\b/i',$text);
    }
}
