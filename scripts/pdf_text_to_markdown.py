from __future__ import annotations

import argparse
import re
import unicodedata
from pathlib import Path

import pypdfium2 as pdfium


SOFT_HYPHENISH = "\u0002\u00ad\u2027\ufffe"
CONTROL_CHARS = re.compile(r"[\x00-\x08\x0b\x0c\x0e-\x1f]")


def normalize_text(text: str) -> str:
    text = text.replace("\r\n", "\n").replace("\r", "\n")
    text = text.replace("\ufffc", "")
    for char in SOFT_HYPHENISH:
        text = text.replace(char, "")
    text = CONTROL_CHARS.sub("", text)
    text = text.replace("\u00a0", " ")
    text = unicodedata.normalize("NFC", text)

    lines: list[str] = []
    for raw in text.splitlines():
        line = re.sub(r"[ \t]+", " ", raw).strip()
        if line:
            lines.append(line)
        elif lines and lines[-1] != "":
            lines.append("")

    return "\n".join(lines).strip()


def write_markdown(pdf_path: Path, md_path: Path, title: str | None = None) -> None:
    document = pdfium.PdfDocument(pdf_path)
    heading = title or pdf_path.stem

    with md_path.open("w", encoding="utf-8", newline="\n") as handle:
        handle.write(f"# {heading}\n\n")
        handle.write(f"Source PDF: {pdf_path.name}\n\n")
        handle.write(f"Total pages: {len(document)}\n\n")

        for page_index in range(len(document)):
            page = document[page_index]
            textpage = page.get_textpage()
            text = normalize_text(textpage.get_text_range())

            handle.write(f"\n\n<!-- page:{page_index + 1} -->\n\n")
            handle.write(f"## Page {page_index + 1}\n\n")
            if text:
                handle.write(text)
                handle.write("\n")


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Extract a text-layer PDF into page-marked Markdown."
    )
    parser.add_argument("pdf", type=Path)
    parser.add_argument("markdown", type=Path)
    parser.add_argument("--title")
    args = parser.parse_args()

    write_markdown(args.pdf, args.markdown, args.title)


if __name__ == "__main__":
    main()
