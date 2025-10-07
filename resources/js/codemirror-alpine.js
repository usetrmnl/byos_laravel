import { createCodeMirror, getSystemTheme, watchThemeChange } from './codemirror-core.js';
import { EditorView } from '@codemirror/view';

/**
 * Alpine.js component for CodeMirror that integrates with textarea and Livewire
 * Inspired by Filament's approach with proper state entanglement
 * @param {Object} config - Configuration object
 * @returns {Object} Alpine.js component object
 */
export function codeEditorFormComponent(config) {
    return {
        editor: null,
        textarea: null,
        isLoading: false,
        unwatchTheme: null,
        
        // Configuration
        isDisabled: config.isDisabled || false,
        language: config.language || 'html',
        state: config.state || '',
        textareaId: config.textareaId || null,
        
        /**
         * Initialize the component
         */
        async init() {
            this.isLoading = true;
            
            try {
                // Wait for textarea if provided
                if (this.textareaId) {
                    await this.waitForTextarea();
                }
                
                await this.$nextTick();
                this.createEditor();
                this.setupEventListeners();
            } finally {
                this.isLoading = false;
            }
        },
        
        /**
         * Wait for textarea to be available in the DOM
         */
        async waitForTextarea() {
            let attempts = 0;
            const maxAttempts = 50; // 5 seconds max wait
            
            while (attempts < maxAttempts) {
                this.textarea = document.getElementById(this.textareaId);
                if (this.textarea) {
                    return;
                }
                
                // Wait 100ms before trying again
                await new Promise(resolve => setTimeout(resolve, 100));
                attempts++;
            }
            
            console.error(`Textarea with ID "${this.textareaId}" not found after ${maxAttempts} attempts`);
        },
        
        /**
         * Update both Livewire state and textarea with new value
         */
        updateState(value) {
            this.state = value;
            if (this.textarea) {
                this.textarea.value = value;
                this.textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },

        /**
         * Create the CodeMirror editor instance
         */
        createEditor() {
            // Clean up any existing editor first
            if (this.editor) {
                this.editor.destroy();
            }
            
            const effectiveTheme = this.getEffectiveTheme();
            const initialValue = this.textarea ? this.textarea.value : this.state;
            
            this.editor = createCodeMirror(this.$refs.editor, {
                value: initialValue || '',
                language: this.language,
                theme: effectiveTheme,
                readOnly: this.isDisabled,
                onChange: (value) => this.updateState(value),
                onUpdate: (value) => this.updateState(value),
                onBlur: () => {
                    if (this.editor) {
                        this.updateState(this.editor.state.doc.toString());
                    }
                }
            });
        },
        
        /**
         * Get effective theme
         */
        getEffectiveTheme() {
            return getSystemTheme();
        },
        
        /**
         * Update editor content with new value
         */
        updateEditorContent(value) {
            if (this.editor && value !== this.editor.state.doc.toString()) {
                this.editor.dispatch({
                    changes: {
                        from: 0,
                        to: this.editor.state.doc.length,
                        insert: value
                    }
                });
            }
        },

        /**
         * Setup event listeners for theme changes and state synchronization
         */
        setupEventListeners() {
            // Watch for state changes from Livewire
            this.$watch('state', (newValue) => {
                this.updateEditorContent(newValue);
            });
            
            // Watch for disabled state changes
            this.$watch('isDisabled', (newValue) => {
                if (this.editor) {
                    this.editor.dispatch({
                        effects: EditorView.editable.reconfigure(!newValue)
                    });
                }
            });
            
            // Watch for textarea changes (from Livewire updates)
            if (this.textarea) {
                this.textarea.addEventListener('input', (event) => {
                    this.updateEditorContent(event.target.value);
                    this.state = event.target.value;
                });
                
                // Listen for Livewire updates that might change the textarea value
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                            this.updateEditorContent(this.textarea.value);
                            this.state = this.textarea.value;
                        }
                    });
                });
                
                observer.observe(this.textarea, {
                    attributes: true,
                    attributeFilter: ['value']
                });
            }
            
            // Listen for theme changes
            this.unwatchTheme = watchThemeChange(() => {
                this.recreateEditor();
            });
        },
        
        /**
         * Recreate the editor (useful for theme changes)
         */
        async recreateEditor() {
            if (this.editor) {
                this.editor.destroy();
                this.editor = null;
                await this.$nextTick();
                this.createEditor();
            }
        },
        
        
        /**
         * Clean up resources when component is destroyed
         */
        destroy() {
            if (this.editor) {
                this.editor.destroy();
                this.editor = null;
            }
            if (this.unwatchTheme) {
                this.unwatchTheme();
            }
        }
    };
}

