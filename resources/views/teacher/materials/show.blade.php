<x-layouts.studyai title="{{ $material->title }}">
    <div class="flex items-center justify-between mb-4">
        <div>
            <a href="{{ route('teacher.materials.index') }}" class="text-xs text-accent">← Materials</a>
            <a href="{{ route('teacher.materials.edit', $material) }}" class="text-xs text-accent ml-3">Edit details</a>
        </div>
        <div class="flex gap-2">
            @if($material->review_status === 'pending')
                <a href="{{ route('teacher.materials.review') }}?mid={{ $material->id }}" class="btn btn-outline btn-sm">Review</a>
            @endif
            @if(!$material->published)
                <form method="POST" action="{{ route('teacher.materials.approve-all', $material) }}">@csrf
                    <button class="btn btn-primary btn-sm">Publish</button>
                </form>
            @else
                <button class="btn btn-outline btn-sm">Unpublish</button>
            @endif
        </div>
    </div>

    <h2 class="font-display text-xl text-ink mb-1">{{ $material->title }}</h2>
    <div class="text-xs text-faint mb-4">
        {{ $material->type }} · Status: {{ $material->status }} · Review: {{ $material->review_status }}
        · Class: {{ $material->classRoom?->name ?? '—' }} · Subject: {{ $material->subject?->name ?? '—' }}
    </div>

    @php($tab = request()->query('tab', 'overview'))
    @php($tabs = [
        ['overview', 'Overview', null],
        ['flashcards', 'Flashcards', $material->flashcards->count()],
        ['questions', 'Questions', $material->questions->count()],
        ['guide', 'Study Guide', $material->studyGuide ? 1 : null],
        ['images', 'Images', null],
    ])

    <div class="border-b border-line mb-5 flex gap-1 flex-wrap">
        @foreach($tabs as [$key, $label, $count])
            @php($active = $tab === $key)
            @if($active)
                <button type="button" data-tab="{{ $key }}" class="tab-btn tab-btn-active">{{ $label }}@if($count) <span class="text-xs text-faint">({{ $count }})</span>@endif</button>
            @else
                <a href="?tab={{ $key }}" data-tab="{{ $key }}" class="tab-btn">{{ $label }}@if($count) <span class="text-xs text-faint">({{ $count }})</span>@endif</a>
            @endif
        @endforeach
    </div>

    {{-- Overview --}}
    @if($tab === 'overview')
    <div data-tab-panel="overview">
        <div class="grid grid-cols-3 gap-4 mb-5">
            <div class="surface p-4 text-center">
                <div class="font-display text-2xl text-ink">{{ $material->flashcards->count() }}</div>
                <div class="text-xs text-faint">Flashcards</div>
            </div>
            <div class="surface p-4 text-center">
                <div class="font-display text-2xl text-ink">{{ $material->questions->count() }}</div>
                <div class="text-xs text-faint">Questions</div>
            </div>
            <div class="surface p-4 text-center">
                <div class="font-display text-2xl text-ink">{{ 0 }}</div>
                <div class="text-xs text-faint">Images</div>
            </div>
        </div>
        @if($material->studyGuide)
            <div class="surface p-4 mb-4">
                <div class="font-medium text-ink">Study Guide</div>
                <div class="text-sm text-muted mt-1">Generated at {{ $material->studyGuide->created_at?->format('M j, Y') }}</div>
            </div>
        @endif
        <form method="POST" action="{{ route('teacher.materials.update', $material) }}">
            @method('PUT') @csrf
            <div class="surface p-4">
                <div class="field-label">Title</div>
                <input type="text" name="title" value="{{ $material->title }}" class="input">
                <div class="field-label mt-2">Description</div>
                <textarea name="description" class="textarea" rows="3">{{ $material->description }}</textarea>
                <button type="submit" class="btn btn-primary btn-sm mt-3">Save changes</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Flashcards tab — list with edit (front/back) / delete --}}
    @if($tab === 'flashcards')
    <div data-tab-panel="flashcards">
        @if($material->flashcards->isEmpty())
            <div class="empty">No flashcards generated yet.<br>Upload a file with text content to generate them.</div>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($material->flashcards as $i => $f)
                    <div class="surface p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div class="font-medium text-sm">Card {{ $i + 1 }}</div>
                            <form method="POST" action="{{ route('teacher.flashcards.destroy', $f) }}" onsubmit="return confirm('Delete this card?')">@csrf @method('DELETE')
                                <button type="submit" class="text-danger text-xs">✕</button>
                            </form>
                        </div>
                        <div class="text-sm"><span class="text-faint">Front:</span> {{ $f->front }}</div>
                        <div class="text-sm mt-1"><span class="text-faint">Back:</span> {{ $f->back }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- Questions tab — list with options + correct answer --}}
    @if($tab === 'questions')
    <div data-tab-panel="questions">
        @if($material->questions->isEmpty())
            <div class="empty">No questions generated yet.</div>
        @else
            <div class="space-y-4">
                @foreach($material->questions as $i => $q)
                    @php($opts = is_array($q->options) ? $q->options : [])
                    <div class="surface p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div class="font-medium">{{ $i + 1 }}. {{ $q->question }}</div>
                            <div class="text-xs text-faint">{{ $q->type }} · diff {{ $q->difficulty ?? $q->review_status }}</div>
                        </div>
                        @if(!empty($opts))
                            <div class="space-y-1 text-sm">
                                @foreach($opts as $oi => $opt)
                                    @php($correct = ($q->correct_idx ?? 0) == $oi)
                                    <div class="flex items-center gap-2">
                                        <input type="radio" checked="{{ $correct }}" disabled>
                                        <span class="{{ $correct ? 'text-ok font-medium' : 'text-muted' }}">{{ $opt }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if($q->explanation)
                            <div class="text-xs text-faint mt-2">{{ $q->explanation }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- Study Guide tab --}}
    @if($tab === 'guide')
    <div data-tab-panel="guide">
        @if(!$material->studyGuide)
            <div class="empty">No study guide generated yet.</div>
        @else
            @php($g = is_array($material->studyGuide->content) ? $material->studyGuide->content : ($material->studyGuide->content ? json_decode($material->studyGuide->content, true) : []))
            <div class="space-y-4">
                @if($g['title'] ?? null)
                    <h3 class="font-display text-lg text-ink">{{ $g['title'] }}</h3>
                @endif
                @if($g['summary'] ?? null)
                    <p class="text-sm text-muted">{{ $g['summary'] }}</p>
                @endif
                @foreach(($g['sections'] ?? []) as $s)
                    <div class="surface p-4">
                        <div class="font-medium text-ink mb-1">{{ $s['heading'] ?? '' }}</div>
                        <div class="text-sm text-muted whitespace-pre-line">{{ $s['content'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- Images tab --}}
    @if($tab === 'images')
    <div data-tab-panel="images">
        @if(!$material->images || $material->images->isEmpty())
            <div class="empty">No images generated for this material.</div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($material->images as $img)
                    <img src="{{ $img->url ?? asset('storage/app/public/'.$img->path) }}" alt="p{{ $img->page_number }}" class="surface w-full h-24 object-cover border border-line">
                @endforeach
            </div>
        @endif
    </div>
    @endif
</x-layouts.studyai>
