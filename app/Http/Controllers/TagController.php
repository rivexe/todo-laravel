<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    /**
     * Отобразить страницу со списком тегов.
     */
    public function index(): View
    {
        $tags = Tag::all();
        return view('tags.index', compact('tags'));
    }

    /**
     * Получить все теги.
     */
    public function getTags(): JsonResponse
    {
        $tags = Tag::all();
        
        return response()->json([
            'tags' => $tags,
        ]);
    }

    /**
     * Сохранить новый тег.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tags',
        ]);

        $tag = Tag::create([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'success' => true,
            'tag' => $tag,
        ]);
    }

    /**
     * Обновить указанный тег.
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tags,name,' . $tag->id,
        ]);

        $tag->update([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'success' => true,
            'tag' => $tag,
        ]);
    }

    /**
     * Удалить указанный тег.
     */
    public function destroy(Tag $tag): JsonResponse
    {
        // Используется ли этот тег в задачах
        $taskCount = $tag->tasks()->count();
        
        if ($taskCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Невозможно удалить тег, который используется в {$taskCount} задачах.",
            ], 422);
        }
        
        $tag->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}