<?php

namespace App\Http\Controllers;

use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Menu; // Import Model Menu
use Yajra\DataTables\Facades\DataTables; // Untuk DataTables
use App\Models\Category; // Import Model Category untuk dropdown filter/form

class MenuController extends Controller
{
    /**
     * Menampilkan halaman index (Daftar Menu).
     */
    public function index()
    {
        $breadcrumb = (object) [
            'title' => 'Manajemen Menu',
            'list' => ['Home', 'Menu']
        ];

        $page = (object) [
            'title' => 'Daftar Menu Restoran'
        ];

        $categories = Category::all();

        // 👇 SOLUSI: Definisikan $activeMenu di sini
        $activeMenu = 'menu'; // Nilai ini harus sama dengan yang diperiksa di sidebar.blade.php

        return view('admin.menu.index', compact('breadcrumb', 'page', 'categories', 'activeMenu'));
        // PASTIKAN Anda menyertakan 'activeMenu' dalam fungsi compact()
    }
    /**
     * Mengambil data untuk DataTables (AJAX).
     */
    public function list(Request $request)
    {
        // Eager load relasi 'category' agar tidak terjadi N+1 problem
        // PASTIKAN kolom 'image' ditambahkan di select()
        $menus = Menu::select('id', 'category_id', 'name','description', 'price', 'has_level', 'image')->with('category');

        // Filter berdasarkan category_id dari AJAX request (sesuai logika di index.blade.php)
        if ($request->category_id) {
            $menus->where('category_id', $request->category_id);
        }

        return DataTables::of($menus)
            ->addIndexColumn()
            ->addColumn('category_name', function ($menu) { // Menambahkan kolom Category Name
                return $menu->category->name ?? 'N/A';
            })
            ->addColumn('has_level_text', function ($menu) { // Menambahkan kolom Teks Level
                return $menu->has_level ? 'Ya' : 'Tidak';
            })
            ->addColumn('image', function ($menu) {
                if ($menu->image) {
                    // JALUR HARUS DIAWALI DENGAN 'storage/' UNTUK MENGAKSES SYMLINK
                    return '<img src="' . asset('storage/' . $menu->image) . '" alt="' . $menu->name . '" style="max-width: 50px; max-height: 50px; border-radius: 5px;">';
                }
                return '-';
            })
            ->addColumn('aksi', function ($menu) { // Menambahkan tombol aksi
                $btn = '<a href="' . url('/admin/menus/' . $menu->id) . '" class="btn btn-info btn-sm">Detail</a> ';
                $btn .= '<a href="' . url('/admin/menus/' . $menu->id . '/edit') . '" class="btn btn-warning btn-sm">Edit</a> ';
                $btn .= '<form class="d-inline-block" method="POST" action="' . url('/admin/menus/' . $menu->id) . '">'
                    . csrf_field() . method_field('DELETE')
                    . '<button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Apakah Anda yakin menghapus data ini?\');">Hapus</button></form>';
                return $btn;
            })
            ->rawColumns(['aksi', 'image']) // PASTIKAN 'image' ada di sini
            ->make(true);
    }
    /**
     * Menampilkan halaman tambah Menu.
     */
    public function create()
    {
        $breadcrumb = (object) [
            'title' => 'Manajemen Menu',
            'list' => ['Home', 'Menu', 'Tambah']
        ];

        $page = (object) [
            'title' => 'Tambah Menu Baru'
        ];

        // Ambil semua kategori untuk dropdown di form
        $categories = Category::all();

        $levels = Level::all(); // Ambil semua data level

        // 👇 SOLUSI: Tambahkan $activeMenu
        $activeMenu = 'menu';

        // Mengirimkan data categories ke view: admin/menu/create.blade.php
        return view('admin.menu.create', compact('breadcrumb', 'page', 'categories', 'levels', 'activeMenu'));
    }

