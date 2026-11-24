@php
    $statePath = $getStatePath();
    $uploadUrl = route('admin.pengurus.proses-foto');
    $storageBase = asset('storage');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            path: $wire.entangle('{{ $statePath }}'),
            proses: false,
            error: '',
            info: '',
            tampilAtur: false,
            hapusBg: true,
            zoom: 1.0,
            geserX: 0,
            geserY: 0,
            analisis: null,
            previewLokal: null,
            adaPerubahanManual: false,
            get urlTampil() {
                if (this.previewLokal) return this.previewLokal;
                if (!this.path) return null;
                return '{{ $storageBase }}/' + String(this.path).replace(/^\/+/, '');
            },
            async prosesFile(source) {
                this.proses = true;
                this.error = '';
                this.info = 'AI sedang menghapus background & menyesuaikan wajah + bahu...';
                try {
                    const mod = await import('/vendor/mediapipe/pengurus-foto-ai.js?v=2');
                    this.analisis = await mod.analisisFotoPengurus(source);
                    this.zoom = 1.0;
                    this.geserX = 0;
                    this.geserY = 0;
                    this.hapusBg = true;
                    this.adaPerubahanManual = false;
                    await this.renderDanSimpan(mod, true);
                } catch (err) {
                    console.error(err);
                    this.error = 'Gagal memproses foto otomatis: ' + (err?.message || err);
                    this.info = '';
                } finally {
                    this.proses = false;
                }
            },
            async pilihDariInput(e) {
                const f = e.target.files?.[0];
                if (!f) return;
                e.target.value = '';
                await this.prosesFile(f);
            },
            async prosesUlangFotoLama() {
                if (!this.urlTampil) return;
                await this.prosesFile(this.urlTampil);
            },
            async updatePreviewManual() {
                if (!this.analisis) return;
                const mod = await import('/vendor/mediapipe/pengurus-foto-ai.js?v=2');
                const canvas = mod.renderPotretPengurus(this.analisis, {
                    hapusBg: this.hapusBg,
                    zoom: Number(this.zoom),
                    geserX: Number(this.geserX),
                    geserY: Number(this.geserY),
                });
                if (this.previewLokal) URL.revokeObjectURL(this.previewLokal);
                const blob = await mod.canvasKeBlob(canvas, 'image/png');
                this.previewLokal = URL.createObjectURL(blob);
                this.adaPerubahanManual = true;
            },
            async terapkanPenyesuaian() {
                if (!this.analisis) return;
                this.proses = true;
                this.error = '';
                this.info = 'Menyimpan hasil penyesuaian...';
                try {
                    const mod = await import('/vendor/mediapipe/pengurus-foto-ai.js?v=2');
                    await this.renderDanSimpan(mod, false);
                    this.adaPerubahanManual = false;
                } catch (err) {
                    this.error = 'Gagal menyimpan hasil: ' + (err?.message || err);
                    this.info = '';
                } finally {
                    this.proses = false;
                }
            },
            async renderDanSimpan(mod, otomatis) {
                const canvas = mod.renderPotretPengurus(this.analisis, {
                    hapusBg: this.hapusBg,
                    zoom: Number(this.zoom),
                    geserX: Number(this.geserX),
                    geserY: Number(this.geserY),
                });
                const blob = await mod.canvasKeBlob(canvas, 'image/png');
                if (this.previewLokal) URL.revokeObjectURL(this.previewLokal);
                this.previewLokal = URL.createObjectURL(blob);

                const fd = new FormData();
                fd.append('foto', blob, 'pengurus-auto.png');
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}';
                const res = await fetch('{{ $uploadUrl }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: fd,
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json?.message || 'Upload gagal (HTTP ' + res.status + ')');

                this.path = json.path;
                const transPath = '{{ $statePath }}'.replace(/\.foto$/, '.foto_transparan');
                await $wire.set('{{ $statePath }}', json.path);
                await $wire.set(transPath, this.hapusBg);
                this.info = otomatis
                    ? (this.analisis.face
                        ? 'Berhasil! Background dihapus & wajah + bahu disesuaikan otomatis.'
                        : 'Berhasil! Background dihapus & potret disesuaikan otomatis.')
                    : 'Penyesuaian berhasil diterapkan. Klik Simpan untuk menyimpan perubahan.';
            },
            async hapusFoto() {
                this.path = null;
                this.analisis = null;
                if (this.previewLokal) { URL.revokeObjectURL(this.previewLokal); this.previewLokal = null; }
                const transPath = '{{ $statePath }}'.replace(/\.foto$/, '.foto_transparan');
                await $wire.set('{{ $statePath }}', null);
                await $wire.set(transPath, false);
                this.info = 'Foto dihapus. Klik Simpan untuk menerapkan.';
                this.error = '';
            }
        }"
        class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/5"
    >
        <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-start">
            {{-- Pratinjau Langsung Lingkaran Emas + Latar Pangkat --}}
            <div class="flex flex-col items-center">
                <div
                    class="relative flex h-32 w-32 items-center justify-center rounded-full p-[5px] shadow-lg"
                    style="background: linear-gradient(145deg, #f9e699 0%, #c69224 32%, #f4d672 58%, #9a6c12 85%, #e8c760 100%);"
                >
                    <div class="h-full w-full rounded-full p-[3px]" style="background: #064428;">
                        <div
                            class="relative flex h-full w-full items-end justify-center overflow-hidden rounded-full"
                            style="background: radial-gradient(circle at 50% 28%, #16ad62 0%, #078B4B 52%, #053d24 100%);"
                        >
                            <template x-if="urlTampil">
                                <img :src="urlTampil" alt="Pratinjau foto pengurus" class="h-full w-full object-contain object-bottom" />
                            </template>
                            <template x-if="!urlTampil">
                                <svg viewBox="0 0 120 120" class="h-[85%] w-[85%] opacity-90" aria-hidden="true">
                                    <circle cx="60" cy="40" r="20" fill="#f0d37c"/>
                                    <path d="M22 115 C 24 80, 38 68, 60 68 C 82 68, 96 80, 98 115 Z" fill="#d9b24c"/>
                                </svg>
                            </template>

                            {{-- Overlay loading saat AI bekerja --}}
                            <div x-show="proses" x-cloak class="absolute inset-0 flex items-center justify-center rounded-full bg-slate-950/65 text-center text-[11px] font-bold text-amber-300 backdrop-blur-[1px]">
                                Memproses AI...
                            </div>
                        </div>
                    </div>
                </div>
                <span class="mt-2 text-[11px] font-medium text-gray-500 dark:text-gray-400">Pratinjau bingkai emas</span>
            </div>

            {{-- Kontrol Unggah Otomatis --}}
            <div class="flex-1 space-y-3 text-xs">
                <div>
                    <div class="font-semibold text-gray-800 dark:text-gray-100">
                        Unggah Foto (Hapus Background &amp; Atur Wajah/Bahu Otomatis)
                    </div>
                    <p class="mt-0.5 text-gray-500 dark:text-gray-400">
                        Pilih foto bebas (selfie/kamera biasa). Sistem otomatis menghapus background dan menyesuaikan posisi wajah serta bahu agar menyatu dengan lingkaran emas.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <label
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                        :class="proses ? 'pointer-events-none opacity-60' : ''"
                    >
                        <span>Pilih Foto</span>
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="pilihDariInput($event)" :disabled="proses" />
                    </label>

                    <button
                        type="button"
                        x-show="urlTampil && !analisis"
                        @click="prosesUlangFotoLama()"
                        :disabled="proses"
                        class="rounded-lg border border-amber-500/60 bg-amber-50 px-3 py-2 font-semibold text-amber-800 hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-950/40 dark:text-amber-300"
                    >
                        Hapus Background &amp; Rapikan Foto Ini
                    </button>

                    <button
                        type="button"
                        x-show="analisis"
                        @click="tampilAtur = !tampilAtur"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 font-semibold text-gray-700 hover:bg-gray-100 dark:border-white/15 dark:bg-white/10 dark:text-gray-200"
                        x-text="tampilAtur ? 'Tutup Penyesuaian' : 'Sesuaikan Posisi / Zoom'"
                    ></button>

                    <button
                        type="button"
                        x-show="urlTampil"
                        @click="hapusFoto()"
                        :disabled="proses"
                        class="rounded-lg border border-red-300 px-3 py-2 font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50 dark:border-red-500/40 dark:text-red-400"
                    >
                        Hapus Foto
                    </button>
                </div>

                <p x-show="info" x-text="info" class="font-medium text-emerald-700 dark:text-emerald-400"></p>
                <p x-show="error" x-text="error" class="font-medium text-red-600 dark:text-red-400"></p>

                {{-- Panel Fine-Tune Opsional --}}
                <div x-show="tampilAtur && analisis" x-cloak class="space-y-2.5 rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-slate-900/60">
                    <label class="flex items-center gap-2 font-medium text-gray-700 dark:text-gray-200">
                        <input type="checkbox" x-model="hapusBg" @change="updatePreviewManual()" class="rounded border-gray-300 text-emerald-600" />
                        <span>Hapus background otomatis (AI)</span>
                    </label>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label class="block">
                            <span class="text-[11px] text-gray-600 dark:text-gray-300">Zoom (<span x-text="Math.round(zoom * 100)"></span>%)</span>
                            <input type="range" min="0.7" max="1.5" step="0.02" x-model="zoom" @input="updatePreviewManual()" class="w-full" />
                        </label>
                        <label class="block">
                            <span class="text-[11px] text-gray-600 dark:text-gray-300">Geser Kiri/Kanan (<span x-text="geserX"></span>)</span>
                            <input type="range" min="-120" max="120" step="2" x-model="geserX" @input="updatePreviewManual()" class="w-full" />
                        </label>
                        <label class="block">
                            <span class="text-[11px] text-gray-600 dark:text-gray-300">Geser Atas/Bawah (<span x-text="geserY"></span>)</span>
                            <input type="range" min="-120" max="120" step="2" x-model="geserY" @input="updatePreviewManual()" class="w-full" />
                        </label>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <button
                            type="button"
                            @click="terapkanPenyesuaian()"
                            :disabled="proses || !adaPerubahanManual"
                            class="rounded-lg bg-amber-500 px-3 py-1.5 font-bold text-slate-950 hover:bg-amber-400 disabled:opacity-50"
                        >
                            Terapkan Penyesuaian
                        </button>
                        <button
                            type="button"
                            @click="zoom = 1.0; geserX = 0; geserY = 0; hapusBg = true; updatePreviewManual()"
                            class="rounded-lg border border-gray-300 px-2.5 py-1.5 font-medium text-gray-600 hover:bg-gray-100 dark:border-white/15 dark:text-gray-300"
                        >
                            Reset Otomatis
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
