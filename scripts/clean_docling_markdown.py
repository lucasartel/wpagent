from __future__ import annotations

import argparse
import re
from pathlib import Path


TABLE_SEPARATOR = re.compile(
    r"^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$"
)
DECORATIVE_ONLY = re.compile(r"^[\s\\_\-—–=·•*#|!\[\]().,;:«»<>■□▪▫]+$")
IMAGE_EXT = re.compile(r"\.(png|jpg|jpeg|webp|gif)\)", re.IGNORECASE)


def clean_line(raw: str) -> str | None:
    line = raw.strip()

    if not line:
        return ""

    if line.startswith("![Image](") or line.startswith("![]("):
        return None

    if "_artifacts" in line or IMAGE_EXT.search(line):
        return None

    if TABLE_SEPARATOR.match(line):
        return None

    line = re.sub(r"^#{1,6}\s*", "", line).strip()
    line = re.sub(r"^\|\s*(.*?)\s*\|$", r"\1", line).strip()
    line = re.sub(r"\s*\|\s*", " ", line).strip()
    line = (
        line.replace(r"\_", "_")
        .replace(r"\*", "*")
        .replace(r"\#", "#")
        .replace(r"\[", "[")
        .replace(r"\]", "]")
    )
    line = re.sub(r"^[-*+]\s+■\s*", "- ", line)
    line = re.sub(r"^■\s*", "", line).strip()
    line = re.sub(r"\s{3,}", "  ", line).strip()

    if not line or DECORATIVE_ONLY.match(line):
        return None

    return line


def clean_markdown(source: Path, destination: Path) -> None:
    text = source.read_text(encoding="utf-8")
    out: list[str] = []
    previous_blank = False

    for raw in text.splitlines():
        line = clean_line(raw)

        if line is None:
            continue

        if line == "":
            if out and not previous_blank:
                out.append("")
                previous_blank = True
            continue

        out.append(line)
        previous_blank = False

    destination.write_text("\n".join(out).strip() + "\n", encoding="utf-8", newline="\n")


def main() -> None:
    parser = argparse.ArgumentParser(description="Clean Docling Markdown into text-only Markdown.")
    parser.add_argument("source", type=Path)
    parser.add_argument("destination", type=Path)
    args = parser.parse_args()

    clean_markdown(args.source, args.destination)


if __name__ == "__main__":
    main()
