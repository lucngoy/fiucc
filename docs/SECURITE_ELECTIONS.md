# Sécurité et intégrité du module électoral — revue de fin de phase 4

Ce document couvre l'anonymat et l'intégrité du vote tel qu'implémenté dans ce
projet (FIUCC-59 à FIUCC-73). Il ne résume pas les tickets — il répond à trois
questions : qu'est-ce qui est garanti et par quoi, qu'est-ce qui ne l'est pas,
et sur quelles hypothèses hors du code tout cela repose.

Destiné au bureau électoral et à quiconque reprend ce projet. C'est aussi le
document à produire si un résultat est contesté : chaque affirmation ci-dessous
renvoie à un mécanisme vérifiable dans le code (fichier, méthode), pas à une
intention.

**Méthode** : chaque garantie de la section 1 est classée à l'un de ces trois
niveaux, du plus solide au plus fragile —

- **Structurel (schéma)** — impossible à contourner sans modifier la structure
  de la base elle-même (ajouter une colonne, une table). Un bug applicatif ne
  suffit pas à le rompre.
- **Garde SQL** — une condition portée par la requête d'écriture elle-même
  (`WHERE ... AND ...`), évaluée par MySQL au moment de l'écriture, pas
  seulement vérifiée par PHP avant. Résiste aux courses (deux requêtes
  concurrentes) et aux oublis d'un contrôle applicatif, mais reste du code :
  une régression peut la retirer.
- **Contrôle applicatif** — une vérification en PHP, avant ou après
  l'écriture. Utile pour le message d'erreur et l'expérience, mais rien
  n'empêche techniquement une future modification de l'oublier ou de la
  contourner.

Un an après ce ticket, si quelqu'un doit décider "peut-on faire confiance à
ça", c'est ce niveau qui répond — pas la présence ou l'absence du mécanisme.

---

## 1. Ce qui est garanti, et ce qui le garantit

### 1.1 Aucun lien, en base, entre un membre et son bulletin

**Niveau : structurel.** La table `votes` (migration `001_initial_schema.sql`)
n'a ni `member_id`, ni `user_id`, ni `token_id`, ni aucune colonne permettant
de remonter à un électeur — pas une valeur vide ou hachée : la colonne
n'existe tout simplement pas. Aucune requête ne peut lire ce qui n'est pas
stocké.

Cette absence est vérifiée automatiquement à chaque modification du schéma par
`.github/scripts/check_vote_anonymity.py`, qui fait échouer la CI si une
migration (`CREATE TABLE` ou `ALTER TABLE`) réintroduit
`member_id`/`user_id`/`token_id`/`voting_token_id` dans `votes`. Voir la
portée exacte de ce garde-fou en §2.7.

### 1.2 Écriture du bulletin et marquage "a voté" sont deux opérations distinctes

**Niveau : structurel** pour la donnée écrite (deux tables, aucune clé
commune) ; **contrôle applicatif + garde SQL** pour la manière dont c'est
exécuté. `Vote::castBallot()` (`app/Models/Vote.php`) écrit dans `votes` (sans
identité) puis, séparément, marque `voting_tokens.used_at` (avec
`member_id`) — deux tables, deux instructions, aucune valeur commune entre
elles dans ce qui est écrit. Voir §2.1 pour la nuance importante : les deux
opérations partagent la même transaction et la même connexion, ce qui n'affecte
jamais la donnée au repos mais compte pour qui observe la base en direct.

### 1.3 Le token de vote n'est jamais stocké en clair côté serveur

**Niveau : contrôle applicatif.** `voting_tokens.token_hash` est un
`CHAR(64)` — la colonne accepte n'importe quelle chaîne de 64 caractères, rien
au niveau schéma ne garantit qu'il s'agit bien d'un SHA-256. La garantie vient
de `VotingToken::issue()`/`regenerate()`, qui ne calculent et n'écrivent que
`hash('sha256', $raw)`, jamais `$raw` lui-même. Le secret en clair n'existe
que transitoirement en mémoire PHP le temps de composer l'email
(`App\Services\VoteTokenNotifier::send()`), jamais persisté.

### 1.4 Impossible de voter deux fois

