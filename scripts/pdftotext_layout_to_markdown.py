from __future__ import annotations

import argparse
import subprocess
import unicodedata
from pathlib import Path


def extract_layout(pdftotext: Path, pdf: Path) -> str:
    result = subprocess.run(
        [
            str(pdftotext),
            "-layout",
            "-enc",
            "UTF-8",
            str(pdf),
            "-",
        ],
        check=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
    return result.stdout.decode("utf-8", errors="replace")


def page_to_markdown(page_text: str) -> str:
    text = page_text.replace("\r\n", "\n").replace("\r", "\n")
    text = text.replace("\u00a0", " ")
    text = text.replace("\ufffe", "")
    text = unicodedata.normalize("NFC", text)
    return text.strip("\n")


def write_markdown(pdftotext: Path, pdf: Path, md: Path, title: str) -> None:
    extracted = extract_layout(pdftotext, pdf)
    pages = extracted.split("\f")

    with md.open("w", encoding="utf-8", newline="\n") as handle:
        handle.write(f"# {title}\n\n")
        handle.write(f"Source PDF: {pdf.name}\n\n")
        handle.write(f"Extraction: Poppler pdftotext -layout -enc UTF-8\n\n")
        handle.write(f"Total pages: {len(pages) if pages[-1].strip() else len(pages) - 1}\n")

        for index, page in enumerate(pages, start=1):
            text = page_to_markdown(page)
            if index == len(pages) and not text:
                continue

            handle.write(f"\n\n<!-- page:{index} -->\n\n")
            handle.write(f"## Page {index}\n\n")
            if text:
                handle.write("```text\n")
                handle.write(text)
                if not text.endswith("\n"):
                    handle.write("\n")
                handle.write("```\n")


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Convert a PDF to layout-preserving page-marked Markdown via pdftotext."
    )
    parser.add_argument("pdftotext", type=Path)
    parser.add_argument("pdf", type=Path)
    parser.add_argument("markdown", type=Path)
    parser.add_argument("--title", required=True)
    args = parser.parse_args()

    write_markdown(args.pdftotext, args.pdf, args.markdown, args.title)


if __name__ == "__main__":
    main()
