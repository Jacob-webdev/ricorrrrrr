/**
 * Ricordella AI - Enhanced text processing tools
 */
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const contentTextarea = document.getElementById('content');
    const titleInput = document.getElementById('title');
    const titleGenButton = document.getElementById('generate-from-title');
    const aiPromptField = document.getElementById('ai-prompt-field');
    const aiActionButtons = document.querySelectorAll('.ai-action');
    const aiResultContainer = document.getElementById('ai-result-container');
    const aiResultOutput = document.getElementById('ai-result');
    const aiResultHeader = document.getElementById('ai-result-header');
    const aiApplyButton = document.getElementById('ai-apply');
    const aiCancelButton = document.getElementById('ai-cancel');
    const aiLoaderContainer = document.getElementById('ai-loader-container');

    // Check if we're on a page with AI functionality
    if (!contentTextarea || !aiActionButtons.length) return;

    // Action button selection handling
    aiActionButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            aiActionButtons.forEach(btn => btn.classList.remove('active'));

            // Add active class to clicked button
            this.classList.add('active');

            // Update prompt placeholder based on selected action
            const action = this.dataset.action;

            switch(action) {
                case 'generate':
                    aiPromptField.placeholder = "Enter instructions for text generation (e.g., 'Expand on these ideas')";
                    break;
                case 'summarize':
                    aiPromptField.placeholder = "Enter instructions for summarization (e.g., 'Focus on key points')";
                    break;
                case 'translate':
                    aiPromptField.placeholder = "Enter target language (e.g., 'English', 'Italian', 'French')";
                    break;
                case 'transcribe':
                    aiPromptField.placeholder = "Enter audio transcription instructions";
                    break;
                default:
                    aiPromptField.placeholder = "Enter instructions for AI";
            }

            // Focus the prompt field
            aiPromptField.focus();
        });
    });

    // Generate from title button
    if (titleGenButton) {
        titleGenButton.addEventListener('click', function() {
            const title = titleInput.value.trim();

            if (!title) {
                showError('Please enter a title first');
                return;
            }

            // Show the loader
            showLoader();

            // Process with AI
            const prompt = "Generate content for a note with this title: ";
            processWithAI('generate', title, prompt);
        });
    }

    // Process button
    const processButton = document.getElementById('ai-process');
    if (processButton) {
        processButton.addEventListener('click', function() {
            const activeAction = document.querySelector('.ai-action.active');
            if (!activeAction) {
                showError('Please select an AI action first');
                return;
            }

            const task = activeAction.dataset.action;
            const text = contentTextarea.value.trim();
            const prompt = aiPromptField.value.trim();

            if (!text) {
                showError('Please add some content to process');
                return;
            }

            // Show loader
            showLoader();

            // Process with AI
            processWithAI(task, text, prompt);
        });
    }

    // Apply button
    if (aiApplyButton) {
        aiApplyButton.addEventListener('click', function() {
            const result = aiResultOutput.textContent;

            // Get insertion mode
            const insertMode = document.querySelector('input[name="insert-mode"]:checked').value;

            if (insertMode === 'replace') {
                contentTextarea.value = result;
            } else {
                contentTextarea.value = result + '\n\n' + contentTextarea.value;
            }

            // Hide result container
            aiResultContainer.classList.remove('active');
        });
    }

    // Cancel button
    if (aiCancelButton) {
        aiCancelButton.addEventListener('click', function() {
            aiResultContainer.classList.remove('active');
        });
    }

    // Process with AI function
    function processWithAI(task, text, prompt) {
        // Added parameter check for special tasks
        let params = {
            task: task,
            text: text,
            prompt: prompt
        };

        // Special handling for translate
        if (task === 'translate') {
            // Extract language from prompt if possible
            const langMap = {
                'english': 'en',
                'italian': 'it',
                'french': 'fr',
                'spanish': 'es',
                'german': 'de'
            };

            const language = prompt.toLowerCase();
            Object.keys(langMap).forEach(lang => {
                if (language.includes(lang)) {
                    params.target_lang = langMap[lang];
                }
            });

            // If no language detected, default to English
            if (!params.target_lang) {
                params.target_lang = 'en';
            }
        }

        fetch('../utils/ai_processor.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(params)
        })
        .then(response => response.json())
        .then(data => {
            // Hide loader
            hideLoader();

            if (data.success) {
                // Show result
                aiResultOutput.textContent = data.result;

                // Update header based on task
                let headerText = 'AI Result';
                switch(task) {
                    case 'generate': headerText = 'Generated Text'; break;
                    case 'summarize': headerText = 'Text Summary'; break;
                    case 'translate': headerText = 'Translation'; break;
                    case 'transcribe': headerText = 'Transcribed Text'; break;
                }

                aiResultHeader.textContent = headerText;

                // Show result container
                aiResultContainer.classList.add('active');
            } else {
                showError(data.error || 'Error processing with AI');

                // If it's a model loading issue, retry
                if (data.retry) {
                    setTimeout(() => {
                        processWithAI(task, text, prompt);
                    }, data.retry_after * 1000);
                }
            }
        })
        .catch(error => {
            hideLoader();
            showError('Network error. Please check your connection.');
            console.error('Error:', error);
        });
    }

    // Show loader
    function showLoader() {
        if (aiLoaderContainer) {
            aiLoaderContainer.classList.add('active');
        }
    }

    // Hide loader
    function hideLoader() {
        if (aiLoaderContainer) {
            aiLoaderContainer.classList.remove('active');
        }
    }

    // Show error
    function showError(message) {
        const errorElement = document.getElementById('ai-error');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';

            // Hide after a few seconds
            setTimeout(() => {
                errorElement.style.display = 'none';
            }, 5000);
        }
    }
});