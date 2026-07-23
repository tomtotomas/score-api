<?php

namespace App\Http\Controllers;

use App\Models\Score;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ScoreController extends Controller
{
    public function index()
    {
        $scores = Cache::remember('top_scores', 30, function () {
            return Score::orderBy('score', 'desc')->take(5)->get();
        });

        return response()->json($scores);
    }

    public function store(Request $request, string $game_id)
    {
        $session = Cache::get($this->sessionKey($game_id));

        if (!$session || !$session['finished'] || ($session['result'] ?? null) !== 'lose') {
            return response()->json(['message' => 'Invalid or expired game session'], 403);
        }

        $data = $request->validate([
            'player' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z0-9]{3}$/'],
        ]);

        $newScore = Score::create([
            'player' => $data['player'],
            'score' => $session['streak'],
        ]);

        Cache::forget($this->sessionKey($game_id));

        $top5Ids = Score::orderBy('score', 'desc')
            ->take(5)
            ->pluck('id');

        Score::whereNotIn('id', $top5Ids)->delete();

        Cache::forget('top_scores');

        return response()->json($newScore, 201);
    }

    private function sessionKey(string $gameId): string
    {
        return "game_session_{$gameId}";
    }
}