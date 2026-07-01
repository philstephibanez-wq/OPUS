<?php declare(strict_types=1);

$lines=["P7_OPS_RUNTIME_DIAGNOSTICS_CORE_SMOKE"];
$root=dirname(__DIR__,2);
$smokeFiles=[
"index"=>$root."/sites/opus-p7-ops/public/index.php",
"router"=>$root."/sites/opus-p7-ops/public/router.php",
"action"=>$root."/sites/opus-p7-ops/public/action.php",
"command"=>$root."/sites/opus-p7-ops/public/command.php",
"navigation"=>$root."/sites/opus-p7-ops/public/navigation.php",
"diagnostics"=>$root."/sites/opus-p7-ops/public/diagnostics.php",
"css"=>$root."/sites/opus-p7-ops/public/ops-ui.css",
"readme"=>$root."/sites/opus-p7-ops/README.md",
];
$combined="";
foreach($smokeFiles as $label=>$file){if(!is_file($file)){throw new RuntimeException("RUNTIME_DIAG_FILE_MISSING: ".$label."=".$file);} $source=file_get_contents($file); if($source===false){throw new RuntimeException("RUNTIME_DIAG_READ_FAILED: ".$label);} $combined.=$source.PHP_EOL;}
foreach(["P7_OPS_RUNTIME_DIAGNOSTICS_CORE","OPUS OPS Runtime Diagnostics","PHP runtime","Composer autoload","Public files","Route checks","Operations view-model","Diagnostics payload","side_effects","/opus-lstsar-manager/diagnostics","/opus-lstsar-manager/runtime-diagnostics","ops-main-nav"] as $marker){if(!str_contains($combined,$marker)){throw new RuntimeException("RUNTIME_DIAG_MARKER_MISSING: ".$marker);}}
$lines[]="CHECK_P7_OPS_RUNTIME_DIAGNOSTICS_MARKERS=OK";
$_SERVER["REQUEST_URI"]="/opus-lstsar-manager/diagnostics?site=site-alpha";
$_GET=["site"=>"site-alpha"];
$diagnosticsFile=$smokeFiles["diagnostics"];
ob_start(); require $diagnosticsFile; $html=(string)ob_get_clean(); http_response_code(200);
foreach(["OPUS OPS Runtime Diagnostics","P7_OPS_RUNTIME_DIAGNOSTICS_CORE","PHP runtime","Composer autoload","Public files","Route checks","Operations view-model","Diagnostics payload","false","site-alpha"] as $marker){if(!str_contains($html,$marker)){throw new RuntimeException("RUNTIME_DIAG_RENDER_MARKER_MISSING: ".$marker);}}
$lines[]="CHECK_P7_OPS_RUNTIME_DIAGNOSTICS_RENDER=OK";
$router=file_get_contents($smokeFiles["router"]);
if($router===false){throw new RuntimeException("RUNTIME_DIAG_ROUTER_READ_FAILED");}
foreach(["/opus-lstsar-manager/diagnostics","/opus-lstsar-manager/runtime-diagnostics","diagnostics.php"] as $marker){if(!str_contains($router,$marker)){throw new RuntimeException("RUNTIME_DIAG_ROUTER_MARKER_MISSING: ".$marker);}}
$lines[]="CHECK_P7_OPS_RUNTIME_DIAGNOSTICS_ROUTER=OK";
$lines[]="P7_OPS_RUNTIME_DIAGNOSTICS_CORE_SMOKE_OK";
echo implode(PHP_EOL,$lines).PHP_EOL;
