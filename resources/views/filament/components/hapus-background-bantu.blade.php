<div
    x-data="{
        terbuka: false,
        proses: false,
        status: '',
        toleransi: 36,
        fileAsli: null,
        urlHasil: null,
        namaHasil: 'pengurus-transparan.png',
        async pilihFile(e) {
            const f = e.target.files?.[0];
            if (!f) return;
            this.fileAsli = f;
            this.namaHasil = f.name.replace(/\.[^.]+$/, '') + '-nobg.png';
            this.status = 'Foto siap diproses (' + Math.round(f.size / 1024) + ' KB). Pilih metode hapus background di bawah.';
            await this.hapusCepatCanvas();
        },
        async hapusCepatCanvas() {
            if (!this.fileAsli) return;
            this.proses = true;
            this.status = 'Menghapus background polos (offline)...';
            try {
                const img = await createImageBitmap(this.fileAsli);
                const maxSide = 800;
                const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
                const w = Math.max(1, Math.round(img.width * scale));
                const h = Math.max(1, Math.round(img.height * scale));
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(img, 0, 0, w, h);
                const imgData = ctx.getImageData(0, 0, w, h);
                const d = imgData.data;

                // Ambil rata-rata warna dari 4 sudut sebagai warna latar
                const corners = [0, (w - 1) * 4, (h - 1) * w * 4, ((h - 1) * w + (w - 1)) * 4];
                let r0 = 0, g0 = 0, b0 = 0;
                for (const idx of corners) { r0 += d[idx]; g0 += d[idx + 1]; b0 += d[idx + 2]; }
                r0 /= 4; g0 /= 4; b0 /= 4;

                // Flood-fill BFS dari tepi gambar agar bagian dalam baju/wajah aman
                const tol = Number(this.toleransi);
                const visited = new Uint8Array(w * h);
                const queue = [];
                const pushIfBg = (x, y) => {
                    const p = y * w + x;
                    if (visited[p]) return;
                    const i = p * 4;
                    const dist = Math.hypot(d[i] - r0, d[i + 1] - g0, d[i + 2] - b0);
                    if (dist <= tol) {
                        visited[p] = 1;
                        queue.push(p);
                    }
                };
                for (let x = 0; x < w; x++) { pushIfBg(x, 0); pushIfBg(x, h - 1); }
                for (let y = 0; y < h; y++) { pushIfBg(0, y); pushIfBg(w - 1, y); }

                let head = 0;
                while (head < queue.length) {
                    const p = queue[head++];
                    d[p * 4 + 3] = 0;
                    const x = p % w, y = (p - x) / w;
                    if (x > 0) pushIfBg(x - 1, y);
                    if (x + 1 < w) pushIfBg(x + 1, y);
                    if (y > 0) pushIfBg(x, y - 1);
                    if (y + 1 < h) pushIfBg(x, y + 1);
                }

                // Feather halus 1px pada tepi transparan
                for (let y = 1; y < h - 1; y++) {
                    for (let x = 1; x < w - 1; x++) {
                        const p = y * w + x;
                        if (d[p * 4 + 3] > 0) {
                            const n0 = d[(p - 1) * 4 + 3] === 0 || d[(p + 1) * 4 + 3] === 0 || d[(p - w) * 4 + 3] === 0 || d[(p + w) * 4 + 3] === 0;
                            if (n0) d[p * 4 + 3] = 175;
                        }
                    }
                }

                ctx.putImageData(imgData, 0, 0);
                if (this.urlHasil) URL.revokeObjectURL(this.urlHasil);
                const blob = await new Promise((res) => canvas.toBlob(res, 'image/png'));
                this.urlHasil = URL.createObjectURL(blob);
                this.status = 'Selesai! Geser toleransi bila perlu, lalu unduh PNG & unggah pada kolom Foto di atas.';
            } catch (err) {
                this.status = 'Gagal memproses foto: ' + (err?.message || err);
            } finally {
                this.proses = false;
            }
        },
        async hapusAiBrowser() {
            if (!this.fileAsli) return;
            this.proses = true;
            this.status = 'Memuat model AI hapus background (butuh internet saat pertama kali)...';
            try {
                const mod = await import('https://esm.sh/@imgly/background-removal@1.5.5');
                const blob = await mod.removeBackground(this.fileAsli);
                if (this.urlHasil) URL.revokeObjectURL(this.urlHasil);
                this.urlHasil = URL.createObjectURL(blob);
                this.status = 'Selesai (AI)! Unduh PNG transparan lalu unggah ke kolom Foto di atas.';
            } catch (err) {
                this.status = 'Mode AI tidak tersedia (cek koneksi internet). Gunakan mode Hapus Cepat Offline atau unggah PNG transparan.';
            } finally {
                this.proses = false;
            }
        }
    }"
    class="rounded-xl border border-amber-400/40 bg-amber-50/40 p-3 text-xs dark:border-amber-500/30 dark:bg-amber-950/20"
