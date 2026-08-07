#!/usr/bin/env python3
"""
Convert a MariaDB/MySQL dump into an InfinityFree-compatible import.

InfinityFree free hosting typically runs MariaDB ~10.4 with:
  - no CREATE DATABASE / USE privileges via SQL
  - ~3MB max_allowed_packet (large multi-row INSERTs can fail)
  - phpMyAdmin upload/time limits
  - CHECK (json_valid(...)) / native JSON quirks on some plans

Usage:
  python3 scripts/convert_sql_for_infinityfree.py input.sql -o output.sql
  python3 scripts/convert_sql_for_infinityfree.py input.sql -o output.sql --split-rows 25
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path


CREATE_DB_RE = re.compile(
    r"^\s*(CREATE\s+DATABASE|USE)\b.*?;\s*$",
    re.IGNORECASE | re.MULTILINE,
)
DEFINER_RE = re.compile(r"\s+DEFINER=`[^`]+`@`[^`]+`", re.IGNORECASE)

# MariaDB dumps JSON columns as LONGTEXT + CHECK (json_valid(`col`))
JSON_CHECK_COL_RE = re.compile(
    r"longtext\s+CHARACTER\s+SET\s+utf8mb4\s+COLLATE\s+utf8mb4_bin"
    r"(\s+(?:NOT\s+NULL|NULL))?"
    r"(\s+DEFAULT\s+NULL)?"
    r"\s+CHECK\s*\(\s*json_valid\s*\(\s*`[^`]+`\s*\)\s*\)",
    re.IGNORECASE,
)

# Any leftover CHECK (json_valid(...))
JSON_CHECK_ANY_RE = re.compile(
    r"\s+CHECK\s*\(\s*json_valid\s*\(\s*`[^`]+`\s*\)\s*\)",
    re.IGNORECASE,
)

# MySQL 8 default collation not available on InfinityFree MariaDB
COLLATION_0900_RE = re.compile(r"utf8mb4_0900_ai_ci", re.IGNORECASE)

CURRENT_TS_FN_RE = re.compile(r"\bcurrent_timestamp\s*\(\s*\)", re.IGNORECASE)

INSERT_RE = re.compile(
    r"^(INSERT\s+INTO\s+`(?P<table>[^`]+)`\s+VALUES\s+)(?P<values>.*);?\s*$",
    re.IGNORECASE | re.DOTALL,
)


def split_value_tuples(values_blob: str) -> list[str]:
    """Split INSERT value tuples while respecting quotes/escapes."""
    values_blob = values_blob.strip()
    if values_blob.endswith(";"):
        values_blob = values_blob[:-1].rstrip()

    tuples: list[str] = []
    i = 0
    n = len(values_blob)
    while i < n:
        while i < n and values_blob[i] in " \t\r\n,":
            i += 1
        if i >= n:
            break
        if values_blob[i] != "(":
            raise ValueError(f"Expected '(' at position {i}")
        depth = 0
        in_string = False
        escape = False
        start = i
        while i < n:
            ch = values_blob[i]
            if in_string:
                if escape:
                    escape = False
                elif ch == "\\":
                    escape = True
                elif ch == "'":
                    # SQL '' escape inside strings
                    if i + 1 < n and values_blob[i + 1] == "'":
                        i += 2
                        continue
                    in_string = False
            else:
                if ch == "'":
                    in_string = True
                elif ch == "(":
                    depth += 1
                elif ch == ")":
                    depth -= 1
                    if depth == 0:
                        i += 1
                        tuples.append(values_blob[start:i].strip())
                        break
            i += 1
        else:
            raise ValueError("Unterminated value tuple in INSERT")
    return tuples


def rewrite_insert(statement: str, split_rows: int) -> str:
    match = INSERT_RE.match(statement.strip())
    if not match:
        return statement if statement.endswith("\n") else statement + "\n"

    prefix = match.group(1)
    values = match.group("values")
    tuples = split_value_tuples(values)
    if len(tuples) <= split_rows:
        return f"{prefix}{','.join(tuples)};\n"

    chunks: list[str] = []
    for i in range(0, len(tuples), split_rows):
        batch = tuples[i : i + split_rows]
        chunks.append(f"{prefix}{','.join(batch)};")
    return "\n".join(chunks) + "\n"


def convert_dump(sql: str, split_rows: int = 25, clear_sessions: bool = True) -> str:
    # Normalize newlines
    sql = sql.replace("\r\n", "\n").replace("\r", "\n")

    # Remove CREATE DATABASE / USE (InfinityFree: create DB in control panel only)
    sql = CREATE_DB_RE.sub("", sql)
    sql = DEFINER_RE.sub("", sql)

    # JSON / CHECK compatibility
    sql = JSON_CHECK_COL_RE.sub(r"longtext\1\2", sql)
    sql = JSON_CHECK_ANY_RE.sub("", sql)

    # Collation + timestamp function normalization
    sql = COLLATION_0900_RE.sub("utf8mb4_unicode_ci", sql)
    sql = CURRENT_TS_FN_RE.sub("CURRENT_TIMESTAMP", sql)

    # Optionally clear host-specific session rows (payloads won't work after migrate)
    if clear_sessions:
        sql = re.sub(
            r"LOCK TABLES `sessions` WRITE;\n"
            r"/\*!40000 ALTER TABLE `sessions` DISABLE KEYS \*/;\n"
            r"INSERT INTO `sessions` VALUES .*?;\n"
            r"/\*!40000 ALTER TABLE `sessions` ENABLE KEYS \*/;\n"
            r"UNLOCK TABLES;",
            "LOCK TABLES `sessions` WRITE;\n"
            "/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;\n"
            "/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;\n"
            "UNLOCK TABLES;",
            sql,
            flags=re.DOTALL,
        )

    # Split statements carefully: keep DELIMITER-free dumps as ; terminated
    # Process INSERT statements that may span one line (mysqldump default)
    out_parts: list[str] = []
    buffer: list[str] = []
    in_insert = False

    def flush_buffer() -> None:
        nonlocal buffer, in_insert
        if not buffer:
            return
        stmt = "".join(buffer)
        buffer = []
        if in_insert:
            out_parts.append(rewrite_insert(stmt, split_rows))
            in_insert = False
        else:
            out_parts.append(stmt)

    for line in sql.splitlines(keepends=True):
        stripped = line.lstrip()
        if not in_insert and stripped.upper().startswith("INSERT INTO"):
            in_insert = True
            buffer = [line]
            if ";" in line:
                # single-line insert
                flush_buffer()
            continue

        if in_insert:
            buffer.append(line)
            # mysqldump extended inserts end with );
            if line.rstrip().endswith(";"):
                flush_buffer()
            continue

        out_parts.append(line)

    flush_buffer()
    body = "".join(out_parts)

    header = """-- InfinityFree-compatible MySQL/MariaDB dump
