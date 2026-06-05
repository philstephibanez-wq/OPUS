# P112D4B — ASAP Render / Controller / Form / Helper Port

## Rôle

Porter un bloc plus massif du cœur ASAP PHP 8 autour de la représentation testable.

## Classes ajoutées

- `ASAP\Controller`
- `ASAP\Renderer`
- `ASAP\Form`
- `ASAP\Helper`
- `ASAP\Url`
- `ASAP\Menu`

## Pipeline mis à jour

```text
REQUEST
  -> SiteResolver
  -> FSM Guard
  -> ACL Guard
  -> Router
  -> ControllerDispatcher
  -> Controller
  -> ViewModel
  -> HtmlRenderer
  -> Response
```

## Contrat

- `Renderer represents`
- `Controller orchestrates`
- `Dispatcher dispatches`
- `ViewModel carries prepared data`
- `Form validates`
- `Helper transforms`
- `URL generates`
- `Menu carries navigation data`

## I18N

Les catalogues `fr/en/es/ru` sont enrichis avec les clés du nouveau palier.
