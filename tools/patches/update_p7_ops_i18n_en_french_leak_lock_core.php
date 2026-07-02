<?php
declare(strict_types=1);
$root=getcwd();
$languageFile=$root.'/sites/opus-p7-ops/public/language.php';
$readmeFile=$root.'/sites/opus-p7-ops/README.md';
if(!is_file($languageFile)){fwrite(STDERR,'P7_OPS_LANGUAGE_FILE_MISSING'.PHP_EOL);exit(1);}
$source=file_get_contents($languageFile);
if($source===false){fwrite(STDERR,'P7_OPS_LANGUAGE_FILE_READ_FAILED'.PHP_EOL);exit(1);}
$enAdditions=json_decode(<<<'JSON'
{"Compteurs OPS":"OPS counters","Compteur OPS":"OPS counter","Synthèse":"Summary","Synthèses":"Summaries","Prochaines étapes":"Next steps","Étapes suivantes":"Next steps","Vue courte des opérations disponibles, sans JSON brut ni colonnes techniques longues.":"Short view of available operations, without raw JSON or long technical columns.","Ouvre Operations pour le détail, Command Center pour preview/dry-run/audit, Health Hub pour la matrice globale.":"Open Operations for details, Command Center for preview/dry-run/audit, and Health Hub for the global matrix.","Ouvre Opérations pour le détail, Centre de commande pour aperçu/simulation/audit, Centre de santé pour la matrice globale.":"Open Operations for details, Command Center for preview/dry-run/audit, and Health Hub for the global matrix.","Tableau de bord OPUS OPS":"OPUS OPS Dashboard","Console d’opérations OPUS OPS":"OPUS OPS Operations Console","Console d'opérations OPUS OPS":"OPUS OPS Operations Console","Tableau de bord":"Dashboard","Vue d’ensemble du tableau de bord":"Dashboard overview","Vue d'ensemble du tableau de bord":"Dashboard overview","Synthèse du tableau de bord":"Dashboard digest","Synthèse des opérations":"Operations digest","État de santé":"Health snapshot","Etat de santé":"Health snapshot","Accès rapide":"Quick access","Détail des opérations":"Operations detail","Détails des opérations":"Operations detail","Détail opération":"Operation detail","Résumé source":"Source summary","Résumé destination":"Destination summary","Centre de commande":"Command Center","Centre de santé":"Health Hub","Diagnostics d’exécution":"Runtime diagnostics","Diagnostics d'exécution":"Runtime diagnostics","Console détaillée séparée":"Separate detailed console","État global":"Global status","Etat global":"Global status","Navigation directe":"Direct navigation","Opérations":"Operations","Opération":"Operation","Statut":"Status","Statuts":"Statuses","État":"Status","Etat":"Status","Aperçu":"Preview","Prévisualisation":"Preview","Simulation":"Dry run","Exécution à blanc":"Dry run","Ouvrir":"Open","Ouvre":"Open","Exécuter":"Run","Executer":"Run","Lancer":"Run","Vue d’ensemble":"Overview","Vue d'ensemble":"Overview","Résumé":"Summary","Détails":"Details","Détail":"Detail","Contrôles":"Checks","Contrôle":"Check","Fichiers":"Files","Fichier":"File","Avertissement":"Warning","Avertissements":"Warnings","Erreur":"Error","Erreurs":"Errors","Succès":"Success","Echec":"Failure","Échec":"Failure","Sans effet de bord":"No side effects","Actif":"Active","Prêt":"Ready","Pret":"Ready","Bloqué":"Blocked","Bloque":"Blocked","actif":"active","prêt":"ready","pret":"ready","bloqué":"blocked","bloque":"blocked","colonnes techniques longues":"long technical columns","sans JSON brut":"without raw JSON","pour le détail":"for details","matrice globale":"global matrix","Langue":"Language","Choisir une langue":"Choose a language"}
JSON,true);
if(!is_array($enAdditions)){fwrite(STDERR,'P7_OPS_EN_FRENCH_LEAK_ADDITIONS_INVALID'.PHP_EOL);exit(1);}
$pattern="/(function p7ops_i18n_page_translation_dictionary\(\): array\s*\{.*?json_decode\(<<<'JSON'\R)(.*?)(\RJSON, true\);)/s";
if(!preg_match($pattern,$source,$match)){fwrite(STDERR,'P7_OPS_PAGE_TRANSLATION_DICTIONARY_BLOCK_NOT_FOUND'.PHP_EOL);exit(1);}
$dictionary=json_decode($match[2],true);
if(!is_array($dictionary)){fwrite(STDERR,'P7_OPS_PAGE_TRANSLATION_DICTIONARY_JSON_INVALID'.PHP_EOL);exit(1);}
$current=isset($dictionary['en'])&&is_array($dictionary['en'])?$dictionary['en']:[];
$dictionary['en']=array_replace($current,$enAdditions);
$json=json_encode($dictionary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
if(!is_string($json)){fwrite(STDERR,'P7_OPS_PAGE_TRANSLATION_DICTIONARY_JSON_ENCODE_FAILED'.PHP_EOL);exit(1);}
$source=preg_replace($pattern,'$1'.$json.'$3',$source,1,$count);
if($count!==1||!is_string($source)){fwrite(STDERR,'P7_OPS_PAGE_TRANSLATION_DICTIONARY_REPLACE_FAILED'.PHP_EOL);exit(1);}
if(!str_contains($source,'P7_OPS_I18N_EN_FRENCH_LEAK_LOCK_CORE')){
    $source=str_replace('P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE / completed visible labels','P7_OPS_I18N_EN_FRENCH_LEAK_LOCK_CORE / English pages must not leak French / P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE / completed visible labels',$source);
}
if(file_put_contents($languageFile,$source)===false){fwrite(STDERR,'P7_OPS_LANGUAGE_FILE_WRITE_FAILED'.PHP_EOL);exit(1);}
$readme=is_file($readmeFile)?file_get_contents($readmeFile):'# OPUS P7 OPS'.PHP_EOL;
if(!is_string($readme)){$readme='# OPUS P7 OPS'.PHP_EOL;}
if(!str_contains($readme,'P7_OPS_I18N_EN_FRENCH_LEAK_LOCK_CORE')){
    $readme.=PHP_EOL.'## P7_OPS_I18N_EN_FRENCH_LEAK_LOCK_CORE'.PHP_EOL.PHP_EOL;
    $readme.='- Adds an English anti-leak translation layer for French fragments that remain in OPS pages.'.PHP_EOL;
    $readme.='- Renders OPS public pages with `lang=en` and rejects visible French UI fragments.'.PHP_EOL;
    $readme.='- Language names in the selector and technical operation/path identifiers are intentionally allowed.'.PHP_EOL;
    $readme.='- Covered by `tools/smokes/smoke_p7_ops_i18n_en_french_leak_lock_core.php`.'.PHP_EOL;
}
if(file_put_contents($readmeFile,$readme)===false){fwrite(STDERR,'P7_OPS_README_WRITE_FAILED'.PHP_EOL);exit(1);}
echo 'P7_OPS_I18N_EN_FRENCH_LEAK_LOCK_CORE_UPDATED'.PHP_EOL;
