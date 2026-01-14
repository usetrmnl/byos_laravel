<?php

use Livewire\Component;
use Livewire\Attributes\Modelable;

new class extends Component
{
    #[Modelable]
    public $model = '';
    public $language = 'html';
    public $theme = 'auto';
    public $readonly = false;
    public $placeholder = '';
    public $height = '200px';
    public $id = '';

    public function mount($language = 'html', $theme = 'auto', $readonly = false, $placeholder = '', $height = '200px', $id = '')
    {
        $this->language = $language;
        $this->theme = $theme;
        $this->readonly = $readonly;
        $this->placeholder = $placeholder;
        $this->height = $height;
        $this->id = $id;
    }


    public function toJSON()
    {
        return json_encode([
            'model' => $this->model,
            'language' => $this->language,
            'theme' => $this->theme,
            'readonly' => $this->readonly,
            'placeholder' => $this->placeholder,
            'height' => $this->height,
            'id' => $this->id,
        ]);
    }
} ?>


<div
    x-data="codeMirrorComponent(@js($language), @js($theme), @js($readonly), @js($placeholder), @js($height), @js($id ?: uniqid()))"
    x-init="init()"
    wire:ignore
    class="codemirror-container"
    @if($id) id="{{ $id }}" @endif
    autocomplete="off"
>
    <!-- Loading state -->
    <div x-show="isLoading" class="flex items-center justify-center p-4 border border-gray-300 rounded-md" style="height: {{ $height }};">
        <div class="flex items-center space-x-2 text-gray-500">
            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Loading editor...</span>
        </div>
    </div>

    <!-- Editor container -->
    <div x-show="!isLoading" x-ref="editor" style="height: {{ $height }};"></div>
</div>
