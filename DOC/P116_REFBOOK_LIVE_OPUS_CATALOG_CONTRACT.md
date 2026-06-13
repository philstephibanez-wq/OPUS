# P116 — Contrat RefBook / OPUS vivant

Statut : **décision d'architecture validée**.

Ce document fige le contrat de travail entre OPUS et OPUS_REF_BOOK pour la documentation runtime du framework.

## Principe souverain

OPUS est la source de vérité.

OPUS_REF_BOOK ne doit jamais porter, copier ou conserver une vérité autonome sur les classes OPUS.

Formule de référence :

```text
OPUS s'auto-documente.
OPUS_REF_BOOK reflète OPUS vivant.
```

## Interdiction des catalogues figés pour les classes OPUS

Pour les classes, interfaces, traits, enums, méthodes publiques et symboles runtime OPUS, sont interdits comme source de vérité :

- fichiers JSON de classes ;
- snapshots persistants ;
- caches disque de symboles ;
- listes générées considérées comme vérité ;
- index obsolètes affichés comme fiables ;
- données hardcodées côté RefBook ;
- fallback silencieux vers un ancien catalogue.

Un cache mémoire strictement limité à une requête PHP est autorisé uniquement comme optimisation locale non persistante.

## Catalogue runtime obligatoire côté OPUS

OPUS doit exposer un catalogue vivant de ses classes réelles.

Responsabilité attendue d'un service OPUS dédié, par exemple :

```text
Opus\RefBook\RuntimeClassCatalog
```

ou nom équivalent validé.

Ce service doit :

- scanner dynamiquement `framework/Opus/**/*.php` ;
- déduire les FQCN selon la convention PSR-4 `Opus\...` ;
- vérifier que chaque symbole existe réellement au runtime ;
- utiliser `ReflectionClass`, `ReflectionMethod` et les objets Reflection adaptés ;
- retourner uniquement les classes/interfaces/traits/enums réellement présentes ;
- exclure ou signaler explicitement les fichiers invalides ;
- ne jamais inventer de symbole ;
- ne jamais dépendre d'un JSON de vérité.

Si un fichier source disparaît, la classe doit disparaître immédiatement du catalogue vivant.

## Données minimales exposées par classe

Chaque entrée de classe doit exposer au minimum :

- FQCN ;
- namespace ;
- short name ;
- type : class, interface, trait, enum ;
- chemin fichier ;
- domaine résolu ;
- parent class ;
- interfaces ;
- traits utilisés ;
- constantes publiques ;
- méthodes publiques ;
- attributs PHP ;
- docblock ;
- lignes source si disponibles ;
- date de modification du fichier source.

Chaque méthode publique doit exposer au minimum :

- nom ;
- visibilité ;
- static oui/non ;
- paramètres ;
- type de retour ;
- classe déclarante ;
- docblock ;
- ligne début/fin si disponible.

## Classification obligatoire : pas de `unclassified`

`unclassified` est interdit dans OPUS_REF_BOOK.

Aucun symbole OPUS ne doit être affiché publiquement avec :

- `unclassified` ;
- `unknown` ;
- `misc` ;
- `other` ;
- catégorie fallback silencieuse.

Le domaine doit être résolu depuis le namespace et/ou le chemin source.

Exemples :

```text
Opus\Template\*  -> Template
Opus\Database\*  -> Database
Opus\RefBook\*   -> RefBook
Opus\Lstsa\*     -> LSTSA
Opus\Routing\*   -> Routing
```

Si un domaine ne peut pas être résolu, ce n'est pas une catégorie UI : c'est une erreur de contrat.

Erreur attendue :

```text
OPUS_REFBOOK_DOMAIN_UNRESOLVED
```

Diagnostic minimal :

- classe ;
- fichier ;
- namespace ;
- chemin ;
- raison.

## Responsabilités séparées

OPUS :

- sait ce qu'il est ;
- expose les classes réelles ;
- expose les domaines résolus ;
- expose la réflexion runtime ;
- signale les incohérences source/runtime.

OPUS_REF_BOOK :

- consomme le catalogue vivant OPUS ;
- prépare ses ViewModels ;
- affiche l'information ;
- ne stocke pas la vérité des classes ;
- ne corrige pas les données OPUS ;
- ne masque pas les incohérences.

## Search / API RefBook

La recherche RefBook doit consulter le catalogue vivant OPUS pour les classes OPUS.

Exemple :

```text
q=smarty
-> catalogue runtime OPUS
-> résultat uniquement si Opus\Template\Smarty existe réellement dans l'OPUS actif
```

Si `framework/Opus/Template/Smarty.php` est absent, `Opus\Template\Smarty` ne doit pas être retourné.

## ScoreTemplate et moteurs legacy

Décision associée : ScoreTemplate devient le moteur de templating cible OPUS.

Twig, Smarty et X64 sont des moteurs de transition ou hérités et doivent disparaître du runtime OPUS final.

Ordre obligatoire pour éviter de casser OPUS_REF_BOOK :

1. créer le catalogue vivant OPUS ;
2. faire consommer ce catalogue par OPUS_REF_BOOK ;
3. migrer OPUS_REF_BOOK vers ScoreTemplate ;
4. retirer définitivement Twig, Smarty, X64 et les adapters legacy.

## Paliers actés

```text
P116B2_OPUS_LIVE_CLASS_CATALOG
P116B3_OPUS_REFBOOK_DOMAIN_RESOLVER
P116C_REFBOOK_USES_LIVE_OPUS_CATALOG
P116D_SCORETEMPLATE_REFBOOK_MIGRATION
P116E_REMOVE_TWIG_SMARTY_X64_FINAL
```

## Règle finale

Un RefBook qui affiche une classe absente de l'OPUS actif ment.

Un RefBook qui affiche `unclassified` masque un bug.

Les deux situations sont interdites.
