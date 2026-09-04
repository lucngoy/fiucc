# **🇨🇩 CAHIER DES CHARGES — PORTAIL COMMUNAUTÉ CONGOLAISE DE FINAL**

## **1. Présentation du projet**

### **Nom du projet**

**Congolese Community of Final — Official Community Portal**

Nom court possible :

**FIUCC Portal**

Le site est le portail numérique officiel de la **Communauté Congolaise de Final International University**.

L'objectif est de centraliser les activités, communications, événements, recensement des étudiants, élections communautaires, archives et contenus multimédias dans une plateforme moderne.

Le site doit être conçu comme une **véritable plateforme communautaire**, et non comme un simple site vitrine.

## **1.1 Vision — un hub numérique permanent**

Le portail ne doit pas être pensé comme un outil ponctuel limité au « recensement + vote ». Le recensement et l'élection ne sont que deux moments d'un cycle beaucoup plus large.

**Objectif réel : faire du site l'infrastructure numérique permanente de la communauté**, vivante toute l'année, et non un outil réactivé uniquement pendant la période électorale.

### **Cycle de vie du membre**

**Un étudiant arrive à Final**

↓

**🇨🇩 Il découvre la communauté**

↓

**📝 Il se recense**

↓

**📅 Il découvre les événements**

↓

**📸 Il retrouve les souvenirs**

↓

**🤝 Il rencontre les autres membres**

↓

**🗳️ Il participe aux élections**

↓

**🎓 Il devient alumni**

↓

**👨‍💼 Il revient comme mentor / partenaire**

↺

et il accueille à son tour les nouveaux arrivants — le cycle recommence.

Chaque étape de ce cycle doit correspondre à une fonctionnalité réelle du site. Une étape sans page ni fonctionnalité associée est une rupture du parcours.

### **Ce que cela change concrètement**

- le site vit toute l'année, et pas seulement pendant les élections ;

- le recensement devient une adhésion à une communauté, et non une simple formalité pour voter ;

- les événements et les médias deviennent la mémoire vivante de la communauté ;

- les membres peuvent se retrouver et s'entraider (promotions, facultés, villes d'origine) ;

- les anciens ne disparaissent pas : ils deviennent alumni, mentors, partenaires ;

- la valeur du site augmente chaque année, au lieu de retomber après chaque élection.

## **1.2 Modules découlant de cette vision**

En plus des modules déjà décrits (recensement, gouvernement, élections, événements, médias, archives), le site doit prévoir les modules suivants :

### **🤝 Annuaire des membres (Members Directory)**

- liste des membres validés, avec recherche et filtres (promotion, faculté, année d'arrivée, ville d'origine) ;

- profil public simplifié : prénom, nom, faculté, année, photo, réseaux sociaux ;

- chaque membre contrôle ce qui est visible publiquement (opt-in, jamais par défaut) ;

- accessible uniquement aux membres connectés et validés.

### **🎓 Espace Alumni**

- statut du membre : student / alumni / honorary ;

