{{--
    Every payload and every produced HTML string below is printed with {{ }}:
    the page shows HTML source as text and never renders the payloads.
--}}
<x-layouts.app :title="__('lab.xss.title')">
    <p class="-mt-2 mb-6 max-w-3xl text-sm text-stone-600">{{ __('lab.xss.intro') }}</p>

    <div class="space-y-8">
        @foreach ($results as $key => $result)
            <x-card id="input-{{ $key }}">
                <x-slot:header>{{ __('lab.xss.inputs.'.$key) }}</x-slot:header>

                <h3 class="mb-1 text-xs font-semibold uppercase text-stone-400">{{ __('lab.xss.input') }}</h3>
                <pre class="mb-4 overflow-x-auto rounded bg-stone-100 p-3 text-xs" data-input>{{ $result['input'] }}</pre>

                <div class="grid gap-4 xl:grid-cols-2">
                    @foreach ($result['outputs'] as $approach => $output)
                        <div @class(['min-w-0 rounded border p-3', 'border-red-300 bg-red-50' => $output['dangerous'], 'border-stone-200' => ! $output['dangerous']])
                             data-approach="{{ $approach }}" data-dangerous="{{ $output['dangerous'] ? 'true' : 'false' }}">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <pre class="overflow-x-auto text-xs font-semibold text-stone-700">{{ $approaches[$approach]['code'] }}</pre>
                                <span @class(['shrink-0 rounded px-2 py-0.5 text-xs', 'bg-red-200 text-red-900' => $output['dangerous'], 'bg-emerald-100 text-emerald-800' => ! $output['dangerous']])>
                                    {{ $output['dangerous'] ? __('lab.xss.dangerous') : __('lab.xss.safe') }}
                                </span>
                            </div>
                            <h4 class="mb-1 text-xs text-stone-400">{{ __('lab.xss.output') }}</h4>
                            <pre class="overflow-x-auto rounded bg-stone-900 p-2 text-xs text-stone-100">{{ $output['html'] !== '' ? $output['html'] : __('lab.xss.empty_output') }}</pre>
                            <p class="mt-2 text-xs text-stone-500">{{ __('lab.xss.approaches.'.$approach) }}</p>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endforeach

        <x-card id="url-validation">
            <x-slot:header>{{ __('lab.xss.url_title') }}</x-slot:header>
            <p class="mb-4 text-sm text-stone-600">{{ __('lab.xss.url_intro') }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-stone-400">
                        <tr>
                            <th class="py-1 pr-4">{{ __('lab.xss.input') }}</th>
                            <th class="py-1 pr-4"><code>{{ __('lab.xss.url_rule') }}</code></th>
                            <th class="py-1"><code>{{ __('lab.xss.url_http_rule') }}</code></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $key => $result)
                            <tr class="border-t border-stone-100">
                                <td class="py-1 pr-4"><code class="text-xs">{{ Str::limit($result['input'], 60) }}</code></td>
                                @foreach (['url', 'url_http'] as $rule)
                                    <td @class(['py-1 pr-4', 'text-emerald-700' => $result[$rule], 'text-red-700' => ! $result[$rule]])>
                                        {{ $result[$rule] ? __('lab.xss.passes') : __('lab.xss.fails') }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-layouts.app>
