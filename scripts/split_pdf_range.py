from __future__ import annotations

import argparse
from pathlib import Path

import fitz


def split_range(source: Path, destination: Path, start_page: int, end_page: int) -> None:
    src = fitz.open(source)
    dst = fitz.open()
    dst.insert_pdf(src, from_page=start_page - 1, to_page=end_page - 1)
    destination.parent.mkdir(parents=True, exist_ok=True)
    dst.save(destination)
    dst.close()
    src.close()


def main() -> None:
    parser = argparse.ArgumentParser(description="Write a 1-based inclusive PDF page range.")
    parser.add_argument("source", type=Path)
    parser.add_argument("destination", type=Path)
    parser.add_argument("start_page", type=int)
    parser.add_argument("end_page", type=int)
    args = parser.parse_args()
    split_range(args.source, args.destination, args.start_page, args.end_page)


if __name__ == "__main__":
    main()
