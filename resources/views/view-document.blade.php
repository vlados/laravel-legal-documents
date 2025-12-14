<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Breadcrumb --}}
        <nav class="text-sm mb-6">
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

        {{-- Document Control Header --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden mb-6">
            <div class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-6 py-4">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ __('legal-documents::legal-documents.document_control') }}
                </h2>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ __('legal-documents::legal-documents.document_title') }}
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $document->title }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ __('legal-documents::legal-documents.document_id') }}
                        </dt>
                        <dd class="mt-1 text-sm font-mono text-gray-900 dark:text-white">
                            {{ strtoupper($documentType->slug) }}-v{{ $document->version }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            {{ __('legal-documents::legal-documents.effective_date') }}
                        </dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ $document->published_at?->format('d.m.Y') ?? '-' }}
                        </dd>
                    </div>
                </div>
            </div>

            {{-- Status Badge --}}
            <div class="px-6 pb-4">
                @if ($document->is_current)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ __('legal-documents::legal-documents.current_version') }}
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ __('legal-documents::legal-documents.archived_version') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Revision History Table --}}
        @if ($this->versions->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden mb-6">
                <div class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        {{ __('legal-documents::legal-documents.revision_history') }}
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('legal-documents::legal-documents.revision_number') }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('legal-documents::legal-documents.revision_date') }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('legal-documents::legal-documents.reason_for_revision') }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('legal-documents::legal-documents.action') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($this->versions as $v)
                                @php
                                    $versionDocument = $documentType->documents()->where('version', $v->version)->first();
                                    $isCurrentlyViewing = $v->version === $selectedVersion;
                                @endphp
                                <tr class="{{ $isCurrentlyViewing ? 'bg-primary-50 dark:bg-primary-900/10' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                v{{ $v->version }}
                                            </span>
                                            @if ($v->is_current)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                    {{ __('legal-documents::legal-documents.current') }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $v->published_at->format('d.m.Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $versionDocument?->summary_of_changes ?? __('legal-documents::legal-documents.initial_release') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        @if ($isCurrentlyViewing)
                                            <span class="text-primary-600 dark:text-primary-400 font-medium">
                                                {{ __('legal-documents::legal-documents.currently_viewing') }}
                                            </span>
                                        @else
                                            <a
                                                href="{{ route('legal.show.version', ['slug' => $documentType->slug, 'version' => $v->version]) }}"
                                                class="text-primary-600 dark:text-primary-400 hover:underline font-medium"
                                            >
                                                {{ __('legal-documents::legal-documents.view') }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Archived Version Warning --}}
        @if (!$document->is_current)
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
                            <a href="{{ route('legal.show', $documentType->slug) }}" class="underline hover:no-underline font-medium">
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
