document.addEventListener('DOMContentLoaded', function() {
	const form = document.getElementById('survey-edit-form');
	const questionType = document.getElementById('question-type');
	const answerOptionsSection = document.querySelector('.answer-options');
	const answerChoices = document.getElementById('answer-choices');
	const addAnswerBtn = document.querySelector('.add-answer');

	if (!form || !questionType || !answerOptionsSection || !answerChoices || !addAnswerBtn) {
		return;
	}

	// Toggle answer options based on question type
	function toggleAnswerOptions() {
		answerOptionsSection.style.display = 
			questionType.value === 'multiple-choice' ? 'table-row' : 'none';
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
		answerChoices.appendChild(createAnswerInput());
	}

	// Remove answer choice
	function removeAnswerChoice(event) {
		if (event.target.classList.contains('remove-answer')) {
			const answerCount = answerChoices.querySelectorAll('.answer-choice').length;
			if (answerCount > 2) {
				event.target.closest('.answer-choice').remove();
			} else {
				alert('Multiple choice questions must have at least 2 answers.');
			}
		}
	}

	// Form validation
	function validateForm(event) {
		const surveyName = document.getElementById('survey-name').value.trim();
		const questionContent = document.getElementById('question-content').value.trim();
		
		let isValid = true;
		let errorMessages = [];

		if (!surveyName) {
			errorMessages.push('Survey name is required.');
			isValid = false;
		}

		if (!questionContent) {
			errorMessages.push('Question content is required.');
			isValid = false;
		}

		if (questionType.value === 'multiple-choice') {
			const answers = Array.from(answerChoices.querySelectorAll('input[type="text"]'))
								.map(input => input.value.trim())
								.filter(value => value !== '');

			if (answers.length < 2) {
				errorMessages.push('Multiple choice questions must have at least 2 answers.');
				isValid = false;
			}
		}

		if (!isValid) {
			event.preventDefault();
			alert(errorMessages.join('\n'));
		}
	}

	// Event Listeners
	questionType.addEventListener('change', toggleAnswerOptions);
	addAnswerBtn.addEventListener('click', addAnswerChoice);
	answerChoices.addEventListener('click', removeAnswerChoice);
	form.addEventListener('submit', validateForm);

	// Initial setup
	toggleAnswerOptions();
});