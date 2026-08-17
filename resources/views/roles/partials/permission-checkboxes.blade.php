<div class="space-y-3">
    <div class="flex items-center justify-between">
        <label class="form-label">دەسەڵاتەکان</label>
        <span class="text-xs text-slate-500">هەر دانەیەک دەتەوێت هەڵیبژێرە</span>
    </div>
    @foreach($permissionGroups as $group => $permissions)
    <fieldset class="bg-slate-100 border border-slate-300 rounded-xl p-4">
        <legend class="font-bold text-slate-900 px-2">{{ $group }}</legend>
        <div class="space-y-2.5 mt-1">
            @foreach($permissions as $permission => $label)
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $selectedPermissions, true)) class="rounded border-slate-400 text-blue-600 focus:ring-blue-500">
                <span>{{ $label }}</span>
            </label>
            @endforeach
        </div>
    </fieldset>
    @endforeach
</div>
