import { FilesetResolver, ImageSegmenter, FaceDetector } from '/vendor/mediapipe/vision_bundle.mjs';

let segmenterPromise = null;
let faceDetectorPromise = null;

async function getModels() {
    if (!segmenterPromise || !faceDetectorPromise) {
        const visionPromise = FilesetResolver.forVisionTasks('/vendor/mediapipe/wasm');

        segmenterPromise = visionPromise.then((vision) =>
            ImageSegmenter.createFromOptions(vision, {
                baseOptions: {
                    modelAssetPath: '/vendor/mediapipe/selfie_segmenter.tflite',
                },
                runningMode: 'IMAGE',
                outputCategoryMask: false,
                outputConfidenceMasks: true,
            })
        );

        faceDetectorPromise = visionPromise.then((vision) =>
            FaceDetector.createFromOptions(vision, {
                baseOptions: {
                    modelAssetPath: '/vendor/mediapipe/blaze_face_short_range.tflite',
                },
                runningMode: 'IMAGE',
                minDetectionConfidence: 0.35,
            })
        );
    }

    const [segmenter, faceDetector] = await Promise.all([segmenterPromise, faceDetectorPromise]);
    return { segmenter, faceDetector };
}

function smoothstep(edge0, edge1, x) {
    const t = Math.max(0, Math.min(1, (x - edge0) / (edge1 - edge0)));
    return t * t * (3 - 2 * t);
}

/**
 * Muat File/Blob/URL ke canvas kerja (maks 960px agar cepat & tajam) + analisis AI 1x saja.
 */
