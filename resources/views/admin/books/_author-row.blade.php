{{-- One credit row of the authors repeater; $index is a number or "__INDEX__" (JS template). --}}
<div class="grid grid-cols-[1fr_9rem_4.5rem_auto] items-start gap-2" data-row>
    <x-form.select name="authors[{{ $index }}][id]" id="author-{{ $index }}-id" :options="$authorOptions" :value="$credit['id'] ?? null"
                   placeholder="—" :aria-label="__('ui.admin.fields.author')" />
    <x-form.select name="authors[{{ $index }}][role]" id="author-{{ $index }}-role" :options="$roles" :value="$credit['role'] ?? null"
                   :aria-label="__('ui.admin.fields.role')" />
    <x-form.input type="number" name="authors[{{ $index }}][sort_order]" id="author-{{ $index }}-order" :value="$credit['sort_order'] ?? null"
                  min="0" max="99" :aria-label="__('ui.admin.fields.sort_order')" />
    <button type="button" class="px-2 py-1 text-lg leading-none text-stone-400 hover:text-red-700" data-remove-row
            aria-label="{{ __('ui.admin.remove_author') }}" title="{{ __('ui.admin.remove_author') }}">×</button>
</div>
