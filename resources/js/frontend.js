const liteSurveys = {
	surveys: [],
	templates: {
		modal: `
			<div class="litesurveys-slidein" data-survey-id="" tabindex="-1" role="dialog">
				<button class="litesurveys-slidein-close" aria-label="Close"></button>
				<div class="litesurveys-slidein-content">
					<div class="litesurveys-slidein-content-form">
						<div class="litesurveys-slide-content-field">
							<label class="litesurveys-slide-content-label"></label>
							<div class="litesurveys-slide-content-control"></div>
						</div>
					</div>
				</div>
			</div>
		`,
		answerButton: `<button class="litesurveys-answer-button"></button>`,
		openAnswer: `<textarea class="litesurveys-slidein-content-textarea"></textarea>`,
		submitButton: `<button class="litesurveys-slidein-content-button">Submit</button>`
	},

	init() {
		this.loadSurveys();
	},

	loadSurveys() {
		fetch(liteSurveysSettings.ajaxUrl + 'surveys')
			.then(response => response.json())
			.then(surveys => {
				if (!this.hasValidSurveys(surveys)) {
					return;
				}
				this.surveys = surveys;
				this.prepareSurveys();
			});
	},

	prepareSurveys() {
		this.surveys.forEach(survey => {
			this.addModalMarkup(survey);
			this.addSlideinListeners(survey);
			this.setPosition(survey);

			// Add trigger based on settings
			const trigger = survey.targeting_settings.trigger[0];
			if (trigger.type === 'auto') {
				setTimeout(() => this.openModal(survey.id), trigger.auto_timing * 1000);
			} else if (trigger.type === 'exit') {
				// Only add exit intent listener once, check all surveys when triggered
				if (!this.exitIntentListenerAdded) {
					document.addEventListener('mouseout', (event) => {
						if (event.clientY <= 10) {
							this.surveys.forEach(s => {
								if (s.targeting_settings.trigger[0].type === 'exit') {
									this.openModal(s.id);
								}
							});
						}
					});
					this.exitIntentListenerAdded = true;
				}
			}
		});
	},

	addModalMarkup(survey) {
		// Create modal element
		const modalElement = document.createElement('div');
		modalElement.innerHTML = this.templates.modal.trim();
		const modal = modalElement.firstChild;
		
		// Set survey ID
		modal.dataset.surveyId = survey.id;

		// Add question content
		const question = survey.questions[0];
		modal.querySelector('.litesurveys-slide-content-label').textContent = question.content;

		const controlDiv = modal.querySelector('.litesurveys-slide-content-control');
		if (question.type === 'multiple-choice') {
			question.answers.forEach(answer => {
				const button = document.createElement('div');
				button.innerHTML = this.templates.answerButton.trim();
				button.firstChild.textContent = answer;
				controlDiv.appendChild(button.firstChild);
			});
		} else {
			// Open answer type
			const textarea = document.createElement('div');
			textarea.innerHTML = this.templates.openAnswer.trim();
			controlDiv.appendChild(textarea.firstChild);

			const submitBtn = document.createElement('div');
			submitBtn.innerHTML = this.templates.submitButton.trim();
			controlDiv.appendChild(submitBtn.firstChild);
		}

		document.body.appendChild(modal);
	},

	setPosition(survey) {
		const modal = document.querySelector(`.litesurveys-slidein[data-survey-id="${survey.id}"]`);
		const horizontalPosition = survey.appearance_settings?.horizontal_position || 'right';
		
		if (horizontalPosition === 'left') {
			modal.style.setProperty('--litesurveys-slidein-left-spacing', '1em');
			modal.style.setProperty('--litesurveys-slidein-right-spacing', 'initial');
		} else {
			modal.style.setProperty('--litesurveys-slidein-left-spacing', 'initial');
			modal.style.setProperty('--litesurveys-slidein-right-spacing', '1em');
		}
	},

	addSlideinListeners(survey) {
		const modal = document.querySelector(`.litesurveys-slidein[data-survey-id="${survey.id}"]`);

		// Close button
		modal.querySelector('.litesurveys-slidein-close').addEventListener('click', () => {
			this.closeModal(survey.id);
		});

		// Multiple choice answers
		modal.querySelectorAll('.litesurveys-answer-button').forEach(button => {
			button.addEventListener('click', (e) => {
				this.submitSurvey(survey.id, e.target.textContent);
			});
		});

		// Open answer submit
		const submitButton = modal.querySelector('.litesurveys-slidein-content-button');
		if (submitButton) {
			submitButton.addEventListener('click', () => {
				const answer = modal.querySelector('.litesurveys-slidein-content-textarea').value;
				if (answer.trim()) {
					this.submitSurvey(survey.id, answer.trim());
				}
			});
		}
	},

	openModal(surveyId) {
		const cookieName = `litesurveys-slideinclosed-${surveyId}`;
		if (!this.getCookie(cookieName)) {
			const modal = document.querySelector(`.litesurveys-slidein[data-survey-id="${surveyId}"]`);
			modal.classList.add('litesurveys-is-active');
			modal.focus();
		}
	},

	closeModal(surveyId) {
		const modal = document.querySelector(`.litesurveys-slidein[data-survey-id="${surveyId}"]`);
		modal.classList.remove('litesurveys-is-active');
		this.setCookie(`litesurveys-slideinclosed-${surveyId}`, 'yes', 365);
	},

	submitSurvey(surveyId, answer) {
		const survey = this.surveys.find(s => s.id === surveyId);
		if (!survey) return;

		const submission = {
			responses: [{
				question_id: survey.questions[0].id,
				content: answer
			}],
			page: window.location.href
		};

		fetch(liteSurveysSettings.ajaxUrl + `surveys/${surveyId}/submissions`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify(submission)
		}).then(() => {
			// Replace content with thank you message
			const modal = document.querySelector(`.litesurveys-slidein[data-survey-id="${surveyId}"]`);
			const content = modal.querySelector('.litesurveys-slidein-content');
			const form = content.querySelector('.litesurveys-slidein-content-form');
			form.innerHTML = survey.submit_message;

			// Set cookie to prevent showing again
			this.setCookie(`litesurveys-slideinclosed-${surveyId}`, 'yes', 365);

			// Close after delay
			setTimeout(() => this.closeModal(surveyId), 3000);
		});
	},

	hasValidSurveys(surveys) {
		return Array.isArray(surveys) && surveys.length > 0 && surveys[0]?.id;
	},

	setCookie(name, value, days) {
		const date = new Date();
		date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
		document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/;samesite=lax`;
	},

	getCookie(name) {
		const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
		return match ? match[2] : null;
	}
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => liteSurveys.init());
} else {
	liteSurveys.init();
}