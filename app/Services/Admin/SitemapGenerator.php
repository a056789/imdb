<?php namespace App\Services\Admin;

use App;
use App\ListModel;
use App\NewsArticle;
use App\Person;
use App\Title;
use Common\Admin\BaseSitemapGenerator;

class SitemapGenerator extends BaseSitemapGenerator {

    protected function getAppQueries(): array {
        return [
            app(Title::class)->where('fully_synced', true)->orWhereNull('tmdb_id')->select(['id', 'name']),
            app(Person::class)->where('fully_synced', true)->orWhereNull('tmdb_id')->select(['id', 'name']),
            app(ListModel::class)->where('public', true)->where('system', false)->select(['id', 'name']),
            app(NewsArticle::class)->select(['id', 'title', 'slug']),
        ];
    }

    protected function getAppStaticUrls(): array
    {
        return [
            'browse?type=series',
            'browse?type=movie',
            'people',
            'news',
        ];
    }
}
