# Contribuer au FIUCC Portal

## Branches

| Branche | Rôle |
|---|---|
| `main` | Production. Protégée : aucun push direct, PR obligatoire. |
| `develop` | Intégration. Base de toutes les branches de travail. |
| `feature/FIUCC-xx-description` | Nouvelle fonctionnalité |
| `fix/FIUCC-xx-description` | Correction de bug |
| `hotfix/FIUCC-xx-description` | Correction urgente branchée sur `main` |

## Commits

Format : `type(scope): description — FIUCC-xx`

```
feat(census): add OTP email verification — FIUCC-23
fix(vote): prevent double submission on slow networks — FIUCC-58
refactor(db): extract PDO connection into a singleton — FIUCC-5
```

Types : `feat`, `fix`, `refactor`, `style`, `docs`, `test`, `chore`, `security`.

Le numéro de ticket Jira est obligatoire : c'est lui qui lie le code au cahier des charges.

## Pull requests

- Une PR = un ticket. Si la PR touche à plusieurs epics, elle est trop grosse.
- La description reprend le ticket Jira et liste ce qui a été vérifié manuellement.
- Toute PR portant le label `security` (vote, tokens, uploads, auth) exige une revue
  d'un second relecteur avant merge. Pas d'exception, même pour un changement d'une ligne.

## Avant d'ouvrir une PR

```bash
find app public routes -name '*.php' -exec php -l {} \;   # aucune erreur de syntaxe
```

- [ ] Aucune credential en dur, aucune modification du `.env` commitée
- [ ] Toutes les requêtes SQL sont préparées
- [ ] Toutes les sorties sont échappées
- [ ] Le formulaire POST ajouté porte un token CSRF
- [ ] Testé sur mobile (390px) et desktop
