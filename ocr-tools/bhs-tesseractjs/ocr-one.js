const fs = require("fs");
const { createWorker } = require("tesseract.js");

async function main() {
  const imagePath = process.argv[2];
  const outPath = process.argv[3];
  const psm = process.argv[4];

  if (!imagePath || !outPath) {
    console.error("Usage: node ocr-one.js <image> <output.txt>");
    process.exit(2);
  }

  const worker = await createWorker("heb+eng", 1, {
    cachePath: process.env.TESSDATA_CACHE || "tessdata-cache",
  });

  await worker.setParameters({
    preserve_interword_spaces: "1",
    ...(psm ? { tessedit_pageseg_mode: psm } : {}),
  });

  const result = await worker.recognize(imagePath);
  await worker.terminate();

  fs.writeFileSync(outPath, result.data.text, "utf8");
  console.log(JSON.stringify({
    textLength: result.data.text.length,
    confidence: result.data.confidence,
  }));
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
