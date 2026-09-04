# Prompts Claude Code — FIUCC Portal

## Comment s'en servir

Place `CLAUDE.md` à la racine du projet : Claude Code le lit automatiquement au démarrage
de chaque session, tu n'as pas à le recoller.

Ensuite, **une session par bloc de tickets**, pas une session pour tout le projet. Entre
deux blocs, tu vérifies dans le navigateur et tu commites. Un contexte trop long dégrade
la qualité du code et fait perdre à Claude Code les contraintes du début.

Lance-le depuis la racine du projet :

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/web/fiucc-portal
claude
```

---

## Session 0 — Prise de connaissance (5 minutes, à faire une fois)

```
Lis CLAUDE.md, puis Projet_Docs_Ref/CAHIER_DES_CHARGES.md, puis
database/migrations/001_initial_schema.sql et routes/web.php.

Ne code rien pour l'instant. Fais-moi un résumé en 15 lignes maximum de :
- ce que fait le projet et pour qui
- les contraintes techniques fermes
- les règles de sécurité que tu dois respecter sur le vote
- ce qui est déjà en place dans le dépôt
- ce qui reste à faire en phase 1

Signale-moi toute contradiction ou zone d'ombre que tu as relevée.
```

Ce tour sert à vérifier qu'il a bien compris avant de lui laisser écrire quoi que ce soit.
S'il se trompe sur l'anonymat du vote ou sur l'absence de framework, corrige-le tout de suite.

---

## Session 1 — Noyau applicatif (FIUCC-14 à FIUCC-17)

```
Implémente les tickets FIUCC-14 à FIUCC-17, dans cet ordre, sur la branche
feature/FIUCC-14-config-env qui est déjà créée et active.

FIUCC-14 — Chargement de la configuration et du .env
- app/Core/Env.php : lecture du fichier .env, gestion des commentaires, des guillemets,
  conversion de "true"/"false"/"null". Une méthode require() qui lève une exception
  si une variable obligatoire manque.
- app/Core/Config.php : charge tous les fichiers de config/, accès en notation pointée
  (Config::get('database.host')).
- config/app.php, config/database.php, config/mail.php, config/security.php.
  Les valeurs viennent de Env, jamais en dur.
- public/index.php : bootstrap — env, config, timezone, gestion des erreurs
  (aucune trace affichée hors local), session sécurisée (httponly, samesite, secure
  hors local).

FIUCC-15 — Front controller et routeur
- app/Core/Router.php : dispatch depuis les tableaux de routes/web.php et routes/admin.php,
  support des paramètres dynamiques type /events/{slug}, méthode HTTP, 404 propre.

FIUCC-16 — Couche MVC légère
- app/Core/Controller.php, app/Core/Model.php, app/Core/View.php avec système de layouts.
- Un helper d'échappement utilisé par défaut dans les vues.

FIUCC-17 — Connexion PDO
- app/Core/Database.php : connexion unique, ERRMODE_EXCEPTION, EMULATE_PREPARES à false,
  FETCH_ASSOC. Message d'erreur clair si la base est injoignable, sans exposer
  les credentials.

Après chaque ticket, arrête-toi, dis-moi ce que tu as fait et comment le vérifier
dans le navigateur. Ne passe au suivant que quand je te le confirme.

Fais un commit par ticket, avec la clé Jira dans le message.
```

---

## Session 2 — Design system et layout (FIUCC-20 à FIUCC-22)

```
Implémente FIUCC-20, FIUCC-21 et FIUCC-22.

Avant de coder, lis
Projet_Docs_Ref/stitch_document_management_hub/fiucc_institutional_portal/DESIGN.md
et Projet_Docs_Ref/stitch_document_management_hub/fiucc_portal_home_page/code.html
pour reprendre la direction visuelle des maquettes.

FIUCC-20 — Design system Tailwind : configuration avec les tokens exacts de CLAUDE.md,
CSS compilé dans public/assets/css. Prévois que la production n'a pas Node.js :
soit un CSS compilé et commité, soit le CDN Tailwind en dernier recours — dis-moi
ce que tu choisis et pourquoi avant de le faire.

