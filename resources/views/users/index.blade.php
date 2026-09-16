@extends('layouts.layout')

@section('content')
<div x-data="userManager()" @keydown.escape.window="closeModals()" class="space-y-4">
    <section class="glass-panel p-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">بەکارهێنەران</h1>
                <p class="text-slate-500 mt-1">دروستکردنی هەژمار و دیاریکردنی ڕۆڵی هەر بەکارهێنەرێک</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @role('super_admin')
                <a href="{{ route('roles.index') }}" class="btn-secondary px-4 py-2.5 rounded-xl font-semibold">ڕۆڵ و دەسەڵاتەکان</a>
                @endrole
                <button type="button" @click="showCreate = true" class="btn-primary px-5 py-2.5 rounded-xl font-semibold">+ بەکارهێنەری نوێ</button>
            </div>
        </div>
    </section>

    <section class="glass-panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr>
                        <th class="px-5 py-4">#</th>
                        <th class="px-5 py-4">ناو</th>
                        <th class="px-5 py-4">ئیمەیڵ</th>
                        <th class="px-5 py-4">ڕۆڵ</th>
                        <th class="px-5 py-4">بەرواری دروستکردن</th>
                        <th class="px-5 py-4">کردارەکان</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td class="px-5 py-4">{{ $loop->iteration }}</td>
                        <td class="px-5 py-4 font-bold">{{ $user->name }}</td>
                        <td class="px-5 py-4" dir="ltr">{{ $user->email }}</td>
                        <td class="px-5 py-4">
                            @forelse($user->roles as $role)
                                <span class="inline-flex bg-blue-100 border border-blue-200 px-3 py-1 rounded-full text-xs font-bold">{{ str_replace('_', ' ', $role->name) }}</span>
                            @empty
                                <span class="text-rose-700">بێ ڕۆڵ</span>
                            @endforelse
                        </td>
                        <td class="px-5 py-4">{{ $user->created_at?->format('Y-m-d') }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <button type="button"
                                    @click="openEdit({{ Js::from(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->roles->first()?->name]) }})"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg transition-colors">دەستکاری</button>
                                @if(!auth()->user()->is($user))
                                <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('دڵنیایت لە سڕینەوەی ئەم بەکارهێنەرە؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded-lg transition-colors">سڕینەوە</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center">هیچ بەکارهێنەرێک نییە.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div x-show="showCreate" x-transition.opacity class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
        <div @click.outside="showCreate = false" class="glass-card w-full max-w-lg p-6">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-xl font-bold">بەکارهێنەری نوێ</h2>
                <button type="button" @click="showCreate = false" class="text-2xl text-slate-500">×</button>
            </div>
            <form action="{{ route('users.store') }}" method="POST" class="space-y-4">
                @csrf
                <div><label class="form-label block mb-1">ناوی تەواو</label><input name="name" value="{{ old('name') }}" class="glass-input w-full px-4 py-2.5 rounded-xl" required></div>
                <div><label class="form-label block mb-1">ئیمەیڵ</label><input type="email" name="email" value="{{ old('email') }}" dir="ltr" class="glass-input w-full px-4 py-2.5 rounded-xl" required></div>
                <div><label class="form-label block mb-1">ڕۆڵ</label><select name="role" class="glass-input w-full px-4 py-2.5 rounded-xl" required><option value="">ڕۆڵ هەڵبژێرە</option>@foreach($roles as $role)<option value="{{ $role->name }}">{{ str_replace('_', ' ', $role->name) }}</option>@endforeach</select></div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div><label class="form-label block mb-1">وشەی نهێنی</label><input type="password" name="password" class="glass-input w-full px-4 py-2.5 rounded-xl" required></div>
                    <div><label class="form-label block mb-1">دووبارەکردنەوە</label><input type="password" name="password_confirmation" class="glass-input w-full px-4 py-2.5 rounded-xl" required></div>
                </div>
                <div class="flex justify-end gap-2 pt-3"><button type="button" @click="showCreate = false" class="btn-secondary px-4 py-2 rounded-xl">پاشگەزبوونەوە</button><button class="btn-primary px-5 py-2 rounded-xl font-bold">پاشەکەوتکردن</button></div>
            </form>
        </div>
    </div>

    <div x-show="showEdit" x-transition.opacity class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
        <div @click.outside="showEdit = false" class="glass-card w-full max-w-lg p-6">
            <div class="flex items-center justify-between mb-5"><h2 class="text-xl font-bold">دەستکاریکردنی بەکارهێنەر</h2><button type="button" @click="showEdit = false" class="text-2xl text-slate-500">×</button></div>
            <form :action="editAction" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div><label class="form-label block mb-1">ناوی تەواو</label><input name="name" x-model="editing.name" class="glass-input w-full px-4 py-2.5 rounded-xl" required></div>
                <div><label class="form-label block mb-1">ئیمەیڵ</label><input type="email" name="email" x-model="editing.email" dir="ltr" class="glass-input w-full px-4 py-2.5 rounded-xl" required></div>
                <div><label class="form-label block mb-1">ڕۆڵ</label><select name="role" x-model="editing.role" class="glass-input w-full px-4 py-2.5 rounded-xl" required>@foreach($roles as $role)<option value="{{ $role->name }}">{{ str_replace('_', ' ', $role->name) }}</option>@endforeach</select></div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div><label class="form-label block mb-1">وشەی نهێنی نوێ</label><input type="password" name="password" class="glass-input w-full px-4 py-2.5 rounded-xl" placeholder="ئەگەر ناگۆڕدرێت بەتاڵ بێت"></div>
                    <div><label class="form-label block mb-1">دووبارەکردنەوە</label><input type="password" name="password_confirmation" class="glass-input w-full px-4 py-2.5 rounded-xl"></div>
                </div>
                <div class="flex justify-end gap-2 pt-3"><button type="button" @click="showEdit = false" class="btn-secondary px-4 py-2 rounded-xl">پاشگەزبوونەوە</button><button class="btn-primary px-5 py-2 rounded-xl font-bold">نوێکردنەوە</button></div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function userManager() {
    return {
        showCreate: {{ $errors->any() && old('email') ? 'true' : 'false' }},
        showEdit: false,
        editAction: '',
        editing: { id: null, name: '', email: '', role: '' },
        openEdit(user) {
            this.editing = user;
            this.editAction = `/users/${user.id}`;
            this.showEdit = true;
        },
        closeModals() {
            this.showCreate = false;
            this.showEdit = false;
        }
    }
}
</script>
@endpush
@endsection
