# Décisions de sécurité qui semblent se contredire — et pourquoi

Ce document existe pour un seul type de problème : deux endroits du code font
des choix opposés face à une même question de sécurité, chacun délibérément
et pour de bonnes raisons — mais rien dans le code lui-même ne dit à un futur
lecteur que l'autre endroit existe. Sans ce document, corriger l'un en le
découvrant "incohérent" avec l'autre est une régression plausible.

---

## 1. Révéler ou non qu'un email existe déjà — connexion vs inscription

**Le contraste.** Deux flux du même projet répondent différemment à
"est-ce que cette adresse existe déjà ?" :

- **Connexion** (`App\Controllers\AuthController::login()`, ligne ~43) : un
  email inconnu et un mot de passe erroné sur un compte existant produisent
  exactement le même message (`auth.invalid`) — le commentaire en place le
  dit explicitement : *"ne jamais révéler si une adresse est enregistrée
  (évite l'énumération)"*. Pour un membre, le délai de réponse est en plus
  le même dans les deux cas (`AuthController::attemptMemberLogin()`,
  ralentissement progressif appliqué *avant* la vérification du mot de
  passe — voir CLAUDE.md, règle 11) : ni le message ni le temps de réponse
  ne distinguent "ce compte n'existe pas" de "ce compte existe, mauvais mot
  de passe".
- **Inscription** (`App\Controllers\RegistrationController::validate()`,
  clés `validation.email_taken` / `validation.duplicate_registration`) : à
  l'inverse, le formulaire dit explicitement "cet email est déjà
  enregistré" quand l'adresse saisie correspond à un compte existant.

Un lecteur qui tombe sur l'un après avoir vu l'autre peut raisonnablement
penser que c'est un oubli. Ce n'en est pas un — trouvé et évalué en revue de
sécurité FIUCC-77 (2026-09-12), gardé tel quel délibérément.

**Pourquoi les deux choix sont corrects malgré tout.**

Ce ne sont pas la même menace. La connexion protège contre un attaquant qui
cible *une personne précise, un jour précis* : la sonder pour savoir si son
adresse a un compte, puis répéter l'opération jour de scrutin pour tenter
de la priver de vote ou simplement pour la harceler — c'est exactement le
scénario que la règle 11 de CLAUDE.md construit tout un mécanisme de
ralentissement pour neutraliser. Là, la moindre fuite d'information
("ce compte existe" / "ce compte n'existe pas") redonne à l'attaquant un
signal qu'aucun ralentissement ne peut compenser.

L'inscription protège — ou plutôt, n'a pas vraiment à protéger — contre
autre chose : une reconnaissance en masse ("combien de ces 500 adresses
`@final.edu.tr` ont un compte sur le portail ?"). Dans une communauté
fermée et de taille connue, l'information exposée par cette confirmation
("cette personne a un compte sur le portail communautaire de son
université") n'a pas la même gravité qu'un e-mail confirmant l'existence
d'un compte bancaire ou médical. Et côté légitime, la confirmation
explicite sert un vrai besoin d'utilisabilité : quelqu'un qui a déjà un
compte et qui retente de s'inscrire doit comprendre pourquoi ça échoue,
sans quoi le formulaire échoue silencieusement pour une raison qu'il ne
peut pas deviner.

**Ce que ça veut dire pour un futur changement.** Ne pas rendre l'un
symétrique à l'autre par réflexe de cohérence :

- Si un jour la connexion révèle plus qu'aujourd'hui (ex. un message
  différent pour "compte introuvable"), c'est une régression de la règle 11
  de CLAUDE.md — pas un rapprochement avec l'inscription.
- Si un jour l'inscription doit devenir résistante à l'énumération (ex. un
  incident réel de sondage en masse d'adresses), la bonne réponse n'est pas
  de copier le mécanisme de la connexion tel quel : un flux de confirmation
  asynchrone ("si un compte existe déjà pour cette adresse, un email vous a
  été envoyé") change l'expérience d'inscription pour tout le monde, pas
  seulement pour un attaquant — à traiter comme son propre ticket, avec son
  propre arbitrage UX, pas comme une correction de cohérence.

Si ce calcul doit être refait un jour, documenter le nouveau raisonnement
ici plutôt que de changer l'un des deux flux en silence.
