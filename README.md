# FIUCC Portal

Portail numérique officiel de la **Communauté Congolaise de Final International University**.

Ce n'est pas un site vitrine ni un outil électoral ponctuel : c'est le hub numérique
permanent de la communauté. Le cycle du membre est le fil conducteur du produit :

```
arrivée → découverte → recensement → événements → souvenirs
   → rencontres → élections → alumni → mentor → (le cycle recommence)
```

## Stack

| Couche | Technologie |
|---|---|
| Backend | PHP 8.2+, architecture MVC légère, PDO |
| Base de données | MySQL 8 / MariaDB 10.6 (InnoDB, utf8mb4) |
| Frontend | HTML5, Tailwind CSS, JavaScript vanilla (Alpine.js seulement si nécessaire) |
| Email | PHPMailer (SMTP) |
| Serveur | Apache + PHP + MySQL, sans Node.js requis en production |

## Installation

```bash
git clone <url-du-repo> fiucc-portal
cd fiucc-portal
composer install
cp .env.example .env          # puis renseigner DB_* et MAIL_*
mysql -u root -p -e "CREATE DATABASE congolese_community CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p congolese_community < database/migrations/001_initial_schema.sql
```

Pointer le DocumentRoot d'Apache sur `public/` — jamais sur la racine du projet.

Permissions d'écriture nécessaires : `storage/` et `public/uploads/`.

## Structure

```
app/           Controllers, Models, Services, Middleware, Helpers, Core
config/        Configuration chargée depuis .env
database/      migrations/ et seeders/
public/        Racine web : index.php, assets/, uploads/
resources/     views/ (layouts, partials, pages)
routes/        web.php et admin.php
storage/       logs/, cache/, backups/
docs/          Documentation projet
```

## Règles de sécurité non négociables

1. **La table `votes` ne contient jamais `member_id`.** Le lien entre un électeur et son
   bulletin ne doit exister nulle part, ni en base, ni dans les logs.
2. Les tokens de vote et les codes OTP sont stockés **hashés**.
3. Toutes les requêtes SQL passent par des **requêtes préparées** PDO.
4. Tous les POST sont protégés par un **token CSRF**.
5. Aucune exécution de PHP dans `public/uploads/` ; validation du type MIME réel à l'upload.
6. Le droit de vote se déduit de `members.status = 'validated'` **et**
   `members.status_type = 'student'`. Un alumni reste membre sans être électeur.

## Charte graphique

| Rôle | Hex |
|---|---|
| Bleu principal | `#0063C6` |
| Bleu clair | `#7BBCFF` |
| Or (accents) | `#ECC117` |
| Rouge (alertes) | `#C00F1F` |
| Vert succès (extension FIUCC-21, confirmation uniquement) | `#15803D` |
| Fond | `#F8FAFC` |
| Texte | `#111827` |
| Texte secondaire | `#64748B` |
| Bordures | `#E5E7EB` |

Le bleu domine. L'or et le rouge sont des accents, jamais des couleurs de fond.

## Design system (Tailwind)

Le CSS est **compilé en local et commité** dans `public/assets/css/app.css` — pas de
CDN Tailwind en production (la prod n'a pas Node.js et ne doit lancer aucune étape de
build). Node.js n'est requis que sur la machine de développement, jamais en prod.

Les tokens (couleurs, typographie, rayons, ombres) sont définis dans
`tailwind.config.js`, alignés sur la charte graphique ci-dessus.

```bash
npm install          # une fois, installe le CLI Tailwind (devDependency)
npm run build:css    # à chaque fois qu'une vue utilise une nouvelle classe Tailwind
```

**Règle impérative :** toute nouvelle classe Tailwind ajoutée dans une vue doit être
suivie d'un `npm run build:css` puis d'un commit du fichier compilé
(`public/assets/css/app.css`). Sans ça, la classe n'existe pas en production — le
fichier commité est la seule source de vérité, pas le `tailwind.config.js` seul.

## Phases

Le développement suit six phases (voir Jira) : Foundation → Community → Content →
Election → Polish → Network & Alumni. Ne pas générer le projet entier en une passe :
vérifier que l'application fonctionne à la fin de chaque phase avant de continuer.
