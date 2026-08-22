<x-layouts.studyai title="Study Session">
    <a href="{{ route('student.study.index') }}" class="text-xs text-accent">← Study sets</a>

    @if(!$flashcard)
        <div class="empty mt-6">No cards in this session. You're all caught up.</div>
    @else
        <div class="flex items-center justify-between mt-3 mb-4">
            <span class="text-sm muted">Card {{ $index + 1 }} of {{ $total }}</span>
            <div class="bar" style="width: 180px"><span style="width: {{ $total ? round(($index / $total) * 100) : 0 }}%"></span></div>
        </div>

        <div class="flashcard surface" id="card" style="min-height: 16rem">
            <div class="flashcard-inner" style="min-height:16rem">
                <div class="flashcard-face flashcard-front" id="front">{{ $flashcard->front }}</div>
                <div class="flashcard-face flashcard-back" id="back">{{ $flashcard->back }}</div>
            </div>
        </div>

        <div class="mt-4 flex gap-2">
            <button class="btn btn-ghost flex-1" onclick="flip()">Show answer</button>
        </div>

        <form method="POST" action="{{ route('student.study.answer', $flashcard) }}" id="grade" style="display:none" class="mt-2">
            @csrf
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button name="quality" value="0" class="btn btn-danger">Again</button>
                <button name="quality" value="3" class="btn btn-ghost">Hard</button>
                <button name="quality" value="4" class="btn btn-outline">Good</button>
                <button name="quality" value="5" class="btn btn-primary">Easy</button>
            </div>
        </form>
    @endif

    <script>
        function flip() {
            document.getElementById('card').classList.add('flipped');
            document.querySelector('#card .flashcard-back').classList.remove('hidden');
            document.getElementById('grade').style.display = 'block';
            document.querySelector('#card .flashcard-front').classList.add('hidden');
        }
    </script>
</x-layouts.studyai>
