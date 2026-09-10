#!/usr/bin/env python3
"""Garde-fou CI : la table `votes` ne doit contenir aucune référence à un membre.

Le lien entre un électeur et son bulletin ne doit exister nulle part. Ce
script échoue si une migration réintroduit member_id, user_id, token_id ou
voting_token_id dans la table votes — que ce soit dans sa définition initiale
(CREATE TABLE) ou ajouté après coup (ALTER TABLE ADD COLUMN / ADD CONSTRAINT).
La première version de ce script ne couvrait que CREATE TABLE ; un ALTER
TABLE serait passé inaperçu (voir docs/SECURITE_ELECTIONS.md, §2.7).
"""
import glob
import re
import sys

FORBIDDEN = ("member_id", "user_id", "token_id", "voting_token_id")

failed = False


def check(path: str, statement_kind: str, body: str) -> None:
    global failed
    clean = "\n".join(
        line for line in body.splitlines() if not line.strip().startswith("--")
    )
    for column in FORBIDDEN:
        if re.search(r"\b" + re.escape(column) + r"\b", clean):
            print(
                f"::error file={path}::Un {statement_kind} sur la table votes "
                f"référence `{column}`. Le scrutin doit rester anonyme "
                f"(cahier des charges, section 20)."
            )
            failed = True


for path in sorted(glob.glob("database/migrations/*.sql")):
    sql = open(path, encoding="utf-8").read()

    for match in re.finditer(
        r"CREATE\s+TABLE\s+`?votes`?\s*\((.*?)\n\)\s*ENGINE", sql, re.S | re.I
    ):
        check(path, "CREATE TABLE", match.group(1))

    # ALTER TABLE votes ...; — capture jusqu'au ';' de fin d'instruction (pas
    # seulement la première ligne), pour couvrir un ADD COLUMN/ADD CONSTRAINT
    # étalé sur plusieurs lignes.
    for match in re.finditer(
        r"ALTER\s+TABLE\s+`?votes`?\s+(.*?);", sql, re.S | re.I
    ):
        check(path, "ALTER TABLE", match.group(1))

sys.exit(1 if failed else 0)