>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="font-semibold text-gray-800 dark:text-gray-200">
            Alat Bantu Hapus Background Foto (Opsional)
        </span>
        <button
            type="button"
            @click="terbuka = !terbuka"
            class="rounded-lg border border-amber-500/50 px-2.5 py-1 font-semibold text-amber-800 hover:bg-amber-100 dark:text-amber-300 dark:hover:bg-amber-900/40"
            x-text="terbuka ? 'Tutup Alat Bantu' : 'Buka Alat Hapus Background'"
        ></button>
    </div>

    <div x-show="terbuka" x-cloak class="mt-3 space-y-3 border-t border-amber-400/30 pt-3">
        <p class="text-gray-600 dark:text-gray-400">
            Pilih foto berlatar polos (merah/biru/putih) untuk menghapus background secara cepat di browser (offline), atau gunakan mode AI untuk latar bebas. Setelah itu unduh PNG dan unggah pada kolom <strong>Foto</strong> di atas.
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <input type="file" accept="image/png,image/jpeg,image/webp" @change="pilihFile($event)" class="text-xs" />
            <button
                type="button"
                @click="hapusCepatCanvas()"
                :disabled="!fileAsli || proses"
                class="rounded-lg bg-emerald-600 px-3 py-1.5 font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
            >Hapus Cepat (Offline)</button>
            <button
                type="button"
                @click="hapusAiBrowser()"
                :disabled="!fileAsli || proses"
                class="rounded-lg bg-slate-800 px-3 py-1.5 font-semibold text-white hover:bg-slate-900 disabled:opacity-50"
            >Hapus AI (Online)</button>
        </div>

        <div x-show="fileAsli" class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                <span>Toleransi warna latar:</span>
                <input type="range" min="10" max="110" x-model="toleransi" @change="hapusCepatCanvas()" />
                <span class="font-mono font-bold" x-text="toleransi"></span>
            </label>
        </div>

        <p x-show="status" x-text="status" class="font-medium text-emerald-700 dark:text-emerald-400"></p>

        <div x-show="urlHasil" class="flex flex-wrap items-center gap-4 pt-1">
            <div class="flex h-24 w-24 items-end justify-center overflow-hidden rounded-full border-4 border-amber-400 shadow"
                 style="background: radial-gradient(circle at 50% 30%, #3b82d6 0%, #1c53a8 58%, #0e2a5c 100%)">
                <img :src="urlHasil" alt="Pratinjau PNG transparan" class="h-[96%] w-auto object-contain" />
            </div>
            <div class="space-y-1.5">
                <a
                    :href="urlHasil"
                    :download="namaHasil"
                    class="inline-flex items-center rounded-lg bg-amber-500 px-3 py-1.5 font-bold text-slate-950 hover:bg-amber-400"
                >Unduh PNG Transparan</a>
                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                    Unggah file PNG hasil unduhan ke kolom Foto di atas & aktifkan toggle <em>Mode foto PNG transparan</em>.
                </p>
            </div>
        </div>
    </div>
</div>
