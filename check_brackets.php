<?php
$code=file('app/Console/Commands/TerminalWebSocket.php');
$stack=[];
foreach($code as $i=>$line){
    $lineNo=$i+1;
    foreach(str_split($line) as $ch){
        if($ch==='{' || $ch==='['){
            $stack[]=$lineNo.' '.$ch;
        } elseif($ch==='}' || $ch===']'){
            array_pop($stack);
        }
    }
}
print_r($stack);
