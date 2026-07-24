@forelse($categories->where('type', $type) as $category)
    <div class="category-row">
        <span class="category-icon-badge {{ $type }}"><span class="material-symbols-rounded">{{ $category->icon ?? 'more_horiz' }}</span></span>
        <div>
            <strong>{{ $category->name }}</strong>
            <small>{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</small>
        </div>
        <div class="category-actions">
            <button data-modal="edit-{{ $category->id }}" class="icon-button" title="Edit">✎</button>
            <form method="post" action="{{ route('categories.destroy', $category) }}">@csrf @method('DELETE')<button class="icon-button" title="Hapus">×</button></form>
        </div>
    </div>
    <dialog id="edit-{{ $category->id }}">
        <div class="modal-head"><h2>Edit kategori</h2><button class="close icon-button">×</button></div>
        <form method="post" action="{{ route('categories.update', $category) }}" class="form-stack">
            @csrf @method('PATCH')
            <label>Nama kategori<input name="name" value="{{ $category->name }}" required></label>
            <label>Ikon kategori
                <div class="icon-picker">
                    @foreach(config('category_icons') as $icon => $label)
                        <label title="{{ $label }}"><input type="radio" name="icon" value="{{ $icon }}" @checked(($category->icon ?? 'more_horiz') === $icon)><span class="material-symbols-rounded">{{ $icon }}</span></label>
                    @endforeach
                </div>
            </label>
            <button class="button primary wide">Simpan perubahan</button>
        </form>
    </dialog>
@empty
    <p class="empty-copy">Belum ada kategori.</p>
@endforelse
