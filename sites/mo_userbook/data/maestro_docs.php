<?php
declare(strict_types=1);

return [
    'fr' => [
        'accueil' => [
            'title' => 'Accueil Maestro V5',
            'kicker' => 'Maestro V5',
            'lead' => 'Point d’entrée humain de la documentation : utilisateur, workflow et framework.',
            'nav' => 'Accueil',
            'html' => '<section class="card hero">
  <h2>Bienvenue dans MAESTRO V5</h2>
  <p>MAESTRO est un assistant de composition et d\'orchestration pour REAPER. Il sécurise le projet, lit la session, propose un chemin musical, explique ses choix et laisse toujours le musicien libre.</p>
  <div class="kpi"><span class="tag ok">Project Guard</span><span class="tag info">Workflow musicien</span><span class="tag info">Framework SYS</span><span class="tag warn">0 fallback</span></div>
</section>
<section class="grid wide">
  <div class="card"><h3>Je veux utiliser Maestro</h3><p>Commence par le guide utilisateur : workdir, certification RPP, Hub, workflow et modules.</p><p><a href="@route/guide-utilisateur">Ouvrir le guide utilisateur</a></p></div>
  <div class="card"><h3>Je veux comprendre le workflow</h3><p>Le parcours musicien va de Project Guard à Sanitize, Intent, Split, Humanize puis FX/Mix.</p><p><a href="@route/workflow">Lire le workflow musicien</a></p></div>
  <div class="card"><h3>Je veux développer</h3><p>Kernel, FSM, Dispatcher, Factory, objets, moteurs, modules et composants sont documentés côté framework.</p><p><a href="@route/framework">Lire le framework</a></p></div>
</section>
<section class="split">
  <div class="card">
    <h2>Chemin musicien résumé</h2>
    <div class="flow">PROJECT_READY
→ SANITIZE : nettoyer la matière MIDI/RPP
→ INTENT : choisir l\'objectif artistique au moment Split
→ SPLIT : préparer instruments, sections et rôles
→ HUMANIZE : chef, groupe, interprètes et façon de jouer
→ FX / MIX : pan, EQ, spatialisation, reverb, rendu</div>
  </div>
  <div class="card">
    <h2>Règle d\'or</h2>
    <p>Le style choisi guide Maestro, mais ne verrouille jamais le musicien. Un projet peut passer d\'une symphonie romantique à une ballade country : Maestro recalcule et explique.</p>
    <p class="warn"><b>Important :</b> Maestro conseille, orchestre et mémorise les décisions. Il ne remplace pas le compositeur.</p>
  </div>
</section>
<section class="card">
  <h2>Entrées principales</h2>
  <ul>
    <li><a href="@route/architecture">Architecture technique</a> — vue développeur.</li>
    <li><a href="@route/objets">Objets framework</a> — Object, UI Layer, RichText Parser, FSM, Workflow Orchestrator.</li>
    <li><a href="@route/composants">Composants publics</a> — UI-only, CSS/ranges, pas de métier.</li>
    <li><a href="@route/fsm">FSM et tables dédiées</a> — boot, Adminer, Project Guard, Workflow.</li>
  </ul>
</section>',
        ],
        'guide-utilisateur' => [
            'title' => 'Guide utilisateur',
            'kicker' => 'Maestro V5',
            'lead' => 'Première documentation utilisateur : Project Guard, Hub et workflow musicien.',
            'nav' => 'Guide utilisateur',
            'html' => '<section class="card hero"><h2>Guide utilisateur — démarrage</h2><p>Cette page démarre la documentation utilisateur. Elle explique le parcours sans rentrer dans le code.</p></section>
<section class="grid wide">
<div class="card"><h3>1. Ouvrir Maestro</h3><p>Maestro vérifie le projet courant et affiche le Hub. Si un prérequis manque, le Hub explique quoi faire.</p></div>
<div class="card"><h3>2. Choisir ou créer la workdir</h3><p>La workdir est le dossier de travail officiel. Si elle manque, Maestro ouvre l\'explorateur Workdir pour la créer ou la sélectionner.</p></div>
<div class="card"><h3>3. Certifier le RPP</h3><p>Le projet REAPER doit être certifié dans la workdir. Maestro peut créer/importer la copie de travail signée.</p></div>
<div class="card"><h3>4. Suivre le workflow</h3><p>Une fois <code>PROJECT_READY</code>, Maestro propose les étapes musicales : Sanitize, Intent, Split, Humanize, FX/Mix.</p></div>
</section>
<section class="card"><h2>Que faire si je change d\'avis ?</h2><p>C\'est normal. Tu peux changer de style, ajouter un instrument, revenir sur une étape ou ignorer une recommandation. Maestro doit accompagner le changement, pas le bloquer.</p></section>
<section class="card"><h2>Modules principaux</h2><ul><li><b>Sanitizer</b> : nettoyer et diagnostiquer.</li><li><b>Splitter</b> : choisir l\'intention et préparer l\'orchestration.</li><li><b>Humanizer</b> : rendre le jeu vivant.</li><li><b>FX Studio / Manager</b> : spatialiser, équilibrer et colorer.</li><li><b>AI Studio</b> : demander des idées ou conseils si disponible.</li></ul></section>',
        ],
        'workflow' => [
            'title' => 'Workflow musicien',
            'kicker' => 'Maestro V5',
            'lead' => 'Documentation Maestro V5.',
            'nav' => 'Workflow musicien',
            'html' => '<section class="card">
  <h2>Workflow musicien</h2>
  <p>Le workflow musicien est l’orchestrateur métier présenté par le Hub et les modules. Il aide le compositeur à avancer sans décider à sa place.</p>
</section>
<section class="card">
  <h2>Pipeline cible</h2>
  <ol>
    <li><strong>Project Guard</strong> : vérifier workdir, certification et import.</li>
    <li><strong>Sanitize</strong> : nettoyer et fiabiliser la matière MIDI/RPP.</li>
    <li><strong>Intent</strong> : choisir ou modifier le style et l’intention artistique au moment du Split.</li>
    <li><strong>Split</strong> : préparer les instruments, rôles, familles et normalisations.</li>
    <li><strong>Humanize</strong> : configurer le style de jeu, le chef, le groupe et les interprètes éventuels.</li>
    <li><strong>FX / Mix</strong> : spatialisation, EQ, pan, reverb et finition.</li>
  </ol>
</section>
<section class="card">
  <h2>Liberté du musicien</h2>
  <p>Le style guide Maestro, mais ne verrouille jamais le musicien. Le musicien peut revenir en arrière, ajouter une section, changer d’instrument, modifier la signature ou transformer une symphonie en ballade country. Maestro recalcule le chemin conseillé.</p>
</section>
<section class="card">
  <h2>P88B</h2>
  <p><code>SYS.WORKFLOW</code> est chargé au boot avant <code>MOD_HUB</code>. Il attend <code>PROJECT_READY</code> avant de produire les recommandations musicales.</p>
</section>

<section class="card"><h2>P88C — Intention artistique après Sanitize</h2><p>Le Workflow Orchestrator demande maintenant une intention artistique après Sanitize, au moment où le Split prépare l\'orchestration. La ListBox de style est affichée par <code>MOD_SPLITTER</code>, alimentée par <code>CONFIG/WORKFLOW_STYLE_PROFILES.lua</code>. Le Hub reste un afficheur du workflow. Le style choisi oriente Split, Humanize et FX/Mix sans verrouiller le musicien.</p></section>
<section class="card"><h2>P88C3 — Intention dans Splitter</h2><p>La sélection de style a été retirée du Hub et placée dans le Splitter. Le musicien choisit l’intention au moment où les instruments et sections vont être préparés.</p></section>

<section class="card"><h2>P88C6 — Reprise d’intention</h2><p>L’intention artistique choisie dans le Splitter est maintenant persistée dans l’ExtState du RPP, section <code>MAESTRO_WORKFLOW</code>. À la réouverture du projet, Maestro restaure l’intention et reprend le workflow musicien au bon endroit, sans passer par la BDD comme source souveraine.</p></section>

<section class="card"><h2>P88D — Sanitize puis mapping GM/GS avant intention</h2><p>Le workflow ne demande plus l’intention artistique trop tôt. Après <code>PROJECT_READY</code>, Maestro vérifie d’abord l’audit Sanitize. Si la matière est fiable, le Splitter affiche une étape <strong>GM/GS Track Mapping</strong> qui propose une famille instrumentale et prépare la standardisation des noms. L’intention artistique n’apparaît qu’après validation de ce mapping.</p><ol><li>Sanitize Audit : vérifier ghost notes, CC et timeline.</li><li>GM/GS Mapping : identifier les pistes et familles instrumentales.</li><li>Intent : choisir le style au moment du Split.</li><li>Split : préparer les instruments et sections.</li></ol></section>',
        ],
        'framework' => [
            'title' => 'Framework Maestro V5',
            'kicker' => 'Maestro V5',
            'lead' => 'Documentation technique du framework et de ses contrats.',
            'nav' => 'Framework',
            'html' => '<section class="card hero"><h2>Framework MAESTRO</h2><p>Le framework fournit les rails : boot, contrats, bus <code>SYS</code>, moteurs, objets, UI Factory, composants et modules. Chaque couche fait son travail et rien d\'autre.</p></section>
<section class="grid wide">
<div class="card"><h3>Kernel</h3><p>Garant du boot et de l\'intégrité. Après preboot, la FSM et le Dispatcher prennent le relais. Pas de chargement sauvage.</p></div>
<div class="card"><h3>FSM + Dispatcher</h3><p>La FSM choisit la transition ; le Dispatcher exécute l\'action officielle. Une transition doit produire un seul <code>STEP_NEXT</code> par moteur.</p></div>
<div class="card"><h3>SYS</h3><p>Bus runtime public unique. Pas de <code>_G</code>, pas de <code>SYS.Services</code>, pas de double exposition cachée.</p></div>
<div class="card"><h3>Factory</h3><p>UI souveraine : composants publics, ranges Excel, CSS, layers, RichText centralisé. Les modules ne dessinent pas en bas niveau.</p></div>
</section>
<section class="card"><h2>Objets framework</h2><p>Les objets structurants documentés : <code>OBJ_Object</code>, <code>OBJ_UI_Layer</code>, <code>OBJ_RichTextParser</code>, <code>OBJ_FSM</code>, <code>OBJ_WorkflowOrchestrator</code>.</p><p><a href="@route/objets">Voir la page objets</a></p></section>
<section class="card"><h2>Contrats non négociables</h2><ul><li>0 fallback silencieux.</li><li>Erreur explicite si le chemin officiel n\'existe pas.</li><li>Composants publics pour toute UI réutilisable.</li><li>Modules métier petits : ils affichent, envoient des intentions et consomment <code>SYS</code>.</li><li>Le RPP reste source de vérité musicale.</li></ul></section>',
        ],
        'architecture' => [
            'title' => 'Architecture technique',
            'kicker' => 'Maestro V5',
            'lead' => 'Vue développeur séparée de l’accueil utilisateur.',
            'nav' => 'Architecture',
            'html' => '<section class="card hero"><h2>Architecture technique</h2><p>Cette page est la vue développeur. Le point d\'entrée humain reste <a href="@route/accueil">Accueil</a>.</p></section>
<section class="grid wide"><div class="card"><h3>Boot</h3><p>Preboot minimal : Manifest, Logger, Panic Handler, Boot Console, SQL, Dispatcher, FSM. Ensuite la FSM pilote les moteurs.</p></div><div class="card"><h3>Profils</h3><p>Maestro et Adminer ont des profils de boot séparés. Maestro charge le workflow ; Adminer reste autonome.</p></div><div class="card"><h3>Runtime</h3><p><code>SYS</code> expose les moteurs et objets officiels. Les modules ne défendent pas le framework déjà garanti.</p></div></section>
<section class="card"><h2>Chaîne Maestro cible</h2><div class="flow">MANIFEST → LOGGER → PANIC_HANDLER → BOOT_CONSOLE → SQL → DISPATCHER → FSM
→ CSS → I18N → SYSTEM_MONITOR → SCHEDULER → UI_FACTORY → MIDI → REST → AI → WORKFLOW → MOD_HUB</div></section>
<section class="card"><h2>Séparation des responsabilités</h2><ul><li><b>Kernel</b> : intégrité et boot.</li><li><b>FSM</b> : contrôle data-driven.</li><li><b>Dispatcher</b> : actions officielles.</li><li><b>Engines</b> : périphériques runtime.</li><li><b>Objects</b> : état et comportements structurants.</li><li><b>Modules</b> : présentation métier.</li><li><b>Components</b> : UI réutilisable, sans métier.</li></ul></section>',
        ],
        'objets' => [
            'title' => 'Objets framework',
            'kicker' => 'Maestro V5',
            'lead' => 'Référence des objets structurants récents.',
            'nav' => 'Objets',
            'html' => '<section class="card hero"><h2>Objets framework</h2><p>Les objets centralisent les comportements transversaux sans créer de chemins parallèles. Ils sont chargés par les moteurs officiels.</p></section>
<table><thead><tr><th>Objet</th><th>Rôle</th><th>Exposition / usage</th></tr></thead><tbody>
<tr><td><code>OBJ_Object</code></td><td>Base objet : héritage, interfaces, validation.</td><td>Socle des autres objets et composants.</td></tr>
<tr><td><code>OBJ_UI_Layer</code></td><td>Layer UI : z-order, dirty/bounds, policy direct/buffered.</td><td>Utilisé par <code>MO_UI_FACTORY</code>.</td></tr>
<tr><td><code>OBJ_RichTextParser</code></td><td>Parsing RichText centralisé : UTF-8, balises, glyphes, fallback I18N préservé.</td><td>Utilisé par les API RichText Factory et composants.</td></tr>
<tr><td><code>OBJ_FSM</code></td><td>FSM objet : état, table source, wildcard explicite, dernières transitions.</td><td><code>SYS.FSM</code>, <code>SYS.PROJECT_GUARD_FSM</code>, <code>SYS.WORKFLOW_FSM</code>.</td></tr>
<tr><td><code>OBJ_WorkflowOrchestrator</code></td><td>Orchestrateur métier musicien : viewmodel, intention, recommandations.</td><td><code>SYS.WORKFLOW</code>.</td></tr>
</tbody></table>
<section class="callout warn"><b>Contrat :</b> un objet ne devient jamais un fallback ni une copie cachée d’un moteur. Il est créé par le chemin officiel et échoue explicitement si le contrat n’est pas disponible.</section>',
        ],
        'engines' => [
            'title' => 'Engines',
            'kicker' => 'Maestro V5',
            'lead' => 'Moteurs officiels et protocole de boot.',
            'nav' => 'Engines',
            'html' => '<section class="card hero"><h2>Moteurs officiels</h2><p>Les moteurs sont les périphériques runtime exposés par <code>SYS</code>. Ils suivent le protocole officiel de boot : load, ping, init, expose, puis <code>STEP_NEXT</code>.</p></section>
<table><thead><tr><th>Famille</th><th>Moteurs</th><th>Rôle</th></tr></thead><tbody>
<tr><td>Preboot</td><td>Manifest, Logger, Panic Handler, Boot Console, SQL, Dispatcher, FSM</td><td>Démarrage minimal et activation du contrôle FSM.</td></tr>
<tr><td>Framework</td><td>CSS, I18N, System Monitor, Scheduler, UI Factory</td><td>Style, traduction, supervision, cadence et UI.</td></tr>
<tr><td>Métier/périphériques</td><td>MIDI, REST, AI</td><td>Lecture RPP/MIDI, pont HTTP/API, conseil externe.</td></tr>
<tr><td>Workflow</td><td>Workflow Orchestrator</td><td>Partie métier présentée par le Hub et les modules après <code>PROJECT_READY</code>.</td></tr>
</tbody></table>
<section class="card"><h2>Adminer</h2><p>Adminer utilise le même framework, mais son profil ne charge pas le workflow musicien. Il reste dédié à l’administration SQL.</p></section>',
        ],
        'composants' => [
            'title' => 'Composants',
            'kicker' => 'Maestro V5',
            'lead' => 'Contrat UI public et composants réutilisables.',
            'nav' => 'Composants',
            'html' => '<section class="card hero"><h2>Composants publics</h2><p>Les composants appartiennent à <code>SYS.UI</code>. Ils sont UI-only, pilotés par CSS/ranges/état et réutilisables dans les modules.</p></section>
<section class="grid wide"><div class="card"><h3>Contrat</h3><ul><li>Pas de logique métier.</li><li>Pas d’accès BDD métier.</li><li>Pas de dessin sauvage dans les modules.</li><li>Overides CSS optionnels et filtrés.</li><li>IDs/handles officiels quand nécessaires.</li></ul></div><div class="card"><h3>Composants récents critiques</h3><p><code>CPNT_Console</code> honore la LED Matrix mutualisée ; <code>CPNT_ListBox</code> et <code>CPNT_ComboBox</code> conservent le clic en SidePanel ; <code>CPNT_File_Explorer</code> reste UI-only.</p></div></section>
<section class="card"><h2>Familles</h2><p>Button, Console, ComboBox, ListBox, Slider, GroupFrame, SideBar, SidePanel, Popup, RichPopup, File Explorer, Notifier, TinyNotepad, RichText, Chassis/Halo/LED Matrix.</p></section>',
        ],
        'modules' => [
            'title' => 'Modules',
            'kicker' => 'Maestro V5',
            'lead' => 'Modules métier, Hub et POP contractuels.',
            'nav' => 'Modules',
            'html' => '<section class="card hero"><h2>Modules et POP</h2><p>Les modules présentent le métier et envoient des intentions vers les moteurs. Ils ne chargent pas le framework et ne contournent pas les composants publics.</p></section>
<section class="grid wide"><div class="card"><h3>Modules Hub</h3><p><code>MOD_HUB</code> affiche Project Health, Session Map, Workflow et panels. Il consomme les viewmodels, il ne décide pas le workflow.</p></div><div class="card"><h3>Modules musicien</h3><p>Sanitizer nettoie, Splitter prépare l’orchestration et l’intention, Humanizer interprète, FX Studio spatialise et colore.</p></div><div class="card"><h3>POP Project Guard</h3><p><code>POP_WORKING_PROJECT_PROBE</code>, <code>POP_PROJECT_CERTIFICATION</code>, <code>POP_PROJECT_IMPORT</code> sont des modules normaux hébergés en popup via FSM dédiée.</p></div></section>
<section class="callout warn"><b>Rappel :</b> les POP ne sont pas des composants spéciaux. Ils suivent le contrat module.</section>',
        ],
        'fsm' => [
            'title' => 'FSM',
            'kicker' => 'Maestro V5',
            'lead' => 'Tables FSM, wildcard et séparation des domaines.',
            'nav' => 'FSM',
            'html' => '<section class="card hero"><h2>FSM et tables dédiées</h2><p>La FSM est le microprocesseur de contrôle : signal, état courant, action, état suivant. Les tables sont séparées par domaine.</p></section>
<table><thead><tr><th>Table</th><th>Domaine</th><th>Rôle</th></tr></thead><tbody>
<tr><td><code>MAESTRO_FSM</code></td><td>Maestro</td><td>Boot et navigation Maestro.</td></tr>
<tr><td><code>ADMINER_FSM</code></td><td>Adminer</td><td>Boot Adminer autonome.</td></tr>
<tr><td><code>PROJECT_GUARD_FSM</code></td><td>Project Guard</td><td>Workdir, certification, import, POP Project Guard.</td></tr>
<tr><td><code>WORKFLOW_FSM</code></td><td>Workflow musicien</td><td>Microcode des recommandations : Sanitize, Intent, Split, Humanize, FX/Mix.</td></tr>
</tbody></table>
<section class="card"><h2>Wildcard explicite</h2><p>Le wildcard <code>*</code> est autorisé uniquement comme règle visible en table. Il n’est pas un fallback silencieux.</p><div class="flow">1. signal exact + state exact
2. signal exact + state "*"
3. signal "*" + state exact
4. signal "*" + state "*"
5. aucun match → erreur explicite</div></section>',
        ],
        'concepts' => [
            'title' => 'Concepts',
            'kicker' => 'Maestro V5',
            'lead' => 'Carte des concepts et contrats documentaires.',
            'nav' => 'Concepts',
            'html' => '<section class="card hero"><h2>Concepts et cahiers des charges</h2><p>Les documents <code>DOC/10_CONCEPTS</code> gardent les décisions stables, les contrats de paliers et les analyses d’architecture.</p></section>
<section class="grid wide"><div class="card"><h3>À lire en priorité</h3><ul><li><a href="@route/concept-project-guard">Project Guard</a></li><li><a href="@route/concept-ui-layer-object-factory">UI Layer Object Factory</a></li><li><a href="@route/concept-workflow-orchestrator-home">Accueil / Workflow / Framework P88C5</a></li></ul></div><div class="card"><h3>Rôle</h3><p>Ces documents inspirent et cadrent. Le contrat actif reste <code>AGENTS.md</code> et les sources runtime.</p></div></section>',
        ],
        'contrat' => [
            'title' => 'Contrat',
            'kicker' => 'Maestro V5',
            'lead' => 'Résumé lisible du contrat AGENTS.md.',
            'nav' => 'Contrat',
            'html' => '<section class="card hero"><h2>Contrat MAESTRO</h2><p>Le contrat de référence est <code>AGENTS.md</code>. Cette page résume les points incontournables.</p></section>
<section class="grid wide"><div class="card"><h3>0 fallback</h3><p>Chemin officiel ou erreur explicite. Pas de secours caché.</p></div><div class="card"><h3>SYS unique</h3><p><code>SYS</code> est le bus runtime public. Pas de <code>_G</code>, pas de <code>SYS.Services</code>.</p></div><div class="card"><h3>UI officielle</h3><p>Factory et composants publics sont souverains. Les modules n\'inventent pas de widgets locaux.</p></div><div class="card"><h3>Documentation miroir</h3><p>Quand une doc Markdown est maintenue, son HTML doit être livré aussi.</p></div></section>',
        ],
        'logs' => [
            'title' => 'Logs',
            'kicker' => 'Maestro V5',
            'lead' => 'Page secondaire de la documentation MAESTRO.',
            'nav' => 'Logs',
            'html' => '<section class="card hero"><h2>Logs</h2><p>Page conservée. Utiliser la navigation ci-dessus pour revenir à l’accueil ou aux pages framework mises à jour.</p></section><section class="card"><p>Les logs runtime doivent rester utiles : boot, erreurs, transitions, actions utilisateur. Les logs de debug répétitifs sont désactivés par défaut.</p></section>',
        ],
        'migration' => [
            'title' => 'Migration',
            'kicker' => 'Maestro V5',
            'lead' => 'Page secondaire de la documentation MAESTRO.',
            'nav' => 'Migration',
            'html' => '<section class="card hero"><h2>Migration</h2><p>Page conservée. Utiliser la navigation ci-dessus pour revenir à l’accueil ou aux pages framework mises à jour.</p></section>',
        ],
        'reference-sql' => [
            'title' => 'SQL référence',
            'kicker' => 'Maestro V5',
            'lead' => 'Page secondaire de la documentation MAESTRO.',
            'nav' => 'Référence SQL',
            'html' => '<section class="card hero"><h2>SQL référence</h2><p>Page conservée. Utiliser la navigation ci-dessus pour revenir à l’accueil ou aux pages framework mises à jour.</p></section>',
        ],
        'uml' => [
            'title' => 'UML',
            'kicker' => 'Maestro V5',
            'lead' => 'Page secondaire de la documentation MAESTRO.',
            'nav' => 'UML',
            'html' => '<section class="card hero"><h2>UML</h2><p>Page conservée. Utiliser la navigation ci-dessus pour revenir à l’accueil ou aux pages framework mises à jour.</p></section>',
        ],
        'rapport-doc-p81doc8' => [
            'title' => 'Rapport DOC P81DOC8',
            'kicker' => 'Maestro V5',
            'lead' => 'Présentation d’origine conservée — contrôles liens/UML',
            'nav' => 'Rapport DOC P81DOC8',
            'html' => '<p>Rapport de génération documentaire P81DOC8.</p>
  <table><tr><th>Contrôle</th><th>Résultat</th></tr>
  <tr><td>Base</td><td><code>DOC.zip</code> origine utilisateur</td></tr>
  <tr><td>CSS présentation</td><td><code>DOC/Maestro_v5/doc_style.css</code> conservé</td></tr>
  <tr><td>Liens HTML internes</td><td>OK — aucun 404 détecté</td></tr>
  <tr><td>UML échappés</td><td>OK — aucun bloc \\n détecté dans les UML générés</td></tr>
  <tr><td>Runtime Lua</td><td>Aucun fichier runtime modifié</td></tr>
  </table>',
        ],
        'audit-ui-factory-dirty-p38' => [
            'title' => 'Audit UI Factory Dirty P38',
            'kicker' => 'Maestro V5',
            'lead' => 'Audit technique rendu par le package Maestro.',
            'nav' => 'Audit UI Factory',
            'html' => '<section class="card hero"><h2>Audit UI Factory Dirty P38</h2><p>Document technique rendu dynamiquement depuis le package Maestro.</p></section><section class="card"><pre># P38 — Audit dirty UI Factory / Console

Date : 2026-05-12

## Verdict

La Factory `MO_UI_FACTORY.lua` fonctionne en rendu immédiat `gfx` : elle prépare la frame, repeint le fond, appelle le module actif, puis applique le post-render et `gfx.update()`.

Dans ce modèle, il ne faut pas rendre la Factory &quot;dirty-skip&quot; globalement pour l&#x27;instant : si la Factory repeint le fond mais qu&#x27;un composant ne redessine pas son contenu visible, le texte disparaît. Le dirty doit donc réduire les recalculs coûteux, pas supprimer le dessin visible final.

## Points audités

- `UI.CaptureInputs()` : lecture centralisée de souris/clavier/molette, puis remise à zéro de `gfx.mouse_wheel`. Contrat correct pour éviter les doubles lectures.
- `UI.PrepareFrame()` : ouvre/retaille la fenêtre, met à jour les métriques, repeint le fond et le châssis de base. Coûteux mais nécessaire tant qu&#x27;il n&#x27;existe pas de framebuffer Factory fiable.
- `UI.Draw_Current_Module()` : délègue au module actif ; les composants doivent gérer leurs caches internes.
- `UI.PostRender()` : dessine les couches finales et fait `gfx.update()`. Un skip global serait risqué pour les overlays, grilles, halo et queue high-priority.

## Décision P38

Ne pas modifier la Factory pour sauter des frames. La correction est placée dans `CPNT_Console.lua` :

```text
content_dirty  = contenu / police / DPI / prompt / couleur change
viewport_dirty = scroll_x / scroll_y / taille viewport / line_step change
render frame   = toujours redessiner les lignes visibles préparées
```

La Factory reste souveraine et immédiate ; la Console devient responsable de ne plus recalculer le clipping à chaque frame.

## Contrat retenu

```text
contenu inchangé + pas de scroll + layout stable
=&gt; aucun recalcul clipping
=&gt; draw direct des lignes visibles déjà préparées

scroll / drag / wheel
=&gt; rebuild viewport uniquement
=&gt; pas de remesure complète du contenu

contenu changé
=&gt; rebuild content_cache + invalidation viewport
```

## Périmètre futur possible

Une optimisation globale Factory ne doit être envisagée que si un framebuffer retained fiable est validé pour toute la fenêtre. Tant que `gfx.blit` offscreen n&#x27;est pas stable dans ce contexte, la Factory ne doit pas sauter le rendu global.
</pre></section>',
        ],
        'concept-project-guard' => [
            'title' => 'Project Guard',
            'kicker' => 'Concept Maestro V5',
            'lead' => 'Page conceptuelle interne réservée à la documentation Maestro.',
            'nav' => 'Project Guard',
            'html' => '<section class="card hero"><h2>Project Guard</h2><p>Ce lien est intégré au routage dynamique du site Maestro. Le fichier conceptuel complet n’était pas présent dans le ZIP source fourni ; la page reste volontairement explicite pour éviter une 404 ou un lien mort.</p></section><section class="card"><p><a href="@route/concepts">Retour aux concepts</a></p></section>',
        ],
        'concept-ui-layer-object-factory' => [
            'title' => 'UI Layer Object Factory',
            'kicker' => 'Concept Maestro V5',
            'lead' => 'Page conceptuelle interne réservée à la documentation Maestro.',
            'nav' => 'UI Layer Object Factory',
            'html' => '<section class="card hero"><h2>UI Layer Object Factory</h2><p>Ce lien est intégré au routage dynamique du site Maestro. Le fichier conceptuel complet n’était pas présent dans le ZIP source fourni ; la page reste volontairement explicite pour éviter une 404 ou un lien mort.</p></section><section class="card"><p><a href="@route/concepts">Retour aux concepts</a></p></section>',
        ],
        'concept-workflow-orchestrator-home' => [
            'title' => 'Workflow Orchestrator',
            'kicker' => 'Concept Maestro V5',
            'lead' => 'Page conceptuelle interne réservée à la documentation Maestro.',
            'nav' => 'Workflow Orchestrator',
            'html' => '<section class="card hero"><h2>Workflow Orchestrator</h2><p>Ce lien est intégré au routage dynamique du site Maestro. Le fichier conceptuel complet n’était pas présent dans le ZIP source fourni ; la page reste volontairement explicite pour éviter une 404 ou un lien mort.</p></section><section class="card"><p><a href="@route/concepts">Retour aux concepts</a></p></section>',
        ],
        'documentation' => [
            'title' => 'Documentation Maestro V5',
            'kicker' => 'Maestro V5',
            'lead' => 'Documentation officielle intégrée dynamiquement dans le package Maestro.',
            'nav' => 'Documentation',
            'html' => '<section class="card hero"><h2>Documentation Maestro V5</h2><p>Les pages sont servies par le routeur du package <code>sites/maestro</code>, sans ouverture directe de fichiers HTML statiques.</p></section><section class="grid wide"><div class="card"><h3>Accueil</h3><p><a href="@route/accueil">Ouvrir</a></p></div><div class="card"><h3>Guide utilisateur</h3><p><a href="@route/guide-utilisateur">Ouvrir</a></p></div><div class="card"><h3>Workflow musicien</h3><p><a href="@route/workflow">Ouvrir</a></p></div><div class="card"><h3>Framework</h3><p><a href="@route/framework">Ouvrir</a></p></div><div class="card"><h3>Architecture</h3><p><a href="@route/architecture">Ouvrir</a></p></div><div class="card"><h3>Objets</h3><p><a href="@route/objets">Ouvrir</a></p></div><div class="card"><h3>Engines</h3><p><a href="@route/engines">Ouvrir</a></p></div><div class="card"><h3>Composants</h3><p><a href="@route/composants">Ouvrir</a></p></div><div class="card"><h3>Modules</h3><p><a href="@route/modules">Ouvrir</a></p></div><div class="card"><h3>FSM</h3><p><a href="@route/fsm">Ouvrir</a></p></div><div class="card"><h3>Concepts</h3><p><a href="@route/concepts">Ouvrir</a></p></div><div class="card"><h3>Contrat</h3><p><a href="@route/contrat">Ouvrir</a></p></div><div class="card"><h3>Logs</h3><p><a href="@route/logs">Ouvrir</a></p></div><div class="card"><h3>Migration</h3><p><a href="@route/migration">Ouvrir</a></p></div><div class="card"><h3>Référence SQL</h3><p><a href="@route/reference-sql">Ouvrir</a></p></div><div class="card"><h3>UML</h3><p><a href="@route/uml">Ouvrir</a></p></div><div class="card"><h3>Audit UI Factory</h3><p><a href="@route/audit-ui-factory-dirty-p38">Ouvrir</a></p></div></section>',
        ],
    ],
    'en' => [
        'home' => [
            'title' => 'Maestro V5',
            'kicker' => 'Maestro V5',
            'lead' => 'Official Maestro documentation, integrated as a dynamic site package.',
            'nav' => 'Home',
            'cards' => [
                [
                    'title' => 'Documentation',
                    'text' => 'Open the French Maestro V5 documentation.',
                    'href' => '@maestro/fr/documentation',
                ],
                [
                    'title' => 'Workflow',
                    'text' => 'Open the musician workflow.',
                    'href' => '@maestro/fr/workflow',
                ],
                [
                    'title' => 'Framework',
                    'text' => 'Open the framework documentation.',
                    'href' => '@maestro/fr/framework',
                ],
            ],
        ],
    ],
    'es' => [
        'home' => [
            'title' => 'Maestro V5',
            'kicker' => 'Maestro V5',
            'lead' => 'Documentación oficial de Maestro, integrada como paquete dinámico del sitio.',
            'nav' => 'Inicio',
            'cards' => [
                [
                    'title' => 'Documentación',
                    'text' => 'Abrir la documentación francesa de Maestro V5.',
                    'href' => '@maestro/fr/documentation',
                ],
                [
                    'title' => 'Workflow',
                    'text' => 'Abrir el workflow musical.',
                    'href' => '@maestro/fr/workflow',
                ],
                [
                    'title' => 'Framework',
                    'text' => 'Abrir la documentación framework.',
                    'href' => '@maestro/fr/framework',
                ],
            ],
        ],
    ],
];
