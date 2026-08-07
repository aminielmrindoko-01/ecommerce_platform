#!/usr/bin/env python3
"""Unit tests for convert_sql_for_infinityfree.py"""

from __future__ import annotations

import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

# Allow importing the converter from the same directory
sys.path.insert(0, str(Path(__file__).resolve().parent))

import convert_sql_for_infinityfree as converter  # noqa: E402


class SplitValueTuplesTest(unittest.TestCase):
    def test_splits_simple_tuples(self) -> None:
        blob = "(1,'a'),(2,'b'),(3,'c')"
        self.assertEqual(
            converter.split_value_tuples(blob),
            ["(1,'a')", "(2,'b')", "(3,'c')"],
        )

    def test_handles_sql_escaped_quotes_inside_strings(self) -> None:
        blob = "(1,'it''s fine'),(2,'ok')"
        self.assertEqual(
            converter.split_value_tuples(blob),
            ["(1,'it''s fine')", "(2,'ok')"],
        )

    def test_handles_backslash_escapes(self) -> None:
        blob = r"(1,'line\nbreak'),(2,'x')"
        self.assertEqual(
            converter.split_value_tuples(blob),
            [r"(1,'line\nbreak')", "(2,'x')"],
        )

    def test_strips_trailing_semicolon(self) -> None:
        blob = "(1,'a'),(2,'b');"
        self.assertEqual(
            converter.split_value_tuples(blob),
            ["(1,'a')", "(2,'b')"],
        )

    def test_raises_on_malformed_blob(self) -> None:
        with self.assertRaises(ValueError):
            converter.split_value_tuples("1,'bad'")


class RewriteInsertTest(unittest.TestCase):
    def test_leaves_small_insert_unchanged(self) -> None:
        stmt = "INSERT INTO `users` VALUES (1,'a'),(2,'b');"
        out = converter.rewrite_insert(stmt, split_rows=25)
        self.assertEqual(out, stmt + "\n")

    def test_splits_large_insert_into_batches(self) -> None:
        tuples = ",".join(f"({i},'v{i}')" for i in range(1, 6))
        stmt = f"INSERT INTO `products` VALUES {tuples};"
        out = converter.rewrite_insert(stmt, split_rows=2)
        lines = [line for line in out.strip().split("\n") if line]
        self.assertEqual(len(lines), 3)
        self.assertTrue(all(line.startswith("INSERT INTO `products` VALUES ") for line in lines))
        self.assertIn("(1,'v1'),(2,'v2')", lines[0])
        self.assertIn("(3,'v3'),(4,'v4')", lines[1])
        self.assertIn("(5,'v5')", lines[2])

    def test_returns_non_insert_statements_unchanged(self) -> None:
        stmt = "CREATE TABLE `users` (`id` int);"
        self.assertEqual(converter.rewrite_insert(stmt, split_rows=25), stmt + "\n")


