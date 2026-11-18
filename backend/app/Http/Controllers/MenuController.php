<?php

namespace App\Http\Controllers;

use App\Http\Resources\MenuItemResource;
use App\Repositories\MenuItemRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuController extends Controller
{
    public function __construct(private readonly MenuItemRepositoryInterface $menuItems) {}

    /** Menú del usuario autenticado, filtrado por sus permisos. */
    public function getUserMenu(Request $request): AnonymousResourceCollection
    {
        return MenuItemResource::collection($this->menuItems->getTreeForUser($request->user()));
    }
}