- passage en « alumni » après la graduation (automatique via graduation_year ou manuel par l'admin) ;

- page Alumni : parcours, promotion, ville actuelle, domaine professionnel ;

- les alumni conservent leur compte, leur historique et l'accès à l'annuaire, mais perdent le droit de vote.

### **👨‍💼 Mentorat & partenariats**

- un alumni peut se déclarer mentor (domaine, disponibilité, mode de contact) ;

- un étudiant peut demander un conseil ou une mise en relation ;

- espace partenaires : anciens, entreprises, sponsors d'événements.

### **🧭 Onboarding du nouvel arrivant**

- page « Nouveau à Final ? » : premières démarches, contacts utiles, FAQ ;

- parcours guidé : découvrir → se recenser → premier événement ;

- email de bienvenue après validation du recensement.

## **1.3 Conséquence sur l'architecture**

**Ces modules ne sont pas obligatoires dans la première version**, mais l'architecture, la base de données et le routing doivent être conçus dès le départ pour les accueillir sans refonte.

Impact minimal à prévoir immédiatement dans la table members (voir section 33) :

- status : student / alumni / honorary ;

- graduation_year ;

- champs de visibilité : is_public, show_email, show_phone ;

- profession, city (facultatifs, utiles pour les alumni et le mentorat).

Règle de conception : **le droit de vote dépend du statut du membre, pas de son existence dans la base**. Un alumni reste membre de la communauté sans être électeur.

# **2. Objectifs**

Le site doit permettre de :

### **🎓 Vie communautaire**

- présenter la communauté ;

- présenter son organisation ;

- présenter les membres du gouvernement ;

- permettre aux étudiants de se recenser ;

- publier les annonces importantes.

### **🗳️ Élections**

- organiser les élections du gouvernement ;

- permettre aux étudiants recensés de voter ;

- empêcher les votes multiples ;

- garantir autant que possible la confidentialité du vote ;

- permettre aux administrateurs de gérer les candidats ;

- afficher les résultats une fois l'élection terminée.

### **📅 Événements**

- afficher les prochains événements ;

- présenter les événements passés ;

- publier les détails d'un événement ;

- permettre éventuellement l'inscription aux événements.

### **📸 Médias**

- galerie photo ;

- vidéos ;

- affiches ;

- archives des événements.

### **📚 Archives**

- anciens gouvernements ;

- anciens événements ;

- documents importants ;

- réalisations de la communauté.

### **🤝 Réseau & entraide**

- permettre aux membres de se retrouver via un annuaire interne ;

- faciliter les contacts entre promotions, facultés et villes d'origine ;

- accueillir et orienter les nouveaux arrivants.

### **🎓 Alumni & mentorat**

- conserver le lien avec les anciens étudiants ;

- valoriser leur parcours après Final ;

- permettre le mentorat des étudiants actuels ;

- ouvrir la voie aux partenariats et au soutien de la communauté.

# **3. Stack technique**

Utiliser une architecture simple, robuste et facilement déployable.

### **Frontend**

- HTML5

- Tailwind CSS

- JavaScript vanilla

- Alpine.js **si nécessaire**, mais éviter les frameworks lourds

### **Backend**

- PHP 8.2+

- Architecture MVC légère et propre

- PDO

- MySQL / MariaDB

### **Email**

**PHPMailer**

Utilisé notamment pour :

- vérification email ;

- codes OTP ;

- notifications ;

- confirmation d'inscription ;

- notifications administratives.

### **Serveur**

Compatible avec :

- Apache

- PHP

- MySQL

Le projet doit pouvoir fonctionner sur un hébergement classique sans Node.js obligatoire en production.

# **4. Identité visuelle**

Le logo de la communauté utilise les couleurs :

Blue \#0063C6

Light Blue \#7BB CFF

Gold \#ECC117

Red \#C00F1F

Correction à utiliser exactement :

\#0063C6

\#7BBCFF

\#ECC117

\#C00F1F

### **Utilisation recommandée**

**\#0063C6  
** → couleur principale  
→ navigation  
→ boutons principaux  
→ titres importants

**\#7BBCFF  
** → backgrounds légers  
→ hover  
→ cartes  
→ éléments secondaires

**\#ECC117  
** → accents  
→ highlights  
→ badges  
→ call-to-action secondaires

**\#C00F1F  
** → alertes  
→ éléments importants  
→ accents culturels  
→ actions destructives

### **Règle**

Ne pas utiliser les quatre couleurs avec la même intensité partout.

Le design doit être :

**moderne + institutionnel + africain subtil + universitaire**

Éviter le style :

> "site d'association des années 2010".

# **5. Direction UI**

Design responsive :

- Desktop

- Tablet

- Mobile

Priorité mobile.

### **Style**

- grandes images ;

- cartes avec border-radius modéré ;

- ombres très légères ;

- beaucoup d'espace blanc ;

- typographie moderne ;

- animations discrètes ;

- transitions fluides.

### **Navigation**

Desktop :

LOGO

Home

Community

Events

News

Gallery

Elections

About

\[Join Community\]

Mobile :

menu hamburger.

# **6. PAGE D'ACCUEIL**

## **Hero**

Grande section avec :

**COMMUNAUTÉ CONGOLAISE DE FINAL**

> Unis par nos racines, poussés par l'avenir.

CTA :

**Join the Community**

**Discover our Activities**

Possibilité d'utiliser une grande photo d'un événement communautaire.

## **Section : À propos**

Petit résumé :

> La Communauté Congolaise de Final rassemble les étudiants congolais de Final International University autour de la solidarité, de la culture, du leadership et du développement.

Bouton :

**Discover the Community**

# **7. Prochains événements**

Afficher 3 événements maximum.

Card :

IMAGE

EVENT TITLE

DATE

LOCATION

Short description

\[View Event\]

# **8. Actualités**

Section :

### **Latest Updates**

Cards :

- titre

- image

- date

- catégorie

- résumé

Exemples :

**Community News**

**Events**

**Announcements**

**Sports**

**Culture**

# **9. Galerie**

Section :

### **Memories**

Afficher quelques photos récentes.

CTA :

**View Gallery**

# **10. Statistiques**

Petite section dynamique :

XXX+

Registered Students

XX

Events

XX

Activities

XX

Years of Community

Les valeurs doivent venir de la base de données.

# **11. PAGE COMMUNITY**

URL :

/community

Contenu :

### **Notre mission**

### **Notre vision**

### **Nos valeurs**

Exemples :

- Unity

- Leadership

- Excellence

- Culture

- Community

- Service

# **12. GOVERNMENT**

URL :

/community/government

Présenter le gouvernement actuel.

Carte :

PHOTO

NAME

POSITION

Instagram

Exemple :

**President**

**General Secretary**

**Vice President**

**Treasurer**

**Communication & Media**

etc.

Les postes doivent être configurables depuis l'administration.

# **13. RECENSEMENT**

URL :

/register

Titre :

# **🇨🇩 Join the Community**

Description :

> Register as a member of the Congolese Community of Final International University.

### **Informations**

- Full Name

- Student ID

- University Email

- Faculty

- Department

- Year of Study

- Phone / WhatsApp

- Instagram username

- Profile photo — optionnelle

### **Email**

L'email doit obligatoirement être :

\*@final.edu.tr

Le backend doit vérifier le domaine.

# **14. VÉRIFICATION EMAIL**

Après inscription :

Enter verification code

PHPMailer envoie un OTP à l'adresse universitaire.

Exemple :

> Your verification code is: **483921**

Le code :

- expire après 10 minutes ;

- ne peut être utilisé qu'une fois ;

- nombre limité de tentatives.

Après validation :

Email Verified ✓

# **15. PROFIL MEMBRE**

Après recensement, chaque étudiant peut avoir un profil.

URL :

/members

Mais les données personnelles sensibles ne doivent **pas être publiquement accessibles par défaut**.

Profil public éventuel :

PHOTO

NAME

Faculty

Department

Year

Instagram

Ne jamais afficher publiquement :

- Student ID

- email

- téléphone

# **16. SYSTÈME D'ÉLECTION 🗳️**

C'est une partie essentielle.

## **Conditions**

Seuls les étudiants :

- recensés ;

- validés ;

- possédant une adresse @final.edu.tr

peuvent voter.

# **17. ÉTAPES DE L'ÉLECTION**

### **Phase 1**

Admin crée l'élection.

Exemple :

Congolese Community Government Election 2026

Dates :

Opening: 15 September 2026

Closing: 20 September 2026

### **Phase 2 — Candidatures**

Admin ajoute les candidats :

Candidate

Name

Photo

Faculty

Department

Year

Position

Manifesto

Biography

Instagram

# **18. PAGE DES CANDIDATS**

URL :

/elections/2026/candidates

Chaque candidat possède une fiche :

PHOTO

NAME

Running for:

PRESIDENT

Short biography

Manifesto

\[View Profile\]

# **19. AUTHENTIFICATION POUR VOTER**

Lorsqu'un étudiant veut voter :

University Email

Le système vérifie :

Does this email exist?

Puis :

Is this member validated?

Puis :

Has this member already voted?

# **20. SÉCURITÉ DU VOTE**

Il faut séparer :

### **Identity database**

members

de :

### **Election database**

votes

Ne jamais stocker directement :

email → candidate

dans la table des votes.

Créer plutôt un mécanisme de **vote token / election token**.

Exemple :

Member

↓

Authentication

↓

Eligibility verification

↓

One-time voting token

↓

Anonymous vote

Le système doit garantir :

**1 électeur = 1 vote**

tout en évitant que l'administrateur puisse simplement consulter :

> Jean a voté pour X.

# **21. BULLETIN**

Interface très simple.

### **Presidential Election**

○ Candidate A

○ Candidate B

○ Candidate C

Puis :

**Review my vote**

Page de confirmation :

> Please review your choices before submitting.

Bouton :

### **SUBMIT MY VOTE**

Confirmation :

> Your vote has been successfully recorded.

# **22. RÉSULTATS**

Pendant l'élection :

Results unavailable

Après fermeture :

Election Results

Afficher :

President

Candidate A — 48%

Candidate B — 37%

Candidate C — 15%

Avec :

- nombre total de votes ;

- pourcentage ;

- graphiques simples.

Les résultats ne doivent devenir visibles qu'après la fermeture officielle de l'élection.

# **23. ADMIN DASHBOARD**

URL :

/admin

Dashboard moderne.

### **Statistiques**

Registered Members

Pending Members

Upcoming Events

Published News

Active Elections

Votes

# **24. GESTION DES MEMBRES**

Admin peut :

- voir les membres ;

- rechercher ;

- filtrer ;

- valider ;

- rejeter ;

- suspendre ;

- modifier certaines informations.

Filtres :

Faculty

Year

Status

# **25. GESTION DES ÉLECTIONS**

Admin peut :

- créer une élection ;

- définir dates ;

- créer postes ;

- ajouter candidats ;

- modifier candidats ;

- publier candidats ;

- ouvrir l'élection ;

- fermer l'élection ;

- publier résultats.

### **IMPORTANT**

Une élection fermée ne doit plus accepter de votes.

# **26. GESTION DES ÉVÉNEMENTS**

Admin peut créer :

Event title

Description

Date

Start time

End time

Location

Cover image

Gallery

Registration link

Status

Status :

Upcoming

Ongoing

Completed

Cancelled

Postponed

# **27. PAGE EVENT**

Exemple :

# **CONGOLESE DAY**

📅 Date

📍 Location

Description

### **Program**

12:00 — Opening

13:00 — Cultural presentation

etc.

Photos après l'événement.

# **28. GALERIE PHOTO 📸**

Admin peut créer des albums :

Summer Escape 2026

Congolese Day 2026

Spring Festival 2026

Football Match

Chaque album contient plusieurs photos.

Fonction :

- upload multiple ;

- compression ;

- thumbnails ;

- lightbox ;

- suppression ;

- ordre des photos.

# **29. NEWS / ANNOUNCEMENTS**

Admin peut publier :

- titre ;

- contenu ;

- image ;

- catégorie ;

- date ;

- auteur.

Catégories :

Announcement

Community

Events

Sports

Culture

Education

# **30. DOCUMENTS**

Section :

### **Community Resources**

Permettre d'ajouter :

- règlements ;

- formulaires ;

- documents communautaires ;

- rapports ;

- archives.

Formats autorisés :

PDF principalement.

# **31. SYSTÈME DE NOTIFICATIONS EMAIL**

PHPMailer.

Exemples :

### **Après recensement**

> Registration received.

### **Après validation**

> Your membership has been approved.

### **Élection**

> Voting is now open.

### **Fermeture**

> Voting has officially closed.

### **Événement**

Possibilité d'envoyer une notification aux membres.

# **32. ADMINISTRATION DES EMAILS**

L'administrateur peut configurer :

SMTP Host

SMTP Port

SMTP Username

SMTP Password

Sender Name

Sender Email

Ces données doivent être stockées dans .env.

**Jamais dans le code source.**

# **33. BASE DE DONNÉES**

Architecture minimale.

### **users**

id

email

password_hash

role

created_at

updated_at

Roles :

admin

member

### **members**

id

user_id

full_name

student_id

faculty

department

year

phone

instagram

photo

status

email_verified_at

created_at

updated_at

Status :

pending

validated

rejected

suspended

### **events**

id

title

slug

description

location

start_date

end_date

cover_image

status

created_at

updated_at

### **event_images**

id

event_id

image_path

caption

sort_order

created_at

### **news**

id

title

slug

excerpt

content

cover_image

category

author_id

published_at

created_at

updated_at

### **elections**

id

title

description

start_at

end_at

status

created_at

updated_at

### **election_positions**

id

election_id

name

description

max_votes

### **candidates**

id

election_position_id

member_id

bio

manifesto

photo

status

### **votes**

Architecture à concevoir pour préserver l'anonymat.

Minimum :

id

election_id

position_id

candidate_id

vote_hash

created_at

**Ne pas mettre member_id dans cette table.**

### **voting_tokens**

id

election_id

member_id

token_hash

used_at

expires_at

created_at

Le token doit être stocké sous forme hashée.

# **34. SÉCURITÉ 🔐**

Le site doit intégrer :

### **Backend**

- PDO prepared statements

- CSRF protection

- XSS protection

- validation serveur

- password hashing avec password_hash()

- sessions sécurisées

- rate limiting

- protection contre brute force

- upload sécurisé

- contrôle MIME type

- taille maximale des fichiers

### **Authentification**

Ne jamais stocker les mots de passe en clair.

# **35. PROTECTION DES UPLOADS**

Images :

jpg

jpeg

png

webp

Documents :

pdf

Renommer les fichiers côté serveur :

random_unique_filename

Ne jamais utiliser directement le nom envoyé par l'utilisateur.

# **36. SEO**

Chaque page doit avoir :

- title ;

- meta description ;

- Open Graph ;

- Twitter/X card ;

- URL propre ;

- slug.

Exemple :

/community

/events

/events/congolese-day-2026

/news

/elections

# **37. SOCIAL MEDIA**

Ajouter :

### **Instagram**

Lien vers le compte officiel.

### **WhatsApp**

CTA :

**Join our WhatsApp Community**

### **Partage**

Boutons :

- WhatsApp

- Facebook

- X

# **38. MULTILINGUE**

Version initiale :

**Français**

Prévoir cependant une architecture permettant d'ajouter :

**English**

plus tard.

Ne pas coder toutes les chaînes directement dans les templates si cela empêche facilement l'internationalisation.

# **39. PAGE CONTACT**

Formulaire :

Name

Email

Subject

Message

Protection :

- CSRF

- rate limiting

- validation.

Email envoyé via PHPMailer.

# **40. FOOTER**

COMMUNAUTÉ CONGOLAISE DE FINAL

Unis par nos racines,

poussés par l'avenir.

Quick Links

Community

Events

News

Gallery

Elections

Connect

Instagram

WhatsApp

© 2026 Congolese Community of Final

# **41. DESIGN DES PAGES**

Toutes les pages doivent avoir une cohérence visuelle.

### **Bouton principal**

Blue :

\#0063C6

### **Hover**

Light blue :

\#7BBCFF

### **Accent**

Gold :

\#ECC117

### **Danger**

Red :

\#C00F1F

Utiliser des couleurs neutres pour le reste :

White

\#F8FAFC

\#111827

\#64748B

\#E5E7EB

# **42. ANIMATIONS**

Utiliser des animations légères :

- fade-in ;

- hover cards ;

- smooth transitions ;

- modal ;

- galerie lightbox.

Éviter les animations excessives.

Le site doit donner :

> **institution moderne**, pas **site de crypto en 2021** 😂.

# **43. RESPONSIVE**

### **Mobile**

Navigation hamburger.

Cards en une colonne.

Gallery :

2 columns

### **Tablet**

2 colonnes.

### **Desktop**

3–4 colonnes selon contenu.

# **44. ACCESSIBILITÉ**

Prévoir :

- alt text ;

- contraste suffisant ;

- navigation clavier ;

- labels sur les formulaires ;

- focus visible ;

- boutons suffisamment grands sur mobile.

# **45. ARCHITECTURE DES DOSSIERS**

Proposition :

/project

│

├── app/

│ ├── Controllers/

│ ├── Models/

│ ├── Services/

│ ├── Middleware/

│ └── Helpers/

│

├── config/

│

├── database/

│ ├── migrations/

│ └── seeders/

│

├── public/

│ ├── index.php

│ ├── assets/

│ │ ├── css/

│ │ ├── js/

│ │ └── images/

│ └── uploads/

│

├── resources/

│ └── views/

│ ├── layouts/

│ ├── home/

│ ├── community/

│ ├── events/

│ ├── news/

│ ├── gallery/

│ ├── elections/

│ ├── auth/

│ └── admin/

│

├── routes/

│

├── storage/

│

├── .env

├── .env.example

└── README.md

# **46. ROUTING**

Exemples :

GET /

GET /community

GET /community/government

GET /events

GET /events/{slug}

GET /news

GET /news/{slug}

GET /gallery

GET /register

POST /register

GET /verify-email

POST /verify-email

GET /elections

GET /elections/{id}

GET /elections/{id}/candidates

GET /elections/{id}/vote

POST /elections/{id}/vote

GET /elections/{id}/results

GET /admin

GET /admin/members

GET /admin/events

GET /admin/news

GET /admin/gallery

GET /admin/elections

# **47. DASHBOARD ADMIN**

Sidebar :

Dashboard

MEMBERS

Members

Pending Members

CONTENT

Events

News

Gallery

Documents

ELECTIONS

Elections

Candidates

Results

SYSTEM

Admins

Settings

Email Settings

# **48. LOGGING**

Créer des logs pour les actions sensibles :

Admin login

Member validation

Election created

Election opened

Election closed

Candidate added

Vote submitted

Mais **ne jamais enregistrer le choix du candidat avec l'identité de l'électeur dans les logs**.

# **49. BACKUP**

Prévoir une stratégie permettant :

- export MySQL ;

- sauvegarde des images ;

- sauvegarde des documents.

# **50. PERFORMANCE**

Le site doit être rapide.

Prévoir :

- images optimisées ;

- lazy loading ;

- pagination ;

- requêtes SQL optimisées ;

- index sur les colonnes importantes ;

- cache éventuel.

# **51. PHASES DE DÉVELOPPEMENT**

Ne pas essayer de tout développer simultanément.

### **PHASE 1 — Foundation**

- architecture ;

- database ;

- routing ;

- authentication ;

- layout ;

- design system ;

- admin.

### **PHASE 2 — Community**

- recensement ;

- vérification email ;

- membres ;

- gouvernement.

### **PHASE 3 — Content**

- events ;

- news ;

- gallery ;

- documents.

### **PHASE 4 — Election**

- elections ;

- positions ;

- candidates ;

- authentication ;

- voting;

- anti-double vote ;

- anonymous vote ;

- results.

### **PHASE 5 — Polish**

- responsive ;

- SEO ;

- accessibility ;

- security audit ;

- performance ;

- testing.

### **PHASE 6 — Network & Alumni**

- annuaire des membres ;

- profils publics opt-in ;

- statut alumni ;

- mentorat ;

- onboarding des nouveaux arrivants.

Cette phase peut arriver après la première élection, mais le schéma de base de données doit déjà la permettre dès la Phase 1.

# **52. TESTS OBLIGATOIRES**

Claude Code doit créer des tests pour :

### **Registration**

- email valide ;

- email non @final.edu.tr ;

- email déjà enregistré ;

- OTP incorrect ;

- OTP expiré.

### **Elections**

- étudiant non validé ;

- étudiant validé ;

- étudiant déjà votant ;

- élection fermée ;

- élection pas encore ouverte ;

- candidat inexistant.

### **Security**

- CSRF ;

- SQL injection ;

- XSS ;

- upload malveillant ;

- brute force.

# **53. SEED DATA**

Créer des données de démonstration :

### **Members**

10 membres fictifs.

### **Events**

5 événements fictifs.

### **News**

5 articles.

### **Election**

Une élection de démonstration avec :

President

Vice President

General Secretary

Treasurer

Et plusieurs candidats fictifs.

# **54. README**

Le projet doit contenir un README expliquant :

Requirements

Installation

Database setup

Environment variables

SMTP configuration

Migration

Seeding

Creating admin

Running locally

Deployment

Backup

# **55. VARIABLES .env**

Exemple :

APP_NAME="Congolese Community of Final"

APP_ENV=local

APP_URL=http://localhost

DB_HOST=127.0.0.1

DB_PORT=3306

DB_DATABASE=congolese_community

DB_USERNAME=root

DB_PASSWORD=

MAIL_HOST=

MAIL_PORT=

MAIL_USERNAME=

MAIL_PASSWORD=

MAIL_FROM_ADDRESS=

MAIL_FROM_NAME="Congolese Community of Final"

# **56. IMPORTANT — APPROCHE DE DÉVELOPPEMENT**

Donner à Claude Code cette consigne :

> **Do not generate the entire project blindly in one pass.**
>
> First inspect the project structure, establish the architecture and database schema, then implement the project in clearly separated phases.
>
> After each phase, verify that the application works before continuing.
>
> Do not introduce unnecessary frameworks or dependencies.
>
> Prioritize security, maintainability, responsive design and clean UX.

# **57. PRIORITÉ ABSOLUE**

Le projet doit être construit autour de **5 piliers** :

### **🇨🇩 COMMUNITY**

Recenser → connecter → informer

### **📅 ACTIVITIES**

Events → photos → archives → culture

### **🗳️ DEMOCRACY**

Candidatures → élections → vote → résultats

### **🤝 NETWORK**

Annuaire → rencontres → entraide

### **🎓 LEGACY**

Alumni → mentorat → partenariats

**Ces cinq piliers forment un cycle continu** : découvrir → se recenser → participer → voter → devenir alumni → revenir comme mentor.

Le site n'est pas un outil électoral ponctuel : c'est l'infrastructure numérique permanente de la communauté.

# **58. ÉVOLUTION FUTURE**

L'architecture doit permettre d'ajouter plus tard :

Note : l'annuaire des membres, l'espace alumni et le mentorat font désormais partie de la vision principale du projet (voir section 1). Ils restent listés ici pour rappel de leur ordre d'implémentation, mais ne sont plus considérés comme de simples options lointaines.

- système de cotisation ;

- carte de membre numérique ;

- QR code membre ;

- notifications push ;

- système de messagerie ;

- calendrier communautaire ;

- offres d'emploi/stages ;

- marketplace communautaire ;

- annuaire des entreprises congolaises ;

- mentorat ;

- espace alumni ;

- partenariats ;

- application mobile.
