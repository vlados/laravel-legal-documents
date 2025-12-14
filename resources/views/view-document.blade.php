<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-8">
            <nav class="text-sm mb-4">
                <ol class="flex items-center space-x-2 text-gray-500 dark:text-gray-400">
                    <li>
                        <a href="{{ url('/') }}" class="hover:text-gray-700 dark:hover:text-gray-300">
                            {{ __('legal-documents::legal-documents.home') }}
                        </a>
                    </li>
                    <li>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </li>
                    <li class="text-gray-900 dark:text-white font-medium">
                        {{ $documentType->name }}
                    </li>
                </ol>
            </nav>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $document->title }}
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                        @if ($document->is_current)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                {{ __('legal-documents::legal-documents.current_version') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                {{ __('legal-documents::legal-documents.archived_version') }}
                            </span>
                        @endif
                        <span>
                            {{ __('legal-documents::legal-documents.version', ['version' => $document->version]) }}
                        </span>
                        @if ($document->published_at)
                            <span>
                                {{ __('legal-documents::legal-documents.published', ['date' => $document->published_at->format('d.m.Y')]) }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Version Selector --}}
                @if ($this->versions->count() > 1)
                    <div class="relative" x-data="{ open: false }">
                        <button
                            type="button"
                            @click="open = !open"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ __('legal-documents::legal-documents.version_history') }}
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            @click.away="open = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-64 rounded-lg bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5 z-50"
                            style="display: none;"
                        >
                            <div class="py-1 max-h-64 overflow-y-auto">
                                @foreach ($this->versions as $v)
                                    <button
                                        type="button"
                                        wire:click="selectVersion('{{ $v->version }}')"
                                        class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 {{ $v->version === $selectedVersion ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                v{{ $v->version }}
                                            </span>
                                            @if ($v->is_current)
                                                <span class="text-xs text-green-600 dark:text-green-400">
                                                    {{ __('legal-documents::legal-documents.current') }}
                                                </span>
                                            @endif
                                        </div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $v->published_at->format('d.m.Y') }}
                                        </span>
                                        @if ($v->version === $selectedVersion)
                                            <span class="text-xs text-primary-600 dark:text-primary-400 block">
                                                {{ __('legal-documents::legal-documents.currently_viewing') }}
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Summary of Changes (if viewing older version) --}}
        @if (!$document->is_current && $document->summary_of_changes)
            <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                <div class="flex">
                    <svg class="w-5 h-5 text-amber-400 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="text-sm font-medium text-amber-800 dark:text-amber-300">
                            {{ __('legal-documents::legal-documents.viewing_archived_version') }}
                        </h3>
                        <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                            {{ __('legal-documents::legal-documents.not_current_version') }}
                            <a href="{{ route('legal.show', $documentType->slug) }}" class="underline hover:no-underline">
                                {{ __('legal-documents::legal-documents.view_current_version') }}
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Document Content --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-8 sm:px-8 sm:py-10">
                <article class="prose prose-lg dark:prose-invert max-w-none">
                    {!! $document->content !!}
                </article>
            </div>
        </div>

        {{-- Footer Info --}}
        <div class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
            <p>
                {{ __('legal-documents::legal-documents.last_updated', ['date' => $document->updated_at->format('d.m.Y H:i')]) }}
            </p>
            @if ($this->versions->count() > 1)
                <p class="mt-1">
                    {{ __('legal-documents::legal-documents.document_versions_count', ['count' => $this->versions->count()]) }}
                </p>
            @endif
        </div>

        {{-- Navigation to other legal documents --}}
        @php
            $otherTypes = \Vlados\LegalDocuments\Models\LegalDocumentType::query()
                ->where('id', '!=', $documentType->id)
                ->whereHas('currentDocument')
                ->ordered()
                ->get();
        @endphp

        @if ($otherTypes->isNotEmpty())
            <div class="mt-12 border-t border-gray-200 dark:border-gray-700 pt-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    {{ __('legal-documents::legal-documents.other_legal_documents') }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($otherTypes as $type)
                        <a
                            href="{{ route('legal.show', $type->slug) }}"
                            class="block p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 transition-colors"
                        >
                            <h3 class="font-medium text-gray-900 dark:text-white">
                                {{ $type->name }}
                            </h3>
                            @if ($type->description)
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ Str::limit($type->description, 100) }}
                                </p>
                            @endif
                            <span class="mt-2 inline-flex items-center text-sm text-primary-600 dark:text-primary-400">
                                {{ __('legal-documents::legal-documents.read_document') }}
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
