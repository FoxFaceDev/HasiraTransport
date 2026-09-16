@extends('layouts.layout')

@section('content')
<div x-data="roleManager()" @keydown.escape.window="showEdit = false" class="min-w-0 space-y-4">
    <section class="glass-panel p-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">ڕۆڵ و دەسەڵاتەکان</h1>
                <p class="text-slate-500 mt-1">ڕۆڵ دروست بکە و بە چێکبۆکس دەسەڵاتی وردی بۆ دیاری بکە</p>
            </div>
            <a href="{{ route('users.index') }}" class="btn-secondary px-4 py-2.5 rounded-xl font-semibold">گەڕانەوە بۆ بەکارهێنەران</a>
        </div>
    </section>

    <div class="grid xl:grid-cols-[minmax(0,1fr)_24rem] gap-4 items-start">
        <section class="min-w-0 space-y-4">
            @foreach($roles as $role)
            <article class="glass-panel p-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <div class="flex items-center gap-2"><h2 class="text-lg font-bold text-slate-900">{{ str_replace('_', ' ', $role->name) }}</h2>@if($role->name === 'super_admin')<span class="bg-blue-100 text-blue-800 border border-blue-200 px-2.5 py-1 rounded-full text-xs font-bold">پارێزراو</span>@endif</div>
                        <p class="text-slate-500 text-xs mt-1">{{ $role->users_count }} بەکارهێنەر · {{ $role->permissions->count() }} دەسەڵات</p>
                    </div>
                    @if($role->name !== 'super_admin')
                    <div class="flex gap-2">
                        <button type="button" @click="openEdit({{ Js::from(['id' => $role->id, 'name' => $role->name, 'permissions' => $role->permissions->pluck('name')->values()]) }})" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg">دەستکاری</button>
                        <form action="{{ route('roles.destroy', $role) }}" method="POST" onsubmit="return confirm('دڵنیایت لە سڕینەوەی ئەم ڕۆڵە؟')">@csrf @method('DELETE')<button class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded-lg">سڕینەوە</button></form>
                    </div>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @forelse($role->permissions as $permission)
                        @php($label = $permissionLabels->get($permission->name, $permission->name))
                        <span class="bg-slate-200 text-slate-800 border border-slate-300 px-2.5 py-1 rounded-lg text-xs">{{ $label }}</span>
                    @empty
                        <span class="text-slate-500">هیچ دەسەڵاتێک دیاری نەکراوە.</span>
                    @endforelse
                </div>
            </article>
            @endforeach
        </section>

        <aside class="glass-panel p-5 xl:sticky xl:top-4">
            <h2 class="text-lg font-bold text-slate-900">دروستکردنی ڕۆڵ</h2>
            <p class="text-sm text-slate-500 mt-1 mb-5">ناو و دەسەڵاتەکانی ڕۆڵە نوێیەکە دیاری بکە.</p>
            <form action="{{ route('roles.store') }}" method="POST" class="space-y-5">
                @csrf
                <div><label class="form-label block mb-1">ناوی ڕۆڵ</label><input name="name" value="{{ old('name') }}" class="glass-input w-full px-4 py-2.5 rounded-xl" required></div>
                @include('roles.partials.permission-checkboxes', ['selectedPermissions' => old('permissions', [])])
                <button class="btn-primary w-full py-2.5 rounded-xl font-bold">دروستکردنی ڕۆڵ</button>
            </form>
        </aside>
    </div>

    <div x-show="showEdit" x-transition.opacity class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
        <div @click.outside="showEdit = false" class="glass-card w-full max-w-3xl max-h-[90vh] overflow-y-auto p-6">
            <div class="flex justify-between items-center mb-5"><div><h2 class="text-xl font-bold">دەستکاریکردنی ڕۆڵ</h2><p class="text-sm text-slate-500">گۆڕینی ناو و دەسەڵاتە دیاریکراوەکان</p></div><button type="button" @click="showEdit = false" class="text-2xl text-slate-500">×</button></div>
            <form :action="editAction" method="POST" class="space-y-5">
                @csrf
                @method('PUT')
                <div><label class="form-label block mb-1">ناوی ڕۆڵ</label><input name="name" x-model="editing.name" class="glass-input w-full px-4 py-2.5 rounded-xl" required></div>
                <div class="grid md:grid-cols-2 gap-3">
                    @foreach($permissionGroups as $group => $permissions)
                    <fieldset class="bg-slate-100 border border-slate-300 rounded-xl p-4">
                        <legend class="font-bold text-slate-900 px-2">{{ $group }}</legend>
                        <div class="space-y-2.5 mt-1">
                            @foreach($permissions as $permission => $label)
                            <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" name="permissions[]" value="{{ $permission }}" x-model="editing.permissions" class="rounded border-slate-400 text-blue-600 focus:ring-blue-500"><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                    @endforeach
                </div>
                <div class="flex justify-end gap-2"><button type="button" @click="showEdit = false" class="btn-secondary px-4 py-2 rounded-xl">پاشگەزبوونەوە</button><button class="btn-primary px-5 py-2 rounded-xl font-bold">نوێکردنەوە</button></div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function roleManager() {
    return {
        showEdit: false,
        editAction: '',
        editing: { id: null, name: '', permissions: [] },
        openEdit(role) {
            this.editing = { ...role, permissions: [...role.permissions] };
            this.editAction = `/roles/${role.id}`;
            this.showEdit = true;
        }
    }
}
</script>
@endpush
@endsection