**Niveau : structurel + garde SQL.** `UNIQUE(election_id, member_id)` sur
`voting_tokens` (un seul jeton possible par membre et par élection) est une
contrainte de schéma. `Vote::consumeToken()` marque le jeton utilisé via
`UPDATE ... WHERE used_at IS NULL` — la condition est évaluée par MySQL au
moment même de l'écriture, dans la même transaction que l'insertion du
bulletin ; deux requêtes concurrentes pour le même membre ne peuvent jamais
toutes les deux réussir (la seconde `UPDATE` affecte zéro ligne).

### 1.5 Un bulletin déposé après la fermeture du scrutin n'est jamais compté

**Niveau : garde SQL, répétée à quatre endroits — pas une contrainte de
schéma.** Aucun trigger ni contrainte `CHECK` MySQL n'empêche techniquement une
écriture hors fenêtre : la garantie tient entièrement au fait que
**chacune** des quatre écritures de `Vote::castBallot()` porte sa propre
condition `AND e.status = 'open' AND e.end_at > NOW()` dans son propre
`WHERE` — le contrôle en tête de `castBallot()`, `insertBlank()`,
`insertForCandidate()`, et `consumeToken()`. C'est délibérément redondant
(défense en profondeur), mais c'est du code répété quatre fois, pas une
propriété intouchable de la base. Une régression qui retirerait cette clause
d'une seule des quatre écritures rouvrirait une brèche sans qu'aucune alerte
ne se déclenche automatiquement — voir §2.8.

En complément, `VoteController::eligibleContext()` empêche même
l'**affichage** du bulletin ou du récapitulatif une fois `end_at` dépassé,
même si le bureau n'a pas encore cliqué "Fermer le vote" — un contrôle
applicatif, pas une garde SQL, mais qui évite qu'un membre arrive au bord de
la fermeture.

### 1.6 Le vote blanc est distinct d'un poste non rempli

**Niveau : contrôle applicatif.** `VoteController::collectSelections()` exige
une valeur explicite pour chaque poste dans `$_POST` — soit un identifiant de
candidat valide, soit la chaîne littérale `"blank"`. Un champ réellement vide
ou absent produit une erreur de formulaire et bloque la soumission ; il
n'atteint jamais `castBallot()`. En base, `votes.candidate_id = NULL` signifie
donc toujours "blanc explicitement choisi", jamais "rien reçu" — mais rien au
niveau schéma ne distingue ces deux intentions si un bug applicatif futur les
confondait.

### 1.7 Un candidat retiré après coup garde ses voix déjà exprimées

**Niveau : contrôle applicatif (aucun chemin de suppression n'existe) +
filet structurel.** Le retrait d'un candidat (FIUCC-61) ne fait jamais de
`DELETE` — seulement `status = 'withdrawn'`. `Vote::resultsForElection()` ne
filtre jamais sur `candidates.status` : un candidat retiré continue
d'apparaître dans le dépouillement avec son total réel. En filet de sécurité,
`votes.candidate_id` porte une contrainte `FOREIGN KEY ... ON DELETE SET
NULL` vers `candidates` — si un jour une suppression physique était ajoutée
malgré tout, les votes déjà exprimés basculeraient en blanc plutôt que de
disparaître ou de pointer vers une ligne fantôme.

### 1.8 Une soumission invalidée entre le récapitulatif et l'envoi est rejetée en entier

**Niveau : structurel (atomicité de transaction InnoDB) + garde SQL.** Les
choix voyagent en champs cachés entre l'écran de récapitulatif et la
soumission finale (jamais en session). `castBallot()` exécute toutes les
écritures d'un même bulletin dans **une seule transaction** ; si un seul
`INSERT` échoue (candidat retiré, poste modifié entre-temps), la transaction
entière est annulée (`ROLLBACK`). Aucune substitution, aucun repli silencieux
sur un vote blanc pour les postes qui auraient réussi — soit tout est
enregistré, soit rien ne l'est.

### 1.9 CSRF sur toute requête POST du module électoral

**Niveau : structurel au niveau du routeur.** `App\Middleware\CsrfMiddleware`
est appliqué par `App\Core\Router` à **tout** POST, sans opt-in par route —
ce n'est pas une décision prise route par route qu'on pourrait oublier pour
une nouvelle action. Le token est vérifié par comparaison en temps constant
(`hash_equals`), lié à la session serveur.

### 1.10 Droit de vote = `members.status = 'validated'` ET `status_type = 'student'`

**Niveau : garde SQL + contrôle applicatif, redondants.**
`VotingToken::issue()`/`regenerate()` n'émettent un jeton que si cette
condition est vraie **au moment de l'écriture** (jointure dans la requête,
pas une lecture préalable). `Member::isVoterEligible()` revérifie la même
règle côté affichage (`VoteController::eligibleContext()`) avant de montrer
authentification, bulletin ou récapitulatif. Un alumni reste membre sans
jamais pouvoir recevoir de jeton.

