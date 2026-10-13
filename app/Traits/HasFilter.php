<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HasFilter
{
    /**
     * @param Request $request
     * @param int $default
     * @return int|mixed
     */
    public function getLimit(Request $request, int $default = 10): mixed
    {
        return $request->has('limit') && $request->limit > 0 ? $request->limit : $default;
    }

    /**
     * @param Builder $query
     * @param Request $request
     * @return Builder
     */
    public function applyFilters(Builder $query, Request $request): Builder
    {
        $query->when($request->has('date'), function ($query) use ($request) {
            $query->whereDate('created_at', $request->date);
        });

        $query->when($request->has('start_date') && $request->has('end_date'), function ($query) use ($request) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        });

        $query->when($request->has('latest') && $request->latest == true, function ($query) {
            $query->latest();
        });

        return $query;
    }
}
