# P38 — Audit dirty UI Factory / Console

Date : 2026-05-12

## Verdict

La Factory `MO_UI_FACTORY.lua` fonctionne en rendu immédiat `gfx` : elle prépare la frame, repeint le fond, appelle le module actif, puis applique le post-render et `gfx.update()`.

Dans ce modèle, il ne faut pas rendre la Factory "dirty-skip" globalement pour l'instant : si la Factory repeint le fond mais qu'un composant ne redessine pas son contenu visible, le texte disparaît. Le dirty doit donc réduire les recalculs coûteux, pas supprimer le dessin visible final.

## Points audités

- `UI.CaptureInputs()` : lecture centralisée de souris/clavier/molette, puis remise à zéro de `gfx.mouse_wheel`. Contrat correct pour éviter les doubles lectures.
- `UI.PrepareFrame()` : ouvre/retaille la fenêtre, met à jour les métriques, repeint le fond et le châssis de base. Coûteux mais nécessaire tant qu'il n'existe pas de framebuffer Factory fiable.
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
=> aucun recalcul clipping
=> draw direct des lignes visibles déjà préparées

scroll / drag / wheel
=> rebuild viewport uniquement
=> pas de remesure complète du contenu

contenu changé
=> rebuild content_cache + invalidation viewport
```

## Périmètre futur possible

Une optimisation globale Factory ne doit être envisagée que si un framebuffer retained fiable est validé pour toute la fenêtre. Tant que `gfx.blit` offscreen n'est pas stable dans ce contexte, la Factory ne doit pas sauter le rendu global.
