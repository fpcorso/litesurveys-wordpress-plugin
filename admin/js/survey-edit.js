document.addEventListener('DOMContentLoaded', function() {
	const form = document.getElementById('survey-edit-form');
	const questionType = document.getElementById('question-type');
	const answerOptionsSection = document.querySelector('.answer-options');
	const answerChoices = document.getElementById('answer-choices');
	const addAnswerBtn = document.querySelector('.add-answer');

	if (!form || !questionType || !answerOptionsSection || !answerChoices || !addAnswerBtn) {
		return;
	}

	// Helper to create error message element
	function createErrorMessage(message) {
		const div = document.createElement('div');
		div.className = 'error-message';
		div.style.color = '#d63638';
		div.style.marginTop = '5px';
		div.textContent = message;
		return div;
	}

	// Helper to add error state to field
	function addErrorState(element, message) {
		// Remove any existing error message
		removeErrorState(element);
		
		// Add error class to the input
		element.classList.add('error');
		element.style.borderColor = '#d63638';
		
		// Add error message after the element
		const errorMessage = createErrorMessage(message);
		element.parentNode.appendChild(errorMessage);
	}

	// Helper to remove error state from field
	function removeErrorState(element) {
		// Remove error class and styling
		element.classList.remove('error');
		element.style.borderColor = '';
		
		// Remove any existing error message
		const existingError = element.parentNode.querySelector('.error-message');
		if (existingError) {
			existingError.remove();
		}
	}

	// Clear all error states
	function clearAllErrors() {
		document.querySelectorAll('.error').forEach(element => {
			removeErrorState(element);
		});
	}

	// Toggle answer options based on question type
	function toggleAnswerOptions() {
		answerOptionsSection.style.display = 
			questionType.value === 'multiple-choice' ? 'table-row' : 'none';
			
		// Clear any error states when switching types
		clearAllErrors();
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
		const answersSection = document.querySelector('.answer-options');
		removeErrorState(answersSection);
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
				const answersSection = document.querySelector('.answer-options');
				addErrorState(answersSection, 'Multiple choice questions must have at least 2 answers.');
			}
		}
	}

	// Validate answers section
	function validateAnswers() {
		const answersSection = document.querySelector('.answer-options');
		if (questionType.value === 'multiple-choice') {
			const answers = Array.from(answerChoices.querySelectorAll('input[type="text"]'))
								.map(input => input.value.trim())
								.filter(value => value !== '');

			if (answers.length < 2) {
				addErrorState(answersSection, 'Multiple choice questions must have at least 2 answers.');
				return false;
			} else {
				removeErrorState(answersSection);
				return true;
			}
		}
		return true;
	}

	// Form validation
	function validateForm(event) {
		event.preventDefault();
		
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
		if (event.target.classList.contains('error')) {
			removeErrorState(event.target);
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