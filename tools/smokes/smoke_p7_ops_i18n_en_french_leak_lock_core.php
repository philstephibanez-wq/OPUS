<?php
declare(strict_types=1);
echo 'P7_OPS_I18N_EN_FRENCH_LEAK_LOCK_CORE_SMOKE'.PHP_EOL;
$root=dirname(__DIR__,2);
$publicDir=$root.'/sites/opus-p7-ops/public';
$languageFile=$publicDir.'/language.php';
if(!is_file($languageFile)){throw new RuntimeException('EN_FRENCH_LEAK_LANGUAGE_FILE_MISSING');}
$languageSource=file_get_contents($languageFile);
if($languageSource===false){throw new RuntimeException('EN_FRENCH_LEAK_LANGUAGE_READ_FAILED');}
foreach(['P7_OPS_I18N_EN_FRENCH_LEAK_LOCK_CORE','Compteurs OPS','OPS counters','Prochaines étapes','Next steps','Short view of available operations'] as $marker){
    if(!str_contains($languageSource,$marker)){throw new RuntimeException('EN_FRENCH_LEAK_MARKER_MISSING: '.$marker);}
}
echo 'CHECK_P7_OPS_EN_FRENCH_LEAK_MARKERS=OK'.PHP_EOL;
require_once $languageFile;
$_GET=['site'=>'site-alpha','lang'=>'en'];
$sample="Compteurs OPS\nSynthèse\nProchaines étapes\nVue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.\nOuvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.\nOpération Source Destination Action\nActif Prêt Bloqué prêt\nTableau de bord OPUS OPS\nCentre de commande\nCentre de santé";
$translated=p7ops_i18n_translate_html($sample);
foreach(['OPS counters','Summary','Next steps','Short view of available operations, without raw JSON or long technical columns.','Open Operations for details, Command Center for preview/dry-run/audit, and Health Hub for the global matrix.','Operation Source Destination Action','Active Ready Blocked ready','OPUS OPS Dashboard','Command Center','Health Hub'] as $marker){
    if(!str_contains($translated,$marker)){throw new RuntimeException('EN_FRENCH_LEAK_DIRECT_TRANSLATION_MISSING: '.$marker.' IN '.$translated);}
}
echo 'CHECK_P7_OPS_EN_FRENCH_LEAK_DIRECT=OK'.PHP_EOL;
$render=static function(string $file,string $uri):string{
    $_SERVER['REQUEST_URI']=$uri;
    $_GET=['site'=>'site-alpha','lang'=>'en'];
    ob_start();
    (static function(string $__file):void{require $__file;})($file);
    $html=ob_get_clean();
    return is_string($html)?p7ops_i18n_translate_html($html):'';
};
$pages=[
    'index'=>[$publicDir.'/index.php','/english/dashboard?site=site-alpha&lang=en'],
    'action'=>[$publicDir.'/action.php','/opus-lstsar-manager/action?site=site-alpha&lang=en'],
    'command'=>[$publicDir.'/command.php','/opus-lstsar-manager/command-center?site=site-alpha&lang=en'],
    'navigation'=>[$publicDir.'/navigation.php','/opus-lstsar-manager/navigation?site=site-alpha&lang=en'],
    'diagnostics'=>[$publicDir.'/diagnostics.php','/opus-lstsar-manager/diagnostics?site=site-alpha&lang=en'],
    'health'=>[$publicDir.'/health.php','/opus-lstsar-manager/health?site=site-alpha&lang=en'],
];
$rendered='';
foreach($pages as $name=>[$file,$uri]){
    if(!is_file($file)){throw new RuntimeException('EN_FRENCH_LEAK_PAGE_FILE_MISSING: '.$name);}
    $html=$render($file,$uri);
    if($html===''){throw new RuntimeException('EN_FRENCH_LEAK_EMPTY_RENDER: '.$name);}
    $rendered.="\n<!-- PAGE ".$name." -->\n".$html;
}
$visible=preg_replace('/<script\b[^>]*>.*?<\/script>/is',' ',$rendered);
$visible=preg_replace('/<style\b[^>]*>.*?<\/style>/is',' ',is_string($visible)?$visible:$rendered);
$visible=preg_replace('/<!--.*?-->/s',' ',is_string($visible)?$visible:$rendered);
$visible=html_entity_decode(strip_tags(is_string($visible)?$visible:$rendered),ENT_QUOTES|ENT_HTML5,'UTF-8');
$visible=preg_replace('/\s+/u',' ',is_string($visible)?$visible:'');
foreach(['OPS counters','Next steps','Summary','Operation','Source','Destination','Action'] as $marker){
    if(!str_contains($visible,$marker)){throw new RuntimeException('EN_FRENCH_LEAK_EN_MARKER_MISSING: '.$marker);}
}
$forbidden=['Compteurs OPS','Synthèse','Prochaines étapes','Vue courte des opérations','sans JSON brut','colonnes techniques','Ouvre Operations pour','Ouvre Opérations pour','pour le détail','matrice globale','Tableau de bord','Console d’opérations','Console d\'opérations','Centre de commande','Centre de santé','Diagnostics d’exécution','Diagnostics d\'exécution','Console détaillée séparée','État global','Navigation directe','Opérations','Opération','Statut','Aperçu','Simulation','Ouvrir','Exécuter','Détails','Contrôles','Fichiers','Avertissement','Erreur','Sans effet de bord','Actif','Prêt','Bloqué','prêt','bloqué'];
foreach($forbidden as $word){
    if(str_contains($visible,$word)){throw new RuntimeException('EN_FRENCH_LEAK_VISIBLE_FORBIDDEN: '.$word);}
}
echo 'CHECK_P7_OPS_EN_FRENCH_LEAK_RENDER=OK'.PHP_EOL;
echo 'P7_OPS_I18N_EN_FRENCH_LEAK_LOCK_CORE_SMOKE_OK'.PHP_EOL;