    /**
     * Menyimpan data Menu baru ke database.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:100|unique:menus,name',
            'description' => 'nullable|string|max:255',
            'price' => 'required|integer|min:0',
            'has_level' => 'required|boolean',
            'level_ids' => 'array', // Validasi input level_ids berupa array
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // <--- Validasi untuk foto
        ]);

        $data = $request->all();

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = time() . '_' . $image->getClientOriginalName();

            // PERBAIKAN UTAMA: Gunakan storeAs() dengan parameter ketiga 'public'
            // Ini memaksa file disimpan ke storage/app/public/images/menu
            $image->storeAs('images/menu', $fileName, 'public');

            // Simpan path relatif ke database
            $data['image'] = 'images/menu/' . $fileName;
        } else {
            $data['image'] = null;
        }

        $menu = Menu::create($data); // Simpan menu dulu

        // Tambahkan baris ini untuk menyimpan ke tabel pivot:
        if ($request->has_level == '1' && $request->has('level_ids')) {
            $menu->levels()->sync($request->level_ids);
        }

        return redirect('/admin/menus')->with('success', 'Data Menu berhasil disimpan.');
    }

    /**
     * Menampilkan detail Menu.
     */
    public function show(string $id)
    {
        // Tambahkan 'levels' di dalam with()
        $menu = Menu::with(['category', 'levels'])->find($id);

        if (!$menu) {
            return redirect('/admin/menus')->with('error', 'Data Menu tidak ditemukan.');
        }

        $breadcrumb = (object) [
            'title' => 'Manajemen Menu',
            'list' => ['Home', 'Menu', 'Detail']
        ];

        $page = (object) [
            'title' => 'Detail Menu'
        ];

        $activeMenu = 'menu';

        return view('admin.menu.show', compact('breadcrumb', 'page', 'menu', 'activeMenu'));
    }
    /**
     * Menampilkan halaman edit Menu.
     */
    public function edit(string $id)
    {
        // Ambil data menu beserta relasi levels-nya
        $menu = Menu::with('levels')->find($id);

        if (!$menu) {
            return redirect('/admin/menus')->with('error', 'Data Menu tidak ditemukan.');
        }

        $breadcrumb = (object) [
            'title' => 'Manajemen Menu',
            'list' => ['Home', 'Menu', 'Edit']
        ];

        $page = (object) [
            'title' => 'Edit Menu'
        ];

        $categories = Category::all();

        // 👇 PERBAIKAN: Ambil data levels dari database
        $levels = Level::all();

        $activeMenu = 'menu';

        // 👇 PERBAIKAN: Pastikan 'levels' dimasukkan ke dalam compact
        return view('admin.menu.edit', compact('breadcrumb', 'page', 'menu', 'categories', 'levels', 'activeMenu'));
    }

    /**
     * Memperbarui data Menu di database.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:100|unique:menus,name,' . $id,
            'description' => 'nullable|string|max:255',
            'price' => 'required|integer|min:0',
            'has_level' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // <--- Validasi
        ]);

        $menu = Menu::find($id);
        $data = $request->all();

        if ($request->hasFile('image')) {
            // 1. Hapus gambar lama (jika ada) dari disk 'public'
            if ($menu->image) {
                // Gunakan Storage::disk('public')->delete()
                Storage::disk('public')->delete($menu->image);
            }

            // 2. Simpan gambar baru ke disk 'public'
            $image = $request->file('image');
            $fileName = time() . '_' . $image->getClientOriginalName();

            // Simpan ke disk 'public'
            $image->storeAs('images/menu', $fileName, 'public');
            $data['image'] = 'images/menu/' . $fileName;
        } else if ($request->input('remove_image')) {
            // 3. Logika jika checkbox hapus gambar dicentang
            if ($menu->image) {
                // Hapus dari disk 'public'
                Storage::disk('public')->delete($menu->image);
            }
            $data['image'] = null;
        }

        $menu->update($data);

        // TAMBAHKAN INI:
        if ($request->has_level == '1') {
            $menu->levels()->sync($request->level_ids ?? []);
        } else {
            $menu->levels()->detach(); // Hapus semua relasi jika has_level diubah ke "Tidak"
        }

        return redirect('/admin/menus')->with('success', 'Data Menu berhasil diubah.');
    }

    /**
     * Menghapus data Menu.
     */
    public function destroy(string $id)
    {
        $menu = Menu::find($id);

        if (!$menu) {
            return redirect('/admin/menus')->with('error', 'Data Menu tidak ditemukan.');
        }

        try {
            // 1. Putuskan relasi dengan level di tabel pivot (menu_level)
            $menu->levels()->detach();

            // 2. Hapus gambar dari storage jika ada
            if ($menu->image) {
                Storage::disk('public')->delete($menu->image);
            }

            // 3. Hapus data menu
            $menu->delete();

            return redirect('/admin/menus')->with('success', 'Data Menu berhasil dihapus.');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect('/admin/menus')->with('error', 'Data Menu gagal dihapus karena masih digunakan dalam transaksi.');
        }
    }
}
