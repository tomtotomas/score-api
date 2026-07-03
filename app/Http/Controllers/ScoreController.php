<?php

namespace App\Http\Controllers;

use App\Models\Score;
use Illuminate\Http\Request;

class ScoreController extends Controller
{
    public function index()
    {
        $scores = Score::orderBy('score', 'desc')->take(5)->get();
        return response()->json($scores);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'player' => 'required|string|max:3',
            'score' => 'required|integer',
        ]);

        $newScore = Score::create($data);

        $top5Ids = Score::orderBy('score', 'desc')
            ->take(5)
            ->pluck('id');

        Score::whereNotIn('id', $top5Ids)->delete();

        return response()->json($newScore, 201);
    }

    public function destroy(Score $score)
    {
        $score->delete();

        return response()->json(['message' => 'Score deleted successfully']);
    }
}
