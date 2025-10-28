<?php

namespace App\Http\Controllers\API\V1\Select;

use App\Http\Controllers\Controller;
use App\Services\Select\SelectService;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Http\Request;

class SelectController extends Controller
{
    private $selectService;

    public function __construct(SelectService $selectService)
    {
        $this->selectService = $selectService;
    }
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('permission:products', only:['getSelects']),
            new Middleware('tenant'),
        ];
    }
    public function getSelects(Request $request)
    {
        $selectData = $this->selectService->getSelects($request->allSelects);

        return response()->json($selectData);
    }


}
