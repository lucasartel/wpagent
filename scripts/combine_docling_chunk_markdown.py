from __future__ import annotations

import argparse
import re
from pathlib import Path


CHUNK_NAME = re.compile(r"bncc_pages_(\d+)_(\d+)\.md$")


def combine(chunks_dir: Path, destination: Path, title: str, source_pdf: str) -> None:
    chunk_files = []
    for path in chunks_dir.rglob("bncc_pages_*.md"):
        match = CHUNK_NAME.match(path.name)
        if match:
            chunk_files.append((int(match.group(1)), int(match.group(2)), path))

    chunk_files.sort(key=lambda item: item[0])
    if not chunk_files:
        raise SystemExit(f"No chunk Markdown files found under {chunks_dir}")

    parts = [
        f"# {title}",
        "",
        f"Source PDF: {source_pdf}",
        "",
        "Extraction: Docling, processed in page chunks to avoid layout-model memory errors.",
        "",
    ]

    for start, end, path in chunk_files:
        text = path.read_text(encoding="utf-8").strip()
        parts.extend(
            [
                "",
                f"<!-- source_pages:{start}-{end} -->",
                "",
                f"## Pages {start}-{end}",
                "",
                text,
                "",
            ]
        )

    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_text("\n".join(parts).strip() + "\n", encoding="utf-8", newline="\n")


def main() -> None:
    parser = argparse.ArgumentParser(description="Combine Docling chunk Markdown files.")
    parser.add_argument("chunks_dir", type=Path)
    parser.add_argument("destination", type=Path)
    parser.add_argument("--title", required=True)
    parser.add_argument("--source-pdf", required=True)
    args = parser.parse_args()
    combine(args.chunks_dir, args.destination, args.title, args.source_pdf)


if __name__ == "__main__":
    main()
