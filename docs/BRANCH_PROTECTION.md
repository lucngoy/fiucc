# Configuration GitHub recommandée

## Branches protégées

**`main`**
- Require a pull request before merging (1 approbation minimum)
- Require status checks to pass : `php-lint`
- Require conversation resolution before merging
- Do not allow bypassing the above settings
- Restrict deletions

**`develop`**
- Require a pull request before merging
- Require status checks to pass : `php-lint`

## Labels à créer

| Label | Couleur | Usage |
|---|---|---|
| `security` | `#C00F1F` | Vote, tokens, auth, uploads — double revue obligatoire |
| `phase-1-foundation` | `#0063C6` | |
| `phase-2-community` | `#0063C6` | |
| `phase-3-content` | `#7BBCFF` | |
| `phase-4-election` | `#ECC117` | |
| `phase-5-polish` | `#64748B` | |
| `phase-6-network` | `#7BBCFF` | |
| `blocked` | `#C00F1F` | En attente d'un autre ticket |
| `good-first-issue` | `#ECC117` | Pour les nouveaux contributeurs de la communauté |

## Secrets à ne jamais commiter

`DB_PASSWORD`, `MAIL_PASSWORD`, `APP_KEY`. Si un secret fuite dans l'historique,
le rotate immédiatement : le retirer du dernier commit ne suffit pas.

## Intégration Jira ↔ GitHub

Installer l'app **GitHub for Jira**. Une fois connectée, mentionner la clé du ticket
dans le nom de branche et dans les messages de commit suffit à faire remonter
les commits, branches et PR dans le ticket Jira correspondant.
