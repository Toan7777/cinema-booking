<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function index()
    {
        return response()->json(Movie::all());
    }

    public function show(int $id)
    {
        return response()->json(Movie::findOrFail($id));
    }

    // Chỉ ADMIN gọi được (đã chặn ở route bằng middleware('role:ADMIN'))
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:200',
            'durationMinutes'  => 'required|integer',
            'genre'            => 'nullable|string',
            'posterUrl'        => 'nullable|string',
            'description'      => 'nullable|string',
        ]);

        $movie = Movie::create([
            'title'            => $data['title'],
            'duration_minutes' => $data['durationMinutes'],
            'genre'            => $data['genre'] ?? null,
            'poster_url'       => $data['posterUrl'] ?? null,
            'description'      => $data['description'] ?? null,
        ]);

        return response()->json($movie, 201);
    }

    public function update(Request $request, int $id)
    {
        $movie = Movie::findOrFail($id);
        $data = $request->validate([
            'title'           => 'sometimes|string|max:200',
            'durationMinutes' => 'sometimes|integer',
            'genre'           => 'nullable|string',
            'posterUrl'       => 'nullable|string',
            'description'     => 'nullable|string',
        ]);
        $movie->update($data);
        return response()->json($movie);
    }

    public function destroy(int $id)
    {
        Movie::destroy($id);
        return response()->json(['message' => 'Đã xóa phim']);
    }
}
