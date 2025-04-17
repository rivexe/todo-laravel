<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    /**
     * Отобразить страницу со списком задач.
     */
    public function index(): View
    {
        $tags = Tag::all();
        return view('tasks.index', compact('tags'));
    }

    /**
     * Получить задачи с пагинацией.
     */
    public function getTasks(Request $request): JsonResponse
    {
        $page = $request->input('page', 1);
        $perPage = 10;
        
        $tasks = Task::with('tags')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
        
        return response()->json([
            'tasks' => $tasks->items(),
            'has_more' => $tasks->hasMorePages(),
            'next_page' => $tasks->currentPage() + 1,
        ]);
    }

    /**
     * Сохранить новую задачу.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $task = Task::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => false,
        ]);

        if (isset($validated['tag_ids']) && !empty($validated['tag_ids'])) {
            $task->tags()->attach($validated['tag_ids']);
        }

        $task->load('tags');

        return response()->json([
            'success' => true,
            'task' => $task,
        ]);
    }

    /**
     * Обновить статус задачи.
     */
    public function updateStatus(Task $task): JsonResponse
    {
        $task->status = !$task->status;
        $task->save();

        return response()->json([
            'success' => true,
            'task' => $task,
        ]);
    }

    /**
     * Обновить указанную задачу.
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $task->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? $task->status,
        ]);

        if (isset($validated['tag_ids'])) {
            $task->tags()->sync($validated['tag_ids']);
        }

        $task->load('tags');

        return response()->json([
            'success' => true,
            'task' => $task,
        ]);
    }

    /**
     * Удалить указанную задачу.
     */
    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json([
            'success' => true,
        ]);
    }
    
    /**
     * Получить задачу по ID.
     */
    public function show(Task $task): JsonResponse
    {
        $task->load('tags');
        
        return response()->json([
            'success' => true,
            'task' => $task,
        ]);
    }
}