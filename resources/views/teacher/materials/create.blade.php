<x-layouts.studyai title="Upload Material">
    <a href="{{ route('teacher.materials.index') }}" class="text-xs text-accent">← Back</a>
    <h2 class="font-semibold text-ink mt-2">Upload Material</h2>
    <p class="text-sm text-muted mb-4">Create a material; optionally generate flashcards, questions & a study guide with AI.</p>

    @if(session('status'))<div class="text-ok text-sm mb-3">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="text-danger text-sm mb-3">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('teacher.materials.store') }}" class="surface p-5 space-y-4 max-w-2xl">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium">Class (optional)</label>
                <select name="class_id" class="w-full border rounded px-2 py-1 text-sm">
                    <option value="">No class</option>
                    @foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Subject (optional)</label>
                <select name="subject_id" class="w-full border rounded px-2 py-1 text-sm">
                    <option value="">Auto-detect</option>
                    @foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="text-sm font-medium">Title</label>
            <input name="title" required class="w-full border rounded px-2 py-1 text-sm" value="{{ old('title') }}">
        </div>
        <div>
            <label class="text-sm font-medium">Description (optional)</label>
            <textarea name="description" rows="2" class="w-full border rounded px-2 py-1 text-sm">{{ old('description') }}</textarea>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium">Type</label>
                <select name="type" class="w-full border rounded px-2 py-1 text-sm">
                    <option value="note">Note / Text</option>
                    <option value="pdf">PDF</option>
                    <option value="pptx">PPTX</option>
                    <option value="youtube">YouTube</option>
                    <option value="video">Video</option>
                    <option value="doc">Document</option>
                    <option value="link">Link</option>
                    <option value="url">URL</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Source URL (for url/youtube/link)</label>
                <input name="source_url" type="url" class="w-full border rounded px-2 py-1 text-sm" value="{{ old('source_url') }}">
            </div>
        </div>
        <div>
            <label class="text-sm font-medium">Content / Transcript (for note type)</label>
            <textarea name="content" rows="5" class="w-full border rounded px-2 py-1 text-sm font-mono">{{ old('content') }}</textarea>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="generate" value="1" checked id="genToggle">
            Generate flashcards, questions & study guide with AI after saving
        </label>

        {{-- Question Settings (mirrors src/app/upload/page.tsx) --}}
        <div id="questionSettings" class="border rounded-lg">
            <button type="button" id="qsToggle" class="w-full flex items-center justify-between p-3 text-sm font-medium">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7l-7 7-7-7"/></svg>
                    Question Settings
                </span>
                <svg id="qsChevron" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div id="qsBody" class="px-4 pb-4 space-y-4 hidden">
                <div>
                    <label class="text-sm font-medium">Number of Questions</label>
                    <div class="flex items-center gap-3">
                        <input id="qCount" type="range" min="3" max="30" value="10" name="question_count" class="flex-1">
                        <span id="qCountVal" class="text-lg font-mono w-8 text-center">10</span>
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium">Question Types</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @php $qtypes = old('question_types', ['multiple-choice']); @endphp
                        @foreach([['multiple-choice','Multiple Choice'],['true-false','True / False'],['fill-blank','Fill in the Blank'],['short-answer','Short Answer']] as [$val,$label])
                            @php $sel = in_array($val, $qtypes); @endphp
                            <label class="flex items-center gap-2 p-3 border rounded cursor-pointer {{ $sel ? 'border-accent bg-accent/10' : 'border-line' }}">
                                <input type="checkbox" name="question_types[]" value="{{ $val }}" {{ $sel ? 'checked' : '' }} class="text-primary focus:ring-accent">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <button class="btn btn-primary">Create Material</button>

        <script>
          (function(){
            var t=document.getElementById('genToggle'); if(!t)return;
            var s=document.getElementById('questionSettings'); var b=document.getElementById('qsBody');
            var cb=document.getElementById('qsChevron'); var qb=document.getElementById('qsToggle');
            var qc=document.getElementById('qCount'); var qcv=document.getElementById('qCountVal');
            if(qc&&qcv){qc.addEventListener('input',function(e){qcv.textContent=e.target.value;});}
            function sync(){ if(s) s.style.display = t.checked ? 'block' : 'none'; } t.addEventListener('change',sync); sync();
            if(qb){qb.addEventListener('click',function(e){e.preventDefault(); if(b){b.classList.toggle('hidden'); if(cb){cb.style.transform=b.classList.contains('hidden')?'':'rotate(180deg)';}});}}
          })();
        </script>
    </form>
</x-layouts.studyai>
