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
| Backend | PHP 8.2+, architecture MVC légère écrite à la main, PDO — aucun framework |
| Base de données | MySQL 8 / MariaDB 10.6 (InnoDB, utf8mb4) |
| Frontend | HTML5, Tailwind CSS (compilé, commité), JavaScript vanilla |
| Email | PHPMailer (SMTP) |
| Serveur | Apache (`mod_rewrite`, `mod_headers`) + PHP + MySQL, sans Node.js requis en production |

## Prérequis

- PHP **8.2 ou plus récent**, avec les extensions : `pdo_mysql`, `mbstring`, `fileinfo`,
  `gd` (redimensionnement des images uploadées), `zip` (sauvegardes, export de documents).
  `exif` est utilisée si présente (orientation des photos) mais n'est pas bloquante.
- MySQL 8 ou MariaDB 10.6+.
- Apache avec `mod_rewrite` (routage via `public/.htaccess`) et `mod_headers` (CSP et
  en-têtes de sécurité, voir ce même fichier).
- Composer 2.x.
- Node.js **uniquement en développement**, pour recompiler le CSS Tailwind — jamais requis
  pour faire tourner l'application en production (le CSS compilé est commité).

## Installation

```bash
git clone <url-du-repo> fiucc-portal
cd fiucc-portal
composer install
cp .env.example .env          # puis renseigner APP_KEY, DB_*, MAIL_* (voir plus bas)
mysql -u root -p -e "CREATE DATABASE congolese_community CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php database/migrate.php    # applique les migrations, une seule fois chacune
php database/seed.php       # optionnel, refusé si APP_ENV=production : comptes de
                             # test et données de démonstration uniquement
```

`APP_KEY` peut rester vide pour l'instant (réservé, voir `config/app.php`) — ce n'est pas
un blocage à l'installation.

`database/migrate.php` fonctionne aussi en production (changement de schéma) ; relancer la
commande est sans risque, chaque migration ne s'applique qu'une fois.

Pointer le **DocumentRoot** d'Apache sur `public/` — jamais sur la racine du projet, sous
peine d'exposer `app/`, `config/`, `.env` et `storage/` directement au navigateur.

Permissions d'écriture nécessaires pour l'utilisateur du serveur web (souvent `www-data`
ou `_www`) : `storage/logs/`, `storage/cache/`, `storage/backups/`, `storage/documents/`,
et chaque sous-dossier de `public/uploads/` (`candidates/`, `documents/`, `events/`,
`gallery/`, `members/`, `news/`, `partners/`). Ces sous-dossiers et le
`public/uploads/.htaccess` qui interdit toute exécution PHP dedans (règle de sécurité non
négociable, section 35 du cahier) sont versionnés — un clone frais les a déjà, il suffit
de vérifier les permissions, pas de les recréer.

### Exemple de configuration Apache (vhost)

```apache
<VirtualHost *:80>
    ServerName fiucc.example.org
    DocumentRoot /var/www/fiucc-portal/public

    <Directory /var/www/fiucc-portal/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/fiucc-error.log
    CustomLog ${APACHE_LOG_DIR}/fiucc-access.log combined
</VirtualHost>
```

