<?php

namespace App\Http\Controllers;

use App\Exceptions\AiServiceException;
use App\Models\Topic;
use App\Services\AiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TopicController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:student,admin,super_admin');
    }

    public function index(): View
    {
        $topics = Topic::where('user_id', auth()->id())->orderBy('created_at', 'desc')->paginate(20);
        return view('student.topics.index', compact('topics'));
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'topic' => 'required|string|max:255',
        ]);

        $context = ['userId' => auth()->id(), 'schoolId' => auth()->user()->currentSchool()?->id];
        $ai = app(AiService::class);

        try {
            $result = $data['topic']
                ? $ai->generateTopics($data['topic'], $context)
                : [];
        } catch (AiServiceException $e) {
            Log::error('Topic generation failed', [
                'reference' => $e->reference(),
                'detail' => $e->privateDetail(),
            ]);

            return back()->with('error', $e->publicMessage().' (ref '.$e->reference().')');
        } catch (\Throwable $e) {
            // Never echo a raw exception message: it can carry provider output,
            // query fragments or file paths.
            $reference = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            Log::error('Topic generation failed', [
                'reference' => $reference,
                'exception' => $e::class,
                'detail' => $e->getMessage(),
            ]);

            return back()->with(
                'error',
                'Topics could not be generated just now. Try again — if it keeps failing, quote reference '.$reference.'.'
            );
        }

        // Persist each generated topic
        $count = 0;
        foreach ($result as $t) {
            if (!is_array($t) || empty($t['topic'])) {
                continue;
            }
            Topic::create([
                'user_id' => auth()->id(),
                'school_id' => auth()->user()->currentSchool()?->id,
                'name' => $t['topic'],
                'content' => is_string($t['description'] ?? null) ? $t['description'] : json_encode($t, JSON_UNESCAPED_SLASHES),
            ]);
            $count++;
        }

        return redirect()->route('student.topics.index')
            ->with('status', $count > 0 ? "Generated {$count} topics." : 'No topics returned.');
    }

    public function destroy(Topic $topic): RedirectResponse
    {
        abort_unless($topic->user_id === auth()->id(), 403);
        $topic->delete();
        return back()->with('status', 'Topic deleted.');
    }
}
