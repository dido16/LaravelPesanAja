@extends('layout.template')

@section('content')
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">{{ $page->title }}</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ url('admin/menus') }}" class="form-horizontal" enctype="multipart/form-data">
                @csrf
                <div class="form-group row">
                    <label class="col-2 control-label col-form-label">Kategori</label>
                    <div class="col-10">
                        <select class="form-control" name="category_id" required>
                            <option value="">- Pilih Kategori -</option>
                            @foreach ($categories as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-2 control-label col-form-label">Nama Menu</label>
                    <div class="col-10">
                        <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-2 control-label col-form-label">Deskripsi</label>
                    <div class="col-10">
                        <input type="text" class="form-control" name="description" value="{{ old('description') }}"
                            required>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-2 control-label col-form-label">Harga</label>
                    <div class="col-10">
                        <input type="number" class="form-control" name="price" value="{{ old('price') }}" required>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-2 control-label col-form-label">Punya Level?</label>
                    <div class="col-10">
                        <select class="form-control" name="has_level" id="has_level" required>
                            <option value="0">Tidak</option>
                            <option value="1">Ya (Level Kepedasan)</option>
                        </select>
                    </div>
                </div>

                <div id="level_container" style="display: none;">
                    <div class="form-group row">
                        <label class="col-2 control-label col-form-label">Pilih Level Tersedia</label>
                        <div class="col-10">
                            <div class="row">
                                @foreach ($levels as $lv)
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input class="custom-control-input" type="checkbox" name="level_ids[]"
                                                id="lv{{ $lv->id }}" value="{{ $lv->id }}">
                                            <label for="lv{{ $lv->id }}" class="custom-control-label">
                                                {{ $lv->name }} </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <small class="form-text text-muted">Ceklis level kepedasan apa saja yang boleh dipilih untuk
                                menu ini.</small>
                        </div>
                    </div>
                </div>

                <div class="form-group row">
                    <label class="col-2 control-label col-form-label">Foto Menu</label>
                    <div class="col-10">
                        <input type="file" class="form-control" name="image">
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-2 control-label col-form-label"></label>
                    <div class="col-10">
                        <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                        <a class="btn btn-sm btn-default ml-1" href="{{ url('admin/menus') }}">Kembali</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $(document).ready(function() {
            $('#has_level').change(function() {
                if ($(this).val() == '1') {
                    $('#level_container').fadeIn();
                } else {
                    $('#level_container').fadeOut();
                    $('input[name="level_ids[]"]').prop('checked', false);
                }
            });
        });
    </script>
@endpush