### 1.11 Un membre n'apprend que le fait d'avoir voté, jamais pour qui

**Niveau : contrôle applicatif — convention de code, pas une impossibilité
technique**, sauf pour la part que garantit déjà §1.1. Vérifié explicitement
sur chaque écran touchant un électeur : la confirmation post-vote
(`ballot_confirmation.php`) n'affiche qu'un message générique et le reçu,
jamais les sélections — y compris dans la **même requête HTTP** que la
soumission, alors que le serveur connaît le choix à cet instant précis.
`ActivityLog::record(..., 'vote.cast', 'election', $electionId)` n'enregistre
ni détail ni sélection. Rien ne transite par `$_SESSION`. Voir §2.9 : ceci
reste une discipline de revue de code à maintenir, pas une propriété qui se
vérifie une fois pour toutes.

### 1.12 Le reçu de vote prouve la participation sans exposer le contenu

**Niveau : structurel pour l'absence de fuite (le reçu vit dans
`voting_tokens`, jamais dans `votes`) ; voir §2.6 pour ce que ce reçu ne
prouve PAS.** `receipt_code` (48 bits, `UNIQUE`) est généré au moment de
`consumeToken()` et stocké à côté de `used_at` — jamais dans `votes`, jamais
dérivé du contenu du bulletin. Il permet à un membre de prouver après coup
que son vote a été enregistré (panneau "vous avez voté" + email de
confirmation), sans qu'aucune requête ne puisse remonter de ce code vers un
choix.

---

## 2. Ce qui n'est PAS garanti

Section directe, à ne pas édulcorer : voici ce que quelqu'un pourrait croire
acquis à tort.

### 2.1 Un accès direct à MySQL, **pendant** un vote, peut potentiellement corréler qui a voté quoi

`castBallot()` exécute l'insertion du bulletin et la mise à jour de
`voting_tokens.used_at` (qui porte `member_id`) dans **la même transaction,
sur la même connexion**, à quelques millisecondes d'écart. Ce n'est pas un
défaut de conception — les deux doivent réussir ou échouer ensemble (§1.8) —
mais ça a une conséquence directe : quiconque a une **visibilité en temps
réel** sur le moteur MySQL pendant que la transaction s'exécute (le journal
général `general_log` s'il est activé, un `binlog` en format `STATEMENT`
qui capture le texte SQL littéral, `SHOW PROCESSLIST`/`performance_schema`,
une capture réseau sur la connexion à la base) verrait les deux instructions
côte à côte, sur le même identifiant de connexion, sans avoir besoin d'aucune
jointure.

C'est fondamentalement différent d'interroger les tables après coup, ce qui
ne révèle structurellement rien (§1.1) — c'est un risque d'observation en
direct, pas un risque sur la donnée au repos.

**Recommandation opérationnelle** (hors du code, à vérifier côté hébergement) :
`general_log` désactivé en production, `binlog_format = ROW` si la
réplication est utilisée (pas `STATEMENT`), et accès au `performance_schema`
restreint aux mêmes personnes déjà en droit d'accéder directement aux tables.

**Le couplage a été examiné pour être réduit, pas seulement constaté.** Avant
d'écrire la recommandation ci-dessus, la question a été posée directement :
peut-on écrire le bulletin et consommer le jeton sur deux connexions
distinctes, ou espacer les deux opérations dans le temps, sans perdre ce que
l'atomicité actuelle garantit (§1.4, §1.8) ? Quatre pistes ont été examinées
et rejetées :