FIUCC-21 — Composants réutilisables en partials PHP : bouton (primaire, secondaire,
accent, destructif), carte, badge de statut, champ de formulaire avec état d'erreur,
alerte, pagination.

FIUCC-22 — Layout global : header sticky avec la navigation de la section 5 du cahier
des charges, menu hamburger sur mobile, switch de langue, footer à quatre colonnes.

Crée une branche par ticket depuis develop. Montre-moi le rendu à vérifier avant
de passer au suivant.
```

---

## Session 3 — Authentification et middlewares (FIUCC-23 à FIUCC-26)

```
Implémente FIUCC-23 à FIUCC-26 : authentification (login, logout, password_hash,
session régénérée à la connexion, verrouillage après 5 tentatives), middlewares auth
et admin, protection CSRF globale, échappement des sorties.

Le CSRF doit être vérifié pour toute méthode POST sans exception, y compris les
formulaires d'administration. Fournis un helper de génération de champ caché
utilisable dans toutes les vues.

Arrête-toi après chaque ticket. Rappelle-moi à la fin comment tester une tentative
de login échouée et un POST sans token CSRF.
```

---

## Session 4 — Fin de la phase 1 (FIUCC-19, FIUCC-27 à FIUCC-30)

```
Termine la phase Foundation : FIUCC-19 (migrations et seeders), FIUCC-27 (pages 404/500),
FIUCC-28 (logging), FIUCC-29 (service Mail avec PHPMailer), FIUCC-30 (i18n EN/FR).

Pour le service Mail : en environnement local, aucun email ne doit partir réellement.
Écris-les dans storage/logs/mail.log. Le comportement est piloté par config/mail.php.

Pour les seeders, appuie-toi sur la section 53 du cahier des charges.

Quand c'est terminé, fais le point sur la phase 1 : ce qui est fait, ce qui reste,
et ce qui doit être vérifié manuellement avant de passer à la phase 2.
```

---

## Sessions suivantes — modèle à réutiliser

Pour les phases 2 à 6, reprends cette trame en remplaçant les numéros et le contenu.
Les tickets sont listés dans `Projet_Docs_Ref/FIUCC_cles_et_branches.md`.

```
Implémente les tickets FIUCC-<x> à FIUCC-<y> (phase <n> — <nom>).

Avant de coder :
- relis les sections <numéros> du cahier des charges
- regarde les maquettes Stitch correspondantes dans Projet_Docs_Ref/

Contraintes rappelées : pas de framework, requêtes préparées, sorties échappées,
CSRF sur les POST, et pour tout ce qui touche au vote, les règles d'anonymat
de CLAUDE.md.

Un ticket à la fois. Arrêt et vérification entre chaque. Un commit par ticket
avec la clé Jira.
```

---

## Prompts utiles en cours de route

À garder sous la main :

```
Relis CLAUDE.md avant de continuer, tu viens de t'en écarter.
```

```
Tu es en train d'anticiper sur la phase suivante. Reste dans le périmètre du ticket.
```

```
Avant de coder cette fonctionnalité de vote, explique-moi comment tu garantis
qu'aucun lien entre l'électeur et son bulletin n'existe, ni en base, ni dans les logs.
Ne code qu'après ma validation.
```

```
Fais une revue de sécurité des fichiers que tu viens d'écrire : injection SQL, XSS,
CSRF, fuite d'information dans les messages d'erreur. Liste ce que tu trouves
avant de corriger.
```

```
Lance php -l sur tous les fichiers modifiés et corrige les erreurs avant de commiter.
```

---

## Deux garde-fous à ne pas relâcher

**Sur la phase 4 (élections), relis le code toi-même.** C'est la partie où une erreur
a des conséquences réelles pour la communauté : un vote qui fuite, un double vote
possible, un résultat contestable. Ne la valide pas sur la seule foi d'un « ça marche ».

**Vérifie les migrations avant de les exécuter.** Si Claude Code propose de modifier
`001_initial_schema.sql` plutôt que d'ajouter un fichier `002_*.sql`, refuse : ta base
locale contiendra déjà des données de test.