class ConvertDumpTest(unittest.TestCase):
    def test_removes_create_database_and_use(self) -> None:
        sql = (
            "CREATE DATABASE `ecommerce_deploy`;\n"
            "USE `ecommerce_deploy`;\n"
            "CREATE TABLE `users` (`id` int);\n"
        )
        out = converter.convert_dump(sql)
        self.assertNotIn("CREATE DATABASE", out)
        self.assertNotIn("USE `ecommerce_deploy`", out)
        self.assertIn("CREATE TABLE `users`", out)

    def test_removes_definer_clauses(self) -> None:
        sql = (
            "CREATE DEFINER=`root`@`localhost` PROCEDURE `p`() BEGIN END;\n"
        )
        out = converter.convert_dump(sql)
        self.assertNotIn("DEFINER=", out)
        self.assertIn("CREATE PROCEDURE `p`()", out)

    def test_replaces_json_check_columns_with_longtext(self) -> None:
        sql = (
            "`meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin "
            "NOT NULL CHECK (json_valid(`meta`)),\n"
        )
        out = converter.convert_dump(sql)
        self.assertIn("`meta` longtext NOT NULL", out)
        self.assertNotIn("json_valid", out)
        self.assertNotRegex(out, r"CHECK\s*\(\s*json_valid")

    def test_normalizes_mysql8_collation(self) -> None:
        sql = "DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;\n"
        out = converter.convert_dump(sql)
        self.assertIn("utf8mb4_unicode_ci", out)
        self.assertNotIn("utf8mb4_0900_ai_ci", out)

    def test_normalizes_current_timestamp_function(self) -> None:
        sql = "`created_at` timestamp NOT NULL DEFAULT current_timestamp(),\n"
        out = converter.convert_dump(sql)
        self.assertIn("DEFAULT CURRENT_TIMESTAMP", out)
        self.assertNotIn("current_timestamp()", out)

    def test_clears_sessions_insert_by_default(self) -> None:
        sql = (
            "LOCK TABLES `sessions` WRITE;\n"
            "/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;\n"
            "INSERT INTO `sessions` VALUES (1,'payload');\n"
            "/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;\n"
            "UNLOCK TABLES;\n"
        )
        out = converter.convert_dump(sql)
        self.assertNotIn("INSERT INTO `sessions`", out)
        self.assertIn("LOCK TABLES `sessions` WRITE;", out)

    def test_keeps_sessions_when_disabled(self) -> None:
        sql = (
            "LOCK TABLES `sessions` WRITE;\n"
            "/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;\n"
            "INSERT INTO `sessions` VALUES (1,'payload');\n"
            "/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;\n"
            "UNLOCK TABLES;\n"
        )
        out = converter.convert_dump(sql, clear_sessions=False)
        self.assertIn("INSERT INTO `sessions` VALUES (1,'payload');", out)

    def test_adds_infinityfree_header_and_strips_mariadb_banner(self) -> None:
        sql = (
            "-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB\n"
            "--\n"
            "-- Host: localhost    Database: ecommerce_deploy\n"
            "-- ------------------------------------------------------\n"
            "-- Server version\t10.4.32-MariaDB\n"
            "\n"
            "CREATE TABLE `users` (`id` int);\n"
        )
        out = converter.convert_dump(sql)
        self.assertTrue(out.startswith("-- InfinityFree-compatible MySQL/MariaDB dump"))
        self.assertNotIn("-- MariaDB dump", out)
        self.assertNotIn("-- Host: localhost", out)
        self.assertIn("CREATE TABLE `users`", out)

    def test_splits_large_insert_statements(self) -> None:
        tuples = ",".join(f"({i},'v{i}')" for i in range(1, 6))
        sql = f"INSERT INTO `products` VALUES {tuples};\n"
        out = converter.convert_dump(sql, split_rows=2)
        self.assertEqual(out.count("INSERT INTO `products` VALUES"), 3)


class CliIntegrationTest(unittest.TestCase):
    def test_cli_writes_converted_output_file(self) -> None:
        script = Path(__file__).resolve().parent / "convert_sql_for_infinityfree.py"
        source_sql = (
            "CREATE DATABASE `ecommerce_deploy`;\n"
            "USE `ecommerce_deploy`;\n"
            "CREATE TABLE `users` (`id` int);\n"
        )
        with tempfile.TemporaryDirectory() as tmp:
            input_path = Path(tmp) / "input.sql"
            output_path = Path(tmp) / "output.sql"
            input_path.write_text(source_sql, encoding="utf-8")

            result = subprocess.run(
                [
                    sys.executable,
                    str(script),
                    str(input_path),
                    "-o",
                    str(output_path),
                    "--split-rows",
                    "10",
                ],
                capture_output=True,
                text=True,
                check=False,
            )

            self.assertEqual(result.returncode, 0, msg=result.stderr)
            self.assertTrue(output_path.is_file())
            converted = output_path.read_text(encoding="utf-8")
            self.assertIn("InfinityFree-compatible", converted)
            self.assertNotIn("CREATE DATABASE", converted)
            self.assertIn("CREATE TABLE `users`", converted)


if __name__ == "__main__":
    unittest.main()