- **Deux transactions séparées, vote d'abord.** Si le vote est validé
  (COMMIT) et que la consommation du jeton échoue ou que le serveur tombe
  entre les deux, le jeton reste `used_at IS NULL` — le membre peut soumettre
  un second bulletin, qui sera compté. Recrée exactement le double vote que
  §1.4 empêche aujourd'hui.
- **Deux transactions séparées, jeton d'abord.** Défaut inversé : le jeton
  est marqué consommé, puis l'insertion du bulletin échoue (candidat retiré
  entre-temps, par exemple) — le membre a "voté" selon le système sans
  qu'aucun bulletin n'existe. Une privation de vote silencieuse, pire qu'un
  double vote pour l'intégrité du scrutin, et qui romprait aussi §1.8 (rejet
  intégral en cas d'échec partiel).
- **Transaction distribuée (XA) entre deux connexions.** Techniquement
  possible avec MySQL (`XA START`/`PREPARE`/`COMMIT`), mais n'apporte rien
  contre le risque décrit : les deux branches d'une transaction XA partagent
  un identifiant de transaction global, visible dans les mêmes journaux
  (`general_log`, `binlog`) — un signal de corrélation *plus* explicite
  qu'une simple proximité temporelle sur une connexion, pas moins. Ajoute en
  plus la complexité d'un coordinateur de transactions et ses propres modes
  d'échec (transaction "in-doubt") pour un résultat pire.
- **Espacer les deux écritures dans le temps, au sein d'une même
  transaction.** Sans effet sur `general_log`, qui journalise chaque
  instruction au moment de son exécution, pas au `COMMIT` — l'écart serait
  visible dans les mêmes journaux qu'aujourd'hui, avec en plus un verrou
  InnoDB tenu plus longtemps sur la ligne `voting_tokens` concernée : une
  dégradation de la concurrence pour un gain nul.

**Conclusion retenue : le couplage reste tel quel.** Le risque de cette
section n'est pas un défaut de conception à corriger dans le code — c'est le
prix d'une garantie plus fondamentale, l'atomicité qui rend le double vote
(§1.4) et la privation de vote silencieuse (§1.8) tous deux impossibles.
Toute séparation examinée échange ce risque, réel mais conditionné à un
accès et une configuration MySQL précis, contre un risque strictement pire
et inconditionnel. Il se traite au niveau de la configuration et de l'accès
au serveur (recommandation ci-dessus), pas en réduisant ce que couvre la
transaction.

### 2.2 La granularité de `votes.created_at` (DATE, pas DATETIME) limite mais n'élimine pas la corrélation temporelle

La colonne est délibérément une `DATE` et non un `DATETIME` (voir le
commentaire de la migration 001) — précisément pour empêcher qu'un
horodatage précis serve à recouper un bulletin avec un instant de
soumission connu par ailleurs. Ça ferme le vecteur le plus direct, mais pas
tous : `voting_tokens.used_at` reste un `DATETIME` précis (c'est voulu, c'est
ce qui permet de dire "a voté" ou non), et les logs Apache portent un
horodatage précis par requête. Sur une élection à très faible participation
simultanée, quelqu'un croisant ces deux sources pourrait, par élimination,
réduire les candidats possibles pour un bulletin donné. Risque statistique
inhérent à tout vote électronique non anonymisé par un protocole
cryptographique dédié (mixnet, etc.) — ce projet n'en implémente pas, et ce
n'était pas l'exigence du cahier des charges.

### 2.3 Un administrateur du back-office voit QUI a voté, jamais CE QU'il a voté

Ce n'est pas un manque — c'est précisément le comportement voulu par la
règle 13 — mais à ne jamais présenter comme "l'admin ne sait rien sur les
votants". Un admin voit, légitimement, via l'interface : `used_at` par
membre (qui a voté, quand), les demandes de régénération de jeton et leur
motif (`activity_logs`), et après publication, le décompte agrégé par
candidat. Il ne voit à aucun moment, via l'interface, le lien entre un membre
précis et son choix — cette information n'existe nulle part pour lui.

### 2.4 Les logs Apache ne contiennent jamais le contenu du bulletin — mais contiennent qui a visité quoi, et quand

