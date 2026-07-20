# OPUS SHARED CODEMIRROR / MERMAID CONTRACT

STATUT: OBLIGATOIRE

CodeMirror et Mermaid sont des briques du framework `Opus`.

## Implantation

```text
Opus/
└── Assets/
    ├── src/
    │   ├── opus-codemirror-entry.js
    │   └── opus-mermaid-entry.js
    └── dist/
        ├── codemirror/
        └── mermaid/
```

Aucun répertoire `assets-src` n'est autorisé à la racine du dépôt.

## Règles

- aucune application ne maintient sa propre copie de CodeMirror ou Mermaid;
- aucun CDN direct;
- les sources et sorties de build appartiennent au framework `Opus`;
- OWASYS ne contient que les points d'intégration;
- Mermaid utilise `securityLevel: strict`;
- les droits d'édition relèvent de l'ACL OPUS, jamais de CodeMirror.

## Build

```cmd
npm install
npm run build
```