export async function analisisFotoPengurus(source) {
    const bitmap = await loadBitmap(source);
    const maxDim = 960;
    const ratio = Math.min(1, maxDim / Math.max(bitmap.width, bitmap.height));
    const w = Math.max(1, Math.round(bitmap.width * ratio));
    const h = Math.max(1, Math.round(bitmap.height * ratio));

    const srcCanvas = document.createElement('canvas');
    srcCanvas.width = w;
    srcCanvas.height = h;
    const srcCtx = srcCanvas.getContext('2d', { willReadFrequently: true });
    srcCtx.drawImage(bitmap, 0, 0, w, h);
    if (typeof bitmap.close === 'function') bitmap.close();

    const { segmenter, faceDetector } = await getModels();

    // 1. Deteksi wajah (posisi mata, dahi, dagu)
    let face = null;
    try {
        const det = faceDetector.detect(srcCanvas);
        if (det?.detections?.length) {
            const terbesar = [...det.detections].sort(
                (a, b) => (b.boundingBox.width * b.boundingBox.height) - (a.boundingBox.width * a.boundingBox.height)
            )[0];
            face = terbesar.boundingBox;
        }
    } catch (_) {
        face = null;
    }

    // 2. Segmentasi orang vs latar belakang (AI confidence mask)
    const segResult = segmenter.segment(srcCanvas);
    const confMask = segResult.confidenceMasks?.[0];
    const maskW = confMask.width;
    const maskH = confMask.height;
    const raw = new Float32Array(confMask.getAsFloat32Array());
    segResult.close();

    // Cek arah mask (pastikan bagian tengah bernilai lebih tinggi dari 4 sudut; bila terbalik, invert)
    let edgeSum = 0, edgeCnt = 0, centerSum = 0, centerCnt = 0;
    for (let y = 0; y < maskH; y++) {
        for (let x = 0; x < maskW; x++) {
            const v = raw[y * maskW + x];
            const isEdge = x < maskW * 0.08 || x > maskW * 0.92 || y < maskH * 0.08;
            const isCenter = x > maskW * 0.35 && x < maskW * 0.65 && y > maskH * 0.30 && y < maskH * 0.80;
            if (isEdge) { edgeSum += v; edgeCnt++; }
            if (isCenter) { centerSum += v; centerCnt++; }
        }
    }
    const invert = (centerCnt > 0 && edgeCnt > 0) && (centerSum / centerCnt) < (edgeSum / edgeCnt);

    // Buat canvas cutout transparan seukuran srcCanvas
    const cutoutCanvas = document.createElement('canvas');
    cutoutCanvas.width = w;
    cutoutCanvas.height = h;
    const cutCtx = cutoutCanvas.getContext('2d', { willReadFrequently: true });
    cutCtx.drawImage(srcCanvas, 0, 0);
    const imgData = cutCtx.getImageData(0, 0, w, h);
    const px = imgData.data;

    // Hitung alpha halus per piksel + bounding box orang
    const alphaBuf = new Uint8ClampedArray(w * h);
    let minX = w, maxX = 0, minY = h, maxY = 0, fgCount = 0;

    for (let y = 0; y < h; y++) {
        const my = Math.min(maskH - 1, Math.floor((y * maskH) / h));
        for (let x = 0; x < w; x++) {
            const mx = Math.min(maskW - 1, Math.floor((x * maskW) / w));
            let c = raw[my * maskW + mx];
            if (invert) c = 1 - c;
            const a = Math.round(smoothstep(0.28, 0.72, c) * 255);
            const idx = y * w + x;
            alphaBuf[idx] = a;
            if (a >= 115) {
                fgCount++;
                if (x < minX) minX = x;
                if (x > maxX) maxX = x;
                if (y < minY) minY = y;
                if (y > maxY) maxY = y;
            }
        }
    }

    // Feathering halus 3x3 hanya pada tepi transisi agar rambut & bahu mulus
    for (let y = 1; y < h - 1; y++) {
        for (let x = 1; x < w - 1; x++) {
            const idx = y * w + x;
            const a = alphaBuf[idx];
            if (a > 0 && a < 250) {
                const avg = (
                    alphaBuf[idx - w - 1] + alphaBuf[idx - w] + alphaBuf[idx - w + 1] +
                    alphaBuf[idx - 1] + a * 2 + alphaBuf[idx + 1] +
                    alphaBuf[idx + w - 1] + alphaBuf[idx + w] + alphaBuf[idx + w + 1]
                ) / 10;
                px[idx * 4 + 3] = Math.round(avg);
            } else {
                px[idx * 4 + 3] = a;
            }
        }
    }
    // Bersihkan pinggir 1px
    for (let x = 0; x < w; x++) {
        px[x * 4 + 3] = alphaBuf[x];
        px[((h - 1) * w + x) * 4 + 3] = alphaBuf[(h - 1) * w + x];
    }
    for (let y = 0; y < h; y++) {
        px[(y * w) * 4 + 3] = alphaBuf[y * w];
        px[(y * w + w - 1) * 4 + 3] = alphaBuf[y * w + w - 1];
    }

    cutCtx.putImageData(imgData, 0, 0);

    // Hitung pusat bahu & puncak kepala akurat dari mask
    const maskBox = fgCount > (w * h * 0.02)
        ? { minX, maxX, minY, maxY, width: Math.max(1, maxX - minX), height: Math.max(1, maxY - minY) }
        : { minX: 0, maxX: w - 1, minY: 0, maxY: h - 1, width: w, height: h };

    // Centroid X bagian bahu/dada atas agar badan & kepala seimbang di tengah lingkaran
    let torsoXSum = 0, torsoCnt = 0;
    const torsoStartY = face ? Math.min(h - 1, Math.round(face.originY + face.height)) : Math.round(maskBox.minY + maskBox.height * 0.35);
    const torsoEndY = face ? Math.min(h - 1, Math.round(face.originY + face.height * 2.1)) : Math.round(maskBox.minY + maskBox.height * 0.85);
    for (let y = Math.max(0, torsoStartY); y <= torsoEndY; y += 2) {
        for (let x = maskBox.minX; x <= maskBox.maxX; x += 2) {
            if (alphaBuf[y * w + x] >= 120) {
                torsoXSum += x;
                torsoCnt++;
            }
        }
    }
    const torsoCX = torsoCnt > 10 ? torsoXSum / torsoCnt : (maskBox.minX + maskBox.maxX) / 2;

    return { srcCanvas, cutoutCanvas, w, h, face, maskBox, torsoCX };
}

/**
 * Render hasil framing otomatis (wajah proporsional + bahu/badan sejajar bawah lingkaran) ke PNG 600x600.
 */
