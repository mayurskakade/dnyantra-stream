<?php
namespace App\Services;

class WatchProgressService {
    /** @var array<string,array<string,mixed>> */
    private array $store = [];

    public function compute(int $position, int $duration): array {
        $percent = $duration>0 ? round(($position/$duration)*100,2) : 0;
        return ['position_seconds'=>$position,'duration_seconds'=>$duration,'progress_percent'=>$percent,'completed'=>$percent>=90];
    }

    public function save(int $userId, string $type, int $playableId, int $position, int $duration): array {
        $computed = $this->compute($position, $duration);
        $key = $this->key($userId, $type, $playableId);
        $this->store[$key] = $computed + ['user_id'=>$userId, 'playable_type'=>$type, 'playable_id'=>$playableId];
        return $this->store[$key];
    }

    public function clear(int $userId, string $type, int $playableId): bool {
        $key = $this->key($userId, $type, $playableId);
        if (!isset($this->store[$key])) return false;
        unset($this->store[$key]);
        return true;
    }

    public function get(int $userId, string $type, int $playableId): ?array {
        return $this->store[$this->key($userId, $type, $playableId)] ?? null;
    }

    private function key(int $userId, string $type, int $playableId): string { return "$userId:$type:$playableId"; }
}
