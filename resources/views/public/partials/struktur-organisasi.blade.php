@php
    /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, \App\Models\Pengurus>> $strukturPengurus */
    $l1 = $strukturPengurus->get(1, collect())->values();
    $l2 = $strukturPengurus->get(2, collect())->values();
    $l3 = $strukturPengurus->get(3, collect())->values();
    $l4 = $strukturPengurus->get(4, collect())->values();
    $adaData = $l1->isNotEmpty() || $l2->isNotEmpty() || $l3->isNotEmpty() || $l4->isNotEmpty();
    $namaKoperasi = strtoupper((string) ($pengaturan->get('koperasi.nama') ?: 'KOPERASI KSU ASSEA LORGAN MANDIRI'));
    $judulStruktur = str_contains($namaKoperasi, 'ASSEA') ? 'KOPERASI KSU ASSEA LORGAN MANDIRI' : $namaKoperasi;
@endphp

@if($adaData)
<section class="relative py-2" data-reveal>
    {{-- Header Bersih Menyatu dengan Landing Page (Tanpa Bingkai/Latar Poster) --}}
    <div class="mb-10 text-center">
        <p class="eyebrow">Kepengurusan</p>
        <h2 class="mt-1 text-2xl font-black uppercase tracking-tight text-brand-dark sm:text-3xl lg:text-[2.1rem] lg:leading-[1.15]">
            STRUKTUR ORGANISASI
            <span class="block text-brand">{{ $judulStruktur }}</span>
        </h2>
        <div class="mx-auto mt-3 h-1 w-20 rounded-full bg-gold"></div>
    </div>

    {{-- ================== TAMPILAN DESKTOP: BAGAN HIERARKI 4 LEVEL + GARIS PANAH SVG ================== --}}
    <div class="so-tree relative hidden lg:block" data-org-chart>
        {{-- Layer SVG Garis Penghubung & Panah (dihitung presisi oleh JS ResizeObserver) --}}
        <svg class="pointer-events-none absolute inset-0 z-0 h-full w-full overflow-visible" data-org-svg aria-hidden="true">
            <defs>
                <marker id="so-arrow-head" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6.5" markerHeight="6.5" orient="auto-start-reverse">
                    <path d="M 1 1.5 L 8.5 5 L 1 8.5 z" fill="#075B37"/>
                </marker>
            </defs>
            <g data-org-lines></g>
        </svg>

        <div class="relative z-10 flex flex-col">
            {{-- LEVEL 1 + LEVEL 2 FLANKING --}}
            @if($l1->isNotEmpty() || $l2->isNotEmpty())
                <div class="relative">
                    @if($l1->isNotEmpty())
                        <div class="flex justify-center gap-8" data-org-level="1">
                            @foreach($l1 as $p)
                                @include('public.partials.kartu-pengurus', ['p' => $p, 'lvl' => 1, 'idx' => $loop->index])
                            @endforeach
                        </div>
                    @endif

                    @if($l2->isNotEmpty())
                        @if($l1->count() === 1 && $l2->count() === 2)
                            <div class="mx-auto -mt-20 flex max-w-4xl items-start justify-between px-12 pb-6" data-org-level="2" data-org-flank="true">
                                @foreach($l2 as $p)
                                    @include('public.partials.kartu-pengurus', ['p' => $p, 'lvl' => 2, 'idx' => $loop->index])
                                @endforeach
                            </div>
                        @else
                            <div class="mt-14 flex flex-wrap justify-center gap-8 pb-4" data-org-level="2" data-org-flank="false">
                                @foreach($l2 as $p)
                                    @include('public.partials.kartu-pengurus', ['p' => $p, 'lvl' => 2, 'idx' => $loop->index])
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            @endif

            {{-- LEVEL 3 (Manajer) --}}
            @if($l3->isNotEmpty())
                <div class="mt-12 grid gap-6 px-4" style="grid-template-columns: repeat({{ min(4, max(1, $l3->count())) }}, minmax(0, 1fr));" data-org-level="3">
                    @foreach($l3 as $p)
                        <div class="flex justify-center">
                            @include('public.partials.kartu-pengurus', ['p' => $p, 'lvl' => 3, 'idx' => $loop->index])
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- LEVEL 4 (Seksi / Pelaksana) --}}
            @if($l4->isNotEmpty())
                <div class="mt-14 grid gap-4" style="grid-template-columns: repeat({{ min(6, max(1, $l4->count())) }}, minmax(0, 1fr));" data-org-level="4">
                    @foreach($l4 as $p)
                        <div class="flex justify-center">
                            @include('public.partials.kartu-pengurus', ['p' => $p, 'lvl' => 4, 'idx' => $loop->index])
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ================== TAMPILAN MOBILE & TABLET: KELOMPOK KARTU RAPI PER LEVEL ================== --}}
    <div class="space-y-8 lg:hidden">
        @foreach([1 => $l1, 2 => $l2, 3 => $l3, 4 => $l4] as $lvlNum => $kelompok)
            @if($kelompok->isNotEmpty())
                <div>
                    <div class="mb-4 flex items-center justify-center gap-3">
                        <span class="h-px flex-1 bg-gradient-to-r from-transparent to-[#d4af37]/70"></span>
                        <span class="rounded-full border border-[#e5b51b]/80 bg-brand-dark px-3.5 py-1 text-[11px] font-bold uppercase tracking-widest text-[#f5cf5b]">
                            {{ \App\Models\Pengurus::LEVEL_RINGKAS[$lvlNum] }}
                        </span>
                        <span class="h-px flex-1 bg-gradient-to-l from-transparent to-[#d4af37]/70"></span>
                    </div>
                    <div class="grid gap-6 {{ $kelompok->count() === 1 ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2' }} justify-items-center">
                        @foreach($kelompok as $p)
                            @include('public.partials.kartu-pengurus', ['p' => $p, 'lvl' => $lvlNum, 'idx' => $loop->index, 'mobile' => true])
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</section>
@endif
