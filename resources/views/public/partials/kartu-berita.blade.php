<article class="overflow-hidden rounded-2xl border border-brand/10 bg-white shadow-sm transition hover:shadow-md" data-reveal>
    <a href="{{ route('berita.show', $b->slug) }}" class="block">
        @if($b->gambar)
            <img src="{{ asset('storage/' . $b->gambar) }}" alt="{{ $b->judul }}" loading="lazy" class="aspect-[16/9] w-full object-cover">
        @else
            <div class="flex aspect-[16/9] w-full items-center justify-center bg-gradient-to-br from-brand to-brand-dark"><i data-lucide="anchor" class="h-10 w-10 text-white/70"></i></div>
        @endif
        <div class="p-5">
            <div class="flex items-center gap-2 text-xs font-semibold text-ink/60">
                @if($b->kategori)<span class="rounded-full bg-brand/10 px-2 py-0.5 text-brand-dark">{{ $b->kategori->nama }}</span>@endif
                <time datetime="{{ $b->published_at?->toDateString() }}">{{ $b->published_at?->translatedFormat('d F Y') }}</time>
            </div>
            <h3 class="mt-3 text-lg font-bold leading-snug text-brand-dark">{{ $b->judul }}</h3>
            @if($b->ringkasan)<p class="mt-2 line-clamp-3 text-sm text-ink/75">{{ $b->ringkasan }}</p>@endif
        </div>
    </a>
</article>
