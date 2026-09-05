# CLAUDE.md — FIUCC Portal

Instructions permanentes pour Claude Code sur ce dépôt. À lire avant toute modification.

## Le projet

Portail numérique officiel de la **Communauté Congolaise de Final International University**.

Ce n'est pas un site vitrine, ni un outil électoral ponctuel. C'est le hub numérique
permanent de la communauté. Le fil conducteur est le cycle de vie du membre :

```
arrivée à Final → découverte → recensement → événements → souvenirs
   → rencontres → élections → alumni → mentor → (le cycle recommence)
```

Chaque étape doit correspondre à une fonctionnalité réelle. Une étape sans page associée
est une rupture du parcours.

## Documents de référence

Lis-les avant de coder, dans cet ordre :

| Fichier | Contenu |
|---|---|
| `Projet_Docs_Ref/CAHIER_DES_CHARGES.md` | La spécification complète, 58 sections. Fait autorité. |
| `database/migrations/001_initial_schema.sql` | Le schéma MySQL. Déjà écrit, ne pas le refondre. |
| `routes/web.php` et `routes/admin.php` | Les routes déjà déclarées. |
| `Projet_Docs_Ref/stitch_document_management_hub/*/code.html` | Maquettes HTML/Tailwind par écran, exportées de Stitch. Référence visuelle. |
| `Projet_Docs_Ref/stitch_document_management_hub/fiucc_institutional_portal/DESIGN.md` | Tokens du design system. |
| `Projet_Docs_Ref/FIUCC_cles_et_branches.md` | Correspondance tickets Jira ↔ noms de branches. |
| `CONTRIBUTING.md` | Conventions de branches et de commits. |

En cas de contradiction entre un de ces fichiers et une consigne de chat, le cahier des
charges l'emporte. Signale la contradiction plutôt que de trancher seul.

## Stack — contraintes fermes

- **PHP 8.2+**, architecture MVC légère écrite à la main. **Aucun framework** : ni Laravel,
  ni Symfony, ni Slim. C'est une contrainte du cahier des charges, pas une préférence.
- **PDO + MySQL 8**. Requêtes préparées partout, sans exception.
- **PHPMailer** pour l'email. Déjà installé.
- **Tailwind CSS + JavaScript vanilla**. Alpine.js seulement si un comportement le rend
  vraiment nécessaire. Pas de React, pas de Vue, pas de bundler obligatoire en production.
- Le projet doit tourner sur un hébergement Apache/PHP/MySQL classique, **sans Node.js
  requis en production**.
- Le CSS Tailwind est **compilé en local et commité** dans `public/assets/css/app.css`
  (pas de CDN Tailwind). Node.js est un outil de dev uniquement (`npm run build:css`),
  jamais requis en prod. **Toute nouvelle classe Tailwind ajoutée dans une vue doit être
  suivie d'un rebuild et d'un commit du CSS compilé** — voir README.md.
- N'ajoute aucune dépendance Composer sans la justifier et le signaler.

## Structure

```
app/Controllers/   Contrôleurs HTTP
app/Models/        Accès aux données, un modèle par table principale
app/Services/      Logique métier (élection, mail, upload, tokens)
app/Middleware/    auth, admin, csrf, vote
app/Helpers/       Fonctions utilitaires
app/Core/          Router, Controller, Model, View, Database, Config, Env
config/            Fichiers de configuration retournant un tableau
database/          migrations/ et seeders/
public/            Racine web. index.php est l'unique point d'entrée.
resources/views/   Vues PHP, organisées par domaine
routes/            web.php et admin.php
storage/           logs/, cache/, backups/ — jamais servis par Apache
```

## Règles de sécurité — non négociables

Ces règles priment sur toute autre considération, y compris la simplicité ou la rapidité.

1. **La table `votes` ne contient jamais `member_id`**, ni aucune clé vers `members`,
   ni référence au token utilisé. Le lien entre un électeur et son bulletin ne doit
   exister nulle part : ni en base, ni dans les logs, ni dans une session.
   Un garde-fou CI le vérifie (`.github/scripts/check_vote_anonymity.py`).
2. L'écriture d'un bulletin et le marquage du token comme utilisé sont **deux opérations
   distinctes**, sans identifiant commun.
3. Les **tokens de vote et codes OTP sont stockés hashés**, jamais en clair.
4. **Toutes** les requêtes SQL passent par des requêtes préparées PDO.
5. **Toutes** les sorties sont échappées (`htmlspecialchars` avec `ENT_QUOTES` et `UTF-8`).
6. **Tous** les POST portent un token CSRF vérifié côté serveur.
7. Uploads : type MIME réel vérifié avec `finfo`, jamais `$_FILES['type']` ni l'extension.
   Nom de fichier aléatoire. Aucune exécution PHP dans `public/uploads/`.
