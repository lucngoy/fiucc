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

**Programme d'un événement (§27 du cahier) : décision prise, pas un manque.** Pas de
table dédiée (type `event_program_items`, un horaire par ligne) pour le déroulé —
`events.description` en texte libre suffit, rendu avec les retours à la ligne respectés
(FIUCC-49). Le déroulé d'un événement communautaire change jusqu'au dernier moment ; une
table imposerait une saisie ligne par ligne au bureau pour un gain purement visuel (une
timeline plutôt qu'un paragraphe). À reconsidérer après quelques événements réels si le
besoin de structuration se confirme dans l'usage — pas avant, et pas par anticipation.

**Photos d'un événement (§26-28 du cahier) : un seul système, pas deux.** Le cahier ne
décrit pas une table de photos par événement (`event_images`) séparée d'une galerie par
albums : §26 liste `Gallery` comme un champ de l'événement, §27 affiche "Photos après
l'événement" sur sa page, et les exemples d'albums du §28 (`Congolese Day 2026`,
`Summer Escape 2026`...) sont des noms d'événements. Les photos d'un événement **sont**
son album, jamais autre chose — `events` n'a pas de table de photos propre. Concrètement :
- Un événement a au plus un album (`albums.event_id`, `UNIQUE`, migration 007). Le bureau
  crée/choisit cet album une seule fois ; les photos uploadées y apparaissent à la fois
  sur la page de l'événement (`Event::images()`) et dans la galerie générale (`/gallery`).
- Un album peut aussi exister sans événement (`event_id NULL`) — utile pour un souvenir
  sans event formel ("Football Match" improvisé, jamais créé comme `Event`).
- Supprimer un événement ne supprime jamais ses photos : `ON DELETE SET NULL` détache
  l'album (qui redevient autonome dans la galerie) au lieu de les emporter avec lui.
- La table `event_images` (001_initial_schema.sql) a été **supprimée** en migration 007 :
  elle n'a jamais eu de chemin d'écriture réel (aucun seeder, aucun CRUD admin construit
  avant cette décision), la retirer n'a perdu aucune photo réelle. **Ne pas la recréer** —
  toute nouvelle photo liée à un événement passe par un album (`album_photos`), point.

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
   **Taille du token de vote (FIUCC-64, décidé explicitement, pas un oubli) :**
   `random_bytes(16)` — 128 bits, pas 256 comme le CSRF token ou les noms de
   fichier upload les plus sensibles. Réduit volontairement par rapport à un
   premier jet à 256 bits, une fois le vrai modèle de menace posé : ce jeton
   est à usage unique, expire avec l'élection (`LEAST(now + vote_token_ttl_hours,
   election.end_at)`), plafonné à `security.vote_token_max_attempts` tentatives
   de saisie (au-delà, même un code correct est refusé), et inutilisable sans
   une session membre déjà authentifiée (`['auth']` sur les routes
   `/elections/{id}/authenticate`). Face à ces contraintes combinées, la borne
   n'a jamais été l'entropie du jeton — 5 tentatives rendraient même un espace
   bien plus petit impraticable à deviner en ligne, et hors ligne, 128 bits
   reste le plancher accepté d'un secret "cryptographiquement sûr" (force
   d'AES-128). Le gain réel de 256 → 128 bits n'est pas la sécurité, c'est la
   longueur affichée au membre : encodé en Base32 de Crockford (alphabet
   `0123456789ABCDEFGHJKMNPQRSTVWXYZ` — chiffres conservés, lettres I/L/O/U
   retirées, aucune ambiguïté visuelle une fois ces lettres absentes) et
   groupé par blocs de 4, un jeton 256 bits donne 13 blocs (64 caractères,
   pire qu'en hexadécimal) contre 7 blocs (26 caractères) à 128 bits — la
   différence entre un code qu'on recopie sur un scrutin et un code devant
   lequel on abandonne. `token_hash` reste du SHA-256 sur les octets bruts,
   inchangé : seul ce qui est montré au membre a changé, jamais ce qui est
   haché ni comparé. Si un jour ce calcul doit être refait (menace différente,
   contrainte de saisie levée), documenter le nouveau raisonnement ici plutôt
   que de remonter silencieusement la taille — le but de ce paragraphe est
   justement qu'un futur changement ne puisse pas être pris pour un
   affaiblissement accidentel.
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
    `/alumni` (FIUCC-86, Page Alumni Network) expose des profils individuels
    (parcours, promotion, ville actuelle, domaine professionnel — section
    "Espace Alumni" du cahier) : elle passe donc sous `['validated']`, comme
    les autres routes de cette liste. Tranché en préparant FIUCC-86
    (2026-09-12) — ne redevient pas un point ouvert sauf si le contenu de la
    page change de nature (ex. devient une vitrine du programme sans profil
    individuel).
    **`members.show_email`/`show_phone` : deux contextes différents, pas une
    contradiction.** Le profil public d'un membre (FIUCC-84, section 15 du
    cahier — "PROFIL MEMBRE") dit explicitement "ne jamais afficher
    publiquement" l'email et le téléphone, sans exception d'opt-in — et
    `DirectoryController`/`directory.show` n'affichent ni l'un ni l'autre,
    conformément. Ça ne rend pas `show_email`/`show_phone` (opt-in,
    `/profile/privacy`, FIUCC-83) inutiles pour autant : le cahier décrit
    l'annuaire ouvert à toute la communauté validée, pas le mentorat
    (FIUCC-87/88), où un étudiant doit pouvoir joindre un alumni qui a
    explicitement accepté d'être contacté — un contexte bien plus restreint
    (une mise en relation acceptée, pas une fiche consultable par n'importe
    quel membre) que justifie un canal de contact que l'annuaire général
    n'a pas à exposer. `show_email`/`show_phone` sont donc à lire dans
    FIUCC-87/88 quand la mise en relation mentor-étudiant est acceptée,
    jamais ailleurs.
13. **Aucune vue, aucun message, aucun email ne peut dépendre du contenu d'un
    bulletin.** La seule chose que le système peut dire à un membre à propos
    de son propre vote, c'est le fait d'avoir voté ou non (déductible de
    `voting_tokens.used_at`) — jamais pour qui, jamais pour quoi. Ça inclut
    la confirmation affichée juste après le vote, y compris dans la même
    requête que le POST du bulletin (le serveur connaît le choix à cet
    instant précis, mais ne doit pas le renvoyer à l'écran) : le message
    reste générique (« votre vote a été enregistré »), jamais un rappel des
    sélections faites. Ça inclut aussi tout scénario futur du type
    « le candidat que vous avez choisi s'est retiré » — un tel message est de
    toute façon irréalisable sans joindre `voting_tokens` à `votes`, ce que
    la règle 1 interdit déjà, mais l'interdiction porte ici sur l'intention
    autant que sur le mécanisme : n'essaie jamais de contourner cette
    impossibilité par une colonne ajoutée, un journal parallèle, ou tout
    autre lien indirect entre un membre et son bulletin, même motivé par une
    bonne raison UX. Vérifié pour FIUCC-61 (retrait de candidat) :
    `Candidate::voteCount()` n'a que deux points d'appel, tous deux dans
    `Admin\CandidateController`, tous deux derrière `AdminMiddleware` —
    aucun contrôleur public ou membre n'y touche. À revérifier explicitement
    à chaque ticket de FIUCC-62 à FIUCC-73 qui affiche quoi que ce soit à un
    électeur (authentification au vote, bulletin, confirmation, résultats).

## Manques connus (tickets à créer)

- **Tableau de bord admin.** `routes/admin.php` déclare `GET /admin` vers
  `Admin\DashboardController@index`, mais ce contrôleur n'existe pas —
  découvert en testant FIUCC-61 (2026-09-08). La route 404 silencieusement ;
  le reste du back office n'est pas affecté, chaque section a son propre
  contrôleur. Pas de ticket Jira existant pour cet écran : à créer côté
  utilisateur, phase 2 (FIUCC-8). Retirer cette entrée une fois le ticket
  créé et l'écran construit.

- **Emails et logs admin.** `routes/admin.php` déclare aussi `GET
  /admin/emails` vers `Admin\EmailController@index` et `GET /admin/logs`
  vers `Admin\LogController@index` — ni l'un ni l'autre n'existe,
  découvert en revue FIUCC-77 (2026-09-12). Même situation que le tableau
  de bord ci-dessus : route 404 silencieusement, aucun lien dans la
  sidebar (`layouts/admin.php` ne référence que les sections réellement
  construites), rien d'autre affecté. Pas de ticket Jira existant pour ces
  deux écrans : à créer côté utilisateur, phase 2. Retirer cette entrée
  une fois les tickets créés et les écrans construits.

- **Image de partage (og:image) générique.** FIUCC-75 (SEO) pose le bloc
  Open Graph/Twitter Card dans `layouts/app.php`, avec un repli sur
  `/assets/images/fiucc-seal.png` (le sceau, 200×200) quand une page ne
  fournit pas `$ogImage` — ce qui est le cas de toutes les pages
  aujourd'hui, aucun contrôleur ne le fait encore. C'est une tâche de
  contenu, pas de code : une vraie image de partage 1200×630, aux couleurs
  de la communauté (charte ci-dessous), remplacerait avantageusement le
  sceau étiré/pixelisé que voient aujourd'hui les étudiants quand un lien
  circule sur WhatsApp — l'aperçu de lien est souvent ce qui décide de
  l'ouverture ou non, donc un vrai visuel pèse directement sur les
  inscriptions. Pas de ticket Jira existant : à créer, phase 2. Retirer
  cette entrée une fois l'image fournie et `seo.default_*`/le repli mis à
  jour en conséquence.

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
- Écrire du texte d'interface en dur dans une vue ou un contrôleur, dans une langue ou
  l'autre : **toute** chaîne visible par l'utilisateur passe par `t()`
  (`resources/lang/fr.php` / `en.php`), y compris le contenu des emails
  (`App\Services\Mail`/`Otp`/`MemberNotifier`) et les libellés lus par du JS via des
  attributs `data-*`. Le français est la langue par défaut de l'interface (cahier des
  charges §38 : « Version initiale : Français »), l'anglais une traduction — dans ce sens,
  pas l'inverse. Le code et les commentaires restent en français, eux, dans tous les cas.
- Commiter `.env`, `vendor/`, ou un fichier `.DS_Store`.

## Environnement local

- macOS, XAMPP. Racine : `/Applications/XAMPP/xampfiles/htdocs/web/fiucc-portal`
- PHP 8.2.4, Composer 2.7.7
- Le DocumentRoot Apache doit pointer sur `public/`, jamais sur la racine du projet.
- Base de données : `congolese_community`