export function renderPotretPengurus(analisis, opsi = {}) {
    const {
        hapusBg = true,
        zoom = 1.0,
        geserX = 0,
        geserY = 0,
        ukuran = 600,
    } = opsi;

    const { srcCanvas, cutoutCanvas, w, h, face, maskBox, torsoCX } = analisis;
    const source = hapusBg ? cutoutCanvas : srcCanvas;

    let scale, anchorX, topY;

    if (face) {
        const faceCX = face.originX + face.width / 2;
        const faceH = Math.max(20, face.height);
        // Puncak rambut: ambil nilai paling atas antara mask atas & estimasi dahi-rambut
        const estHairTop = Math.max(0, face.originY - faceH * 0.44);
        const hairTop = Math.max(0, Math.min(maskBox.minY, estHairTop));
        const chinY = face.originY + faceH;
        const headHeight = Math.max(faceH * 1.15, chinY - hairTop);

        // Standar potret struktur organisasi: kepala (puncak rambut -> dagu) ~ 42% tinggi lingkaran
        const targetHeadPx = ukuran * 0.42;
        scale = targetHeadPx / headHeight;

        // Batasi agar lebar bahu tidak terlalu sempit/terlalu besar
        const personSpanY = Math.max(headHeight, maskBox.maxY - hairTop);
        const targetTopPx = ukuran * 0.085; // ~51px ruang atas agar rambut tidak pernah terpotong
        const projectedBottom = targetTopPx + personSpanY * scale;

        // Jika foto aslinya selfie dekat (badan/dada pendek), sesuaikan skala & posisi agar bagian bawah baju
        // tetap menempel rapi di dasar lingkaran (tidak melayang) dan kepala tetap utuh
        if (projectedBottom < ukuran) {
            const neededScale = (ukuran - ukuran * 0.05) / personSpanY;
            scale = Math.min(neededScale, scale * 1.38);
            const finalBottom = targetTopPx + personSpanY * scale;
            topY = finalBottom < ukuran ? ukuran - personSpanY * scale : targetTopPx;
            topY = Math.max(ukuran * 0.03, topY);
        } else {
            topY = targetTopPx;
        }

        // Pusatkan horizontal: dominan ke tengah wajah (78%) + seimbang dengan bahu (22%)
        anchorX = faceCX * 0.78 + torsoCX * 0.22;

        // Terapkan penyesuaian manual opsional (zoom / geser)
        const finalScale = scale * zoom;
        const drawW = w * finalScale;
        const drawH = h * finalScale;
        // Titik pusat vertikal wajah tetap stabil saat zoom
        const headCenterSrcY = hairTop + headHeight * 0.55;
        const headCenterDstY = topY + headHeight * 0.55 * scale;
        let dx = ukuran / 2 - anchorX * finalScale + geserX;
        let dy = headCenterDstY - headCenterSrcY * finalScale + geserY;

        // Jaga agar dasar orang tidak terangkat saat zoom standar
        if (zoom === 1 && geserY === 0) {
            const bottomPx = dy + maskBox.maxY * finalScale;
            if (bottomPx < ukuran) {
                dy += (ukuran - bottomPx);
            }
        }

        return gambarKeCanvas(source, ukuran, dx, dy, drawW, drawH);
    }

    // Fallback bila wajah tidak terdeteksi (pakai bounding box orang)
    const spanY = Math.max(1, maskBox.height);
    const spanX = Math.max(1, maskBox.width);
    const baseScale = Math.max((ukuran * 0.92) / spanY, (ukuran * 0.75) / spanX);
    const finalScale = baseScale * zoom;
    const drawW = w * finalScale;
    const drawH = h * finalScale;
    const dx = ukuran / 2 - torsoCX * finalScale + geserX;
    const dy = ukuran * 0.08 - maskBox.minY * finalScale + geserY;

    return gambarKeCanvas(source, ukuran, dx, dy, drawW, drawH);
}

function gambarKeCanvas(source, ukuran, dx, dy, drawW, drawH) {
    const out = document.createElement('canvas');
    out.width = ukuran;
    out.height = ukuran;
    const ctx = out.getContext('2d');
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.clearRect(0, 0, ukuran, ukuran);
    ctx.drawImage(source, dx, dy, drawW, drawH);
    return out;
}

async function loadBitmap(source) {
    if (typeof source === 'string') {
        const res = await fetch(source, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('Gagal memuat gambar asal.');
        const blob = await res.blob();
        return createImageBitmap(blob, { imageOrientation: 'from-image' });
    }
    return createImageBitmap(source, { imageOrientation: 'from-image' });
}

export function canvasKeBlob(canvas, mime = 'image/png', quality = 0.92) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Gagal membuat gambar PNG.'))), mime, quality);
    });
}
