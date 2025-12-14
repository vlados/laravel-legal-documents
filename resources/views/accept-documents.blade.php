<div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    {{ __('legal-documents::legal-documents.legal_documents') }}
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    {{ __('legal-documents::legal-documents.please_review_documents') }}
                </p>

                @if ($errors->has('acceptance'))
                    <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <p class="text-red-600 dark:text-red-400 text-sm">
                            {{ $errors->first('acceptance') }}
                        </p>
                    </div>
                @endif

                <div class="space-y-4">
                    @foreach ($this->pendingDocuments as $document)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        id="doc-{{ $document->id }}"
                                        wire:click="toggleAcceptance({{ $document->id }})"
                                        @checked(in_array($document->id, $acceptedIds))
                                        class="mt-1 h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                    >
                                    <div>
                                        <label for="doc-{{ $document->id }}" class="font-medium text-gray-900 dark:text-white cursor-pointer">
                                            {{ $document->type->name }}
                                        </label>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('legal-documents::legal-documents.version', ['version' => $document->version]) }}
                                            @if ($document->published_at)
                                                &middot; {{ $document->published_at->format('d.m.Y') }}
                                            @endif
                                        </p>
                                        @if ($document->summary_of_changes)
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                <strong>{{ __('legal-documents::legal-documents.changes') }}</strong> {{ $document->summary_of_changes }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    wire:click="viewDocument({{ $document->id }})"
                                    class="text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 text-sm font-medium"
                                >
                                    {{ __('legal-documents::legal-documents.view') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <button
                        type="button"
                        wire:click="acceptAll"
                        class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                    >
                        {{ __('legal-documents::legal-documents.accept_all') }}
                    </button>

                    <button
                        type="button"
                        wire:click="submit"
                        @disabled(count($acceptedIds) !== count($this->pendingDocuments))
                        class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        {{ __('legal-documents::legal-documents.continue') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Document Viewer Modal --}}
    @if ($this->viewingDocument)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" wire:click="closeDocument"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                                {{ $this->viewingDocument->title }}
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $this->viewingDocument->type->name }} &middot; {{ __('legal-documents::legal-documents.version', ['version' => $this->viewingDocument->version]) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="closeDocument"
                            class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                        >
                            <span class="sr-only">{{ __('legal-documents::legal-documents.close') }}</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-4 max-h-[60vh] overflow-y-auto">
                        <div class="prose dark:prose-invert max-w-none">
                            {!! $this->viewingDocument->content !!}
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                        <button
                            type="button"
                            wire:click="closeDocument"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            {{ __('legal-documents::legal-documents.close') }}
                        </button>
                        @unless (in_array($this->viewingDocument->id, $acceptedIds))
                            <button
                                type="button"
                                wire:click="toggleAcceptance({{ $this->viewingDocument->id }})"
                                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
                            >
                                {{ __('legal-documents::legal-documents.accept') }}
                            </button>
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