Le format de journalisation utilisé (`common`/`combined`) enregistre la
première ligne de la requête (méthode + URL + version HTTP), jamais le corps
d'une requête POST. Les sélections du bulletin (`position_12=45&confirm=1`)
voyagent en corps de requête — elles ne sont donc **jamais** dans un log
Apache. Ces logs contiennent en revanche l'IP, l'horodatage précis et l'URL
de chaque requête, y compris `/elections/{id}/vote` — quelqu'un avec ces
logs et un moyen indépendant de lier IP↔identité (le réseau de l'université,
par exemple) apprend QUAND un membre a interagi avec le module de vote, ce
qui alimente le risque de corrélation décrit en §2.2 — jamais le contenu.

### 2.5 Une sauvegarde standard préserve la garantie structurelle — une sauvegarde qui inclut le journal des requêtes ne la préserve pas mieux que §2.1

Un `mysqldump` classique capture l'état final des tables : `votes` et
`voting_tokens` y restent aussi séparées et sans lien qu'en production
(§1.1 s'applique intégralement à un dump). En revanche, si l'outil de
sauvegarde capture aussi le binlog ou un historique de requêtes plutôt qu'un
simple état final, le même risque qu'en §2.1 s'applique au fichier de
sauvegarde — le stocker avec moins de rigueur qu'un accès direct à la
production reviendrait à recréer ce risque ailleurs.

### 2.6 Aucune vérifiabilité de bout en bout — le reçu ne prouve pas QUOI a été enregistré

Le `receipt_code` prouve qu'un vote a été pris en compte, jamais lequel. Un
membre ne peut pas, après coup, vérifier cryptographiquement que son choix
précis a été correctement compté — ce n'est pas un oubli, c'est la
conséquence directe et voulue de la règle 13 (le permettre supposerait de
pouvoir répondre "pour qui avez-vous voté", ce que le système ne doit
justement jamais pouvoir faire). À ne pas présenter au bureau comme
équivalent à un système à vérifiabilité cryptographique (type Helios) —
ce n'en est pas un, et ce n'était pas l'exigence du cahier des charges.

### 2.7 Ce que le garde-fou CI couvre, et ce qu'il ne peut pas couvrir

**Corrigé pendant cette revue.** La première version de
`check_vote_anonymity.py` ne scannait que la définition `CREATE TABLE votes
(...)` des migrations — une future migration qui aurait ajouté
`member_id`/`user_id`/`token_id`/`voting_token_id` via `ALTER TABLE votes
ADD COLUMN ...` plutôt qu'en modifiant la définition historique serait
passée inaperçue. C'était une lacune plus dangereuse que l'absence de
garde-fou : elle aurait donné une fausse assurance. Le script scanne
maintenant aussi bien `CREATE TABLE` que `ALTER TABLE` sur `votes`, sur
l'ensemble des migrations — vérifié par un test de régression (ajout
synthétique d'un `ALTER TABLE votes ADD COLUMN member_id ...` hors dépôt,
confirmé détecté et bloquant).

Ce que ce garde-fou ne couvre toujours pas, par construction : toute
modification de schéma qui ne passe pas par un fichier
`database/migrations/*.sql` de ce dépôt — une instruction DDL exécutée
directement contre la base de production par quelqu'un disposant d'un accès
administrateur à MySQL. Cette limite rejoint l'hypothèse de la section 3
(accès admin à la base de confiance, §3 point 1) : le garde-fou protège
contre une erreur dans le processus de développement normal de ce projet,
pas contre un accès direct hostile ou compromis à la base de production.

### 2.8 La garde de fermeture (§1.5) est une discipline de code répétée, pas une contrainte de base