`AllowOverride All` est nécessaire : le routage (`RewriteRule`) et les en-têtes de
sécurité (CSP, `X-Frame-Options`...) sont définis dans `public/.htaccess`, pas dans la
config Apache globale. En production, cette configuration doit être servie en **HTTPS**
(voir la checklist plus bas — ce n'est pas une option).

## Configuration email (SMTP réel)

`config/mail.php` a deux modes, pilotés par `MAIL_DRIVER` :

- `log` (**défaut**) : aucun email n'est réellement envoyé, chaque message est écrit dans
  `storage/logs/mail.log`. C'est le mode sûr pour développer sans risquer d'envoyer un
  email réel à une adresse `@final.edu.tr`.
- `smtp` : envoi réel via PHPMailer. **À positionner explicitement en production.**

Pour activer l'envoi réel, dans `.env` :

```env
MAIL_DRIVER=smtp
MAIL_HOST=smtp.example.org
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=notifications@example.org
MAIL_PASSWORD=<mot de passe ou mot de passe d'application>
MAIL_FROM_ADDRESS=notifications@example.org
MAIL_FROM_NAME="Communauté Congolaise de Final"
```

Quelques repères selon le fournisseur :

- **Gmail / Google Workspace** : `smtp.gmail.com`, port `587`, `tls`. Nécessite un
  [mot de passe d'application](https://myaccount.google.com/apppasswords) si la
  validation en deux étapes est active — le mot de passe du compte lui-même ne fonctionne
  pas.
- **Un serveur SMTP fourni par l'université** (`final.edu.tr`) : demander host/port/
  identifiants au service informatique. C'est l'option la plus cohérente avec l'usage
  réel (les emails partent au nom du domaine de la communauté), mais elle n'est pas
  encore configurée — voir la checklist de mise en production.
- **SendGrid / Mailgun / un service transactionnel** : host/port fournis par le service,
  `MAIL_USERNAME`/`MAIL_PASSWORD` sont la clé API et son identifiant technique, pas un
  compte personnel.

`MAIL_REVEAL_SECRETS=true` affiche les codes OTP en clair dans `mail.log` — **utile
uniquement en local** (`APP_ENV=local`) pour tester un parcours d'inscription sans boîte
mail réelle. Hors `local`, le réglage est ignoré (et un avertissement journalisé) même
s'il reste positionné à `true` par erreur : ne compte pas dessus, mets-le à `false` avant
tout déploiement.

**Vérifier l'envoi réel** une fois `MAIL_DRIVER=smtp` configuré : déclencher un parcours
qui envoie un email (inscription → OTP, ou validation d'un membre par un admin) et
confirmer sa réception, plutôt que de se fier à l'absence d'erreur seule.

## Sauvegardes

`App\Services\Backup` (section 41 du cahier des charges) produit une archive ZIP
(`database.sql` + `uploads/` + `documents/`) dans `storage/backups/` — jamais servi par
Apache, jamais commité. Deux déclenchements possibles :

- à la demande, depuis le back office (`/admin/backups`) ;
- en ligne de commande : `php bin/backup.php` (ou `--env=testing` pour cibler `.env.testing`).

Ce projet n'a aucune tâche planifiée intégrée — c'est volontaire (voir `bin/backup.php`),
la commande est faite pour être appelée par le cron de l'hébergeur. Exemple, une sauvegarde
chaque nuit à 2h :

```cron
0 2 * * * cd /var/www/fiucc-portal && php bin/backup.php >> storage/logs/backup-cron.log 2>&1
```

Il n'y a pas de rotation automatique des archives : elles s'accumulent dans
`storage/backups/` jusqu'à suppression manuelle (bouton du back office, ou `Backup::delete()`).
Prévoir une purge périodique si l'espace disque est contraint.

**Restaurer une sauvegarde** est une procédure manuelle (aucun bouton "restaurer" n'existe,
volontairement — une restauration est un geste rare et à risque, pas un clic accidentel) :

```bash
unzip fiucc_backup_2026-01-01_020000.zip -d restore/
mysql -u root -p congolese_community < restore/database.sql
cp -r restore/uploads/*   public/uploads/
cp -r restore/documents/* storage/documents/
```

## Tests

```bash
cp .env.testing.example .env.testing   # une fois, puis créer la base indiquée (vide)
composer test                           # migre .env.testing puis lance PHPUnit
```

`tests/bootstrap.php` refuse de démarrer si `APP_ENV` n'est pas exactement `testing` ou si
`DB_DATABASE` ne se termine pas par `_test` — voir `docs/SECURITE_ELECTIONS.md`. La suite
ne touche jamais la base de développement.

Avant de pousser une modification touchant à la sécurité (vote, auth, upload) : lancer
aussi `.github/scripts/check_vote_anonymity.py`, le garde-fou CI qui vérifie qu'aucune
requête ne relie `votes` à `members`.

## Checklist de mise en production

À vérifier avant et pendant le premier déploiement réel — chaque point correspond à un
comportement du code, pas à une recommandation générique.

- [ ] **`.env`** créé sur le serveur (jamais commité), avec `APP_ENV=production` et
      `APP_DEBUG=false`. `APP_ENV` différent de `local` active déjà, automatiquement,
      les cookies de session `Secure` (`public/index.php`) — **servir le site en HTTP
      simple en `production` casse silencieusement les connexions** (le navigateur
      rejette un cookie `Secure` sur une origine non-HTTPS). HTTPS n'est donc pas une
      option ici, c'est une dépendance directe du code d'authentification.
- [ ] Certificat TLS en place (Let's Encrypt ou équivalent) et toutes les URLs `APP_URL`
      en `https://`.
- [ ] Base de données de production créée, migrations appliquées
      (`php database/migrate.php`), **`php database/seed.php` jamais lancé** (il refuse
      de toute façon si `APP_ENV=production`, mais ne pas compter sur ce filet pour
      sauter l'étape de réflexion).
- [ ] Premier compte administrateur créé — pas de seed en production, donc pas de
      `test.admin`. Le créer directement en base avec un mot de passe fort :
      ```bash
      php -r "echo password_hash('un-mot-de-passe-fort-et-unique', PASSWORD_DEFAULT), PHP_EOL;"
      ```
      ```sql
      INSERT INTO users (email, password_hash, role)
      VALUES ('admin@final.edu.tr', '<hash généré ci-dessus>', 'admin');
      ```
      Se connecter une fois, puis retirer ce mot de passe de tout historique de shell.
- [ ] `MAIL_DRIVER=smtp` avec de vrais identifiants (voir section Email ci-dessus) —
      tant que ce n'est pas fait, aucun email de bienvenue, d'OTP ou de notification de
      candidature ne part réellement, ils restent dans `mail.log`. `MAIL_REVEAL_SECRETS`
      à `false`.
- [ ] `ALLOWED_EMAIL_DOMAINS` vérifié : c'est ce qui détermine qui peut s'inscrire. Une
      erreur ici bloque silencieusement tout le recensement (FIUCC-31/32).
- [ ] Permissions d'écriture confirmées pour l'utilisateur du serveur web sur
      `storage/logs/`, `storage/cache/`, `storage/backups/`, `storage/documents/`, et
      chaque sous-dossier de `public/uploads/`.
- [ ] Sauvegarde automatique planifiée (cron, voir section Sauvegardes) — et **au moins
      une restauration testée une fois**, sur un environnement de test, avant d'en avoir
      réellement besoin en urgence.
- [ ] `WHATSAPP_GROUP_URL` / `INSTAGRAM_URL` renseignées si les liens réels de la
      communauté existent au moment du déploiement (sinon les liens correspondants
      restent inertes dans le footer et la page « Nouveau à Final ? » — ce n'est pas une
      erreur, juste un contenu en attente, voir `CLAUDE.md`, Manques connus).
- [ ] Un `og:image` réel (1200×630) ajouté si disponible — sinon la prévisualisation de
      lien (WhatsApp, réseaux sociaux) utilise le sceau de la communauté étiré, fonctionnel
      mais peu engageant (voir `CLAUDE.md`, Manques connus).
- [ ] `npm run build:css` lancé une dernière fois et `public/assets/css/app.css` commité,
      si une classe Tailwind a été touchée après le dernier commit — la prod lit ce
      fichier tel quel, jamais Tailwind lui-même.
- [ ] Suite de tests verte (`composer test`) et garde-fou d'anonymité du vote vert
      (`python3 .github/scripts/check_vote_anonymity.py`) sur le commit déployé.
- [ ] Une inscription complète testée en conditions réelles après déploiement (recensement
      → email OTP réel reçu → validation admin → email de bienvenue réel reçu) — un test
      qui touche à la fois HTTPS, SMTP et la base en une seule fois.

## Structure

```
app/           Controllers, Models, Services, Middleware, Helpers, Core
config/        Configuration chargée depuis .env
database/      migrations/ et seeders/
public/        Racine web : index.php, assets/, uploads/
resources/     views/ (layouts, partials, pages)
routes/        web.php et admin.php
storage/       logs/, cache/, backups/, documents/ — jamais servis par Apache
docs/          Documentation complémentaire (voir plus bas)
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

La liste complète, avec le raisonnement derrière chaque règle, est dans `CLAUDE.md`
(section « Règles de sécurité — non négociables ») — c'est le document de référence pour
qui modifie le code, celui-ci est le point d'entrée pour qui le déploie.

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

## Documentation complémentaire

- `CLAUDE.md` — conventions de développement complètes (règles de sécurité détaillées,
  décisions d'architecture, manques connus) : à lire avant toute modification du code.
- `docs/DECISIONS_SECURITE.md` — historique des choix de sécurité et leur justification.
- `docs/SECURITE_ELECTIONS.md` — le modèle d'anonymat du vote en détail.
- `docs/BRANCH_PROTECTION.md` — règles de protection des branches Git.
- `Projet_Docs_Ref/CAHIER_DES_CHARGES.md` — la spécification fonctionnelle complète.

## Phases

Le développement a suivi six phases (voir Jira) : Foundation → Community → Content →
Election → Polish → Network & Alumni. Chaque phase a été vérifiée en état de marche avant
de passer à la suivante — voir l'historique Git pour le détail ticket par ticket.
