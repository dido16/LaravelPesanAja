@extends('layout.template')

@section('content')
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">{{ $page->title }}</h3>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover table-sm">
                <tr>
                    <th>Foto</th>
                    <td>
                        @if ($menu->image)
                            <img src="{{ asset('storage/' . $menu->image) }}" style="max-width: 200px; border-radius: 10px;">
                        @else
                            <span class="badge badge-secondary">Tidak ada foto</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>ID</th>
                    <td>{{ $menu->id }}</td>
                </tr>
                <tr>
                    <th>Nama Menu</th>
                    <td>{{ $menu->name }}</td>
                </tr>
                <tr>
                    <th>Kategori</th>
                    <td>{{ $menu->category->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Deskripsi</th>
                    <td>{{ $menu->description }}</td>
                </tr>
                <tr>
                    <th>Harga</th>
                    <td>Rp {{ number_format($menu->price, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Status Level</th>
                    <td>
                        <span class="badge {{ $menu->has_level ? 'badge-success' : 'badge-secondary' }}">
                            {{ $menu->has_level ? 'Memiliki Level' : 'Tanpa Level' }}
                        </span>
                    </td>
                </tr>
                @if ($menu->has_level)
                    <tr>
                        <th>Daftar Level Tersedia</th>
                        <td>
                            @foreach ($menu->levels as $lv)
                                <span class="badge badge-info">{{ $lv->name }}</span>
                            @endforeach
                        </td>
                    </tr>
                @endif
            </table>
            <div class="mt-3">
                <a href="{{ url('admin/menus') }}" class="btn btn-sm btn-default">Kembali</a>
            </div>
        </div>
    </div>
@endsection
