#!/usr/bin/env python3
"""Garde-fou CI : la table `votes` ne doit contenir aucune référence à un membre.

Le lien entre un électeur et son bulletin ne doit exister nulle part.
Ce script échoue si une migration réintroduit member_id, user_id ou token_id
dans la définition de la table votes.
"""
import glob
import re
import sys

FORBIDDEN = ("member_id", "user_id", "token_id", "voting_token_id")

failed = False
for path in sorted(glob.glob("database/migrations/*.sql")):
    sql = open(path, encoding="utf-8").read()
    match = re.search(r"CREATE\s+TABLE\s+`?votes`?\s*\((.*?)\n\)\s*ENGINE", sql, re.S | re.I)
    if not match:
        continue
    body = "\n".join(
        line for line in match.group(1).splitlines()
        if not line.strip().startswith("--")
    )
    for column in FORBIDDEN:
        if column in body:
            print(f"::error file={path}::La table votes contient `{column}`. "
                  f"Le scrutin doit rester anonyme (cahier des charges, section 20).")
            failed = True

sys.exit(1 if failed else 0)