Rien n'empêche techniquement une future voie d'écriture (nouvel endpoint,
commande d'import, correction manuelle en base) d'oublier la clause
`AND e.end_at > NOW()`. Elle est appliquée avec constance aujourd'hui à
quatre endroits distincts, mais c'est une propriété du code actuel, pas une
impossibilité — à revérifier explicitement à chaque nouvelle voie
d'écriture touchant `votes` ou `voting_tokens`.

### 2.9 La règle 13 (rien ne sort du contenu d'un bulletin) est une discipline de revue permanente

Rien au niveau technique n'empêche un futur développeur d'ajouter, par
exemple, un journal de débogage qui capture `$_POST` en clair, ou d'afficher
les sélections sur l'écran de confirmation "pour rassurer l'électeur" — une
telle modification romprait la garantie sans toucher au schéma ni déclencher
aucune alerte automatique. C'est un point de vigilance à revérifier à chaque
ticket touchant un écran, un email ou un log visible par un électeur ou un
admin — pas une propriété qui se vérifie une fois pour toutes.

### 2.10 Un accès superutilisateur au serveur rend cette section sans objet

Quelqu'un avec un accès shell/SSH/FTP root au serveur de production peut lire
`storage/logs/*.log` directement, lire (ou modifier) le code source en
place, ou intercepter toute donnée en mémoire au moment du traitement. Ce
niveau d'accès dépasse ce que n'importe quelle garantie logicielle peut
contrer — voir section 3.

---

## 3. Hypothèses hors du code

Tout ce qui précède repose sur les postulats suivants, qui ne sont vérifiés
par aucune ligne de ce projet :

1. **Accès physique et administratif au serveur de production restreint et
   de confiance.** Aucune garantie de la section 1 ne résiste à quelqu'un
   d'hostile disposant d'un accès shell/SSH ou d'un accès direct non
   supervisé à MySQL en production.

2. **Comptes admin du back-office non compromis.** Le verrouillage dur
   (CLAUDE.md règle 11) protège contre le brute-force, pas contre un mot de
   passe réutilisé et fuité ailleurs, un poste de travail admin compromis, ou
   un admin malveillant. Un compte admin légitime peut régénérer un jeton,
   voir qui a voté, publier des résultats — jamais voir un bulletin via
   l'interface (§2.3), mais un accès plus profond que l'interface change la
   donne (§2.1, §2.9, §2.10).

3. **L'hébergeur, et quiconque a accès à son infrastructure, est de
   confiance ou suffisamment isolé.** Un hébergeur hostile ou compromis a par
   construction accès au disque, à la mémoire, et potentiellement au trafic
   réseau interne — aucune garantie logicielle de ce projet n'y résiste.

4. **HTTPS est effectivement configuré et actif en production.** Le cookie de
   session ne porte l'attribut `secure` qu'hors environnement `local`
   (`public/index.php`) — ça demande au navigateur de ne jamais l'envoyer
   hors TLS, mais si le site tourne réellement sans TLS (configuration
   serveur, hors du code), cette protection ne s'active jamais et
   l'authentification transite en clair.

5. **L'horloge du serveur MySQL est correcte et non manipulable.** Toutes
   les gardes temporelles (`end_at`, `expires_at`, le délai de 12h entre deux
   rappels) reposent sur `NOW()` côté MySQL — une dérive d'horloge ou une
   manipulation (accès admin système) les fausserait sans qu'aucun code ne
   puisse le détecter.

6. **Le canal email (SMTP) n'est ni intercepté ni usurpé en transit.** Le
   jeton de vote et le lien d'authentification voyagent par email — leur
   confidentialité dépend entièrement du fournisseur SMTP et de la boîte de
   réception du membre, hors du contrôle de ce projet.

7. **`.env` de production est correctement configuré** (`APP_ENV`,
   `MAIL_REVEAL_SECRETS`). Le code refuse explicitement de révéler des
   secrets en dehors de l'environnement `local`
   (`App\Core\Logger::revealsSecrets()`) et n'affiche la trace complète d'une
   exception au navigateur qu'en local (`public/index.php`) — mais les deux
   dépendent d'une variable de configuration externe, pas d'une propriété
   intouchable du code. Une erreur de déploiement (`APP_ENV=local` en
   production) désactiverait silencieusement les deux protections à la fois.

8. **Toute sauvegarde ou export de la base est conservé avec la même
   rigueur d'accès que la base de production elle-même** (§2.5) — un dump
   oublié dans un espace partagé ou moins protégé annule cette réserve.

---

## 4. Avant et après chaque scrutin — ce que le bureau doit faire

Ce document ne sert pas qu'à comprendre le système une fois : voici la
check-list opérationnelle qui en découle, à suivre à chaque élection. Rien
ici n'est automatisé par l'application — c'est précisément pour ça que ça
doit être fait à la main, à chaque fois.

### Avant l'ouverture du vote

- [ ] **Sauvegarde complète de la base** (`mysqldump`) juste avant de cliquer
  "Ouvrir le vote". Capture l'état de référence — candidats approuvés,
  membres éligibles — avant toute écriture de bulletin, utile en cas de
  contestation portant sur la configuration elle-même, pas seulement sur le
  résultat. Un dump standard n'affaiblit jamais l'anonymat (§2.5) ; à
  conserver avec le même soin d'accès que la production elle-même.
- [ ] **Vérifier que `general_log` est désactivé** sur le serveur MySQL de
  production (`SHOW VARIABLES LIKE 'general_log';` → `OFF`), et que si la
  réplication utilise un binlog, il est en format `ROW`, pas `STATEMENT`
  (`SHOW VARIABLES LIKE 'binlog_format';`). C'est le seul levier qui réduit
  réellement le risque de corrélation en temps réel décrit en §2.1 — hors du
  code de ce projet, à vérifier avant chaque scrutin, pas une fois pour
  toutes.
- [ ] **Vérifier `APP_ENV=production`** dans le `.env` du serveur (jamais
  `local`) — sinon les protections de journalisation et d'affichage
  d'erreurs (§3, point 7) sont silencieusement désactivées.
- [ ] **Vérifier que le site sert en HTTPS** (cadenas navigateur, aucun
  avertissement de certificat) — condition du §3 point 4, sans laquelle le
  cookie de session n'est jamais réellement protégé.
- [ ] **Confirmer la liste des personnes disposant d'un accès admin** au
  back-office et à la base de données pour ce scrutin — un compte par
  personne, jamais partagé (un compte partagé rend `activity_logs`
  inutilisable pour attribuer une action à quelqu'un en particulier, §3
  point 2), et révoqué dès qu'une personne quitte le bureau.
- [ ] **Envoyer les jetons de vote** ("Envoyer les jetons", page Jetons de
  vote) une fois l'élection ouverte, pas avant.

### Après la clôture, avant la publication

- [ ] **Fermer le vote** ("Fermer le vote") — l'acceptation de nouveaux
  bulletins est déjà bloquée en continu dès `end_at` (§1.5), cette action
  fige surtout l'état affiché et débloque la suite.
- [ ] **Seconde sauvegarde complète**, juste après la clôture — capture
  l'état "voté, compté, pas encore publié". En cas de contestation sur le
  résultat lui-même, c'est cette sauvegarde-là qui fait foi de ce qui a été
  effectivement enregistré, avant toute action de publication.
- [ ] **Vérifier le dépouillement via l'aperçu admin**
  (page Résultats, accessible dès que le vote est fermé) avant de publier
  quoi que ce soit — c'est précisément la fenêtre prévue pour ça : fermer,
  vérifier, *puis* publier, jamais les deux d'un coup.
- [ ] **Publier les résultats** ("Publier les résultats") seulement une fois
  la vérification faite.
- [ ] **Exporter et archiver le rapport officiel** ("Rapport imprimable",
  impression ou export PDF) — l'application ne conserve aucune copie de ce
  PDF une fois généré, il est recalculé à la demande depuis `votes` à chaque
  ouverture de la page. C'est au bureau de le sauvegarder hors du serveur
  (archives du bureau électoral, hors de ce projet) dès qu'il est produit —
  en particulier juste après publication, pendant que le contexte est
  encore là pour l'accompagner d'une note si besoin.
- [ ] **Envoyer l'annonce de clôture** ("Envoyer l'annonce de clôture"), si
  le bureau le souhaite — rejouable sans risque de doublon, n'atteint jamais
  un membre déjà notifié ou ayant déjà voté.

---

*Document à mettre à jour à chaque ticket touchant le vote, la table `votes`,
`voting_tokens`, ou tout écran/email/log visible par un électeur ou un admin
— pas seulement relu une fois. Reflète l'état du code à la fin de la phase 4
(FIUCC-59 à FIUCC-73).*
