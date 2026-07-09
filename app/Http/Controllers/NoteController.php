<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\storeNoteRequest;
use Illuminate\Http\Request;
use App\Models\Note;
use App\Models\Tag;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->notes()->with('tags');

        $isArchived = $request->query('archived', 'false') === 'true';
        $query->where('is_archived', $isArchived);

        // Filter by tag if requested
        if ($request->has('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('name', $request->query('tag'));
            });
        }

        return response()->json($query->latest()->get());
    }

    public function store(storeNoteRequest $request)
    {

        $validated=$request->validated();

        $note = $request->user()->notes()->create([
            'title' => $validated['title'],
            'content' => $validated['content'] ?? '',
            'is_archived' => false,
        ]);

        $this->syncTags($request->user(), $note, $validated['tags'] ?? []);

        //? what is load in here 
        return response()->json($note->load('tags'), 201);
    }
    public function update(storeNoteRequest $request, int $id)
    {
        $note = Note::findorfail($id);
        if ($note->user_id !== $request->user()->id) {
            return response()->json(['message' => 'الملاحظة غير موجودة'], 403);
        }

        $validated = $request->validated();

        $note->update($request->safe()->only(['title', 'content']));

        if ($request->has('tags')) {
            $this->syncTags($request->user(), $note, $validated['tags']);
        }

        return response()->json($note->load('tags'), 200);
    }

    public function destroy(Request $request, int $id)
    {
        $note = Note::findorfail($id);

        if ($note->user_id !== $request->user()->id) {
            return response()->json(['message' => 'الملاحظة غير موجودة'], 403);
        }

        $note->delete();

        return response()->json(['message' => 'Note deleted permanently'], 200);
    }

    public function ToggleArchive(Request $request, int $id)
    {
        $note = Note::findorfail($id);
        if ($note->user_id !== $request->user()->id) {
            return response()->json(['message' => 'الملاحظة غير موجودة'], 403);
        }
        $is_archive = $note['is_archived'];
        $meassage = $is_archive ? 'Note unArchived successfully' : 'Note Archived successfully';
        $note->update(['is_archived' => !$is_archive]);

        return response()->json(['message' => $meassage, 'note' => $note]);
    }


    // Helper function to manage SQL tags
    private function syncTags($user, $note, array $tagNames)
    {
        $tagIds = [];
        foreach ($tagNames as $tagName) {
            $tagName = strtolower(trim($tagName));
            // Find the tag for this user, or create it if it doesn't exist
            $tag = Tag::firstOrCreate([
                'name' => $tagName,
                'user_id' => $user->id
            ]);
            $tagIds[] = $tag->id;
        }
        // Laravel handles deleting old pivot rows and inserting new ones!
        $note->tags()->sync($tagIds);
    }
}
