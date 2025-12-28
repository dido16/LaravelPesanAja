<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index()
    {
        // Mengambil semua menu
        $menus = Menu::all();
        $menus = Menu::with('levels')->get();

        // Mengembalikan data dalam bentuk JSON agar bisa dibaca Retrofit
        return response()->json($menus);
    }
}