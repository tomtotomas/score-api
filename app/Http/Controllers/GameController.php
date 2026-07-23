<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class GameController extends Controller
{
    private const SUITS = ['clu', 'dia', 'hea', 'spa'];
    private const VALUES = ['a', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'j', 'q', 'k'];
    private const SESSION_TTL_MINUTES = 30;

    public function start(Request $request)
    {
        $request->validate([
            'game_id' => 'nullable|string|uuid',
        ]);

        $existing = $request->game_id ? Cache::get($this->key($request->game_id)) : null;

        if ($existing && !$existing['finished']) {
            $gameId = $request->game_id;
            $streak = $existing['streak'];
        } else {
            $gameId = (string) Str::uuid();
            $streak = 0;
        }

        $deck = $this->freshShuffledDeck();

        $session = [
            'deck' => $deck,
            'player_hand' => [$this->draw($deck), $this->draw($deck)],
            'dealer_hand' => [$this->draw($deck), $this->draw($deck)],
            'streak' => $streak,
            'phase' => 'player_turn',
            'result' => null,
            'finished' => false,
            'created_at' => now()->timestamp,
        ];
        $session['deck'] = $deck;

        if ($this->points($session['player_hand']) === 21) {
            $session = $this->resolveDealerTurn($session);
        }

        $this->persist($gameId, $session);

        return response()->json($this->publicState($gameId, $session));
    }

    public function hit(Request $request, string $gameId)
    {
        $session = Cache::get($this->key($gameId));

        if (!$session || $session['phase'] !== 'player_turn') {
            return response()->json(['message' => 'Invalid game state'], 409);
        }

        $deck = $session['deck'];
        $session['player_hand'][] = $this->draw($deck);
        $session['deck'] = $deck;

        if ($this->points($session['player_hand']) >= 21) {
            $session = $this->resolveDealerTurn($session);
        }

        $this->persist($gameId, $session);

        return response()->json($this->publicState($gameId, $session));
    }

    public function stand(Request $request, string $gameId)
    {
        $session = Cache::get($this->key($gameId));

        if (!$session || $session['phase'] !== 'player_turn') {
            return response()->json(['message' => 'Invalid game state'], 409);
        }

        $session = $this->resolveDealerTurn($session);

        $this->persist($gameId, $session);

        return response()->json($this->publicState($gameId, $session));
    }

    public function requestSaveUrl(string $gameId)
    {
        $session = Cache::get($this->key($gameId));

        if (!$session || !$session['finished'] || ($session['result'] ?? null) !== 'lose') {
            return response()->json(['message' => 'No finished game to save'], 409);
        }

        $signedUrl = URL::temporarySignedRoute(
            'api.save-score',
            now()->addMinutes(15),
            ['game_id' => $gameId]
        );

        return response()->json([
            'save_url' => $signedUrl,
            'streak' => $session['streak'],
        ]);
    }

    private function resolveDealerTurn(array $session): array
    {
        $playerPoints = $this->points($session['player_hand']);
        $deck = $session['deck'];

        if ($playerPoints <= 21) {
            while ($this->points($session['dealer_hand']) < 17) {
                $session['dealer_hand'][] = $this->draw($deck);
            }
        }

        $session['deck'] = $deck;
        $dealerPoints = $this->points($session['dealer_hand']);

        if ($playerPoints > 21) {
            $result = 'lose';
        } elseif ($dealerPoints > 21 || $playerPoints > $dealerPoints) {
            $result = 'win';
        } elseif ($playerPoints < $dealerPoints) {
            $result = 'lose';
        } else {
            $result = 'tie';
        }

        $session['result'] = $result;
        $session['phase'] = 'finished';

        if ($result === 'win') {
            $session['streak']++;
        }

        $session['finished'] = ($result === 'lose');

        return $session;
    }

    private function publicState(string $gameId, array $session): array
    {
        $dealerRevealed = $session['phase'] === 'finished';

        return [
            'game_id' => $gameId,
            'player_hand' => $session['player_hand'],
            'dealer_hand' => $dealerRevealed
                ? $session['dealer_hand']
                : [$session['dealer_hand'][0]],
            'dealer_hidden_count' => $dealerRevealed ? 0 : count($session['dealer_hand']) - 1,
            'player_points' => $this->points($session['player_hand']),
            'dealer_points' => $dealerRevealed ? $this->points($session['dealer_hand']) : null,
            'phase' => $session['phase'],
            'result' => $session['result'],
            'streak' => $session['streak'],
        ];
    }

    private function persist(string $gameId, array $session): void
    {
        Cache::put($this->key($gameId), $session, now()->addMinutes(self::SESSION_TTL_MINUTES));
    }

    private function freshShuffledDeck(): array
    {
        $deck = [];
        foreach (self::SUITS as $suit) {
            foreach (self::VALUES as $value) {
                $deck[] = ['value' => $value, 'suit' => $suit];
            }
        }
        shuffle($deck);
        return $deck;
    }

    private function draw(array &$deck): array
    {
        if (empty($deck)) {
            $deck = $this->freshShuffledDeck();
        }
        return array_pop($deck);
    }

    private function points(array $hand): int
    {
        $points = 0;
        $aces = 0;

        foreach ($hand as $card) {
            $value = $card['value'];
            if ($value === 'a') {
                $aces++;
                $points += 11;
            } elseif (in_array($value, ['j', 'q', 'k'], true)) {
                $points += 10;
            } else {
                $points += (int) $value;
            }
        }

        while ($points > 21 && $aces > 0) {
            $points -= 10;
            $aces--;
        }

        return $points;
    }

    private function key(string $gameId): string
    {
        return "game_session_{$gameId}";
    }
}