-- Generated for phpMyAdmin import on InfinityFree free hosting.
--
-- Import steps:
--   1. Create an empty database in the InfinityFree control panel
--      (SQL schema-create / USE statements are stripped; pick the DB in phpMyAdmin).
--   2. Open phpMyAdmin -> select that database (left sidebar).
--   3. Import this file (or use BigDump if phpMyAdmin times out).
--   4. Point Laravel .env at the InfinityFree DB host/user/name/password.
--
-- Changes applied vs local MariaDB dump:
--   - Removed schema-create / USE / DEFINER statements
--   - Replaced MariaDB JSON CHECK columns with plain LONGTEXT
--   - Normalized MySQL-8-only collations to utf8mb4_unicode_ci
--   - Normalized timestamp defaults to CURRENT_TIMESTAMP
--   - Split large multi-row INSERT batches
--   - Cleared sessions rows (host-specific)
--
"""
    # Drop original MariaDB dump banner if present; keep rest
    body = re.sub(r"^-- MariaDB dump.*?\n(--\n)?", "", body, count=1, flags=re.MULTILINE)
    body = re.sub(
        r"^-- Host:.*?\n-- -+\n-- Server version.*?\n\n",
        "",
        body,
        count=1,
        flags=re.MULTILINE,
    )

    return header + body


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("input", type=Path, help="Source .sql dump")
    parser.add_argument(
        "-o",
        "--output",
        type=Path,
        required=True,
        help="InfinityFree-compatible output .sql",
    )
    parser.add_argument(
        "--split-rows",
        type=int,
        default=25,
        help="Max rows per INSERT statement (default: 25)",
    )
    parser.add_argument(
        "--keep-sessions",
        action="store_true",
        help="Keep sessions table INSERT data",
    )
    args = parser.parse_args()

    source = args.input.read_text(encoding="utf-8", errors="replace")
    converted = convert_dump(
        source,
        split_rows=max(1, args.split_rows),
        clear_sessions=not args.keep_sessions,
    )
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(converted, encoding="utf-8")
    print(
        f"Wrote {args.output} ({args.output.stat().st_size} bytes) "
        f"from {args.input} ({args.input.stat().st_size} bytes)"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
