from __future__ import annotations

import argparse
from pathlib import Path

import fitz


def split_chunks(source: Path, output_dir: Path, chunk_size: int) -> None:
    src = fitz.open(source)
    output_dir.mkdir(parents=True, exist_ok=True)

    for start in range(1, len(src) + 1, chunk_size):
        end = min(start + chunk_size - 1, len(src))
        dst_path = output_dir / f"bncc_pages_{start:03d}_{end:03d}.pdf"
        if dst_path.exists():
            continue

        dst = fitz.open()
        dst.insert_pdf(src, from_page=start - 1, to_page=end - 1)
        dst.save(dst_path)
        dst.close()

    src.close()


def main() -> None:
    parser = argparse.ArgumentParser(description="Split a PDF into fixed-size chunks.")
    parser.add_argument("source", type=Path)
    parser.add_argument("output_dir", type=Path)
    parser.add_argument("--chunk-size", type=int, default=50)
    args = parser.parse_args()
    split_chunks(args.source, args.output_dir, args.chunk_size)


if __name__ == "__main__":
    main()
