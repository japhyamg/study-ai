<x-layouts.studyai title="Flashcards">
    <div class="mb-3 flex items-center justify-between">
        <span class="font-semibold text-ink">Flashcards</span>
        <a href="{{ route('student.flashcards', ['view' => 'due']) }}" class="text-xs text-accent">Due only</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($flashcards as $f)
            <div class="flashcard surface" data-id="{{ $f->id }}">
                <div class="flashcard-inner relative" style="min-height:140px">
                    <div class="flashcard-face flashcard-front absolute inset-0 p-4 flex items-center justify-center text-center font-medium text-ink">
                        {{ $f->front }}
                    </div>
                    <div class="flashcard-face flashcard-back absolute inset-0 p-4 flex items-center justify-center text-center text-muted hidden">
                        {{ $f->back }}
                    </div>
                </div>
                <div class="border-t px-3 py-2 text-xs text-faint">Due: {{ $f->due_date?->format('Y-m-d') ?? 'now' }} · Reps: {{ $f->repetitions }}</div>
                <div class="px-3 pb-3 flex gap-2">
                    <button class="flip-btn flex-1 px-2 py-1 bg-paper-sunk rounded text-xs" data-show="back">Show answer</button>
                    <form method="POST" action="{{ route('student.flashcards.review', $f) }}" class="flex-1">@csrf
                        <button name="quality" value="5" class="w-full px-2 py-1 bg-green-600 text-white rounded text-xs">Easy</button>
                    </form>
                    <form method="POST" action="{{ route('student.flashcards.review', $f) }}" class="flex-1">@csrf
                        <button name="quality" value="3" class="w-full px-2 py-1 bg-yellow-500 text-white rounded text-xs">Good</button>
                    </form>
                    <form method="POST" action="{{ route('student.flashcards.review', $f) }}" class="flex-1">@csrf
                        <button name="quality" value="0" class="w-full px-2 py-1 bg-red-600 text-white rounded text-xs">Hard</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="text-faint text-sm">No flashcards.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $flashcards->links() }}</div>

    <script>
      document.querySelectorAll('.flip-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var card = btn.closest('.flashcard');
          var front = card.querySelector('.flashcard-front');
          var back = card.querySelector('.flashcard-back');
          var showing = btn.dataset.show === 'back';
          front.classList.toggle('hidden', showing);
          back.classList.toggle('hidden', !showing);
          btn.dataset.show = showing ? 'front' : 'back';
          btn.textContent = showing ? 'Show answer' : 'Hide answer';
        });
      });
    </script>
</x-layouts.studyai>
