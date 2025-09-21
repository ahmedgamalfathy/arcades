<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\User;

class AllUserCollection extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

     private $pagination;

     public function __construct($resource)
     {
         $this->pagination = [
             'total' => $resource->total(),
             'count' => $resource->count(),
             'perPage' => $resource->perPage(),
             'currentPage' => $resource->currentPage(),
             'totalPages' => $resource->lastPage()
         ];

         $resource = $resource->getCollection();

         parent::__construct($resource);
     }


    public function toArray(Request $request): array
    {

        return [
            'users' => AllUserResource::collection(($this->collection)->values()->all()),
            'pagination' => $this->pagination
        ];

    }
}
