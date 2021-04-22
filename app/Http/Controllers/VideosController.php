<?php

namespace App\Http\Controllers;

use App\Episode;
use App\Services\Videos\CrupdateVideo;
use App\Video;
use Common\Core\BaseController;
use Common\Database\Paginator;
use Common\Plays\LogModelPlay;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideosController extends BaseController
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Video
     */
    private $video;

    /**
     * @var Episode
     */
    private $episode;

    /**
     * @param Request $request
     * @param Video $video
     * @param Episode $episode
     */
    public function __construct(Request $request, Video $video, Episode $episode)
    {
        $this->request = $request;
        $this->video = $video;
        $this->episode = $episode;
    }

    public function index()
    {
        $this->authorize('index', Video::class);

        $paginator = (new Paginator($this->video, $this->request->all()));
        $paginator->filterColumns = ['source', 'category', 'approved', 'quality', 'type'];
        $paginator->with(['captions', 'title' => function(BelongsTo $query) {
            $query->with('seasons')->select('id', 'name', 'poster', 'backdrop', 'is_series', 'season_count');
        }]);
        $paginator->withCount(['reports', 'plays']);

        if ($titleId = $this->request->get('titleId')) {
            $paginator->where('title_id', $titleId);

            if ($episode = $this->request->get('episode')) {
                $paginator->where('episode', $episode);
            }

            if ($season = $this->request->get('season')) {
                $paginator->where('season', $season);
            }
        }

        if ($source = $this->request->get('source')) {
            $paginator->where('source', $source);
        }

        if ($userId = $paginator->param('userId')) {
            $paginator->where('user_id', $userId);
        }

        // order by percentage of likes, taking into account total amount of likes and dislikes
        if ($this->request->get('order_by') === 'score') {
            $paginator->query()->selectScore();
        }

        $pagination = $paginator->paginate();

        return $this->success(['pagination' => $pagination]);
    }

    public function store()
    {
        $this->authorize('store', Video::class);

        $this->validate($this->request, [
            'title_id' => 'required|integer',
            'name' => ['required', 'string', 'min:3', 'max:250'],
            'url' => 'required|max:1000',
            'type' => 'required|string|min:3|max:250',
            'category' => 'required|string|min:3|max:20',
            'quality' => 'nullable|string|min:2|max:250',
            'language' => 'required|nullable|string|max:10',
            'season' => 'nullable|integer',
            'episode' => 'requiredWith:season|integer|nullable',
        ], [
            'title_id.*' => __('Select a title this video should be attached to.'),
        ]);

        $video = app(CrupdateVideo::class)->execute($this->request->all());

        return $this->success(['video' => $video]);
    }

    public function update($id)
    {
        $this->authorize('update', Video::class);

        $this->validate($this->request, [
            'name' => 'string|min:3|max:250',
            'url' => 'required|max:1000',
            'type' => 'string|min:3|max:1000',
            'quality' => 'nullable|string|min:2|max:250',
            'language' => 'required|nullable|string|max:10',
            'title_id' => 'integer',
            'season' => 'nullable|integer',
            'episode' => 'requiredWith:season|integer|nullable',
        ], [
            'title_id.*' => __('Select a title this video should be attached to.'),
        ]);

        $video = app(CrupdateVideo::class)->execute($this->request->all(), $id);

        return $this->success(['video' => $video]);
    }

    public function destroy($ids)
    {
        $ids = explode(',', $ids);
        $this->authorize('destroy', [Video::class, $ids]);

        foreach ($ids as $id) {
            $video = $this->video->find($id);
            if (is_null($video)) continue;

            $video->delete();
        }

        return $this->success();
    }

    /**
     * @param Video $video
     * @return JsonResponse
     */
    public function logPlay(Video $video)
    {
        $this->authorize('show', Video::class);

        app(LogModelPlay::class)->execute($video);

        return $this->success();
    }
}
