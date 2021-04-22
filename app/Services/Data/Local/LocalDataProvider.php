<?php

namespace App\Services\Data\Local;

use App\Episode;
use App\Person;
use App\Services\Data\Contracts\DataProvider;
use App\Title;
use Carbon\Carbon;
use Common\Tags\Tag;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Arr;
use Str;

class LocalDataProvider implements DataProvider
{
    /**
     * @var Title
     */
    private $title;

    /**
     * @var Tag
     */
    private $tag;

    /**
     * @param Title $title
     * @param Tag $tag
     */
    public function __construct(Title $title, Tag $tag)
    {
        $this->title = $title;
        $this->tag = $tag;
    }

    public function getTitle(Title $title)
    {
        return [];
    }

    public function getPerson(Person $person)
    {
        return [];
    }

    public function getSeason(Title $title, $seasonNumber)
    {
        return [];
    }

    public function search($query, $params = [])
    {
        $titles = collect();
        $people = collect();
        $mode = config('common.site.local_search_mode');

        if (Arr::get($params, 'type') !== 'person') {
            $titleSelectCols = 'id, name, year, description, poster, is_series, tmdb_id';

            if ($mode === 'fulltext') {
                $query = addslashes($query);
                $builder = $this->title
                    ->selectRaw("$titleSelectCols, ROUND(MATCH(name) AGAINST('$query' IN NATURAL LANGUAGE MODE), 1) AS relevance")
                    ->whereRaw("MATCH(name) AGAINST('$query')")
                    ->orderBy('relevance', 'desc');
            } else {
                $builder = $this->title
                    ->selectRaw($titleSelectCols)
                    ->where('name', 'like', "%$query%");
            }

            if ($with = Arr::get($params, 'with')) {
                $with = array_filter(explode(',', $with));
                $builder->with($with);
            }

            $titles = $builder
                ->orderBy('popularity', 'desc')
                ->limit(20)
                ->get();
        }

        if (Arr::get($params, 'type') !== 'title') {
            $personSelectCols = 'id, name, poster, tmdb_id';

            if ($mode === 'fulltext') {
                $query = addslashes($query);
                $builder = app(Person::class)
                    ->selectRaw("$personSelectCols, ROUND(MATCH(name) AGAINST('$query' IN NATURAL LANGUAGE MODE), 1) AS relevance")
                    ->whereRaw("MATCH(name) AGAINST('$query')")
                    ->orderBy('relevance', 'desc');
            } else {
                $builder = app(Person::class)
                    ->selectRaw($personSelectCols)
                    ->where('name', 'like', "%$query%");
            }

            $people = $builder
                ->orderBy('popularity', 'desc')
                ->with('popularCredits')
                ->limit(20)
                ->get();
        }

        return $titles
            ->concat($people)
            ->slice(0, Arr::get($params, 'limit', 8))
            ->values();
    }

    public function getTitles($titleType, $titleCategory)
    {
        $titleType = $titleType === 'tv' ? Title::SERIES_TYPE : Title::MOVIE_TYPE;

        if ($titleCategory === 'popular') {
            return $this->title
                ->orderBy('popularity', 'desc')
                ->limit(20)
                ->where('is_series', $titleType === Title::SERIES_TYPE)
                ->get();
        } else if ($titleCategory === 'topRated') {
            return $this->getTopRatedTitles($titleType);
        } else if ($titleCategory === 'upcoming') {
            return $this->getMoviesReleasingBetween(Carbon::now(), Carbon::now()->addWeek());
        } else if ($titleCategory === 'nowPlaying') {
            return $this->getMoviesReleasingBetween(Carbon::now(), Carbon::now()->subWeek(2));
        } else if ($titleCategory === 'onTheAir') {
           $this->getSeriesAiringBetween(Carbon::now(), Carbon::now()->addWeek());
        } else if ($titleCategory === 'airingToday') {
            return $this->getSeriesAiringBetween(Carbon::now(), Carbon::now()->addDay());
        } else if ($titleCategory === 'latestVideos') {
            return $this->title
                ->join('videos', 'titles.id', '=', 'videos.title_id')
                ->where('videos.source', 'local')
                ->where('approved', true)
                ->orderBy('videos.created_at', 'desc')
                ->select('titles.*')
                ->distinct()
                ->limit(20)
                ->get();
        } else if ($titleCategory === 'lastAdded') {
            return $this->title
                ->where('is_series', $titleType === Title::SERIES_TYPE)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        } else if (Str::contains($titleCategory, ['keyword', 'genre'])) {
            list($_, $tagId) = explode(':', $titleCategory);
            $tagName = $this->tag->find($tagId)->name;
            $relation = Str::startsWith($titleCategory, 'keyword') ? 'keywords' : 'genres';
            $query = $this->title
                ->whereHas($relation, function(Builder $query) use($tagName) {
                    $query->where('name', $tagName);
                })
                ->orderBy('popularity', 'desc')
                ->limit(40);
            if ($titleType !== '*') {
                $query->where('is_series', $titleType === Title::SERIES_TYPE);
            }
            return $query->get();

        }
    }
    
    private function getTopRatedTitles($type)
    {
        $ratingCol = config('common.site.rating_column');

        $query = $this->title
            ->where('is_series', $type === Title::SERIES_TYPE);

        if (Str::contains($ratingCol, 'tmdb_vote_average')) {
            $query->orderBy(DB::raw('tmdb_vote_count > 100'), 'desc');
        }

        return $query
            ->orderBy($ratingCol, 'desc')
            ->limit(20)
            ->get();
    }

    private function getMoviesReleasingBetween($from, $to)
    {
        return $this->title
            ->whereBetween('release_date', [$from, $to])
            ->orderBy('popularity', 'desc')
            ->limit(20)
            ->where('is_series', false)
            ->get(['id', 'name']);
    }

    private function getSeriesAiringBetween($from, $to)
    {
        $titleIds = app(Episode::class)
            ->whereBetween('release_date',  [$from, $to])
            ->limit(300)
            ->get(['title_id'])
            ->pluck('title_id')
            ->unique()
            ->slice(0, 20);

        return $this->title->whereIn('id', $titleIds)->get(['id', 'name']);
    }
}
