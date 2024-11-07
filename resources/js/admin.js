document.addEventListener('DOMContentLoaded', function() {
	const form = document.getElementById('survey-edit-form');
	const questionType = document.getElementById('question-type');
	const answerOptionsSection = document.querySelector('.answer-options');
	const answerChoices = document.getElementById('answer-choices');
	const addAnswerBtn = document.querySelector('.add-answer');
	const saveTypeInput = document.getElementById('save_type');
	const submitButtons = document.querySelectorAll('.submitbox button[type="submit"]');

	if (!form || !questionType || !answerOptionsSection || !answerChoices || !addAnswerBtn || !saveTypeInput) {
		return;
	}

	// Helper to create error message
	function createErrorMessage(message) {
		const div = document.createElement('div');
		div.className = 'error-message';
		div.textContent = message;
		return div;
	}

	// Helper to add error state to field
	function addErrorState(element, message) {
		// Remove any existing error state
		removeErrorState(element);
		
		// Find the appropriate wrapper or use the element itself
		let targetElement;
		if (element.id === 'question-content') {
			targetElement = element.closest('.question-content-wrapper');
		} else if (element.classList.contains('answer-options')) {
			targetElement = element.querySelector('.answer-choices-wrapper');
		} else {
			targetElement = element;
			element.classList.add('error');
			// For elements without wrappers, add error message after the input
			const errorMessage = createErrorMessage(message);
			element.parentNode.insertBefore(errorMessage, element.nextSibling);
			return;
		}
		
		// Add error class
		targetElement.classList.add('error');
		
		// Add error message after the wrapper
		const errorMessage = createErrorMessage(message);
		targetElement.appendChild(errorMessage);
	}

	// Helper to remove error state from field
	function removeErrorState(element) {
		// Find the appropriate wrapper or use the element itself
		let targetElement;
		if (element.id === 'question-content') {
			targetElement = element.closest('.question-content-wrapper');
		} else if (element.classList.contains('answer-options')) {
			targetElement = element.querySelector('.answer-choices-wrapper');
		} else {
			targetElement = element;
			element.classList.remove('error');
			// For elements without wrappers, find and remove error message
			const nextSibling = element.nextSibling;
			if (nextSibling && nextSibling.classList && nextSibling.classList.contains('error-message')) {
				nextSibling.remove();
			}
			return;
		}
		
		// Remove error class
		targetElement.classList.remove('error');
		
		// Remove any existing error message
		const existingError = targetElement.querySelector('.error-message');
		if (existingError) {
			existingError.remove();
		}
	}

	// Toggle answer options based on question type
	function toggleAnswerOptions() {
		answerOptionsSection.style.display = 
			questionType.value === 'multiple-choice' ? 'table-row' : 'none';
			
		// Clear any error states when switching types
		clearAllErrors();
	}

	// Clear all error states
	function clearAllErrors() {
		document.querySelectorAll('.error-message').forEach(msg => msg.remove());
		document.querySelectorAll('.error').forEach(element => {
			element.classList.remove('error');
		});
	}

	// Create new answer input
	function createAnswerInput(value = '') {
		const div = document.createElement('div');
		div.className = 'answer-choice';
		
		div.innerHTML = `
			<input type="text" name="answers[]" value="${value}" class="regular-text">
			<button type="button" class="button remove-answer">Remove</button>
		`;

		return div;
	}

	// Add new answer choice
	function addAnswerChoice() {
		const newAnswer = createAnswerInput();
		answerChoices.appendChild(newAnswer);
		
		// Clear any answer-related errors when adding new answers
		removeErrorState(answerOptionsSection);
	}

	// Remove answer choice
	function removeAnswerChoice(event) {
		if (event.target.classList.contains('remove-answer')) {
			const answerCount = answerChoices.querySelectorAll('.answer-choice').length;
			if (answerCount > 2) {
				event.target.closest('.answer-choice').remove();
				
				// Re-validate answers after removal
				validateAnswers();
			} else {
				addErrorState(answerOptionsSection, 'Multiple choice questions must have at least 2 answers.');
			}
		}
	}

	// Validate answers section
	function validateAnswers() {
		if (questionType.value === 'multiple-choice') {
			const answers = Array.from(answerChoices.querySelectorAll('input[type="text"]'))
								.map(input => input.value.trim())
								.filter(value => value !== '');

			if (answers.length < 2) {
				addErrorState(answerOptionsSection, 'Multiple choice questions must have at least 2 answers.');
				return false;
			} else {
				removeErrorState(answerOptionsSection);
				return true;
			}
		}
		return true;
	}

	// Form validation
	function validateForm(event) {
		event.preventDefault();
		
		// Set the save type based on which button was clicked
		const clickedButton = event.submitter;
		if (clickedButton) {
			if (clickedButton.classList.contains('button-warning')) {
				saveTypeInput.value = 'unpublish';
			} else if (clickedButton.classList.contains('button-primary')) {
				saveTypeInput.value = 'publish';
			} else {
				saveTypeInput.value = 'draft';
			}
		}
		
		clearAllErrors();
		
		const surveyName = document.getElementById('survey-name');
		const questionContent = document.getElementById('question-content');
		
		let isValid = true;

		if (!surveyName.value.trim()) {
			addErrorState(surveyName, 'Survey name is required.');
			isValid = false;
		}

		if (!questionContent.value.trim()) {
			addErrorState(questionContent, 'Question content is required.');
			isValid = false;
		}

		if (!validateAnswers()) {
			isValid = false;
		}

		// If everything is valid, submit the form
		if (isValid) {
			form.submit();
		}
	}

	// Handle input changes to clear errors
	function handleInputChange(event) {
		const element = event.target.classList.contains('error') ? 
			event.target : 
			event.target.closest('.error');
			
		if (element) {
			removeErrorState(element);
		}
	}

	// Event Listeners
	questionType.addEventListener('change', toggleAnswerOptions);
	addAnswerBtn.addEventListener('click', addAnswerChoice);
	answerChoices.addEventListener('click', removeAnswerChoice);
	form.addEventListener('submit', validateForm);
	form.addEventListener('input', handleInputChange);

	// Initial setup
	toggleAnswerOptions();
});