8. Le droit de vote se déduit de `members.status = 'validated'` **et**
   `members.status_type = 'student'`. Un alumni reste membre sans être électeur.
9. Aucune credential en dur. Tout passe par `.env`, qui n'est jamais commité.
10. En production, aucune trace d'erreur ne fuit vers le navigateur.

## Charte graphique

| Rôle | Hex | Usage |
|---|---|---|
| Bleu principal | `#0063C6` | Navigation, boutons primaires, titres |
| Bleu clair | `#7BBCFF` | Fonds légers, hover, cartes |
| Or | `#ECC117` | Badges, accents, CTA secondaires |
| Rouge | `#C00F1F` | Alertes, actions destructives |
| Fond | `#F8FAFC` | |
| Texte | `#111827` | |
| Texte secondaire | `#64748B` | |
| Bordures | `#E5E7EB` | |

Le bleu domine. L'or et le rouge sont des accents, jamais des couleurs de fond.
Pas de texte or sur blanc. Le rouge n'est jamais un bouton ordinaire.

Style visé : moderne, institutionnel, africain subtil, universitaire.
Style à éviter : « site d'association des années 2010 ».

## Méthode de travail

**Une phase à la fois, un ticket à la fois.** Ne génère jamais le projet entier en une
passe. Après chaque ticket, vérifie que l'application fonctionne avant de continuer.

Ordre imposé des phases :

| Phase | Epic Jira | Contenu |
|---|---|---|
| 1 | FIUCC-7 | Foundation — config, routing, MVC, PDO, auth, design system |
| 2 | FIUCC-8 | Community — recensement, OTP, membres, gouvernement |
| 3 | FIUCC-9 | Content — événements, news, galerie, documents, accueil |
| 4 | FIUCC-10 | Election — élections, candidats, vote anonyme, résultats |
| 5 | FIUCC-11 | Polish — responsive, SEO, a11y, sécurité, perfs, tests |
| 6 | FIUCC-12 | Network & Alumni — annuaire, alumni, mentorat, onboarding |

Les stories vont de `FIUCC-13` à `FIUCC-90`, dans l'ordre du fichier
`Projet_Docs_Ref/FIUCC_cles_et_branches.md`.

`FIUCC-13` (arborescence) et `FIUCC-18` (schéma MySQL) sont **déjà faits**.

## Git

- Branche de travail : `develop`. Jamais de commit direct sur `main`.
- Une branche par ticket : `feature/FIUCC-<numéro>-<description-courte>`.
- Message de commit : `type(scope): description — FIUCC-<numéro>`
  Types : `feat`, `fix`, `refactor`, `style`, `docs`, `test`, `chore`, `security`.
- La clé du ticket est obligatoire dans le message : c'est elle qui fait le lien avec Jira.
- Ne pousse jamais sans que `php -l` passe sur les fichiers modifiés.

## Definition of done d'un ticket

- [ ] Le code fait ce que décrit le ticket, rien de plus
- [ ] Requêtes préparées, sorties échappées, CSRF sur les POST
- [ ] Testé manuellement dans le navigateur, y compris un cas d'erreur
- [ ] Responsive vérifié à 390px et en desktop si le ticket touche à une vue
- [ ] Si une vue utilise une nouvelle classe Tailwind : `npm run build:css` relancé et
  `public/assets/css/app.css` commité
- [ ] `php -l` passe
- [ ] Commit avec la clé Jira

## Ce qu'il ne faut pas faire

- Installer un framework ou un ORM.
- Refondre le schéma MySQL existant. S'il manque une colonne, ajoute une migration
  `002_*.sql`, ne modifie pas `001_initial_schema.sql`.
- Créer des fichiers dans `Projet_Docs_Ref/` : c'est de la documentation de référence,
  en lecture seule.
- Anticiper sur une phase ultérieure « tant qu'on y est ».
- Écrire du texte d'interface en français : l'interface est en anglais, avec traduction
  française via le système i18n. Le code et les commentaires sont en français.
- Commiter `.env`, `vendor/`, ou un fichier `.DS_Store`.

## Environnement local

- macOS, XAMPP. Racine : `/Applications/XAMPP/xampfiles/htdocs/web/fiucc-portal`
- PHP 8.2.4, Composer 2.7.7
- Le DocumentRoot Apache doit pointer sur `public/`, jamais sur la racine du projet.
- Base de données : `congolese_community`
