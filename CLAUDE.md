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
app/Core/          Router, Controller, Model, View, Database, Config, Env, Logger
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
5. **Toutes** les sorties sont échappées avec le helper `e()` (`app/Helpers/helpers.php`,
   `htmlspecialchars` avec `ENT_QUOTES` et `UTF-8`) — jamais `htmlspecialchars()` en direct,
   jamais une valeur imprimée sans passer par `e()`, y compris une valeur qu'on croit
   interne/sûre (booléen, énumération) : la règle ne souffre pas d'exception au cas par
   cas, elle se vérifie par grep. Deux exceptions **documentées**, seules tolérées :
   - `$content` dans `resources/views/layouts/*.php` : HTML déjà rendu par la vue
     enfant (voir `App\Core\View::render`), pas une donnée à échapper une seconde fois.
   - le paramètre `content` de `resources/views/partials/card.php` : même principe,
     un fragment HTML déjà composé par l'appelant.
   Un fragment HTML **statique**, sans aucune variable interpolée (ex. un `<span>`
   codé en dur dans un ternaire), n'a rien à échapper — mais dès qu'une variable
   entre en jeu, elle passe par `e()`, sans exception.
6. **Tous** les POST portent un token CSRF vérifié côté serveur.
7. Uploads : type MIME réel vérifié avec `finfo`, jamais `$_FILES['type']` ni l'extension.
   Nom de fichier aléatoire. Aucune exécution PHP dans `public/uploads/`.
8. Le droit de vote se déduit de `members.status = 'validated'` **et**
   `members.status_type = 'student'`. Un alumni reste membre sans être électeur.
9. Aucune credential en dur. Tout passe par `.env`, qui n'est jamais commité.
10. En production, aucune trace d'erreur ne fuit vers le navigateur.
11. **Jamais de verrouillage de compte par identifiant seul pour un membre** — un
    électeur reconnaissable (email `@final.edu.tr` au format prévisible) peut sinon
    être privé de vote par n'importe qui, en saisissant volontairement 5 mauvais
    mots de passe le jour du scrutin. Les membres subissent un ralentissement
    progressif par `(compte, IP)` (`App\Models\LoginThrottle`), plafonné à **30
    secondes** (`security.member_login_throttle_max_seconds`) : assez long pour
    rendre un brute-force en ligne impraticable, assez court pour ne jamais
    empêcher réellement quelqu'un de se connecter — le bon mot de passe fonctionne
    toujours, à n'importe quel moment. Le délai est appliqué **avant** de vérifier
    le mot de passe (pas après avoir constaté l'échec), pour qu'un essai correct
    ne soit jamais plus rapide qu'un essai raté au même niveau de pénalité — sinon
    le temps de réponse à lui seul révélerait si le mot de passe était bon.
    Les comptes **admin** gardent le verrouillage dur (`users.failed_attempts`/
    `locked_until`) : peu de comptes, cible à forte valeur, aucun risque de priver
    quelqu'un de son droit de vote. Chaque échec est journalisé dans
    `activity_logs` avec l'IP (`App\Models\ActivityLog`), admin et membre :
    supprimer le verrouillage membre ne doit pas faire perdre la capacité de
    constater un harcèlement après coup.
    Compromis assumé : le délai est un `sleep()` côté serveur, qui bloque le
    worker Apache/PHP pendant sa durée. De nombreuses requêtes lentes en
    parallèle peuvent donc dégrader la disponibilité du serveur — un vecteur de
    déni de service différent de celui qu'on corrige, limité mais pas éliminé par
    le plafond de 30s. Un déblocage par email (lien à usage unique envoyé à
    l'adresse universitaire, pour que le titulaire réel reprenne la main
    immédiatement même sur un compte harcelé) est prévu en ticket séparé, phase 2,
    avec le reste de l'infrastructure email.
12. **« Connecté » n'est pas « membre validé »** — une adresse universitaire au
    format prévisible suffit à créer un compte et à vérifier son email (OTP,
    FIUCC-33/34) sans qu'aucun admin n'ait validé l'appartenance à la
    communauté (`members.status = 'pending'`). `App\Middleware\AuthMiddleware`
    (alias route `['auth']`) ne vérifie que la session ; il autorise donc un
    membre `pending` à accéder à sa propre page profil, mais ne doit **jamais**
    protéger une route qui expose d'autres membres. Ces routes utilisent
    `App\Middleware\ValidatedMiddleware` (alias `['validated']`, `routes/web.php`
    — annuaire, mentorat, et tout futur écran listant des profils d'autrui) :
    il exige en plus `members.status = 'validated'` et redirige sinon vers
    `/profile`. `['validated']` suffit seul, `['auth', 'validated']` n'est
    jamais nécessaire (il regroupe la vérification de session). Sans cette
    distinction, une simple adresse `@final.edu.tr` — jamais vérifiée par un
    humain — donnerait accès à la liste complète de la communauté.
    **Point ouvert (FIUCC-86, Page Alumni Network) :** `/alumni` n'a
    aujourd'hui aucun middleware (page publique, `AlumniController` n'existe
    pas encore). À trancher au moment de construire cette page : si son
    contenu expose des profils d'alumni (comme l'annuaire), elle doit passer
    sous `['validated']` comme les autres routes de cette liste ; si c'est une
    page vitrine du programme sans profils individuels, rester publique est
    correct. Ne pas deviner avant que la page existe réellement.

## Charte graphique

| Rôle | Hex | Usage |
|---|---|---|
| Bleu principal | `#0063C6` | Navigation, boutons primaires, titres |
| Bleu clair | `#7BBCFF` | Fonds légers, hover, cartes |
| Or | `#ECC117` | Badges, accents, CTA secondaires |
| Rouge | `#C00F1F` | Alertes, actions destructives |
| Vert succès | `#15803D` | **Extension FIUCC-21.** Confirmation/succès uniquement (ex. « votre recensement est validé »). Jamais pour une information générale (bleu) ni comme accent décoratif (or). Contraste ≈ 5,1:1 sur blanc — conforme AA texte normal, dans les deux sens (texte vert sur fond clair, ou texte blanc sur fond vert plein). |
| Fond | `#F8FAFC` | |
| Texte | `#111827` | |
| Texte secondaire | `#64748B` | |
| Bordures | `#E5E7EB` | |

Le bleu domine. L'or et le rouge sont des accents, jamais des couleurs de fond.
Pas de texte or sur blanc. Le rouge n'est jamais un bouton ordinaire. Le vert
succès ne remplace ni le bleu (information) ni l'or (mise en avant) : un succès
et une information ne doivent pas se ressembler.

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
