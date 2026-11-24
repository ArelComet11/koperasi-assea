@php
    $isMobile = ! empty($mobile);
    $ringSize = match ($lvl) {
        1 => 'h-36 w-36 sm:h-40 sm:w-40',
        2 => 'h-32 w-32',
        3 => 'h-[7.5rem] w-[7.5rem]',
        default => 'h-28 w-28',
    };
    $pillWidth = match ($lvl) {
        1 => 'min-w-[230px] max-w-[260px]',
        2 => 'min-w-[215px] max-w-[245px]',
        3 => 'min-w-[205px] max-w-[235px]',
        default => 'min-w-[175px] max-w-[205px]',
    };
    $fotoUrl = $p->fotoUrl();
    $transparan = $p->isFotoTransparan();
@endphp

<article
    class="so-node group relative flex flex-col items-center text-center"
    @if(! $isMobile)
        data-org-node
        data-node-level="{{ $lvl }}"
        data-node-index="{{ $idx }}"
    @endif
>
    {{-- Lingkaran Foto dengan Bingkai Ganda Emas + Navy --}}
    <div class="so-ring-outer relative {{ $ringSize }} flex items-center justify-center rounded-full p-[5px] transition duration-300 group-hover:scale-[1.03]" data-org-ring>
        {{-- Cincin Navy + Emas Dalam --}}
        <div class="so-ring-inner relative h-full w-full rounded-full p-[3.5px]">
            {{-- Latar Belakang Pangkat Bergradien --}}
            <div
                class="relative h-full w-full rounded-full {{ $transparan ? 'overflow-visible' : 'overflow-hidden' }}"
                style="background: {{ $p->gradasiLatar() }};"
            >
                {{-- Kilau Halus Di Belakang Subjek --}}
                <div class="pointer-events-none absolute inset-0 rounded-full bg-[radial-gradient(circle_at_50%_22%,rgba(255,255,255,0.22),transparent_62%)]"></div>

                @if($fotoUrl)
                    @if($transparan)
                        {{-- Potret PNG transparan hasil framing otomatis (wajah proporsional & bahu menyatu di lingkaran) --}}
                        <div class="absolute inset-x-0 bottom-0 flex h-[106%] items-end justify-center overflow-hidden rounded-b-full">
                            <img
                                src="{{ $fotoUrl }}"
                                alt="{{ $p->nama }}"
                                loading="lazy"
                                class="h-full w-full object-contain object-bottom drop-shadow-[0_6px_10px_rgba(0,0,0,0.32)]"
                            >
                        </div>
                    @else
                        <img
                            src="{{ $fotoUrl }}"
                            alt="{{ $p->nama }}"
                            loading="lazy"
                            class="h-full w-full rounded-full object-cover object-top"
                        >
                    @endif
                @else
                    {{-- Placeholder Formal Elegan Saat Foto Belum Diunggah --}}
                    <div class="relative flex h-full w-full items-end justify-center overflow-hidden rounded-full">
                        <svg viewBox="0 0 120 120" class="h-[88%] w-[88%]" aria-hidden="true">
                            <defs>
                                <linearGradient id="soSil{{ $p->id }}" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#f6e09c" stop-opacity="0.92"/>
                                    <stop offset="100%" stop-color="#c9972b" stop-opacity="0.82"/>
                                </linearGradient>
                            </defs>
                            <circle cx="60" cy="40" r="20" fill="url(#soSil{{ $p->id }})"/>
                            <path d="M22 115 C 24 80, 38 68, 60 68 C 82 68, 96 80, 98 115 Z" fill="url(#soSil{{ $p->id }})"/>
                        </svg>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Papan Nama Kapsul / Pill Gelap Berbingkai Emas --}}
    <div class="so-pill relative z-20 -mt-3.5 {{ $pillWidth }} rounded-full px-3.5 py-1.5 shadow-md transition duration-300 group-hover:shadow-lg" data-org-pill>
        <div class="truncate text-[12px] font-extrabold leading-tight text-white sm:text-[12.5px]" title="{{ $p->nama }}">
            {{ $p->nama }}
        </div>
        <div class="mt-0.5 truncate text-[10.5px] font-bold leading-tight tracking-wide text-[#f5cf5b] {{ $lvl <= 3 ? 'uppercase' : '' }}" title="{{ $p->jabatan }}">
            {{ $p->jabatan }}
        </div>
    </div>
</article>
