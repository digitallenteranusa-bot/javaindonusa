<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AreaResource;
use App\Models\Area;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AreaController extends Controller
{
    /**
     * List active areas.
     */
    public function index(): AnonymousResourceCollection
    {
        $areas = Area::where('is_active', true)
            ->orderBy('name')
            ->get();

        return AreaResource::collection($areas);
    }
}
