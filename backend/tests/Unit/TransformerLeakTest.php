<?php
namespace Tests\Unit;

use App\Http\Transformers\CategoryTransformer;
use App\Http\Transformers\EpisodeTransformer;
use App\Http\Transformers\GenreTransformer;
use App\Http\Transformers\MediaAssetTransformer;
use App\Http\Transformers\MovieTransformer;
use App\Http\Transformers\SeasonTransformer;
use App\Http\Transformers\SeriesTransformer;
use PHPUnit\Framework\TestCase;

class TransformerLeakTest extends TestCase {
    public function test_transformers_never_expose_sensitive_internal_keys(): void {
        $movieTransformer = new MovieTransformer(new MediaAssetTransformer());
        $seriesTransformer = new SeriesTransformer();
        $seasonTransformer = new SeasonTransformer();
        $episodeTransformer = new EpisodeTransformer();
        $categoryTransformer = new CategoryTransformer();
        $genreTransformer = new GenreTransformer();

        $movieDetail = $movieTransformer->detail([
            'id' => 1,
            'slug' => 'movie-1',
            'title' => 'Movie 1',
            'synopsis' => 'Synopsis',
            'year' => 2024,
            'poster_url' => 'poster',
            'banner_url' => 'banner',
            'duration_seconds' => 120,
            'media_asset_id' => 22,
            'provider_uid' => 'secret-provider-uid',
            'storage_key' => 'secret-storage-key',
            'rights_status' => 'owned_by_me',
            'public_streaming_enabled' => 1,
            'visibility' => 'private',
        ], [
            $categoryTransformer->transform(['id' => 9, 'name' => 'Category', 'slug' => 'category']),
        ], [
            $genreTransformer->transform(['id' => 11, 'name' => 'Genre', 'slug' => 'genre']),
        ]);

        $seriesDetail = $seriesTransformer->detail([
            'id' => 2,
            'slug' => 'series-2',
            'title' => 'Series 2',
            'synopsis' => 'Synopsis',
            'poster_url' => 'poster',
            'banner_url' => 'banner',
            'year' => 2023,
            'rights_status' => 'owned_by_me',
            'visibility' => 'public',
        ], [
            $seasonTransformer->transform(['id' => 4, 'season_number' => 1, 'episode_count' => 8]),
        ]);

        $episode = $episodeTransformer->transform([
            'id' => 7,
            'episode_number' => 1,
            'title' => 'Episode 1',
            'synopsis' => 'Episode synopsis',
            'duration_seconds' => 55,
            'series_poster_url' => 'poster',
            'storage_key' => 'secret-storage-key',
        ]);

        $payload = json_encode([
            'movie' => $movieDetail,
            'series' => $seriesDetail,
            'episode' => $episode,
        ], JSON_UNESCAPED_SLASHES);

        $this->assertIsString($payload);
        $this->assertStringNotContainsString('provider_uid', $payload);
        $this->assertStringNotContainsString('storage_key', $payload);
        $this->assertStringNotContainsString('rights_status', $payload);
        $this->assertStringNotContainsString('public_streaming_enabled', $payload);
        $this->assertStringNotContainsString('visibility', $payload);
    }
}
