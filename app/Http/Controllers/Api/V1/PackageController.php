<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackageController extends Controller
{
    /**
     * List active packages.
     */
    public function index(): AnonymousResourceCollection
    {
        $packages = Package::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return PackageResource::collection($packages);
    }
}
