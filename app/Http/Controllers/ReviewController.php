<?php

namespace App\Http\Controllers;

use App\Episode;
use App\Review;
use App\Services\Reviews\UpdateReviewableAverageScore;
use App\Title;
use Auth;
use Common\Core\BaseController;
use Common\Database\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReviewController extends BaseController
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Review
     */
    private $review;

    /**
     * @param Request $request
     * @param Review $review
     */
    public function __construct(Request $request, Review $review)
    {
        $this->request = $request;
        $this->review = $review;
    }

    public function index()
    {
        $this->authorize('index', Review::class);

        $paginator = (new Paginator($this->review, $this->request->all()));
        $paginator->searchColumn = 'body';
        $paginator->filterColumns = ['body' => function(Builder $builder, $value) {
            if ($value === 'review') {
                $builder->whereNotNull('body');
            } else {
                $builder->whereNull('body');
            }
        }, 'user_id'];

        if ($titleId = $this->request->get('titleId')) {
            if ($episode = $this->request->get('episode') && $season = $this->request->get('season')) {
                $episodeId = app(Episode::class)
                    ->where('title_id', $titleId)
                    ->where('episode_number', $episode)
                    ->where('season_number', $season)
                    ->pluck('id');
                $paginator->where('reviewable_id', $episodeId)
                    ->where('reviewable_type', Episode::class);
            } else {
                $paginator->where('reviewable_id', $titleId)
                    ->where('reviewable_type', Title::class);
            }
        }

        if ($withTextOnly = $this->request->get('withTextOnly')) {
            $paginator->query()->whereNotNull('body');
        }

        $pagination = $paginator->paginate();

        if ($this->request->get('compact')) {
            $pagination->map(function(Review $review) {
                $review->body = Str::limit($review->body, 200);
                return $review;
            });
        }

        return $this->success(['pagination' => $pagination]);
    }

    public function update($id)
    {
        $review = $this->review->findOrFail($id);

        $this->authorize('update', $review);

        $review->fill($this->request->all())->save();

        app(UpdateReviewableAverageScore::class)
            ->execute($review->reviewable_id, $review->reviewable_type);

        return $this->success(['review' => $review]);
    }

    public function store()
    {
        $this->authorize('store', Review::class);

        $this->validate($this->request, [
            'mediaId' => 'required|integer',
            'mediaType' => 'required|string',
            'review' => 'string|min:50|max:5000',
            'score' => 'required|integer|min:1|max:10'
        ]);

        $reviewableId = $this->request->get('mediaId');
        $reviewableType = $this->getType();

        $values = [
            'score' => $this->request->get('score'),
            'type' => Review::USER_REVIEW_TYPE,
        ];
        // don't override reviews with score changes
        if ($this->request->get('body')) {
            $values['body'] =  $this->request->get('body');
        }

        $review = $this->review->updateOrCreate([
            'user_id' => Auth::id(),
            'reviewable_type' => $reviewableType,
            'reviewable_id' => $reviewableId,
        ], $values);

        $review->load('user');
        app(UpdateReviewableAverageScore::class)
            ->execute($reviewableId, $reviewableType);

        return $this->success(['review' => $review]);
    }

    public function destroy()
    {
        $reviews = $this->review
            ->whereIn('id', $this->request->get('ids'))
            ->get();

        $this->authorize('destroy', [Review::class, $reviews]);

        $reviews->each(function(Review $review) {
            app(UpdateReviewableAverageScore::class)
                ->execute($review->reviewable_id, $review->reviewable_type);
        });

        $this->review->whereIn('id', $reviews->pluck('id'))->delete();



        return $this->success();
    }

    private function getType() {
        return $this->request->get('mediaType') === Episode::EPISODE_TYPE ?
            Episode::class :
            Title::class;
    }
}
