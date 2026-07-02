<?php declare(strict_types=1);

$lines=["P7_OPS_UI_DISTINCTION_WRAP_CORE_SMOKE"];
$root=dirname(__DIR__,2);
$files=[
"index"=>$root."/sites/opus-p7-ops/public/index.php",
"router"=>$root."/sites/opus-p7-ops/public/router.php",
"action"=>$root."/sites/opus-p7-ops/public/action.php",
"command"=>$root."/sites/opus-p7-ops/public/command.php",
"navigation"=>$root."/sites/opus-p7-ops/public/navigation.php",
"diagnostics"=>$root."/sites/opus-p7-ops/public/diagnostics.php",
"health"=>$root."/sites/opus-p7-ops/public/health.php",
"css"=>$root."/sites/opus-p7-ops/public/ops-ui.css",
"readme"=>$root."/sites/opus-p7-ops/README.md",
];
$combined="";
foreach($files as $label=>$file){if(!is_file($file)){throw new RuntimeException("UI_DISTINCTION_FILE_MISSING: ".$label."=".$file);} $source=file_get_contents($file); if($source===false){throw new RuntimeException("UI_DISTINCTION_READ_FAILED: ".$label);} $combined.=$source.PHP_EOL;}
foreach(["P7_OPS_UI_DISTINCTION_WRAP_CORE","OPUS OPS Dashboard","OPUS OPS Operations Console","Operations digest","Health snapshot","Quick access","Operations detail","Source summary","Destination summary","ops-table","ops-polished-table","ops-table-wrap","white-space","overflow-wrap","word-break","table-layout","Dashboard","Operations","Command Center","Navigation","Diagnostics","Health Hub"] as $marker){if(!str_contains($combined,$marker)){throw new RuntimeException("UI_DISTINCTION_MARKER_MISSING: ".$marker);}}
$lines[]="CHECK_P7_OPS_UI_DISTINCTION_MARKERS=OK";
$render=function(string $file,string $uri,array $get): string { $_SERVER["REQUEST_URI"]=$uri; $_GET=$get; ob_start(); (static function(string $__file): void { require $__file; })($file); $out=ob_get_clean(); return is_string($out)?$out:""; };
$dashboard=$render($files["index"],"/opus-lstsar-manager?site=site-alpha",["site"=>"site-alpha"]);
foreach(["OPUS OPS Dashboard","P7_OPS_UI_DISTINCTION_WRAP_CORE","Operations digest","Health snapshot","Quick access","Dashboard","Operations"] as $marker){if(!str_contains($dashboard,$marker)){throw new RuntimeException("UI_DISTINCTION_DASHBOARD_RENDER_MISSING: ".$marker);}}
$lines[]="CHECK_P7_OPS_UI_DISTINCTION_DASHBOARD=OK";
$router=file_get_contents($files["router"]);
if($router===false){throw new RuntimeException("UI_DISTINCTION_ROUTER_READ_FAILED");}
foreach(["/opus-lstsar-manager/operations","/opus-lstsar-manager/health","/opus-lstsar-manager/health-hub"] as $marker){if(!str_contains($router,$marker)){throw new RuntimeException("UI_DISTINCTION_ROUTER_MARKER_MISSING: ".$marker);}}
$lines[]="CHECK_P7_OPS_UI_DISTINCTION_ROUTER=OK";
$lines[]="P7_OPS_UI_DISTINCTION_WRAP_CORE_SMOKE_OK";
echo implode(PHP_EOL,$lines).PHP_EOL;
